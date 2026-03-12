<?php

use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WeeklySchedule;

// ── Helpers ───────────────────────────────────────────────────────────────────

function providerWithToken(): array
{
    $provider = User::factory()->provider()->create();
    $token = $provider->createToken('api')->plainTextToken;

    return [$provider, $token];
}

function clientWithToken(): array
{
    $client = User::factory()->client()->create();
    $token = $client->createToken('api')->plainTextToken;

    return [$client, $token];
}

// ── Weekly schedule — index ───────────────────────────────────────────────────

it('provider can retrieve the weekly schedule', function () {
    [$provider, $token] = providerWithToken();

    WeeklySchedule::factory()->create([
        'provider_id' => $provider->id,
        'day_of_week' => 1,
        'is_active' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/schedule')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.day_of_week', 1)
        ->assertJsonPath('data.0.is_active', true);
});

it('client cannot access the weekly schedule', function () {
    [, $token] = clientWithToken();

    $this->withToken($token)
        ->getJson('/api/v1/schedule')
        ->assertForbidden();
});

it('unauthenticated request cannot access the weekly schedule', function () {
    $this->getJson('/api/v1/schedule')->assertUnauthorized();
});

// ── Weekly schedule — updateDay ───────────────────────────────────────────────

it('provider can set a working day with sessions', function () {
    [$provider, $token] = providerWithToken();

    $this->withToken($token)
        ->putJson('/api/v1/schedule/1', [
            'is_active' => true,
            'sessions' => [
                ['start_time' => '09:00', 'end_time' => '13:00'],
                ['start_time' => '14:00', 'end_time' => '18:00'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.day_of_week', 1)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonCount(2, 'data.sessions');

    $this->assertDatabaseHas('weekly_schedule', [
        'provider_id' => $provider->id,
        'day_of_week' => 1,
        'is_active' => true,
    ]);
    $this->assertDatabaseCount('work_sessions', 2);
});

it('provider can mark a day as non-working', function () {
    [$provider, $token] = providerWithToken();

    $this->withToken($token)
        ->putJson('/api/v1/schedule/1', [
            'is_active' => false,
            'sessions' => [],
        ])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonCount(0, 'data.sessions');
});

it('updating a day replaces existing sessions', function () {
    [$provider, $token] = providerWithToken();

    // First update: 2 sessions
    $this->withToken($token)->putJson('/api/v1/schedule/2', [
        'is_active' => true,
        'sessions' => [
            ['start_time' => '08:00', 'end_time' => '12:00'],
            ['start_time' => '13:00', 'end_time' => '17:00'],
        ],
    ]);

    // Second update: 1 session
    $this->withToken($token)->putJson('/api/v1/schedule/2', [
        'is_active' => true,
        'sessions' => [
            ['start_time' => '09:00', 'end_time' => '17:00'],
        ],
    ])->assertOk()->assertJsonCount(1, 'data.sessions');

    $this->assertDatabaseCount('work_sessions', 1);
});

it('rejects overlapping sessions', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->putJson('/api/v1/schedule/3', [
            'is_active' => true,
            'sessions' => [
                ['start_time' => '09:00', 'end_time' => '13:00'],
                ['start_time' => '12:00', 'end_time' => '17:00'], // overlaps
            ],
        ])
        ->assertStatus(422);
});

it('rejects session where end time is before start time', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->putJson('/api/v1/schedule/3', [
            'is_active' => true,
            'sessions' => [
                ['start_time' => '14:00', 'end_time' => '09:00'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sessions.0.end_time']);
});

it('rejects invalid day of week', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->putJson('/api/v1/schedule/7', [
            'is_active' => true,
            'sessions' => [],
        ])
        ->assertStatus(404); // route regex [0-6] causes 404
});

it('rejects missing is_active field', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->putJson('/api/v1/schedule/1', [
            'sessions' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_active']);
});

// ── Overrides — index ────────────────────────────────────────────────────────

it('provider can list overrides', function () {
    [$provider, $token] = providerWithToken();

    ScheduleOverride::factory()->count(2)->create(['provider_id' => $provider->id]);

    $this->withToken($token)
        ->getJson('/api/v1/schedule/overrides')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('provider only sees their own overrides', function () {
    [$providerA, $tokenA] = providerWithToken();
    [$providerB] = providerWithToken();

    ScheduleOverride::factory()->create(['provider_id' => $providerA->id]);
    ScheduleOverride::factory()->create(['provider_id' => $providerB->id]);

    $this->withToken($tokenA)
        ->getJson('/api/v1/schedule/overrides')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Overrides — store ────────────────────────────────────────────────────────

it('provider can create a working day override with sessions', function () {
    [$provider, $token] = providerWithToken();

    $this->withToken($token)
        ->postJson('/api/v1/schedule/overrides', [
            'date' => '2026-12-24',
            'is_working' => true,
            'reason' => 'Extended holiday hours',
            'sessions' => [
                ['start_time' => '10:00', 'end_time' => '14:00'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.date', '2026-12-24')
        ->assertJsonPath('data.is_working', true)
        ->assertJsonCount(1, 'data.sessions');

    $this->assertDatabaseHas('schedule_overrides', [
        'provider_id' => $provider->id,
        'is_working' => true,
    ]);
});

it('provider can create a day-off override', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->postJson('/api/v1/schedule/overrides', [
            'date' => '2026-12-25',
            'is_working' => false,
            'reason' => 'Christmas holiday',
            'sessions' => [],
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_working', false)
        ->assertJsonCount(0, 'data.sessions');
});

it('override rejects invalid date format', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->postJson('/api/v1/schedule/overrides', [
            'date' => '24/12/2026', // wrong format
            'is_working' => true,
            'sessions' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('override rejects overlapping sessions', function () {
    [, $token] = providerWithToken();

    $this->withToken($token)
        ->postJson('/api/v1/schedule/overrides', [
            'date' => '2026-11-20',
            'is_working' => true,
            'sessions' => [
                ['start_time' => '09:00', 'end_time' => '13:00'],
                ['start_time' => '11:00', 'end_time' => '15:00'],
            ],
        ])
        ->assertStatus(422);
});

// ── Overrides — update ────────────────────────────────────────────────────────

it('provider can update their own override', function () {
    [$provider, $token] = providerWithToken();
    $override = ScheduleOverride::factory()->create([
        'provider_id' => $provider->id,
        'is_working' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/schedule/overrides/{$override->id}", [
            'reason' => 'Updated reason',
        ])
        ->assertOk()
        ->assertJsonPath('data.reason', 'Updated reason');
});

it('provider cannot update another provider\'s override', function () {
    [$providerA, $tokenA] = providerWithToken();
    [$providerB] = providerWithToken();

    $override = ScheduleOverride::factory()->create(['provider_id' => $providerB->id]);

    $this->withToken($tokenA)
        ->putJson("/api/v1/schedule/overrides/{$override->id}", [
            'reason' => 'Attempted hijack',
        ])
        ->assertForbidden();
});

// ── Overrides — delete ────────────────────────────────────────────────────────

it('provider can delete their own override', function () {
    [$provider, $token] = providerWithToken();
    $override = ScheduleOverride::factory()->create(['provider_id' => $provider->id]);

    $this->withToken($token)
        ->deleteJson("/api/v1/schedule/overrides/{$override->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('schedule_overrides', ['id' => $override->id]);
});

it('provider cannot delete another provider\'s override', function () {
    [$providerA, $tokenA] = providerWithToken();
    [$providerB] = providerWithToken();

    $override = ScheduleOverride::factory()->create(['provider_id' => $providerB->id]);

    $this->withToken($tokenA)
        ->deleteJson("/api/v1/schedule/overrides/{$override->id}")
        ->assertForbidden();
});
