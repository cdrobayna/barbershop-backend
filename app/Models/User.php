<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    public function weeklySchedule(): HasMany
    {
        return $this->hasMany(WeeklySchedule::class, 'provider_id');
    }

    public function scheduleOverrides(): HasMany
    {
        return $this->hasMany(ScheduleOverride::class, 'provider_id');
    }

    public function appointmentsAsProvider(): HasMany
    {
        return $this->hasMany(Appointment::class, 'provider_id');
    }

    public function appointmentsAsClient(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeProvider(Builder $query): Builder
    {
        return $query->where('role', UserRole::Provider);
    }

    public function scopeClient(Builder $query): Builder
    {
        return $query->where('role', UserRole::Client);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isProvider(): bool
    {
        return $this->role === UserRole::Provider;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }
}
