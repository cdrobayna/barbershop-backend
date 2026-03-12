<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->isProvider() || $appointment->client_id === $user->id;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->isProvider() || $appointment->client_id === $user->id;
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $user->isProvider() || $appointment->client_id === $user->id;
    }

    /** Only the owning provider may request a reschedule. */
    public function requestReschedule(User $user, Appointment $appointment): bool
    {
        return $user->isProvider() && $appointment->provider_id === $user->id;
    }

    /** Only the owning provider may confirm. */
    public function confirm(User $user, Appointment $appointment): bool
    {
        return $user->isProvider() && $appointment->provider_id === $user->id;
    }

    /** Only the owning provider may mark as complete. */
    public function complete(User $user, Appointment $appointment): bool
    {
        return $user->isProvider() && $appointment->provider_id === $user->id;
    }
}
