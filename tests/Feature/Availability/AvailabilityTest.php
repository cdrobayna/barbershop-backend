<?php

use App\Models\Appointment;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Models\WorkSession;

// ── Dates used across tests ───────────────────────────────────────────────────
// 2026-03-16 is a Monday (day_of_week = 1)
// 2026-03-15 is a Sunday (day_of_week = 0)
const AVAIL_MONDAY = '2026-03-16';
const AVAIL_SUNDAY = '2026-03-15';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create a provider with a Monday schedule: 09:00-13:00 and 14:00-18:00.
 */
function makeProviderWithSchedule(): array
{
    $provider = User::factory()->provider()->create();
    $token = $provider->createToken('api')->plainTextToken;

    $schedule = WeeklySchedule::create([
        'provider_id' => $provider->id,
        'day_of_week' => 1, // Monday
        'is_active' => true,
    ]);

    WorkSession::create([
        'schedule_id' => $schedule->id,
        'schedule_type' => 'weekly',
        'start_time' => '09:00',
        'end_time' => '13:00',
    ]);

    WorkSession::create([
        'schedule_id' => $schedule->id,
        'schedule_type' => 'weekly',
        'start_time' => '14:00',
        'end_time' => '18:00',
    ]);

    return [$provider, $token];
}

// ── Non-working days ──────────────────────────────────────────────────────────

it('returns not working for a day with no schedule configured', function () {
    [$provider, $token] = makeProviderWithSchedule();

    $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_SUNDAY)
        ->assertOk()
        ->assertJsonPath('data.is_working', false)
        ->assertJsonPath('data.sessions', []);
});

