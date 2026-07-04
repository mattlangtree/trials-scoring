<?php

namespace App\Events;

use App\Models\Score;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoreRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Score $score)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('event.'.$this->score->event_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->score->loadMissing(['rider.riderClass', 'section', 'sectionClaim']);

        return [
            'id' => $this->score->id,
            'rider' => [
                'number' => $this->score->rider->rider_number,
                'name' => $this->score->rider->name,
                'class' => $this->score->rider->riderClass->name,
            ],
            'section' => $this->score->section->number,
            'lap' => $this->score->lap,
            'laps_total' => $this->score->rider->riderClass->laps,
            'points' => $this->score->points,
            'status' => $this->score->status,
            'observer' => $this->score->sectionClaim?->observer_name,
            'recorded_at' => $this->score->recorded_at->toIso8601String(),
            'created_at' => $this->score->created_at->toIso8601String(),
        ];
    }
}
