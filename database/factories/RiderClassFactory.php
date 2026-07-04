<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Trial '.fake()->unique()->numberBetween(1, 100),
            'laps' => 2,
            'section_count' => 15,
        ];
    }
}
