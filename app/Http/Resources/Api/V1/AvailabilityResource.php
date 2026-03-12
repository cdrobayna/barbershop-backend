<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'is_working' => $this->resource['is_working'],
            'sessions' => $this->resource['sessions'],
        ];
    }
}
