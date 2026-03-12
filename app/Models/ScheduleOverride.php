<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'date',
        'is_working',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_working' => 'boolean',
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
            ->where('schedule_type', 'override')
            ->orderBy('start_time');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForProvider(Builder $query, int $providerId): Builder
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForProviderAndDate(Builder $query, int $providerId, string $date): Builder
    {
        return $query->where('provider_id', $providerId)->whereDate('date', $date);
    }
}
