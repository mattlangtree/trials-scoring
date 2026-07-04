<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Classic Trial',
            'event_date' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
