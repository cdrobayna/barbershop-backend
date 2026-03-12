<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'is_working' => $this->is_working,
            'reason' => $this->reason,
            'sessions' => WorkSessionResource::collection($this->whenLoaded('workSessions')),
        ];
    }
}
