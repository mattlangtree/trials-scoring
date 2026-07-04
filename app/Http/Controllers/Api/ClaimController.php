<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Section;
use App\Models\SectionClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClaimController extends Controller
{
    /**
     * An observer claims a section by entering the code the organiser
     * gave them (e.g. "CZ6"). Returns the token they must send with
     * scores for those scores to count as official.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:255'],
            'observer_name' => ['required', 'string', 'max:255'],
        ]);

        $section = Section::where('event_id', $event->id)
            ->where('claim_code', strtoupper(trim($validated['code'])))
            ->first();

        if (! $section) {
            throw ValidationException::withMessages([
                'code' => 'That code does not match any section for this event.',
            ]);
        }

        $claim = $section->claims()->create([
            'device_id' => $validated['device_id'],
            'observer_name' => $validated['observer_name'],
            'token' => SectionClaim::generateToken(),
            'claimed_at' => now(),
        ]);

        return response()->json([
            'claim_token' => $claim->token,
            'section' => [
                'id' => $section->id,
                'number' => $section->number,
            ],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'observer_name' => $claim->observer_name,
            'claimed_at' => $claim->claimed_at->toIso8601String(),
        ], 201);
    }
}
