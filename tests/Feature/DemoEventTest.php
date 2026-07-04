<?php

use App\Events\ScoreRecorded;
use App\Models\Score;
use App\Models\StagedScore;
use App\Services\DemoEvent;
use App\Services\ScoreRelease;
use Illuminate\Support\Facades\Event as EventFacade;

it('creates a fully staged demo event', function () {
    $event = app(DemoEvent::class)->create('Test Trial', minutes: 10, riders: 20);

    $expected = $event->riders()->with('riderClass')->get()
        ->sum(fn ($r) => $r->riderClass->laps * $r->riderClass->section_count);

    expect($event->riders()->count())->toBe(20)
        ->and($event->sections()->count())->toBe(15)
        ->and($event->sections()->whereDoesntHave('claims')->count())->toBe(0)
        ->and($event->stagedScores()->count())->toBe($expected)
        ->and($event->scores()->count())->toBe(0)
        ->and($event->riding_ends_at->diffInMinutes(now()))->toBeLessThanOrEqual(10)
        ->and($event->stagedScores()->max('due_at'))->toBeLessThan($event->riding_ends_at);
});

it('releases due staged scores as real broadcast scores', function () {
    EventFacade::fake([ScoreRecorded::class]);

    $event = app(DemoEvent::class)->create('Test Trial', minutes: 10, riders: 5);
    $release = app(ScoreRelease::class);

    // Force three rows due now; the rest stay in the future.
    $event->stagedScores()->orderBy('due_at')->limit(3)->update(['due_at' => now()->subSecond()]);

    expect($release->tick($event))->toBe(3)
        ->and(Score::where('event_id', $event->id)->count())->toBe(3)
        ->and($event->stagedScores()->whereNull('released_at')->count())
            ->toBe($release->pending($event));

    EventFacade::assertDispatchedTimes(ScoreRecorded::class, 3);

    // Nothing else is due — a second tick is a no-op.
    expect($release->tick($event))->toBe(0)
        ->and(Score::where('event_id', $event->id)->count())->toBe(3);
});

it('releases scores as official with the section observer attached', function () {
    $event = app(DemoEvent::class)->create('Test Trial', minutes: 10, riders: 5);

    $event->stagedScores()->orderBy('due_at')->limit(1)->update(['due_at' => now()->subSecond()]);
    app(ScoreRelease::class)->tick($event);

    expect(Score::where('event_id', $event->id)->sole())
        ->status->toBe(Score::STATUS_OFFICIAL)
        ->section_claim_id->not->toBeNull();
});

it('starting an event from the home page redirects to its dashboard', function () {
    Livewire\Livewire::test('dashboard.home')
        ->set('name', 'Button Trial')
        ->set('minutes', 5)
        ->call('startEvent')
        ->assertRedirect();

    expect(App\Models\Event::where('name', 'Button Trial')->exists())->toBeTrue();
});

it('prunes events a day after their cards-in deadline when a new one starts', function () {
    $old = App\Models\Event::factory()->create(['cards_in_at' => now()->subDays(2)]);

    app(DemoEvent::class)->create('Fresh Trial', minutes: 5, riders: 5);

    expect(App\Models\Event::find($old->id))->toBeNull();
});
