<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\NotificationPreference;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('client can view own profile', function () {
    $client = User::factory()->client()->create([
        'name' => 'Client User',
        'email' => 'client@example.com',
        'phone' => '+1234567',
    ]);

    $this->actingAs($client)
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.name', 'Client User')
        ->assertJsonPath('data.email', 'client@example.com')
        ->assertJsonPath('data.phone', '+1234567')
        ->assertJsonPath('data.role', 'client');
});

it('client can update own profile', function () {
    $client = User::factory()->client()->create([
        'email' => 'before@example.com',
    ]);

    $this->actingAs($client)
        ->putJson('/api/v1/profile', [
            'name' => 'Updated Client',
            'email' => 'after@example.com',
            'phone' => '+999999',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Client')
        ->assertJsonPath('data.email', 'after@example.com')
        ->assertJsonPath('data.phone', '+999999');

    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'name' => 'Updated Client',
        'email' => 'after@example.com',
        'phone' => '+999999',
    ]);
});

it('client can update password with current password', function () {
    $client = User::factory()->client()->create([
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAs($client)
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password updated successfully.');

    expect(Hash::check('new-password', $client->fresh()->password))->toBeTrue();
});

it('client can list effective notification preferences', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)
        ->getJson('/api/v1/profile/notification-preferences')
        ->assertOk()
        ->assertJsonCount(count(NotificationEventType::cases()) * count(NotificationChannel::cases()), 'data.preferences')
        ->assertJsonPath('data.reminder_lead_time_hours', 24);
});

it('client can update notification preferences and reminder lead time', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)
        ->putJson('/api/v1/profile/notification-preferences', [
            'preferences' => [
                [
                    'event_type' => NotificationEventType::AppointmentConfirmed->value,
                    'channel' => NotificationChannel::Email->value,
                    'enabled' => false,
                ],
                [
                    'event_type' => NotificationEventType::AppointmentCreated->value,
                    'channel' => NotificationChannel::InApp->value,
                    'enabled' => false,
                ],
            ],
            'reminder_lead_time_hours' => 48,
        ])
        ->assertOk()
        ->assertJsonPath('data.reminder_lead_time_hours', 48);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $client->id,
        'event_type' => NotificationEventType::AppointmentConfirmed->value,
        'channel' => NotificationChannel::Email->value,
        'enabled' => 0,
    ]);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $client->id,
        'event_type' => NotificationEventType::AppointmentReminder->value,
        'channel' => NotificationChannel::Email->value,
        'lead_time_hours' => 48,
    ]);
});

it('provider can view provider profile', function () {
    $provider = User::factory()->provider()->create([
        'name' => 'Provider Name',
    ]);

    ProviderProfile::create([
        'user_id' => $provider->id,
        'bio' => 'Experienced professional',
        'photo_url' => 'https://example.com/photo.jpg',
        'appointment_duration_minutes' => 40,
        'min_cancel_notice_hours' => 6,
    ]);

    $this->actingAs($provider)
        ->getJson('/api/v1/provider/profile')
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Provider Name')
        ->assertJsonPath('data.bio', 'Experienced professional')
        ->assertJsonPath('data.appointment_duration_minutes', 40)
        ->assertJsonPath('data.min_cancel_notice_hours', 6);
});

it('provider can update provider profile settings', function () {
    $provider = User::factory()->provider()->create([
        'name' => 'Before Name',
    ]);

    $this->actingAs($provider)
        ->putJson('/api/v1/provider/profile', [
            'name' => 'After Name',
            'bio' => 'Updated bio',
            'photo_url' => 'https://example.com/new-photo.jpg',
            'appointment_duration_minutes' => 50,
            'min_cancel_notice_hours' => 8,
        ])
        ->assertOk()
        ->assertJsonPath('data.user.name', 'After Name')
        ->assertJsonPath('data.bio', 'Updated bio')
        ->assertJsonPath('data.appointment_duration_minutes', 50)
        ->assertJsonPath('data.min_cancel_notice_hours', 8);

    $this->assertDatabaseHas('users', [
        'id' => $provider->id,
        'name' => 'After Name',
    ]);

    $this->assertDatabaseHas('provider_profiles', [
        'user_id' => $provider->id,
        'bio' => 'Updated bio',
        'photo_url' => 'https://example.com/new-photo.jpg',
        'appointment_duration_minutes' => 50,
        'min_cancel_notice_hours' => 8,
    ]);
});

it('enforces role-based access on profile endpoints', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();

    $this->actingAs($client)
        ->getJson('/api/v1/provider/profile')
        ->assertForbidden();

    $this->actingAs($provider)
        ->getJson('/api/v1/profile')
        ->assertForbidden();
});
