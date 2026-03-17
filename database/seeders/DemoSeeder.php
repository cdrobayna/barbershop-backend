<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\NotificationPreference;
use App\Models\ProviderProfile;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Models\WorkSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $provider = User::updateOrCreate(
                ['email' => 'provider@example.com'],
                [
                    'name' => 'Provider Demo',
                    'password' => 'password123',
                    'role' => UserRole::Provider,
                    'phone' => '+5355500000',
                ]
            );

            ProviderProfile::updateOrCreate(
                ['user_id' => $provider->id],
                [
                    'bio' => 'Especialista en cortes modernos.',
                    'photo_url' => 'https://example.com/provider.jpg',
                    'appointment_duration_minutes' => 30,
                    'min_cancel_notice_hours' => 2,
                ]
            );

            $client = User::updateOrCreate(
                ['email' => 'frontend.client@example.com'],
                [
                    'name' => 'Frontend Client',
                    'password' => 'password123',
                    'role' => UserRole::Client,
                    'phone' => '+5355512345',
                ]
            );

            $monday = WeeklySchedule::updateOrCreate(
                ['provider_id' => $provider->id, 'day_of_week' => 1],
                ['is_active' => true]
            );

            WorkSession::query()
                ->where('schedule_type', 'weekly')
                ->where('schedule_id', $monday->id)
                ->delete();

            WorkSession::create([
                'schedule_type' => 'weekly',
                'schedule_id' => $monday->id,
                'start_time' => '09:00:00',
                'end_time' => '13:00:00',
            ]);

            WorkSession::create([
                'schedule_type' => 'weekly',
                'schedule_id' => $monday->id,
                'start_time' => '14:00:00',
                'end_time' => '18:00:00',
            ]);

            $tuesday = WeeklySchedule::updateOrCreate(
                ['provider_id' => $provider->id, 'day_of_week' => 2],
                ['is_active' => true]
            );

            WorkSession::query()
                ->where('schedule_type', 'weekly')
                ->where('schedule_id', $tuesday->id)
                ->delete();

            WorkSession::create([
                'schedule_type' => 'weekly',
                'schedule_id' => $tuesday->id,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ]);

            $override = ScheduleOverride::updateOrCreate(
                ['provider_id' => $provider->id, 'date' => '2030-01-20'],
                ['is_working' => false, 'reason' => 'Cerrado por evento familiar']
            );

            WorkSession::query()
                ->where('schedule_type', 'override')
                ->where('schedule_id', $override->id)
                ->delete();

            Appointment::firstOrCreate(
                [
                    'provider_id' => $provider->id,
                    'client_id' => $client->id,
                    'scheduled_at' => '2030-01-22 10:00:00',
                ],
                [
                    'duration_minutes' => 60,
                    'party_size' => 2,
                    'status' => AppointmentStatus::Pending,
                    'notes' => 'Padre con dos hijos',
                ]
            );

            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $client->id,
                    'event_type' => NotificationEventType::AppointmentConfirmed,
                    'channel' => NotificationChannel::Email,
                ],
                ['enabled' => false]
            );

            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $client->id,
                    'event_type' => NotificationEventType::AppointmentCreated,
                    'channel' => NotificationChannel::InApp,
                ],
                ['enabled' => true]
            );

            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $client->id,
                    'event_type' => NotificationEventType::AppointmentReminder,
                    'channel' => NotificationChannel::Email,
                ],
                [
                    'enabled' => true,
                    'lead_time_hours' => 48,
                ]
            );
        });
    }
}
