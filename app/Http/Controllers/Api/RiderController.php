<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Rider;
use App\Models\Score;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    /**
     * Full scorecard for a rider: lap × section grid of official points.
     */
    public function show(Event $event, int $riderNumber): JsonResponse
    {
        $rider = $this->resolveRider($event, $riderNumber);

        $scores = $rider->officialScores()->with('section')->get();

        $laps = collect(range(1, $rider->riderClass->laps))->map(function (int $lap) use ($rider, $scores) {
            $lapScores = $scores->where('lap', $lap);

            return [
                'lap' => $lap,
                'sections' => collect(range(1, $rider->riderClass->section_count))
                    ->map(fn (int $number) => [
                        'section' => $number,
                        'points' => $lapScores->first(fn (Score $s) => $s->section->number === $number)?->points,
                    ])->values(),
                'total' => $lapScores->sum('points'),
            ];
        });

        return response()->json([
            'rider' => $this->riderSummary($rider),
            'laps' => $laps,
            'totals' => [
                'points' => $scores->sum('points'),
                'cleans' => $scores->where('points', 0)->count(),
                'sections_scored' => $scores->count(),
                'sections_expected' => $rider->riderClass->laps * $rider->riderClass->section_count,
            ],
        ]);
    }

    /**
     * The "prime the observer" endpoint. An observer punches in a rider
     * number (optionally with their section) and learns which lap they
     * are about to record and how many more visits to expect.
     */
    public function progress(Request $request, Event $event, int $riderNumber): JsonResponse
    {
        $rider = $this->resolveRider($event, $riderNumber);

        $lapsTotal = $rider->riderClass->laps;
        $response = [
            'rider' => $this->riderSummary($rider),
            'laps_total' => $lapsTotal,
            'sections_per_lap' => $rider->riderClass->section_count,
        ];

        if ($sectionNumber = $request->integer('section')) {
            $section = $event->sections()->where('number', $sectionNumber)->first();
            abort_if(! $section, 404, 'No section with that number exists for this event.');

            $lapsScoredHere = $rider->officialScores()
                ->where('section_id', $section->id)
                ->distinct('lap')
                ->count('lap');

            $complete = $lapsScoredHere >= $lapsTotal;

            $response['section'] = [
                'number' => $section->number,
                'laps_scored' => $lapsScoredHere,
                'current_lap' => $complete ? null : $lapsScoredHere + 1,
                'remaining_visits' => max(0, $lapsTotal - $lapsScoredHere),
                'complete' => $complete,
                'message' => $complete
                    ? "Rider {$rider->rider_number} has finished all {$lapsTotal} laps at section {$section->number}."
                    : sprintf(
                        'Recording lap %d of %d for rider %d — you will see them %s.',
                        $lapsScoredHere + 1,
                        $lapsTotal,
                        $rider->rider_number,
                        $lapsTotal - $lapsScoredHere - 1 === 0
                            ? 'no more times after this'
                            : ($lapsTotal - $lapsScoredHere - 1).' more time(s) after this'
                    ),
            ];
        }

        $response['laps'] = collect(range(1, $lapsTotal))->map(fn (int $lap) => [
            'lap' => $lap,
            'sections_scored' => $rider->officialScores()->where('lap', $lap)->count(),
            'sections_total' => $rider->riderClass->section_count,
        ]);

        return response()->json($response);
    }

    private function resolveRider(Event $event, int $riderNumber): Rider
    {
        $rider = Rider::with('riderClass')
            ->where('event_id', $event->id)
            ->where('rider_number', $riderNumber)
            ->first();

        abort_if(! $rider, 404, 'No rider with that number is entered in this event.');

        return $rider;
    }

    private function riderSummary(Rider $rider): array
    {
        return [
            'number' => $rider->rider_number,
            'name' => $rider->name,
            'class' => $rider->riderClass->name,
        ];
    }
}
