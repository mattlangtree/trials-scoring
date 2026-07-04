<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    /**
     * An event with its classes and section numbers. Claim codes are
     * deliberately excluded — those are handed out by the organiser.
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->event_date->toDateString(),
            ],
            'classes' => $event->riderClasses->map(fn ($class) => [
                'id' => $class->id,
                'name' => $class->name,
                'laps' => $class->laps,
                'section_count' => $class->section_count,
            ]),
            'sections' => $event->sections()->orderBy('number')->pluck('number'),
        ]);
    }
}
