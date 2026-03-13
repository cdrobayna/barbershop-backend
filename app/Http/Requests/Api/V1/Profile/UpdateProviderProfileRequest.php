<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'appointment_duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'min_cancel_notice_hours' => ['required', 'integer', 'min:0', 'max:336'],
        ];
    }
}
