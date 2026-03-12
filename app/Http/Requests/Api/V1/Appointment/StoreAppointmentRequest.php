<?php

namespace App\Http\Requests\Api\V1\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id'  => ['required', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'party_size'   => ['sometimes', 'integer', 'min:1', 'max:20'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
