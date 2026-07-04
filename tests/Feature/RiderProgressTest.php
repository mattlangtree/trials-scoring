<?php

use App\Models\Score;

it('tells an observer which lap they are about to record for a rider', function () {
    $world = trialsWorld(laps: 3);

    // Rider 14 has already been scored once at section 6.
    Score::factory()
        ->for($world['event'])->for($world['rider'])->for($world['section'])
        ->create(['lap' => 1, 'status' => Score::STATUS_OFFICIAL]);

    $response = $this->getJson(api('/riders/14/progress?section=6'));

    $response->assertOk()
        ->assertJsonPath('rider.number', 14)
        ->assertJsonPath('laps_total', 3)
        ->assertJsonPath('section.laps_scored', 1)
        ->assertJsonPath('section.current_lap', 2)
        ->assertJsonPath('section.remaining_visits', 2)
        ->assertJsonPath('section.complete', false);
});

it('reports a rider as complete at a section once all laps are scored', function () {
    $world = trialsWorld(laps: 2);

    foreach ([1, 2] as $lap) {
        Score::factory()
            ->for($world['event'])->for($world['rider'])->for($world['section'])
            ->create(['lap' => $lap, 'status' => Score::STATUS_OFFICIAL]);
    }

    $this->getJson(api('/riders/14/progress?section=6'))
        ->assertOk()
        ->assertJsonPath('section.complete', true)
        ->assertJsonPath('section.current_lap', null)
        ->assertJsonPath('section.remaining_visits', 0);
});

it('ignores self scores when working out progress', function () {
    $world = trialsWorld(laps: 2);

    Score::factory()
        ->for($world['event'])->for($world['rider'])->for($world['section'])
        ->create(['lap' => 1, 'status' => Score::STATUS_SELF]);

    $this->getJson(api('/riders/14/progress?section=6'))
        ->assertOk()
        ->assertJsonPath('section.laps_scored', 0)
        ->assertJsonPath('section.current_lap', 1);
});

it('404s for an unknown rider number', function () {
    trialsWorld();

    $this->getJson(api('/riders/999/progress'))->assertNotFound();
});
