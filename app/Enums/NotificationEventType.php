<?php

namespace App\Enums;

enum NotificationEventType: string
{
    case AppointmentCreated = 'appointment_created';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentCancelled = 'appointment_cancelled';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case RescheduleRequested = 'reschedule_requested';
    case AppointmentReminder = 'appointment_reminder';
    case ScheduleChanged = 'schedule_changed';
}
