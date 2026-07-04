<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\SectionClaim;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SectionClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'device_id' => (string) Str::uuid(),
            'observer_name' => fake()->firstName().' '.fake()->lastName(),
            'token' => SectionClaim::generateToken(),
            'claimed_at' => now(),
        ];
    }
}
