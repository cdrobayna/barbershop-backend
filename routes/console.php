<?php

use App\Jobs\SendAppointmentReminderJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule appointment reminders to run every 5 minutes
Schedule::job(new SendAppointmentReminderJob)->everyFiveMinutes();
