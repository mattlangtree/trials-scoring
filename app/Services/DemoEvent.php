<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Section;
use App\Models\SectionClaim;
use App\Models\StagedScore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Creates a self-running demo event: full field of riders, observers on
 * every section, and the whole event's scores pre-generated into
 * staged_scores, due across the requested timespan. ScoreRelease turns
 * them into real broadcast scores as their time comes.
 */
class DemoEvent
{
    private const CLASSES = [
        ['name' => 'Trial 1', 'laps' => 2, 'section_count' => 15],
        ['name' => 'Trial 2', 'laps' => 2, 'section_count' => 15],
        ['name' => 'Trial 4', 'laps' => 3, 'section_count' => 15],
        ['name' => 'Veterans 40+', 'laps' => 2, 'section_count' => 15],
        ['name' => 'Sub Junior', 'laps' => 2, 'section_count' => 8],
    ];

    private const FIRST_NAMES = [
        'Jack', 'Toby', 'Sam', 'Mia', 'Liam', 'Noah', 'Olive', 'Harry', 'Leo', 'Ava',
        'Max', 'Ella', 'Owen', 'Ruby', 'Finn', 'Zoe', 'Kai', 'Ivy', 'Ben', 'Amy',
        'Ted', 'Eve', 'Gus', 'Nina', 'Rex', 'Sky', 'Jed', 'Lux', 'Ash', 'Joy',
    ];

    private const LAST_NAMES = [
        'Miller', 'Price', 'Hill', 'Sanders', 'Ford', 'Reid', 'Trent', 'Cole', 'Marsh', 'Booth',
        'Dunn', 'Frost', 'Blake', 'Nash', 'Hale', 'Lang', 'Storm', 'Chen', 'Walsh', 'Kerr',
    ];

    private const OBSERVERS = [
        'Karen Mills', 'Bob Tanner', 'Priya Shah', 'Dave Hooper', 'Jenny Falk',
        'Ron Baxter', 'Sue Ellis', 'Tim Draper', 'Meg Hurst', 'Col Parker',
        'Pat Reeve', 'Lyn Marsh', 'Steve Naylor', 'Rae Colby', 'Dan Whitfield',
    ];

    private const NAME_PARTS = [
        ['Rocky', 'Muddy', 'Sunny', 'Foggy', 'Dusty', 'Windy', 'Stony', 'Misty'],
        ['Creek', 'Ridge', 'Gully', 'Quarry', 'Falls', 'Hollow', 'Spur', 'Flats'],
    ];

    public static function suggestName(): string
    {
        return self::NAME_PARTS[0][array_rand(self::NAME_PARTS[0])]
            .' '.self::NAME_PARTS[1][array_rand(self::NAME_PARTS[1])]
            .' Trial';
    }

    public function create(?string $name, int $minutes, int $riders = 60, ?string $template = null): Event
    {
        $this->pruneOldEvents();

        $minutes = max(2, min(120, $minutes));
        $start = now();
        $blueprint = $template ? app(EventTemplates::class)->load($template) : null;

        $event = Event::create([
            'name' => trim($name ?: '') ?: ($blueprint['name'] ?? self::suggestName()),
            'event_date' => $start->toDateString(),
            'is_active' => true,
            'riding_ends_at' => $start->copy()->addMinutes($minutes),
            'cards_in_at' => $start->copy()->addMinutes($minutes + 15),
        ]);

        $sectionCount = $blueprint
            ? max(array_column($blueprint['classes'], 'section_count'))
            : 15;

        $sections = collect(range(1, $sectionCount))->map(fn (int $number) => $event->sections()->create([
            'number' => $number,
            'claim_code' => Section::generateClaimCode($event->id),
        ]));

        $claims = $sections->mapWithKeys(fn (Section $section) => [
            $section->number => $section->claims()->create([
                'device_id' => 'demo-obs-s'.$section->number,
                'observer_name' => self::OBSERVERS[($section->number - 1) % count(self::OBSERVERS)],
                'token' => SectionClaim::generateToken(),
                'claimed_at' => $start,
            ]),
        ]);

        $blueprint
            ? $this->buildFromTemplate($event, $blueprint, $claims, $start, $minutes * 60)
            : $this->buildRandomField($event, $riders, $claims, $start, $minutes * 60);

        return $event;
    }

    private function buildRandomField(Event $event, int $riders, $claims, Carbon $start, int $duration): void
    {
        $classes = collect(self::CLASSES)->map(fn (array $class) => $event->riderClasses()->create($class));

        foreach (range(1, max(5, min(200, $riders))) as $i) {
            $event->riders()->create([
                'rider_class_id' => $classes[($i - 1) % $classes->count()]->id,
                'rider_number' => $i,
                'name' => self::FIRST_NAMES[($i * 7) % count(self::FIRST_NAMES)]
                    .' '.self::LAST_NAMES[($i * 13) % count(self::LAST_NAMES)],
            ]);
        }

        $this->stageSchedule($event, $claims, $start, $duration);
    }

