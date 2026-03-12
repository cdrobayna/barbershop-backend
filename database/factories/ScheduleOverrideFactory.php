<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => User::factory()->provider(),
            'date' => $this->faker->dateTimeBetween('+1 day', '+6 months')->format('Y-m-d'),
            'is_working' => $this->faker->boolean(),
            'reason' => $this->faker->optional()->sentence(),
        ];
    }

    public function working(): static
    {
        return $this->state(['is_working' => true]);
    }

    public function dayOff(): static
    {
        return $this->state(['is_working' => false]);
    }
}
