<?php

namespace App\Services;

use App\Events\ScoreRecorded;
use App\Models\Event;
use App\Models\Score;
use App\Models\StagedScore;
use Illuminate\Support\Facades\Cache;

/**
 * Turns due staged scores into real scores and broadcasts them. Called
 * from a polling ticker on the event dashboard, so any viewer's browser
 * drives the simulation — no queue worker or shell required.
 */
class ScoreRelease
{
    public function tick(Event $event, int $limit = 150): int
    {
        // Several viewers may tick at once; only one releases per window.
        $lock = Cache::lock("score-release-{$event->id}", 10);

        if (! $lock->get()) {
            return 0;
        }

        try {
            $due = StagedScore::where('event_id', $event->id)
                ->whereNull('released_at')
                ->where('due_at', '<=', now())
                ->orderBy('due_at')
                ->limit($limit)
                ->get();

            foreach ($due as $staged) {
                $score = Score::create([
                    'event_id' => $staged->event_id,
                    'section_id' => $staged->section_id,
                    'rider_id' => $staged->rider_id,
                    'section_claim_id' => $staged->section_claim_id,
                    'lap' => $staged->lap,
                    'points' => $staged->points,
                    'status' => Score::STATUS_OFFICIAL,
                    'idempotency_key' => $staged->idempotency_key,
                    'device_id' => 'demo-simulator',
                    'recorded_at' => $staged->due_at,
                ]);

                rescue(fn () => ScoreRecorded::dispatch($score), report: false);

                $staged->update(['released_at' => now()]);
            }

            return $due->count();
        } finally {
            $lock->release();
        }
    }

    public function pending(Event $event): int
    {
        return StagedScore::where('event_id', $event->id)->whereNull('released_at')->count();
    }
}
