<?php

use App\Events\ScoreRecorded;
use App\Models\Score;
use App\Models\Section;
use Illuminate\Support\Facades\Event as EventFacade;

it('records an official score when submitted with a valid claim token', function () {
    $world = trialsWorld();

    $response = $this->withToken($world['claim']->token)
        ->postJson(api('/scores'), scorePayload(), ['Idempotency-Key' => 'key-1']);

    $response->assertCreated()
        ->assertJsonPath('replayed', false)
        ->assertJsonPath('data.status', 'official')
        ->assertJsonPath('data.section', 6)
        ->assertJsonPath('data.observer', $world['claim']->observer_name);

    expect(Score::sole())
        ->status->toBe(Score::STATUS_OFFICIAL)
        ->section_claim_id->toBe($world['claim']->id);
});

it('records a self score without a token and requires a section number', function () {
    trialsWorld();

    $this->postJson(api('/scores'), scorePayload(), ['Idempotency-Key' => 'key-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('section_number');

    $this->postJson(api('/scores'), scorePayload(['section_number' => 6]), ['Idempotency-Key' => 'key-2'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'self');
});

it('rejects an invalid claim token', function () {
    trialsWorld();

    $this->withToken('not-a-real-token')
        ->postJson(api('/scores'), scorePayload(), ['Idempotency-Key' => 'key-1'])
        ->assertUnauthorized();
});

it('replays the original score when the same idempotency key is submitted twice', function () {
    $world = trialsWorld();

    $first = $this->withToken($world['claim']->token)
        ->postJson(api('/scores'), scorePayload(['points' => 3]), ['Idempotency-Key' => 'dupe-key']);
    $first->assertCreated();

    // Same key, even with different points — the stored score wins.
    $second = $this->withToken($world['claim']->token)
        ->postJson(api('/scores'), scorePayload(['points' => 5]), ['Idempotency-Key' => 'dupe-key']);

    $second->assertOk()
        ->assertJsonPath('replayed', true)
        ->assertJsonPath('data.points', 3)
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Score::count())->toBe(1);
});

it('requires an idempotency key', function () {
    trialsWorld();

    $this->postJson(api('/scores'), scorePayload(['section_number' => 6]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('idempotency_key');
});

it('accepts the idempotency key as a body field', function () {
    trialsWorld();

    $this->postJson(api('/scores'), scorePayload(['section_number' => 6, 'idempotency_key' => 'body-key']))
        ->assertCreated();

    expect(Score::sole()->idempotency_key)->toBe('body-key');
});

it('rejects points outside the trials marks', function (int $points) {
    trialsWorld();

    $this->postJson(api('/scores'), scorePayload(['section_number' => 6, 'points' => $points]), ['Idempotency-Key' => 'key-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('points');
})->with([4, 6, -1]);

it('rejects a lap beyond the laps ridden by the rider class', function () {
    trialsWorld(laps: 2);

    $this->postJson(api('/scores'), scorePayload(['section_number' => 6, 'lap' => 3]), ['Idempotency-Key' => 'key-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lap');
});

it('rejects a section not ridden by the rider class', function () {
    $world = trialsWorld(sectionCount: 8);
    Section::factory()->for($world['event'])->create(['number' => 12]);

    $this->postJson(api('/scores'), scorePayload(['section_number' => 12]), ['Idempotency-Key' => 'key-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('section_number');
});

it('rejects an unknown rider number', function () {
    trialsWorld();

    $this->postJson(api('/scores'), scorePayload(['rider_number' => 999, 'section_number' => 6]), ['Idempotency-Key' => 'key-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rider_number');
});

it('broadcasts ScoreRecorded when a score is created but not when replayed', function () {
    $world = trialsWorld();
    EventFacade::fake([ScoreRecorded::class]);

    $this->withToken($world['claim']->token)
        ->postJson(api('/scores'), scorePayload(), ['Idempotency-Key' => 'key-1']);
    EventFacade::assertDispatchedTimes(ScoreRecorded::class, 1);

    $this->withToken($world['claim']->token)
        ->postJson(api('/scores'), scorePayload(), ['Idempotency-Key' => 'key-1']);
    EventFacade::assertDispatchedTimes(ScoreRecorded::class, 1);
});
