<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklySchedule extends Model
{
    protected $table = 'weekly_schedule';

    protected $fillable = [
        'provider_id',
        'day_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class, 'schedule_id')
            ->where('schedule_type', 'weekly')
            ->orderBy('start_time');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForProvider(Builder $query, int $providerId): Builder
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
