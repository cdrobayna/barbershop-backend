<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case RescheduleRequested = 'reschedule_requested';
    case Completed = 'completed';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::RescheduleRequested]);
    }

    public function canBeCancelledBy(UserRole $role): bool
    {
        return $this->isActive();
    }

    public function canBeRescheduledBy(UserRole $role): bool
    {
        if ($role === UserRole::Client) {
            return in_array($this, [self::Pending, self::Confirmed, self::RescheduleRequested]);
        }

        return $this->isActive();
    }

    public function blocksAvailability(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed]);
    }
}
