<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'provider'                => new UserResource($this->whenLoaded('provider')),
            'client'                  => new UserResource($this->whenLoaded('client')),
            'parent_appointment_id'   => $this->parent_appointment_id,
            'scheduled_at'            => $this->scheduled_at,
            'duration_minutes'        => $this->duration_minutes,
            'party_size'              => $this->party_size,
            'status'                  => $this->status->value,
            'notes'                   => $this->notes,
            'cancelled_by'            => $this->cancelled_by?->value,
            'cancelled_at'            => $this->cancelled_at,
            'reschedule_requested_by' => $this->reschedule_requested_by?->value,
            'reschedule_requested_at' => $this->reschedule_requested_at,
            'created_at'              => $this->created_at,
        ];
    }
}
