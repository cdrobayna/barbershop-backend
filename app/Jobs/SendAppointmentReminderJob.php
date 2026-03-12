<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\NotificationPreference;
use App\Notifications\AppointmentReminderNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = Carbon::now();

        // Get all active future appointments (pending or confirmed)
        $appointments = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->where('scheduled_at', '>', $now)
            ->with(['client'])
            ->get();

        foreach ($appointments as $appointment) {
            $client = $appointment->client;

            // Get client's reminder lead time preference
            $leadTimeHours = NotificationPreference::getReminderLeadTime($client);

            $reminderTime = $appointment->scheduled_at->copy()->subHours($leadTimeHours);

            // Check if reminder should be sent now (within a 5-minute window)
            if ($now->between($reminderTime->copy()->subMinutes(2), $reminderTime->copy()->addMinutes(3))) {
                Log::info("Sending reminder for appointment {$appointment->id} to client {$client->id}");
                $client->notify(new AppointmentReminderNotification($appointment));
            }
        }
    }
}
