<?php

namespace App\Services;

use App\Enums\RescheduleRequestedBy;
use App\Exceptions\ScheduleConflictException;
use App\Jobs\ProcessScheduleChangeJob;
use App\Models\Appointment;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    /**
     * Return the full weekly schedule for a provider (all 7 days with their sessions).
     */
    public function getWeeklySchedule(User $provider): Collection
    {
        return WeeklySchedule::query()
            ->forProvider($provider->id)
            ->with('workSessions')
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * Upsert a day's schedule and sync its work sessions.
     * Detects and marks affected future appointments as reschedule_requested.
     *
     * @throws ScheduleConflictException
     */
    public function updateDay(User $provider, int $dayOfWeek, bool $isActive, array $sessions): WeeklySchedule
    {
        $this->validateSessionsNoOverlap($sessions);

        return DB::transaction(function () use ($provider, $dayOfWeek, $isActive, $sessions) {
            $schedule = WeeklySchedule::updateOrCreate(
                ['provider_id' => $provider->id, 'day_of_week' => $dayOfWeek],
                ['is_active' => $isActive]
            );

            // Sync work sessions: delete old, insert new
            WorkSession::where('schedule_id', $schedule->id)
                ->where('schedule_type', 'weekly')
                ->delete();

            foreach ($sessions as $session) {
                WorkSession::create([
                    'schedule_id' => $schedule->id,
                    'schedule_type' => 'weekly',
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                ]);
            }

            // Detect and invalidate affected future appointments
            $affectedIds = $this->findAffectedAppointmentIds($provider, $dayOfWeek, $isActive ? $sessions : []);

            if (! empty($affectedIds)) {
                $this->appointmentService->markAffectedAsRescheduleRequested(
                    $affectedIds,
                    RescheduleRequestedBy::System
                );

                // Dispatch job to notify affected clients
                $clientIds = Appointment::whereIn('id', $affectedIds)->pluck('client_id')->all();
                $nextDate = $this->getNextDateForDayOfWeek($dayOfWeek);
                ProcessScheduleChangeJob::dispatch($provider->id, $nextDate, $clientIds);
            }

            return $schedule->fresh(['workSessions']);
        });
    }

    /**
     * Return all schedule overrides for a provider.
     */
    public function getOverrides(User $provider): Collection
    {
        return ScheduleOverride::query()
            ->forProvider($provider->id)
            ->with('workSessions')
            ->orderBy('date')
            ->get();
    }

    /**
     * Create a schedule override for a specific date.
     *
     * @throws ScheduleConflictException
     */
    public function createOverride(User $provider, array $data): ScheduleOverride
    {
        $sessions = $data['sessions'] ?? [];

        if ($data['is_working'] && ! empty($sessions)) {
            $this->validateSessionsNoOverlap($sessions);
        }

        return DB::transaction(function () use ($provider, $data, $sessions) {
            $override = ScheduleOverride::create([
                'provider_id' => $provider->id,
                'date' => $data['date'],
                'is_working' => $data['is_working'],
                'reason' => $data['reason'] ?? null,
            ]);

            foreach ($sessions as $session) {
                WorkSession::create([
                    'schedule_id' => $override->id,
                    'schedule_type' => 'override',
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                ]);
            }

            // If marking a day as non-working, invalidate appointments on that date
            if (! $data['is_working']) {
                $affectedIds = Appointment::query()
                    ->forProvider($provider->id)
                    ->forDate($data['date'])
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->pluck('id')
                    ->all();

                if (! empty($affectedIds)) {
                    $this->appointmentService->markAffectedAsRescheduleRequested(
                        $affectedIds,
                        RescheduleRequestedBy::System
                    );

                    // Dispatch job to notify affected clients
                    $clientIds = Appointment::whereIn('id', $affectedIds)->pluck('client_id')->all();
                    ProcessScheduleChangeJob::dispatch($provider->id, Carbon::parse($data['date']), $clientIds);
                }
            }

            return $override->fresh(['workSessions']);
        });
    }

    /**
     * Update an existing schedule override.
     *
     * @throws ScheduleConflictException
     */
    public function updateOverride(ScheduleOverride $override, array $data): ScheduleOverride
    {
        $sessions = $data['sessions'] ?? [];

        if (($data['is_working'] ?? $override->is_working) && ! empty($sessions)) {
            $this->validateSessionsNoOverlap($sessions);
        }

        return DB::transaction(function () use ($override, $data, $sessions) {
            $override->update([
                'is_working' => $data['is_working'] ?? $override->is_working,
                'reason' => $data['reason'] ?? $override->reason,
            ]);

            if (array_key_exists('sessions', $data)) {
                WorkSession::where('schedule_id', $override->id)
                    ->where('schedule_type', 'override')
                    ->delete();

                foreach ($sessions as $session) {
                    WorkSession::create([
                        'schedule_id' => $override->id,
                        'schedule_type' => 'override',
                        'start_time' => $session['start_time'],
                        'end_time' => $session['end_time'],
                    ]);
                }
            }

            return $override->fresh(['workSessions']);
        });
    }

    /**
     * Delete a schedule override, restoring the weekly schedule behaviour for that date.
     */
    public function deleteOverride(ScheduleOverride $override): void
    {
        DB::transaction(function () use ($override) {
            WorkSession::where('schedule_id', $override->id)
                ->where('schedule_type', 'override')
                ->delete();

            $override->delete();
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Find IDs of future active appointments that no longer fit in the new sessions for a given day of week.
     */
    private function findAffectedAppointmentIds(User $provider, int $dayOfWeek, array $newSessions): array
    {
        $appointments = Appointment::query()
            ->forProvider($provider->id)
            ->forDayOfWeek($dayOfWeek)
            ->future()
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        if ($appointments->isEmpty()) {
            return [];
        }

        // If the day is now non-working, all are affected
        if (empty($newSessions)) {
            return $appointments->pluck('id')->all();
        }

        // Otherwise, check each appointment against the new sessions
        return $appointments->filter(function (Appointment $appt) use ($newSessions) {
            $start = Carbon::parse($appt->scheduled_at);
            $end = $start->copy()->addMinutes($appt->duration_minutes);

            foreach ($newSessions as $session) {
                $sessionStart = Carbon::createFromTimeString($session['start_time'])->setDateFrom($start);
                $sessionEnd = Carbon::createFromTimeString($session['end_time'])->setDateFrom($start);

                if ($start->gte($sessionStart) && $end->lte($sessionEnd)) {
                    return false; // still fits — not affected
                }
            }

            return true; // does not fit in any session — affected
        })->pluck('id')->all();
    }

    /**
     * @throws ScheduleConflictException
     */
    private function validateSessionsNoOverlap(array $sessions): void
    {
        $sorted = collect($sessions)->sortBy('start_time')->values();

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];

            if ($curr['start_time'] < $prev['end_time']) {
                throw new ScheduleConflictException(
                    "Work sessions overlap: {$prev['start_time']}-{$prev['end_time']} and {$curr['start_time']}-{$curr['end_time']}."
                );
            }
        }
    }

    /**
     * Get the next occurrence of a given day of week.
     */
    private function getNextDateForDayOfWeek(int $dayOfWeek): Carbon
    {
        $now = Carbon::now();
        $currentDay = $now->dayOfWeek;

        if ($currentDay === $dayOfWeek) {
            return $now->copy()->addWeek()->startOfDay();
        }

        if ($dayOfWeek > $currentDay) {
            return $now->copy()->next($dayOfWeek)->startOfDay();
        }

        return $now->copy()->next($dayOfWeek)->startOfDay();
    }
}
