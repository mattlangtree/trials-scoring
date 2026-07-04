<?php

use App\Models\Score;

it('returns a lap-by-section scorecard with totals that exclude self scores', function () {
    $world = trialsWorld(laps: 2, sectionCount: 15);

    Score::factory()
        ->for($world['event'])->for($world['rider'])->for($world['section'])
        ->create(['lap' => 1, 'points' => 3, 'status' => Score::STATUS_OFFICIAL]);

    // A self score that must not count.
    Score::factory()
        ->for($world['event'])->for($world['rider'])->for($world['section'])
        ->create(['lap' => 2, 'points' => 5, 'status' => Score::STATUS_SELF]);

    $response = $this->getJson(api('/riders/14'));

    $response->assertOk()
        ->assertJsonPath('rider.number', 14)
        ->assertJsonPath('totals.points', 3)
        ->assertJsonPath('totals.sections_scored', 1)
        ->assertJsonPath('totals.sections_expected', 30)
        ->assertJsonPath('laps.0.total', 3)
        ->assertJsonPath('laps.1.total', 0);

    // Section 6 on lap 1 has the 3; lap 2 remains unscored (self ignored).
    expect($response->json('laps.0.sections.5.points'))->toBe(3)
        ->and($response->json('laps.1.sections.5.points'))->toBeNull();
});

it('counts cleans', function () {
    $world = trialsWorld();

    Score::factory()
        ->for($world['event'])->for($world['rider'])->for($world['section'])
        ->create(['lap' => 1, 'points' => 0, 'status' => Score::STATUS_OFFICIAL]);

    $this->getJson(api('/riders/14'))
        ->assertOk()
        ->assertJsonPath('totals.cleans', 1);
});
