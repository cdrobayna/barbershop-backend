<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateClientProfileRequest;
use App\Http\Requests\Api\V1\Profile\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Api\V1\Profile\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProviderProfileRequest;
use App\Http\Resources\Api\V1\ProviderProfileResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\NotificationPreference;
use App\Models\ProviderProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function showClientProfile(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function updateClientProfile(UpdateClientProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->string('password')),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = NotificationPreference::query()
            ->forUser($user->id)
            ->get()
            ->keyBy(fn (NotificationPreference $preference) => "{$preference->event_type->value}|{$preference->channel->value}");

        $resolvedPreferences = collect(NotificationEventType::cases())
            ->flatMap(function (NotificationEventType $eventType) use ($preferences) {
                return collect(NotificationChannel::cases())->map(function (NotificationChannel $channel) use ($eventType, $preferences) {
                    $key = "{$eventType->value}|{$channel->value}";

                    return [
                        'event_type' => $eventType->value,
                        'channel' => $channel->value,
                        'enabled' => $preferences->get($key)?->enabled ?? true,
                    ];
                });
            })
            ->values();

        return response()->json([
            'data' => [
                'preferences' => $resolvedPreferences,
                'reminder_lead_time_hours' => NotificationPreference::getReminderLeadTime($user),
            ],
        ]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->input('preferences', []) as $preference) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'event_type' => $preference['event_type'],
                    'channel' => $preference['channel'],
                ],
                [
                    'enabled' => (bool) $preference['enabled'],
                ],
            );
        }

        if ($request->filled('reminder_lead_time_hours')) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'event_type' => NotificationEventType::AppointmentReminder,
                    'channel' => NotificationChannel::Email,
                ],
                [
                    'enabled' => true,
                    'lead_time_hours' => $request->integer('reminder_lead_time_hours'),
                ],
            );
        }

        return $this->getNotificationPreferences($request);
    }

    public function showProviderProfile(Request $request): ProviderProfileResource
    {
        $profile = $this->resolveProviderProfile($request->user()->id);

        return new ProviderProfileResource($profile->load('user'));
    }

    public function updateProviderProfile(UpdateProviderProfileRequest $request): JsonResponse
    {
        $provider = $request->user();

        $profile = DB::transaction(function () use ($provider, $request) {
            $provider->update([
                'name' => $request->string('name')->toString(),
            ]);

            $profile = $this->resolveProviderProfile($provider->id);

            $profile->update([
                'bio' => $request->input('bio'),
                'photo_url' => $request->input('photo_url'),
                'appointment_duration_minutes' => $request->integer('appointment_duration_minutes'),
                'min_cancel_notice_hours' => $request->integer('min_cancel_notice_hours'),
            ]);

            return $profile;
        });

        return (new ProviderProfileResource($profile->load('user')))
            ->response()
            ->setStatusCode(200);
    }

    private function resolveProviderProfile(int $providerId): ProviderProfile
    {
        return ProviderProfile::firstOrCreate(
            ['user_id' => $providerId],
            [
                'appointment_duration_minutes' => (int) config('booking.default_appointment_duration_minutes'),
                'min_cancel_notice_hours' => (int) config('booking.default_min_cancel_notice_hours'),
            ]
        );
    }
}
