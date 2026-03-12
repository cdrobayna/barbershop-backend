<?php

namespace App\Http\Requests\Api\V1\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'party_size'   => ['sometimes', 'integer', 'min:1', 'max:20'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
