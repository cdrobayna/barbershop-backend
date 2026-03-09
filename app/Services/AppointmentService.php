<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\RescheduleRequestedBy;
use App\Enums\UserRole;
use App\Exceptions\AppointmentActionNotAllowedException;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
    ) {}

    /**
     * Create a new appointment after validating availability.
     */
    public function create(User $client, User $provider, array $data): Appointment
    {
        $profile = $provider->providerProfile;
        $baseDuration = $profile?->getEffectiveDurationMinutes()
            ?? (int) config('booking.default_appointment_duration_minutes');

        $partySize = $data['party_size'] ?? 1;
        $duration = $baseDuration * $partySize;

        $start = Carbon::parse($data['scheduled_at']);

        $this->availabilityService->isSlotAvailable($provider, $start, $duration);

        $initialStatus = config('booking.initial_appointment_status', 'pending');

        return Appointment::create([
            'provider_id' => $provider->id,
            'client_id' => $client->id,
            'scheduled_at' => $start,
            'duration_minutes' => $duration,
            'party_size' => $partySize,
            'status' => $initialStatus,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Confirm a pending appointment (provider action).
     *
     * @throws AppointmentActionNotAllowedException
     */
    public function confirm(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Pending) {
            throw new AppointmentActionNotAllowedException('Only pending appointments can be confirmed.');
        }

        $appointment->update(['status' => AppointmentStatus::Confirmed]);

        return $appointment->fresh();
    }

    /**
     * Cancel an appointment.
     *
     * @throws AppointmentActionNotAllowedException
     */
    public function cancel(Appointment $appointment, User $actor): Appointment
    {
        if (! $appointment->isActive()) {
            throw new AppointmentActionNotAllowedException('Only active appointments can be cancelled.');
        }

        // Enforce minimum notice for clients (not when status is reschedule_requested)
        if ($actor->role === UserRole::Client
            && $appointment->status !== AppointmentStatus::RescheduleRequested) {
            $this->enforceMinimumNotice($appointment, $actor);
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_by' => $actor->isProvider() ? CancelledBy::Provider : CancelledBy::Client,
            'cancelled_at' => now(),
        ]);

        return $appointment->fresh();
    }

    /**
     * Reschedule an appointment to a new time.
     * Marks the original as rescheduled and creates a new appointment.
     *
     * @throws AppointmentActionNotAllowedException
     */
    public function reschedule(Appointment $appointment, User $actor, array $newData): Appointment
    {
        if (! $appointment->status->canBeRescheduledBy($actor->role)) {
            throw new AppointmentActionNotAllowedException(
                'This appointment cannot be rescheduled in its current status.'
            );
        }

        // Enforce minimum notice for clients UNLESS status is reschedule_requested
        if ($actor->role === UserRole::Client
            && $appointment->status !== AppointmentStatus::RescheduleRequested) {
            $this->enforceMinimumNotice($appointment, $actor);
        }

        $provider = User::find($appointment->provider_id);
        $partySize = $newData['party_size'] ?? $appointment->party_size;

        $profile = $provider->providerProfile;
        $baseDuration = $profile?->getEffectiveDurationMinutes()
            ?? (int) config('booking.default_appointment_duration_minutes');

        $duration = $baseDuration * $partySize;
        $newStart = Carbon::parse($newData['scheduled_at']);

        // Exclude current appointment from overlap check
        $this->availabilityService->isSlotAvailable($provider, $newStart, $duration, $appointment->id);

        return DB::transaction(function () use ($appointment, $newStart, $duration, $partySize, $newData, $provider) {
            $appointment->update(['status' => AppointmentStatus::Rescheduled]);

            return Appointment::create([
                'provider_id' => $provider->id,
                'client_id' => $appointment->client_id,
                'parent_appointment_id' => $appointment->id,
                'scheduled_at' => $newStart,
                'duration_minutes' => $duration,
                'party_size' => $partySize,
                'status' => config('booking.initial_appointment_status', 'pending'),
                'notes' => $newData['notes'] ?? $appointment->notes,
            ]);
        });
    }

    /**
     * Mark an appointment as reschedule_requested (provider requests client to reschedule).
     *
     * @throws AppointmentActionNotAllowedException
     */
    public function requestReschedule(Appointment $appointment, RescheduleRequestedBy $requestedBy = RescheduleRequestedBy::Provider): Appointment
    {
        if (! $appointment->isActive()) {
            throw new AppointmentActionNotAllowedException('Only active appointments can be marked for rescheduling.');
        }

        if ($appointment->status === AppointmentStatus::RescheduleRequested) {
            throw new AppointmentActionNotAllowedException('Appointment is already pending rescheduling.');
        }

        $appointment->update([
            'status' => AppointmentStatus::RescheduleRequested,
            'reschedule_requested_by' => $requestedBy,
            'reschedule_requested_at' => now(),
        ]);

        return $appointment->fresh();
    }

    /**
     * Mark a confirmed appointment as completed.
     *
     * @throws AppointmentActionNotAllowedException
     */
    public function complete(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Confirmed) {
            throw new AppointmentActionNotAllowedException('Only confirmed appointments can be marked as completed.');
        }

        $appointment->update(['status' => AppointmentStatus::Completed]);

        return $appointment->fresh();
    }

    /**
     * Bulk-mark appointments as reschedule_requested when a schedule change affects them.
     * Returns the count of affected appointments.
     */
    public function markAffectedAsRescheduleRequested(
        array $appointmentIds,
        RescheduleRequestedBy $requestedBy = RescheduleRequestedBy::System
    ): int {
        if (empty($appointmentIds)) {
            return 0;
        }

        return Appointment::whereIn('id', $appointmentIds)->update([
            'status' => AppointmentStatus::RescheduleRequested,
            'reschedule_requested_by' => $requestedBy,
            'reschedule_requested_at' => now(),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @throws AppointmentActionNotAllowedException
     */
    private function enforceMinimumNotice(Appointment $appointment, User $actor): void
    {
        $provider = User::find($appointment->provider_id);
        $noticeHours = $provider->providerProfile?->getEffectiveMinCancelNoticeHours()
            ?? (int) config('booking.default_min_cancel_notice_hours');

        $minimumTime = now()->addHours($noticeHours);

        if (Carbon::parse($appointment->scheduled_at)->lt($minimumTime)) {
            throw new AppointmentActionNotAllowedException(
                "This action requires at least {$noticeHours} hour(s) notice before the appointment."
            );
        }
    }
}
