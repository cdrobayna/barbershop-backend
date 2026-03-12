<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeeklyScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => User::factory()->provider(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
