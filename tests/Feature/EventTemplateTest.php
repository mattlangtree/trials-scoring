<?php

use App\Services\DemoEvent;
use App\Services\EventTemplates;

it('lists the real-event templates', function () {
    expect(EventTemplates::options())->toHaveKeys(['glenmaggie-2025', 'glenmaggie-2026']);
});

it('loads glenmaggie 2026 with its real 10-section layout', function () {
    $template = app(EventTemplates::class)->load('glenmaggie-2026');

    expect($template['classes'])->toHaveCount(11)
        ->and(collect($template['classes'])->sum(fn ($c) => count($c['riders'])))->toBe(77)
        ->and(collect($template['classes'])->max('section_count'))->toBe(10);

    // Matt Langtree's staged scores must sum to his real published total.
    $veterans = collect($template['classes'])->firstWhere('name', 'Veterans');
    $matt = collect($veterans['riders'])->firstWhere('name', 'Matt Langtree');

    expect(collect($matt['scores'])->flatten()->sum())->toBe(28)
        ->and($veterans['laps'])->toBe(8);
});

it('loads glenmaggie with exact per-section scores', function () {
    $template = app(EventTemplates::class)->load('glenmaggie-2025');

    expect($template['classes'])->toHaveCount(5)
        ->and(collect($template['classes'])->sum(fn ($c) => count($c['riders'])))->toBe(62);

    // Every score is a legal trials mark.
    foreach ($template['classes'] as $class) {
        foreach ($class['riders'] as $rider) {
            foreach ($rider['scores'] as $sections) {
                foreach ($sections as $points) {
                    expect($points)->toBeIn([0, 1, 2, 3, 5]);
                }
            }
        }
    }

    // A rider's flattened scores sum to their source total (validators
    // guarantee sections → lap → day → total arithmetic in the source).
    $source = json_decode(file_get_contents(database_path('templates/glenmaggie-2025.json')), true);
    $sourceRider = $source[0]['riders'][0];
    $templateRider = $template['classes'][0]['riders'][0];

    expect(collect($templateRider['scores'])->flatten()->sum())->toBe($sourceRider['total']);
});

it('creates a staged event from a template with the real field', function () {
    $event = app(DemoEvent::class)->create(null, minutes: 10, template: 'glenmaggie-2025');

    expect($event->name)->toBe('Glenmaggie Easter Trial 2025')
        ->and($event->riderClasses()->count())->toBe(5)
        ->and($event->riders()->count())->toBe(62)
        ->and($event->stagedScores()->count())->toBeGreaterThan(1000)
        ->and($event->stagedScores()->max('due_at'))->toBeLessThan($event->riding_ends_at);

    // Spot-check a rider: staged points must sum to their real total.
    $source = json_decode(file_get_contents(database_path('templates/glenmaggie-2025.json')), true);
    $rider = $event->riders()->where('name', trim($source[0]['riders'][0]['firstName'].' '.$source[0]['riders'][0]['lastName']))->first();

    expect((int) $event->stagedScores()->where('rider_id', $rider->id)->sum('points'))
        ->toBe($source[0]['riders'][0]['total']);
});
