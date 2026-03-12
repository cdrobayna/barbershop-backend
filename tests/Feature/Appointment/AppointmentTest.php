<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Models\WorkSession;
use Carbon\Carbon;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Set up provider + weekly schedule + work session for the given date.
 * Returns [provider, dayOfWeek].
 */
function setupProviderSchedule(?User $provider = null, ?Carbon $date = null): array
{
    $provider ??= User::factory()->provider()->create();
    $date ??= Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0, 0);
    $dayOfWeek = $date->dayOfWeek;

    $schedule = WeeklySchedule::create([
        'provider_id' => $provider->id,
        'day_of_week' => $dayOfWeek,
        'is_active' => true,
    ]);

    WorkSession::create([
        'schedule_type' => 'weekly',
        'schedule_id' => $schedule->id,
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
    ]);

    return [$provider, $dayOfWeek];
}

/**
 * Return a future Carbon date (next Monday at 10:00) used across store/reschedule tests.
 */
function futureSlot(): Carbon
{
    return Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0, 0);
}

// ── Store (POST /appointments) ────────────────────────────────────────────────

it('client can book an appointment', function () {
    $date = futureSlot();
    [$provider] = setupProviderSchedule(date: $date);
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)->postJson('/api/v1/appointments', [
        'provider_id' => $provider->id,
        'scheduled_at' => $date->toIso8601String(),
        'party_size' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.party_size', 1);
});

it('client can book for multiple people and duration scales', function () {
    $date = futureSlot();
    [$provider] = setupProviderSchedule(date: $date);
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)->postJson('/api/v1/appointments', [
        'provider_id' => $provider->id,
        'scheduled_at' => $date->toIso8601String(),
        'party_size' => 3,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.party_size', 3)
        ->assertJsonPath('data.duration_minutes', 90); // 30 * 3
});

it('provider cannot book an appointment', function () {
    $date = futureSlot();
    [$provider] = setupProviderSchedule(date: $date);

    $response = $this->actingAs($provider)->postJson('/api/v1/appointments', [
        'provider_id' => $provider->id,
        'scheduled_at' => $date->toIso8601String(),
    ]);

    $response->assertForbidden();
});

it('store returns 422 when slot is not available', function () {
    $date = futureSlot();
    [$provider] = setupProviderSchedule(date: $date);
    $client = User::factory()->client()->create();

    // Fill the slot
    Appointment::factory()->create([
        'provider_id' => $provider->id,
        'client_id' => $client->id,
        'scheduled_at' => $date,
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Confirmed->value,
    ]);

    $response = $this->actingAs($client)->postJson('/api/v1/appointments', [
        'provider_id' => $provider->id,
        'scheduled_at' => $date->toIso8601String(),
        'party_size' => 1,
    ]);

    $response->assertUnprocessable();
});

// ── Index (GET /appointments) ─────────────────────────────────────────────────

it('client sees only their own appointments', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();

    Appointment::factory()->create(['client_id' => $client->id, 'provider_id' => $provider->id]);
    Appointment::factory()->create(['client_id' => $other->id, 'provider_id' => $provider->id]);

    $response = $this->actingAs($client)->getJson('/api/v1/appointments');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('provider sees all their appointments', function () {
    $provider = User::factory()->provider()->create();
    $otherProvider = User::factory()->provider()->create();

    Appointment::factory()->count(3)->create(['provider_id' => $provider->id]);
    Appointment::factory()->count(2)->create(['provider_id' => $otherProvider->id]);

    $response = $this->actingAs($provider)->getJson('/api/v1/appointments');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('provider can filter appointments by status', function () {
    $provider = User::factory()->provider()->create();
    $client = User::factory()->client()->create();

    Appointment::factory()->pending()->create(['provider_id' => $provider->id, 'client_id' => $client->id]);
    Appointment::factory()->confirmed()->create(['provider_id' => $provider->id, 'client_id' => $client->id]);

    $response = $this->actingAs($provider)->getJson('/api/v1/appointments?status=confirmed');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('provider can filter appointments by client_id', function () {
    $provider = User::factory()->provider()->create();
    $client1 = User::factory()->client()->create();
    $client2 = User::factory()->client()->create();

    Appointment::factory()->create(['provider_id' => $provider->id, 'client_id' => $client1->id]);
    Appointment::factory()->create(['provider_id' => $provider->id, 'client_id' => $client2->id]);

    $response = $this->actingAs($provider)->getJson("/api/v1/appointments?client_id={$client1->id}");

    $response->assertOk()->assertJsonCount(1, 'data');
});

// ── Show (GET /appointments/{id}) ─────────────────────────────────────────────

it('client can view their own appointment', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create(['client_id' => $client->id, 'provider_id' => $provider->id]);

    $this->actingAs($client)->getJson("/api/v1/appointments/{$appointment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $appointment->id);
});

it('client cannot view another client\'s appointment', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create(['client_id' => $other->id, 'provider_id' => $provider->id]);

    $this->actingAs($client)->getJson("/api/v1/appointments/{$appointment->id}")
        ->assertForbidden();
});

it('provider can view any appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->getJson("/api/v1/appointments/{$appointment->id}")
        ->assertOk();
});

// ── Cancel (PATCH /appointments/{id}/cancel) ──────────────────────────────────

it('client can cancel their appointment with sufficient notice', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addDays(3),
        'status' => AppointmentStatus::Pending->value,
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('client cannot cancel without sufficient notice', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addMinutes(30),
        'status' => AppointmentStatus::Pending->value,
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertUnprocessable();
});

it('client can cancel a reschedule_requested appointment even without notice', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addMinutes(30),
        'status' => AppointmentStatus::RescheduleRequested->value,
        'reschedule_requested_by' => 'provider',
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('provider can cancel any appointment regardless of notice', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addMinutes(30),
        'status' => AppointmentStatus::Pending->value,
    ]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('client cannot cancel another client\'s appointment', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create([
        'client_id' => $other->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addDays(3),
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertForbidden();
});

// ── Reschedule (PATCH /appointments/{id}/reschedule) ─────────────────────────

it('client can reschedule their appointment with sufficient notice', function () {
    $date = futureSlot();
    $client = User::factory()->client()->create();
    [$provider] = setupProviderSchedule(date: $date);

    // Create the original appointment 7 days from now (enough notice)
    $originalDate = Carbon::now()->addDays(7)->setTime(9, 0, 0);
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => $originalDate,
        'status' => AppointmentStatus::Pending->value,
    ]);

    $response = $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'scheduled_at' => $date->toIso8601String(),
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
    expect(Appointment::find($appointment->id)->status)->toBe(AppointmentStatus::Rescheduled);
});

it('client cannot reschedule without sufficient notice', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addMinutes(30),
        'status' => AppointmentStatus::Pending->value,
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'scheduled_at' => now()->addDays(7)->toIso8601String(),
    ])->assertUnprocessable();
});

it('client can reschedule a reschedule_requested appointment without notice', function () {
    $date = futureSlot();
    $client = User::factory()->client()->create();
    [$provider] = setupProviderSchedule(date: $date);

    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addMinutes(30),
        'status' => AppointmentStatus::RescheduleRequested->value,
        'reschedule_requested_by' => 'provider',
    ]);

    $response = $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'scheduled_at' => $date->toIso8601String(),
    ]);

    $response->assertCreated();
});

