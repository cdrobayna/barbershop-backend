<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Appointment Duration
    |--------------------------------------------------------------------------
    | Duration in minutes used when no service catalog entry exists.
    | The effective duration is: default_appointment_duration_minutes × party_size
    */
    'default_appointment_duration_minutes' => env('BOOKING_DEFAULT_DURATION_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Default Reminder Lead Time
    |--------------------------------------------------------------------------
    | Hours before the appointment when reminder notifications are sent.
    | Clients can override this per their notification preferences.
    */
    'default_reminder_hours' => env('BOOKING_DEFAULT_REMINDER_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Minimum Cancellation / Rescheduling Notice
    |--------------------------------------------------------------------------
    | Hours before the appointment that a client must give notice to cancel
    | or reschedule. Provider can override this in their profile settings.
    | Does NOT apply when appointment status is reschedule_requested.
    */
    'default_min_cancel_notice_hours' => env('BOOKING_DEFAULT_MIN_CANCEL_NOTICE_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Initial Appointment Status
    |--------------------------------------------------------------------------
    | Status assigned to a newly created appointment.
    | Accepted values: 'pending', 'confirmed'
    */
    'initial_appointment_status' => env('BOOKING_INITIAL_APPOINTMENT_STATUS', 'pending'),
];
