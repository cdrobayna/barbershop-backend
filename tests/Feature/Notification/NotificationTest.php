<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Appointment;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Models\WorkSession;
use App\Notifications\AppointmentCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

// ── NotificationPreference::isEnabled ─────────────────────────────────────────

it('isEnabled returns true when no preference exists (opt-out model)', function () {
    $user = User::factory()->client()->create();

    $result = NotificationPreference::isEnabled($user, NotificationEventType::AppointmentCreated, NotificationChannel::Email);

    expect($result)->toBeTrue();
});

it('isEnabled returns false when preference is explicitly disabled', function () {
    $user = User::factory()->client()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => NotificationEventType::AppointmentCreated,
        'channel' => NotificationChannel::Email,
        'enabled' => false,
    ]);

    $result = NotificationPreference::isEnabled($user, NotificationEventType::AppointmentCreated, NotificationChannel::Email);

    expect($result)->toBeFalse();
});

it('isEnabled returns true when preference is explicitly enabled', function () {
    $user = User::factory()->client()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => NotificationEventType::AppointmentCreated,
        'channel' => NotificationChannel::Email,
        'enabled' => true,
    ]);

    $result = NotificationPreference::isEnabled($user, NotificationEventType::AppointmentCreated, NotificationChannel::Email);

    expect($result)->toBeTrue();
});

// ── Appointment Created Notification ──────────────────────────────────────────

it('appointment created sends notification to client and provider', function () {
    Notification::fake();

    $date = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0, 0);
    $provider = User::factory()->provider()->create();
    $client = User::factory()->client()->create();

    $schedule = WeeklySchedule::create([
        'provider_id' => $provider->id,
        'day_of_week' => $date->dayOfWeek,
        'is_active' => true,
    ]);

    WorkSession::create([
        'schedule_type' => 'weekly',
        'schedule_id' => $schedule->id,
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
    ]);

    $this->actingAs($client)->postJson('/api/v1/appointments', [
        'provider_id' => $provider->id,
        'scheduled_at' => $date->toIso8601String(),
        'party_size' => 1,
    ])->assertCreated();

    Notification::assertSentTo($client, AppointmentCreatedNotification::class);
    Notification::assertSentTo($provider, AppointmentCreatedNotification::class);
});

it('notification is omitted when disabled by user preference', function () {
    Notification::fake();

    $date = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0, 0);
    $provider = User::factory()->provider()->create();
    $client = User::factory()->client()->create();

    // Disable mail notifications for appointment_created
    NotificationPreference::create([
        'user_id' => $client->id,
        'event_type' => NotificationEventType::AppointmentCreated,
        'channel' => NotificationChannel::Email,
        'enabled' => false,
    ]);

    // Disable in-app notifications as well
    NotificationPreference::create([
        'user_id' => $client->id,
        'event_type' => NotificationEventType::AppointmentCreated,
        'channel' => NotificationChannel::InApp,
        'enabled' => false,
    ]);

    $schedule = WeeklySchedule::create([
        'provider_id' => $provider->id,
        'day_of_week' => $date->dayOfWeek,
        'is_active' => true,
    ]);

    WorkSession::create([
        'schedule_type' => 'weekly',
        'schedule_id' => $schedule->id,
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
    ]);

    $this->actingAs($client)->postJson('/api/v1/appointments', [
        'provider_id' => $provider->id,
        'scheduled_at' => $date->toIso8601String(),
        'party_size' => 1,
    ])->assertCreated();

    // Client should not receive notification (both channels disabled)
    Notification::assertNothingSentTo($client);
    
    // Provider still receives it (no preferences set = enabled)
    Notification::assertSentTo($provider, AppointmentCreatedNotification::class);
});

// ── Notification Controller ───────────────────────────────────────────────────

it('client can list unread notifications', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();

    // Create a notification manually
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
    ]);

    $client->notify(new AppointmentCreatedNotification($appointment));

    $response = $this->actingAs($client)->getJson('/api/v1/notifications');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('client can mark notification as read', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();

    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
    ]);

    $client->notify(new AppointmentCreatedNotification($appointment));

    $notification = $client->unreadNotifications()->first();

    $this->actingAs($client)->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    expect($client->unreadNotifications()->count())->toBe(0);
});

it('client can mark all notifications as read', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();

    // Create multiple notifications
    for ($i = 0; $i < 3; $i++) {
        $appointment = Appointment::factory()->create([
            'client_id' => $client->id,
            'provider_id' => $provider->id,
        ]);
        $client->notify(new AppointmentCreatedNotification($appointment));
    }

    expect($client->unreadNotifications()->count())->toBe(3);

    $this->actingAs($client)->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('count', 3);

    expect($client->unreadNotifications()->count())->toBe(0);
});

it('getReminderLeadTime returns custom value when preference exists', function () {
    $client = User::factory()->client()->create();

    NotificationPreference::create([
        'user_id' => $client->id,
        'event_type' => NotificationEventType::AppointmentReminder,
        'channel' => NotificationChannel::Email,
        'enabled' => true,
        'lead_time_hours' => 48,
    ]);

    $leadTime = NotificationPreference::getReminderLeadTime($client);

    expect($leadTime)->toBe(48);
});

it('getReminderLeadTime returns default when no preference exists', function () {
    $client = User::factory()->client()->create();

    $leadTime = NotificationPreference::getReminderLeadTime($client);

    expect($leadTime)->toBe(24); // config default
});