it('provider can reschedule any appointment', function () {
    $date = futureSlot();
    [$provider] = setupProviderSchedule(date: $date);

    $appointment = Appointment::factory()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addMinutes(30),
        'status' => AppointmentStatus::Pending->value,
    ]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'scheduled_at' => $date->toIso8601String(),
    ])->assertCreated();
});

it('cancelled appointment cannot be rescheduled', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->cancelled()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
        'scheduled_at' => now()->addDays(5),
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'scheduled_at' => now()->addDays(7)->toIso8601String(),
    ])->assertUnprocessable();
});

// ── Confirm (PATCH /appointments/{id}/confirm) ────────────────────────────────

it('provider can confirm a pending appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->pending()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});

it('cannot confirm a non-pending appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->confirmed()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/confirm")
        ->assertUnprocessable();
});

it('client cannot confirm an appointment', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->pending()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/confirm")
        ->assertForbidden();
});

// ── Complete (PATCH /appointments/{id}/complete) ──────────────────────────────

it('provider can complete a confirmed appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->confirmed()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('cannot complete a pending appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->pending()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/complete")
        ->assertUnprocessable();
});

// ── Request Reschedule (PATCH /appointments/{id}/request-reschedule) ──────────

it('provider can request a reschedule of an active appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->pending()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/request-reschedule")
        ->assertOk()
        ->assertJsonPath('data.status', 'reschedule_requested');
});

it('requesting reschedule frees the slot for availability', function () {
    $date = futureSlot();
    [$provider] = setupProviderSchedule(date: $date);
    $client = User::factory()->client()->create();

    // Create appointment at the target slot
    $appointment = Appointment::factory()->pending()->create([
        'provider_id' => $provider->id,
        'client_id' => $client->id,
        'scheduled_at' => $date,
        'duration_minutes' => 30,
    ]);

    // Mark as reschedule_requested (slot is freed)
    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/request-reschedule")
        ->assertOk();

    // Slot should now be available
    $this->actingAs($client)->getJson("/api/v1/availability?provider_id={$provider->id}&date={$date->toDateString()}")
        ->assertOk()
        ->assertJsonPath('data.is_working', true);
});

it('cannot request reschedule on an already reschedule_requested appointment', function () {
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->rescheduleRequested()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->patchJson("/api/v1/appointments/{$appointment->id}/request-reschedule")
        ->assertUnprocessable();
});

it('client cannot request reschedule', function () {
    $client = User::factory()->client()->create();
    $provider = User::factory()->provider()->create();
    $appointment = Appointment::factory()->pending()->create([
        'client_id' => $client->id,
        'provider_id' => $provider->id,
    ]);

    $this->actingAs($client)->patchJson("/api/v1/appointments/{$appointment->id}/request-reschedule")
        ->assertForbidden();
});
