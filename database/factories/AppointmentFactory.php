<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => User::factory()->provider(),
            'client_id' => User::factory()->client(),
            'scheduled_at' => $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'party_size' => 1,
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
            'cancelled_by' => null,
            'reschedule_requested_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => AppointmentStatus::Pending->value]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => AppointmentStatus::Confirmed->value]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => AppointmentStatus::Cancelled->value]);
    }

    public function rescheduleRequested(): static
    {
        return $this->state([
            'status' => AppointmentStatus::RescheduleRequested->value,
            'reschedule_requested_by' => 'provider',
        ]);
    }
}
