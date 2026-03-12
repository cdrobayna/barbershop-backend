<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Appointment\RescheduleAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\StoreAppointmentRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Appointment::query()->with(['provider', 'client'])->orderBy('scheduled_at');

        if ($user->isClient()) {
            $query->forClient($user->id);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
        } else {
            // Provider sees all their appointments with optional filters
            $query->forProvider($user->id);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('client_id')) {
                $query->forClient((int) $request->input('client_id'));
            }
            if ($request->filled('date')) {
                $query->forDate($request->input('date'));
            }
            if ($request->filled('date_from')) {
                $query->where('scheduled_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->where('scheduled_at', '<=', $request->input('date_to').' 23:59:59');
            }
        }

        return AppointmentResource::collection($query->get());
    }

    public function show(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment->load(['provider', 'client']));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $provider = User::findOrFail($request->integer('provider_id'));

        $appointment = $this->appointmentService->create(
            $request->user(),
            $provider,
            $request->validated()
        );

        return (new AppointmentResource($appointment->load(['provider', 'client'])))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('cancel', $appointment);

        $updated = $this->appointmentService->cancel($appointment, $request->user());

        return new AppointmentResource($updated->load(['provider', 'client']));
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('reschedule', $appointment);

        $newAppointment = $this->appointmentService->reschedule(
            $appointment,
            $request->user(),
            $request->validated()
        );

        return (new AppointmentResource($newAppointment->load(['provider', 'client'])))
            ->response()
            ->setStatusCode(201);
    }

    public function confirm(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('confirm', $appointment);

        $updated = $this->appointmentService->confirm($appointment);

        return new AppointmentResource($updated->load(['provider', 'client']));
    }

    public function complete(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('complete', $appointment);

        $updated = $this->appointmentService->complete($appointment);

        return new AppointmentResource($updated->load(['provider', 'client']));
    }

    public function requestReschedule(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('requestReschedule', $appointment);

        $updated = $this->appointmentService->requestReschedule($appointment);

        return new AppointmentResource($updated->load(['provider', 'client']));
    }
}
