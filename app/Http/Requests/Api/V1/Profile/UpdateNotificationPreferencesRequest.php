<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['sometimes', 'array'],
            'preferences.*.event_type' => [
                'required_with:preferences',
                Rule::in(array_map(fn (NotificationEventType $event) => $event->value, NotificationEventType::cases())),
            ],
            'preferences.*.channel' => [
                'required_with:preferences',
                Rule::in(array_map(fn (NotificationChannel $channel) => $channel->value, NotificationChannel::cases())),
            ],
            'preferences.*.enabled' => ['required_with:preferences', 'boolean'],
            'reminder_lead_time_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
        ];
    }
}
