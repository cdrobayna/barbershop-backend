<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ── Register ──────────────────────────────────────────────────────────────────

it('registers a new client and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'phone', 'role'],
            'token',
        ])
        ->assertJsonPath('user.role', UserRole::Client->value)
        ->assertJsonPath('user.email', 'john@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'role' => UserRole::Client->value,
    ]);
});

it('registers without a phone number', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.phone', null);
});

it('always assigns client role on registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Hacker',
        'email' => 'hacker@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'provider', // attempt to set provider role
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'hacker@example.com',
        'role' => UserRole::Client->value,
    ]);
});

it('fails registration with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Another',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('fails registration when password is too short', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Short',
        'email' => 'short@example.com',
        'password' => '1234567',
        'password_confirmation' => '1234567',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('fails registration when passwords do not match', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mismatch',
        'email' => 'mismatch@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('fails registration with missing required fields', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

// ── Login ─────────────────────────────────────────────────────────────────────

it('logs in with correct credentials and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'phone', 'role'],
            'token',
        ])
        ->assertJsonPath('user.id', $user->id);
});

it('fails login with wrong password', function () {
    User::factory()->create(['email' => 'wrong@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrongpassword',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('fails login with non-existent email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ])->assertUnauthorized();
});

it('fails login with missing fields', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

// ── Logout ────────────────────────────────────────────────────────────────────

it('logs out and invalidates the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;
    [$tokenId] = explode('|', $token);

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    // Token record should be gone from DB
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

it('requires authentication to logout', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});
