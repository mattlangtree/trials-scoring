<?php

namespace App\Http\Controllers\Api;

use App\Events\ScoreRecorded;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Rider;
use App\Models\Score;
use App\Models\Section;
use App\Models\SectionClaim;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScoreController extends Controller
{
    /**
     * Record a section score from a device.
     *
     * Idempotent: the client supplies an Idempotency-Key header (or
     * idempotency_key body field). Replaying the same key returns the
     * originally stored score with `replayed: true` instead of a duplicate.
     *
     * A valid section-claim bearer token makes the score official (the
     * section comes from the claim). Without one the score is recorded
     * as a self-score against the supplied section_number and is
     * excluded from results.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        if (! $idempotencyKey) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An Idempotency-Key header (or idempotency_key field) is required.',
            ]);
        }

        if ($existing = Score::where('idempotency_key', $idempotencyKey)->first()) {
            return $this->scoreResponse($existing, replayed: true);
        }

        $claim = $this->resolveClaim($request, $event);

        $validated = $request->validate([
            'rider_number' => ['required', 'integer'],
            'lap' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', Rule::in(Score::VALID_POINTS)],
            'device_id' => ['required', 'string', 'max:255'],
            'recorded_at' => ['required', 'date'],
            'section_number' => [Rule::requiredIf(! $claim), 'nullable', 'integer'],
        ]);

        $rider = Rider::with('riderClass')
            ->where('event_id', $event->id)
            ->where('rider_number', $validated['rider_number'])
            ->first();

        if (! $rider) {
            throw ValidationException::withMessages([
                'rider_number' => 'No rider with that number is entered in this event.',
            ]);
        }

        $section = $claim
            ? $claim->section
            : Section::where('event_id', $event->id)->where('number', $validated['section_number'])->first();

        if (! $section) {
            throw ValidationException::withMessages([
                'section_number' => 'No section with that number exists for this event.',
            ]);
        }

        if ($validated['lap'] > $rider->riderClass->laps) {
            throw ValidationException::withMessages([
                'lap' => "Lap {$validated['lap']} is beyond the {$rider->riderClass->laps} laps ridden by {$rider->riderClass->name}.",
            ]);
        }

        if ($section->number > $rider->riderClass->section_count) {
            throw ValidationException::withMessages([
                'section_number' => "Section {$section->number} is not ridden by {$rider->riderClass->name} (sections 1–{$rider->riderClass->section_count}).",
            ]);
        }

        try {
            $score = Score::create([
                'event_id' => $event->id,
                'section_id' => $section->id,
                'rider_id' => $rider->id,
                'section_claim_id' => $claim?->id,
                'lap' => $validated['lap'],
                'points' => $validated['points'],
                'status' => $claim ? Score::STATUS_OFFICIAL : Score::STATUS_SELF,
                'idempotency_key' => $idempotencyKey,
                'device_id' => $validated['device_id'],
                'recorded_at' => Carbon::parse($validated['recorded_at'])->utc(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two devices raced on the same key — return the winner.
            return $this->scoreResponse(
                Score::where('idempotency_key', $idempotencyKey)->firstOrFail(),
                replayed: true,
            );
        }

        // A score must never be lost because the websocket server is down.
        rescue(fn () => ScoreRecorded::dispatch($score), report: true);

        return $this->scoreResponse($score, replayed: false);
    }

    private function resolveClaim(Request $request, Event $event): ?SectionClaim
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $claim = SectionClaim::with('section')
            ->where('token', $token)
            ->whereHas('section', fn ($query) => $query->where('event_id', $event->id))
            ->first();

        if (! $claim) {
            abort(401, 'Invalid or expired claim token.');
        }

        return $claim;
    }

    private function scoreResponse(Score $score, bool $replayed): JsonResponse
    {
        $score->loadMissing(['rider.riderClass', 'section', 'sectionClaim']);

        return response()->json([
            'replayed' => $replayed,
            'data' => [
                'id' => $score->id,
                'rider' => [
                    'number' => $score->rider->rider_number,
                    'name' => $score->rider->name,
                    'class' => $score->rider->riderClass->name,
                ],
                'section' => $score->section->number,
                'lap' => $score->lap,
                'points' => $score->points,
                'status' => $score->status,
                'observer' => $score->sectionClaim?->observer_name,
                'idempotency_key' => $score->idempotency_key,
                'recorded_at' => $score->recorded_at->toIso8601String(),
                'created_at' => $score->created_at->toIso8601String(),
            ],
        ], $replayed ? 200 : 201);
    }
}