    /**
     * Recreate a real event from a template: its classes, its riders, and
     * their actual scores staged across the timespan in riding order.
     */
    private function buildFromTemplate(Event $event, array $blueprint, $claims, Carbon $start, int $duration): void
    {
        $sectionIds = $event->sections()->pluck('id', 'number');
        $number = 0;
        $rows = [];

        foreach ($blueprint['classes'] as $classData) {
            $class = $event->riderClasses()->create([
                'name' => $classData['name'],
                'laps' => $classData['laps'],
                'section_count' => $classData['section_count'],
            ]);

            foreach ($classData['riders'] as $riderData) {
                $rider = $event->riders()->create([
                    'rider_class_id' => $class->id,
                    'rider_number' => ++$number,
                    'name' => $riderData['name'],
                    'status' => $riderData['status'] ?? 'placed',
                    'note' => $riderData['note'] ?? null,
                ]);

                $attempts = collect($riderData['scores'])->sum(fn ($lap) => count($lap));
                if ($attempts === 0) {
                    continue; // dns rider — entered but never sets off
                }

                $offset = mt_rand(0, (int) ($duration * 0.15));
                $finish = mt_rand((int) ($duration * 0.80), $duration - 10);
                $gap = ($finish - $offset) / $attempts;
                $t = (float) $offset;

                foreach ($riderData['scores'] as $lap => $sections) {
                    foreach ($sections as $section => $points) {
                        $t += $gap * (mt_rand(75, 125) / 100);
                        $rows[] = [
                            'event_id' => $event->id,
                            'section_id' => $sectionIds[$section],
                            'rider_id' => $rider->id,
                            'section_claim_id' => $claims[$section]->id,
                            'lap' => $lap,
                            'points' => $points,
                            'idempotency_key' => 'demo-'.$event->id.'-'.$rider->rider_number.'-'.$lap.'-'.$section.'-'.Str::random(6),
                            'due_at' => $start->copy()->addSeconds((int) min($t, $duration - 5)),
                            'created_at' => $start,
                            'updated_at' => $start,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StagedScore::insert($chunk);
        }
    }

    /**
     * Riders start staggered through the first 15% of the event, ride their
     * sections in order lap by lap, and all finish before riding ends.
     * Per-section difficulty skews the points so results look organic.
     */
    private function stageSchedule(Event $event, $claims, Carbon $start, int $duration): void
    {
        $difficulty = [];
        foreach (range(1, 15) as $number) {
            $difficulty[$number] = mt_rand(5, 80) / 100;
        }

        $sectionIds = $event->sections()->pluck('id', 'number');
        $rows = [];

        foreach ($event->riders()->with('riderClass')->get() as $rider) {
            $attempts = $rider->riderClass->laps * $rider->riderClass->section_count;
            $offset = mt_rand(0, (int) ($duration * 0.15));
            $finish = mt_rand((int) ($duration * 0.80), $duration - 10);
            $gap = ($finish - $offset) / $attempts;

            $t = (float) $offset;
            foreach (range(1, $rider->riderClass->laps) as $lap) {
                foreach (range(1, $rider->riderClass->section_count) as $section) {
                    $t += $gap * (mt_rand(75, 125) / 100);
                    $rows[] = [
                        'event_id' => $event->id,
                        'section_id' => $sectionIds[$section],
                        'rider_id' => $rider->id,
                        'section_claim_id' => $claims[$section]->id,
                        'lap' => $lap,
                        'points' => $this->weightedPoints($difficulty[$section]),
                        'idempotency_key' => 'demo-'.$event->id.'-'.$rider->rider_number.'-'.$lap.'-'.$section.'-'.Str::random(6),
                        'due_at' => $start->copy()->addSeconds((int) min($t, $duration - 5)),
                        'created_at' => $start,
                        'updated_at' => $start,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StagedScore::insert($chunk);
        }
    }

    private function weightedPoints(float $difficulty): int
    {
        $weights = [
            0 => 80 - 60 * $difficulty,
            1 => 20,
            2 => 10 + 10 * $difficulty,
            3 => 10 + 20 * $difficulty,
            5 => 5 + 30 * $difficulty,
        ];

        $roll = mt_rand(1, (int) round(array_sum($weights)));
        foreach ($weights as $points => $weight) {
            $roll -= (int) round($weight);
            if ($roll <= 0) {
                return $points;
            }
        }

        return 0;
    }

    /** Demo events self-clean a day after their cards-in deadline. */
    private function pruneOldEvents(): void
    {
        Event::where('cards_in_at', '<', now()->subDay())->delete();
    }
}
