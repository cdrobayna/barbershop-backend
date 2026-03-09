<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkSession extends Model
{
    protected $fillable = [
        'schedule_id',
        'schedule_type',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'string',
            'end_time' => 'string',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function schedule(): MorphTo
    {
        return $this->morphTo('schedule', 'schedule_type', 'schedule_id', 'id');
    }
}
