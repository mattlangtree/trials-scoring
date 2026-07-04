<?php

namespace App\Services;

use App\Models\Score;

/**
 * Preconfigured event templates built from real results exported from
 * Entry Place (database/templates/*.json, in its score_data format).
 *
 * Multi-day events are flattened into consecutive laps (2 days × 3 laps
 * becomes laps 1–6). Where a dataset has real per-section scores they are
 * replayed exactly; where it only has per-lap totals, each total is
 * decomposed into plausible section marks that sum to it.
 */
class EventTemplates
{
    private const SECTIONS_PER_LAP = 15;

    private const TEMPLATES = [
        'glenmaggie-2025' => [
            'file' => 'glenmaggie-2025.json',
            'name' => 'Glenmaggie Easter Trial 2025',
            'detail' => 'Real per-section scores — 62 riders, 5 classes, 2 days',
        ],
        'tassie-2day-2026' => [
            'file' => 'tassie-2day-2026.json',
            'name' => 'Tassie 2-Day 2026',
            'detail' => 'Real lap totals, synthesised sections — 47 riders, 8 classes',
        ],
        'pptc-round1-2026' => [
            'file' => 'pptc-round1-2026.json',
            'name' => 'PPTC Club Round 1 2026',
            'detail' => 'Real lap totals, synthesised sections — 32 riders, 7 classes',
        ],
    ];

    /** @return array<string, array{name: string, detail: string}> */
    public static function options(): array
    {
        return collect(self::TEMPLATES)
            ->map(fn (array $t) => ['name' => $t['name'], 'detail' => $t['detail']])
            ->all();
    }

    public static function exists(string $key): bool
    {
        return isset(self::TEMPLATES[$key]);
    }

    /**
     * @return array{name: string, classes: list<array{name: string, laps: int, section_count: int,
     *               riders: list<array{name: string, scores: array<int, array<int, int>>}>}>}
     */
    public function load(string $key): array
    {
        $meta = self::TEMPLATES[$key] ?? throw new \InvalidArgumentException("Unknown event template [$key].");
        $data = json_decode(file_get_contents(database_path('templates/'.$meta['file'])), true);

        $classes = [];
        foreach ($data as $class) {
            $riders = [];
            $classLaps = 1;

            foreach ($class['riders'] as $rider) {
                $scores = $this->riderScores($rider);
                $classLaps = max($classLaps, count($scores));

                $riders[] = [
                    'name' => trim($rider['firstName'].' '.$rider['lastName']),
                    'scores' => $scores,
                ];
            }

            $classes[] = [
                'name' => $class['className'],
                'laps' => $classLaps,
                'section_count' => self::SECTIONS_PER_LAP,
                'riders' => $riders,
            ];
        }

        return ['name' => $meta['name'], 'classes' => $classes];
    }

    /**
     * Flatten a rider's results into [lap => [section => points]].
     * Laps without data (dns, retired) simply produce no scores.
     *
     * @return array<int, array<int, int>>
     */
    private function riderScores(array $rider): array
    {
        $flatLaps = [];

        if (isset($rider['days'])) {
            foreach ($rider['days'] as $day) {
                foreach ($day['laps'] as $lap) {
                    $flatLaps[] = $lap;
                }
            }
        } else {
            foreach ($rider['laps'] ?? [] as $total) {
                $flatLaps[] = ['total' => $total];
            }
        }

        $scores = [];
        foreach ($flatLaps as $i => $lap) {
            if (isset($lap['sections'])) {
                $marks = collect($lap['sections'])
                    ->map(fn ($points) => $this->clampMark((int) $points))
                    ->values()
                    ->all();
                $scores[$i + 1] = array_combine(range(1, count($marks)), $marks);
            } elseif (isset($lap['total']) && is_int($lap['total'])) {
                $scores[$i + 1] = $this->synthesiseSections($lap['total']);
            }
        }

        return $scores;
    }

    /** Decompose a lap total into section marks (0/1/2/3/5) that sum to it. */
    private function synthesiseSections(int $total): array
    {
        $sections = array_fill(0, self::SECTIONS_PER_LAP, 0);
        $remaining = min($total, self::SECTIONS_PER_LAP * 5);

        // Marks upgrade 0→1→2→3→5; the last step costs 2.
        $steps = [0 => 1, 1 => 1, 2 => 1, 3 => 2];

        while ($remaining > 0) {
            $candidates = array_keys(array_filter(
                $sections,
                fn ($mark) => $mark < 5 && $steps[$mark] <= $remaining,
            ));

            if ($candidates === []) {
                break; // e.g. remaining=1 with every section on 3 — accept the near miss
            }

            $pick = $candidates[array_rand($candidates)];
            $remaining -= $steps[$sections[$pick]];
            $sections[$pick] = $sections[$pick] === 3 ? 5 : $sections[$pick] + 1;
        }

        // Re-key sections 1-based to match section numbers.
        return array_combine(range(1, self::SECTIONS_PER_LAP), $sections);
    }

    private function clampMark(int $points): int
    {
        return in_array($points, Score::VALID_POINTS, true)
            ? $points
            : collect(Score::VALID_POINTS)->sortBy(fn ($v) => abs($v - $points))->first();
    }
}
