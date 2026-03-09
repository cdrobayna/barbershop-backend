<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'photo_url',
        'appointment_duration_minutes',
        'min_cancel_notice_hours',
    ];

    protected function casts(): array
    {
        return [
            'appointment_duration_minutes' => 'integer',
            'min_cancel_notice_hours' => 'integer',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getEffectiveDurationMinutes(): int
    {
        return $this->appointment_duration_minutes
            ?: (int) config('booking.default_appointment_duration_minutes');
    }

    public function getEffectiveMinCancelNoticeHours(): int
    {
        return $this->min_cancel_notice_hours
            ?: (int) config('booking.default_min_cancel_notice_hours');
    }
}
