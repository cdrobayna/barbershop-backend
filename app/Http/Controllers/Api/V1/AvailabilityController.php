<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Availability\GetAvailabilityRequest;
use App\Http\Resources\Api\V1\AvailabilityResource;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
    ) {}

    public function show(GetAvailabilityRequest $request): AvailabilityResource
    {
        $provider = User::findOrFail($request->integer('provider_id'));
        $date = Carbon::parse($request->input('date'))->startOfDay();
        $excludeId = $request->integer('exclude_appointment_id') ?: null;

        $availability = $this->availabilityService->getAvailabilityForDate($provider, $date, $excludeId);

        return new AvailabilityResource($availability);
    }
}
