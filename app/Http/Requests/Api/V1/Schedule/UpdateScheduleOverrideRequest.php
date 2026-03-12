<?php

namespace App\Http\Requests\Api\V1\Schedule;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_working' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sessions' => ['sometimes', 'array'],
            'sessions.*.start_time' => ['required_with:sessions', 'date_format:H:i'],
            'sessions.*.end_time' => ['required_with:sessions', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach ($this->input('sessions', []) as $i => $session) {
                if (isset($session['start_time'], $session['end_time'])) {
                    if ($session['end_time'] <= $session['start_time']) {
                        $v->errors()->add(
                            "sessions.$i.end_time",
                            'End time must be after start time.'
                        );
                    }
                }
            }
        });
    }
}
