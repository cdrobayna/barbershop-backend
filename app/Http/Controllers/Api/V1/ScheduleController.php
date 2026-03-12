<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Schedule\StoreScheduleOverrideRequest;
use App\Http\Requests\Api\V1\Schedule\UpdateScheduleOverrideRequest;
use App\Http\Requests\Api\V1\Schedule\UpdateWeeklyDayRequest;
use App\Http\Resources\Api\V1\ScheduleOverrideResource;
use App\Http\Resources\Api\V1\WeeklyScheduleResource;
use App\Models\ScheduleOverride;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $schedule = $this->scheduleService->getWeeklySchedule($request->user());

        return WeeklyScheduleResource::collection($schedule);
    }

    public function updateDay(UpdateWeeklyDayRequest $request, int $dayOfWeek): WeeklyScheduleResource
    {
        abort_if($dayOfWeek < 0 || $dayOfWeek > 6, 422, 'Day of week must be between 0 (Sunday) and 6 (Saturday).');

        $schedule = $this->scheduleService->updateDay(
            $request->user(),
            $dayOfWeek,
            $request->boolean('is_active'),
            $request->input('sessions', [])
        );

        return new WeeklyScheduleResource($schedule);
    }

    public function indexOverrides(Request $request): AnonymousResourceCollection
    {
        $overrides = $this->scheduleService->getOverrides($request->user());

        return ScheduleOverrideResource::collection($overrides);
    }

    public function storeOverride(StoreScheduleOverrideRequest $request): JsonResponse
    {
        $override = $this->scheduleService->createOverride($request->user(), $request->validated());

        return (new ScheduleOverrideResource($override))
            ->response()
            ->setStatusCode(201);
    }

    public function updateOverride(UpdateScheduleOverrideRequest $request, ScheduleOverride $override): ScheduleOverrideResource
    {
        abort_if($override->provider_id !== $request->user()->id, 403, 'This override does not belong to you.');

        $updated = $this->scheduleService->updateOverride($override, $request->validated());

        return new ScheduleOverrideResource($updated);
    }

    public function destroyOverride(Request $request, ScheduleOverride $override): JsonResponse
    {
        abort_if($override->provider_id !== $request->user()->id, 403, 'This override does not belong to you.');

        $this->scheduleService->deleteOverride($override);

        return response()->json(null, 204);
    }
}
