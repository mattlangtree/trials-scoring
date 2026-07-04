<?php

use App\Models\Event;
use App\Models\Rider;
use App\Models\RiderClass;
use App\Models\Section;
use App\Models\SectionClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A ready-to-score world: one active event with a 2-lap × 15-section
 * class, a rider (#14), a section (6) and an observer claim on it.
 */
function trialsWorld(int $laps = 2, int $sectionCount = 15): array
{
    $event = Event::factory()->create();
    $GLOBALS['trialsWorldEventId'] = $event->id;

    $class = RiderClass::factory()->for($event)->create([
        'name' => 'Trial 2',
        'laps' => $laps,
        'section_count' => $sectionCount,
    ]);

    $rider = Rider::factory()->for($event)->for($class, 'riderClass')->create([
        'rider_number' => 14,
    ]);

    $section = Section::factory()->for($event)->create(['number' => 6]);

    $claim = SectionClaim::factory()->for($section)->create();

    return compact('event', 'class', 'rider', 'section', 'claim');
}

/** API path scoped to the event created by the last trialsWorld() call. */
function api(string $path = ''): string
{
    return '/api/v1/events/'.$GLOBALS['trialsWorldEventId'].$path;
}

function scorePayload(array $overrides = []): array
{
    return array_merge([
        'rider_number' => 14,
        'lap' => 1,
        'points' => 3,
        'device_id' => 'test-device',
        'recorded_at' => now()->toIso8601String(),
    ], $overrides);
}
