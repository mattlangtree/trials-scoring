<?php

use App\Models\Event;
use App\Models\Score;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Event $event;

    #[On('echo:event.{event.id},ScoreRecorded')]
    public function onScoreRecorded(): void
    {
        // Just re-render — standings recompute from the database.
    }

    #[Computed]
    public function standings()
    {
        return $this->event->riderClasses()->orderBy('name')->get()->map(function ($class) {
            $riders = $class->riders()
                ->withSum(['scores as points' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)], 'points')
                ->withCount(['scores as scored' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)])
                ->withCount(['scores as cleans' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)->where('points', 0)])
                ->get()
                // Lowest points wins; ties split on most cleans.
                ->sortBy([['points', 'asc'], ['cleans', 'desc']])
                ->values();

            return ['class' => $class, 'riders' => $riders];
        });
    }
};
?>

<div class="p-6 space-y-6" wire:poll.30s>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Standings</h1>
            <p class="text-sm text-zinc-400 mt-1">{{ $this->event->name }} — lowest points wins, ties split on cleans. Official scores only.</p>
        </div>

        <div class="grid md:grid-cols-2 gap-4 items-start">
            @foreach ($this->standings as $board)
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50">
                    <div class="px-4 py-3 border-b border-zinc-800 flex items-baseline justify-between">
                        <h2 class="font-medium">{{ $board['class']->name }}</h2>
                        <span class="text-xs text-zinc-500">{{ $board['class']->laps }} laps × {{ $board['class']->section_count }} sections</span>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-zinc-500">
                                <th class="px-4 py-2 font-normal w-12">Pos</th>
                                <th class="py-2 font-normal w-10">#</th>
                                <th class="py-2 font-normal">Rider</th>
                                <th class="py-2 font-normal text-right">Scored</th>
                                <th class="py-2 font-normal text-right">Cleans</th>
                                <th class="py-2 font-normal text-right pr-4">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($board['riders'] as $i => $rider)
                                <tr class="border-t border-zinc-800/60">
                                    <td class="px-4 py-2 font-semibold {{ $i === 0 ? 'text-amber-400' : 'text-zinc-400' }}">{{ $i + 1 }}</td>
                                    <td class="py-2 text-zinc-500 tabular-nums">{{ $rider->rider_number }}</td>
                                    <td class="py-2">
                                        <a href="{{ route('event.riders', ['event' => $this->event, 'rider' => $rider->rider_number]) }}" class="hover:text-amber-400">{{ $rider->name }}</a>
                                    </td>
                                    <td class="py-2 text-right tabular-nums text-zinc-500">{{ $rider->scored }}/{{ $board['class']->laps * $board['class']->section_count }}</td>
                                    <td class="py-2 text-right tabular-nums text-emerald-400">{{ $rider->cleans }}</td>
                                    <td class="py-2 text-right pr-4 font-medium tabular-nums">{{ $rider->points ?? 0 }}</td>
                                </tr>
                            @endforeach
                            @if ($board['riders']->isEmpty())
                                <tr class="border-t border-zinc-800/60">
                                    <td colspan="6" class="px-4 py-6 text-center text-zinc-600">No riders entered.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
</div>
