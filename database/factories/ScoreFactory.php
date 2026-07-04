<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Rider;
use App\Models\Score;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'section_id' => Section::factory(),
            'rider_id' => Rider::factory(),
            'section_claim_id' => null,
            'lap' => 1,
            'points' => fake()->randomElement(Score::VALID_POINTS),
            'status' => Score::STATUS_OFFICIAL,
            'idempotency_key' => (string) Str::uuid(),
            'device_id' => (string) Str::uuid(),
            'recorded_at' => now(),
        ];
    }
}
