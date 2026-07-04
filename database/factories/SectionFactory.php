<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'number' => fake()->unique()->numberBetween(1, 250),
            'claim_code' => fn (array $attributes) => Section::generateClaimCode((int) $attributes['event_id']),
        ];
    }
}
