<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'appointment_duration_minutes' => $this->appointment_duration_minutes,
            'min_cancel_notice_hours' => $this->min_cancel_notice_hours,
        ];
    }
}
