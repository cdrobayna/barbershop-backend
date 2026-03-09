<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\RescheduleRequestedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $fillable = [
        'provider_id',
        'client_id',
        'parent_appointment_id',
        'scheduled_at',
        'duration_minutes',
        'party_size',
        'status',
        'notes',
        'cancelled_by',
        'cancelled_at',
        'reschedule_requested_by',
        'reschedule_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reschedule_requested_at' => 'datetime',
            'duration_minutes' => 'integer',
            'party_size' => 'integer',
            'status' => AppointmentStatus::class,
            'cancelled_by' => CancelledBy::class,
            'reschedule_requested_by' => RescheduleRequestedBy::class,
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function parentAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'parent_appointment_id');
    }

    public function childAppointment(): HasOne
    {
        return $this->hasOne(Appointment::class, 'parent_appointment_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AppointmentStatus::Pending);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', AppointmentStatus::Confirmed);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AppointmentStatus::Pending->value,
            AppointmentStatus::Confirmed->value,
            AppointmentStatus::RescheduleRequested->value,
        ]);
    }

    public function scopeBlockingAvailability(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AppointmentStatus::Pending->value,
            AppointmentStatus::Confirmed->value,
        ]);
    }

    public function scopeForProvider(Builder $query, int $providerId): Builder
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('scheduled_at', $date);
    }

    public function scopeFuture(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now());
    }

    public function scopeForDayOfWeek(Builder $query, int $dayOfWeek): Builder
    {
        // PostgreSQL: DOW returns 0=Sunday, 6=Saturday
        return $query->whereRaw('EXTRACT(DOW FROM scheduled_at) = ?', [$dayOfWeek]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function blocksAvailability(): bool
    {
        return $this->status->blocksAvailability();
    }
}