it('returns not working when weekly day is inactive', function () {
    $provider = User::factory()->provider()->create();
    $token = $provider->createToken('api')->plainTextToken;

    WeeklySchedule::create([
        'provider_id' => $provider->id,
        'day_of_week' => 1,
        'is_active' => false,
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk()
        ->assertJsonPath('data.is_working', false);
});

// ── Override behaviour ────────────────────────────────────────────────────────

it('override marks a normally-working day as day-off', function () {
    [$provider, $token] = makeProviderWithSchedule();

    ScheduleOverride::create([
        'provider_id' => $provider->id,
        'date' => AVAIL_MONDAY,
        'is_working' => false,
        'reason' => 'Holiday',
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk()
        ->assertJsonPath('data.is_working', false)
        ->assertJsonPath('data.sessions', []);
});

it('override replaces weekly sessions with its own sessions', function () {
    [$provider, $token] = makeProviderWithSchedule();

    $override = ScheduleOverride::create([
        'provider_id' => $provider->id,
        'date' => AVAIL_MONDAY,
        'is_working' => true,
    ]);

    WorkSession::create([
        'schedule_id' => $override->id,
        'schedule_type' => 'override',
        'start_time' => '10:00',
        'end_time' => '14:00',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk()
        ->assertJsonPath('data.is_working', true);

    expect($response->json('data.sessions'))->toHaveCount(1)
        ->and($response->json('data.sessions.0.start_time'))->toBe('10:00')
        ->and($response->json('data.sessions.0.end_time'))->toBe('14:00');
});

it('override enables a normally non-working day', function () {
    [$provider, $token] = makeProviderWithSchedule();

    $override = ScheduleOverride::create([
        'provider_id' => $provider->id,
        'date' => AVAIL_SUNDAY,
        'is_working' => true,
    ]);

    WorkSession::create([
        'schedule_id' => $override->id,
        'schedule_type' => 'override',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_SUNDAY)
        ->assertOk()
        ->assertJsonPath('data.is_working', true)
        ->assertJsonCount(1, 'data.sessions');
});

// ── Occupied slots ────────────────────────────────────────────────────────────

it('shows an empty occupied_slots list when no appointments exist', function () {
    [$provider, $token] = makeProviderWithSchedule();

    $response = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk()
        ->assertJsonPath('data.is_working', true);

    foreach ($response->json('data.sessions') as $session) {
        expect($session['occupied_slots'])->toBeEmpty();
    }
});

it('shows occupied slot for an existing appointment', function () {
    [$provider, $token] = makeProviderWithSchedule();

    Appointment::factory()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => AVAIL_MONDAY.' 10:00:00',
        'duration_minutes' => 30,
        'status' => 'pending',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk();

    $sessions = $response->json('data.sessions');
    // First session (09:00-13:00) should have the occupied slot
    $morningSession = collect($sessions)->firstWhere('start_time', '09:00');
    expect($morningSession['occupied_slots'])->toHaveCount(1)
        ->and($morningSession['occupied_slots'][0]['start'])->toBe('10:00')
        ->and($morningSession['occupied_slots'][0]['end'])->toBe('10:30');
});

it('does not show cancelled appointments as occupied', function () {
    [$provider, $token] = makeProviderWithSchedule();

    Appointment::factory()->cancelled()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => AVAIL_MONDAY.' 10:00:00',
        'duration_minutes' => 30,
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk();

    $sessions = $response->json('data.sessions');
    foreach ($sessions as $session) {
        expect($session['occupied_slots'])->toBeEmpty();
    }
});

it('does not show reschedule_requested appointments as occupied', function () {
    [$provider, $token] = makeProviderWithSchedule();

    Appointment::factory()->rescheduleRequested()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => AVAIL_MONDAY.' 10:00:00',
        'duration_minutes' => 30,
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk();

    foreach ($response->json('data.sessions') as $session) {
        expect($session['occupied_slots'])->toBeEmpty();
    }
});

it('occupied slot appears only in the session it falls within', function () {
    [$provider, $token] = makeProviderWithSchedule();

    // Appointment in afternoon session (14:00-18:00)
    Appointment::factory()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => AVAIL_MONDAY.' 15:00:00',
        'duration_minutes' => 60,
        'status' => 'confirmed',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk();

    $sessions = $response->json('data.sessions');
    $morning = collect($sessions)->firstWhere('start_time', '09:00');
    $afternoon = collect($sessions)->firstWhere('start_time', '14:00');

    expect($morning['occupied_slots'])->toBeEmpty()
        ->and($afternoon['occupied_slots'])->toHaveCount(1)
        ->and($afternoon['occupied_slots'][0]['start'])->toBe('15:00')
        ->and($afternoon['occupied_slots'][0]['end'])->toBe('16:00');
});

// ── Exclude appointment (rescheduling use case) ───────────────────────────────

it('excludes a specified appointment from occupied slots', function () {
    [$provider, $token] = makeProviderWithSchedule();

    $appointment = Appointment::factory()->create([
        'provider_id' => $provider->id,
        'scheduled_at' => AVAIL_MONDAY.' 10:00:00',
        'duration_minutes' => 30,
        'status' => 'pending',
    ]);

    // Without exclusion → slot appears occupied
    $withoutExclusion = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertOk();

    $morning = collect($withoutExclusion->json('data.sessions'))->firstWhere('start_time', '09:00');
    expect($morning['occupied_slots'])->toHaveCount(1);

    // With exclusion → slot appears free
    $withExclusion = $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY."&exclude_appointment_id={$appointment->id}")
        ->assertOk();

    $morning = collect($withExclusion->json('data.sessions'))->firstWhere('start_time', '09:00');
    expect($morning['occupied_slots'])->toBeEmpty();
});

// ── Validation ────────────────────────────────────────────────────────────────

it('requires date and provider_id parameters', function () {
    [, $token] = makeProviderWithSchedule();

    $this->withToken($token)
        ->getJson('/api/v1/availability')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider_id', 'date']);
});

it('rejects invalid date format', function () {
    [$provider, $token] = makeProviderWithSchedule();

    $this->withToken($token)
        ->getJson("/api/v1/availability?provider_id={$provider->id}&date=16-03-2026")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('rejects non-existent provider_id', function () {
    [, $token] = makeProviderWithSchedule();

    $this->withToken($token)
        ->getJson('/api/v1/availability?provider_id=99999&date='.AVAIL_MONDAY)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider_id']);
});

it('requires authentication', function () {
    $provider = User::factory()->provider()->create();

    $this->getJson("/api/v1/availability?provider_id={$provider->id}&date=".AVAIL_MONDAY)
        ->assertUnauthorized();
});
