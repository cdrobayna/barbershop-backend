<?php

namespace App\Http\Requests\Api\V1\Availability;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'exclude_appointment_id' => ['sometimes', 'integer', 'exists:appointments,id'],
        ];
    }
}
