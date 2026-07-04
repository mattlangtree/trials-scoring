<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\RiderClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'rider_class_id' => RiderClass::factory(),
            'rider_number' => fake()->unique()->numberBetween(1, 999),
            'name' => fake()->firstName().' '.fake()->lastName(),
        ];
    }
}
