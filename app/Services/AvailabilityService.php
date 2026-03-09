<?php

namespace App\Services;

use App\Exceptions\AppointmentNotAvailableException;
use App\Models\Appointment;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Resolve the effective schedule for a given date.
     * Overrides take precedence over the weekly schedule.
     *
     * Returns: ['is_working' => bool, 'sessions' => Collection<WorkSession>]
     */
    public function getEffectiveScheduleForDate(User $provider, Carbon $date): array
    {
        $override = ScheduleOverride::query()
            ->forProviderAndDate($provider->id, $date->toDateString())
            ->with('workSessions')
            ->first();

        if ($override !== null) {
            return [
                'is_working' => $override->is_working,
                'sessions' => $override->is_working ? $override->workSessions : collect(),
                'source' => 'override',
            ];
        }

        $schedule = WeeklySchedule::query()
            ->forProvider($provider->id)
            ->forDay($date->dayOfWeek)
            ->with('workSessions')
            ->first();

        if ($schedule === null || ! $schedule->is_active) {
            return ['is_working' => false, 'sessions' => collect(), 'source' => 'weekly'];
        }

        return ['is_working' => true, 'sessions' => $schedule->workSessions, 'source' => 'weekly'];
    }

    /**
     * Return all time intervals occupied by active (blocking) appointments for a given date.
     *
     * Returns: array of ['start' => Carbon, 'end' => Carbon]
     */
    public function getOccupiedSlots(User $provider, Carbon $date, ?int $excludeAppointmentId = null): array
    {
        $query = Appointment::query()
            ->forProvider($provider->id)
            ->forDate($date->toDateString())
            ->blockingAvailability();

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->get()->map(function (Appointment $appt) {
            $start = Carbon::parse($appt->scheduled_at);

            return [
                'start' => $start,
                'end' => $start->copy()->addMinutes($appt->duration_minutes),
            ];
        })->all();
    }

    /**
     * Full availability response for a date: effective schedule + occupied slots per session.
     */
    public function getAvailabilityForDate(User $provider, Carbon $date): array
    {
        $effective = $this->getEffectiveScheduleForDate($provider, $date);

        if (! $effective['is_working']) {
            return ['is_working' => false, 'sessions' => []];
        }

        $occupiedSlots = $this->getOccupiedSlots($provider, $date);

        $sessions = $effective['sessions']->map(function ($session) use ($occupiedSlots) {
            $sessionStart = Carbon::createFromTimeString($session->start_time);
            $sessionEnd = Carbon::createFromTimeString($session->end_time);

            $busy = array_filter($occupiedSlots, function ($slot) use ($sessionStart, $sessionEnd) {
                return $slot['start']->lt($sessionEnd) && $slot['end']->gt($sessionStart);
            });

            return [
                'id' => $session->id,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'occupied_slots' => array_values(array_map(fn ($s) => [
                    'start' => $s['start']->format('H:i'),
                    'end' => $s['end']->format('H:i'),
                ], $busy)),
            ];
        });

        return ['is_working' => true, 'sessions' => $sessions->values()->all()];
    }

    /**
     * Validate all RF-AVAIL-02 rules for a potential appointment slot.
     *
     * @throws AppointmentNotAvailableException
     */
    public function isSlotAvailable(
        User $provider,
        Carbon $start,
        int $durationMinutes,
        ?int $excludeAppointmentId = null
    ): bool {
        $end = $start->copy()->addMinutes($durationMinutes);

        // Rule 5: must be in the future
        if ($start->lte(now())) {
            throw new AppointmentNotAvailableException('The appointment must be scheduled in the future.');
        }

        $effective = $this->getEffectiveScheduleForDate($provider, $start->copy()->startOfDay());

        // Rule 1: must be a working day
        if (! $effective['is_working']) {
            throw new AppointmentNotAvailableException('The provider does not work on the selected date.');
        }

        // Rules 2 & 3: start must be inside a session AND end must be within the same session
        $containingSession = null;
        foreach ($effective['sessions'] as $session) {
            $sessionStart = Carbon::createFromTimeString($session->start_time)->setDateFrom($start);
            $sessionEnd = Carbon::createFromTimeString($session->end_time)->setDateFrom($start);

            if ($start->gte($sessionStart) && $end->lte($sessionEnd)) {
                $containingSession = $session;
                break;
            }
        }

        if ($containingSession === null) {
            throw new AppointmentNotAvailableException(
                'The appointment does not fit within a single work session (it may cross a break or exceed session hours).'
            );
        }

        // Rule 4: no overlap with existing appointments
        $occupied = $this->getOccupiedSlots($provider, $start->copy()->startOfDay(), $excludeAppointmentId);

        foreach ($occupied as $slot) {
            if ($start->lt($slot['end']) && $end->gt($slot['start'])) {
                throw new AppointmentNotAvailableException('The selected time slot overlaps with an existing appointment.');
            }
        }

        return true;
    }
}
