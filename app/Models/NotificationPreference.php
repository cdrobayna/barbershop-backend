<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'channel',
        'enabled',
        'lead_time_hours',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'lead_time_hours' => 'integer',
            'event_type' => NotificationEventType::class,
            'channel' => NotificationChannel::class,
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEvent(Builder $query, NotificationEventType $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    /**
     * Check whether a user has a given notification channel enabled.
     * Returns true by default when no preference record exists (opt-out model).
     */
    public static function isEnabled(User $user, NotificationEventType $event, NotificationChannel $channel): bool
    {
        $preference = static::query()
            ->forUser($user->id)
            ->forEvent($event)
            ->where('channel', $channel)
            ->first();

        return $preference === null || $preference->enabled;
    }

    /**
     * Get the reminder lead time for a user, falling back to the system default.
     */
    public static function getReminderLeadTime(User $user): int
    {
        $preference = static::query()
            ->forUser($user->id)
            ->forEvent(NotificationEventType::AppointmentReminder)
            ->whereNotNull('lead_time_hours')
            ->first();

        return $preference?->lead_time_hours
            ?? (int) config('booking.default_reminder_hours');
    }
}
