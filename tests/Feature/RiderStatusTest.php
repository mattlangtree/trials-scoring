<?php

use App\Models\Rider;
use App\Services\DemoEvent;
use App\Services\EventTemplates;
use Livewire\Livewire;

it('carries rider statuses through the template loader', function () {
    $template = app(EventTemplates::class)->load('glenmaggie-2026');

    $subJunior = collect($template['classes'])->firstWhere('name', 'Sub Junior');
    $statuses = collect($subJunior['riders'])->pluck('status', 'name');

    expect($statuses['Mimi Brady'])->toBe('placed')
        ->and($statuses['Oliver Langtree'])->toBe('nc')
        ->and($statuses['Hugo Langtree'])->toBe('nc');
});

it('persists status and note onto riders when creating a template event', function () {
    $event = app(DemoEvent::class)->create(null, minutes: 10, template: 'glenmaggie-2026');

    expect($event->riders()->where('status', Rider::STATUS_DNF)->count())->toBeGreaterThan(0)
        ->and($event->riders()->where('status', Rider::STATUS_NC)->count())->toBeGreaterThan(0)
        ->and($event->riders()->where('status', Rider::STATUS_DNS)->count())->toBeGreaterThan(0)
        ->and($event->riders()->where('name', 'Reece Chalmers')->value('note'))->toContain('NA');
});

it('groups standings placed then dnf then nc then dns, with shared ranks for ties', function () {
    $world = trialsWorld();
    $event = $world['event'];
    $class = $world['class'];

    // world rider #14 (placed, no scores yet) plus a controlled field:
    $mk = fn (int $n, string $name, string $status) => $event->riders()->create([
        'rider_class_id' => $class->id, 'rider_number' => $n, 'name' => $name, 'status' => $status,
    ]);
    $winner = $mk(1, 'Winner One', 'placed');
    $tiedA = $mk(2, 'Tied Alpha', 'placed');
    $tiedB = $mk(3, 'Tied Beta', 'placed');
    $dnf = $mk(4, 'Retired Rider', 'dnf');
    $nc = $mk(5, 'Non Comp', 'nc');
    $dns = $mk(6, 'No Show', 'dns');

    $score = fn ($rider, int $lap, int $section, int $points) => App\Models\Score::factory()
        ->for($event)->for($rider)
        ->for(App\Models\Section::factory()->for($event)->create(['number' => $section]), 'section')
        ->create(['lap' => $lap, 'points' => $points, 'status' => 'official']);

    $score($winner, 1, 1, 0);
    $score($tiedA, 1, 2, 3);
    $score($tiedB, 1, 3, 3);
    $score($dnf, 1, 4, 5);

    $component = Livewire::test('dashboard.standings', ['event' => $event]);
    $board = collect($component->instance()->standings)->firstWhere(fn ($b) => $b['class']->id === $class->id);

    expect($board['placed']->pluck('name')->all())->toBe(['Winner One', $world['rider']->name, 'Tied Alpha', 'Tied Beta'])
        ->and($board['placed']->pluck('live_rank')->all())->toBe([1, 2, 3, 3])
        ->and($board['tiedRanks'])->toBe([3])
        ->and($board['dnf']->pluck('name')->all())->toBe(['Retired Rider'])
        ->and($board['nc']->pluck('name')->all())->toBe(['Non Comp'])
        ->and($board['dns']->pluck('name')->all())->toBe(['No Show']);
});
