<?php

use App\Models\Event;
use App\Models\Rider;
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

    /**
     * Grouped like Entry Place's results tables: placed riders ranked
     * (ties share a rank, shown with '='), then DNF, then NC, then DNS.
     */
    #[Computed]
    public function standings()
    {
        return $this->event->riderClasses()->orderBy('name')->get()->map(function ($class) {
            $riders = $class->riders()
                ->withSum(['scores as points' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)], 'points')
                ->withCount(['scores as scored' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)])
                ->withCount(['scores as cleans' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)->where('points', 0)])
                ->get();

            $placed = $riders->where('status', Rider::STATUS_PLACED)
                ->sortBy([['points', 'asc'], ['cleans', 'desc']])
                ->values();

            // Competition ranking: equal (points, cleans) share a rank, next rank skips.
            $rank = 0;
            $previousKey = null;
            foreach ($placed as $i => $rider) {
                $key = ($rider->points ?? 0).':'.$rider->cleans;
                if ($key !== $previousKey) {
                    $rank = $i + 1;
                    $previousKey = $key;
                }
                $rider->live_rank = $rank;
            }
            $tiedRanks = $placed->countBy('live_rank')->filter(fn ($n) => $n > 1)->keys()->all();

            return [
                'class' => $class,
                'placed' => $placed,
                'tiedRanks' => $tiedRanks,
                'dnf' => $riders->where('status', Rider::STATUS_DNF)->sortBy([['points', 'asc'], ['cleans', 'desc']])->values(),
                'nc' => $riders->where('status', Rider::STATUS_NC)->values(),
                'dns' => $riders->where('status', Rider::STATUS_DNS)->values(),
            ];
        });
    }
};
?>

<div class="p-6 space-y-6" wire:poll.10s>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Standings</h1>
            <p class="text-sm text-zinc-400 mt-1">{{ $this->event->name }} — lowest points wins, ties share a place. Official scores only; NC and DNS riders do not compete.</p>
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
                            @foreach ($board['placed'] as $rider)
                                <tr class="border-t border-zinc-800/60">
                                    <td class="px-4 py-2 font-semibold {{ $rider->live_rank === 1 ? 'text-amber-400' : 'text-zinc-400' }}">
                                        {{ $rider->live_rank }}@if (in_array($rider->live_rank, $board['tiedRanks']))=@endif
                                    </td>
                                    <td class="py-2 text-zinc-500 tabular-nums">{{ $rider->rider_number }}</td>
                                    <td class="py-2">
                                        <a href="{{ route('event.riders', ['event' => $this->event, 'rider' => $rider->rider_number]) }}" class="hover:text-amber-400">{{ $rider->name }}</a>
                                        @if ($rider->note)
                                            <span class="block text-xs text-zinc-500">{{ $rider->note }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right tabular-nums text-zinc-500">{{ $rider->scored }}/{{ $board['class']->laps * $board['class']->section_count }}</td>
                                    <td class="py-2 text-right tabular-nums text-emerald-400">{{ $rider->cleans }}</td>
                                    <td class="py-2 text-right pr-4 font-medium tabular-nums">{{ $rider->points ?? 0 }}</td>
                                </tr>
                            @endforeach

                            {{-- DNF: rode but retired — scores shown, status instead of a place --}}
                            @foreach ($board['dnf'] as $rider)
                                <tr class="border-t border-zinc-800/60 text-zinc-500">
                                    <td class="px-4 py-2 text-xs uppercase tracking-wide">DNF</td>
                                    <td class="py-2 tabular-nums">{{ $rider->rider_number }}</td>
                                    <td class="py-2">
                                        <a href="{{ route('event.riders', ['event' => $this->event, 'rider' => $rider->rider_number]) }}" class="hover:text-zinc-300">{{ $rider->name }}</a>
                                        @if ($rider->note)
                                            <span class="block text-xs text-zinc-600">{{ $rider->note }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right tabular-nums">{{ $rider->scored }}/{{ $board['class']->laps * $board['class']->section_count }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $rider->cleans }}</td>
                                    {{-- The score sheet prints DNF where the total would be — no partial totals. --}}
                                    <td class="py-2 text-right pr-4">—</td>
                                </tr>
                            @endforeach

                            {{-- NC: riding but not competing --}}
                            @foreach ($board['nc'] as $rider)
                                <tr class="border-t border-zinc-800/60 text-zinc-500">
                                    <td class="px-4 py-2 text-xs uppercase tracking-wide">NC</td>
                                    <td class="py-2 tabular-nums">{{ $rider->rider_number }}</td>
                                    <td class="py-2">
                                        {{ $rider->name }}
                                        @if ($rider->note)
                                            <span class="block text-xs text-zinc-600">{{ $rider->note }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right tabular-nums">{{ $rider->scored ? $rider->scored.'/'.$board['class']->laps * $board['class']->section_count : '—' }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $rider->scored ? $rider->cleans : '—' }}</td>
                                    <td class="py-2 text-right pr-4 tabular-nums">{{ $rider->scored ? $rider->points : '—' }}</td>
                                </tr>
                            @endforeach

                            {{-- DNS: collapsed row, no scores --}}
                            @foreach ($board['dns'] as $rider)
                                <tr class="border-t border-zinc-800/60 text-zinc-600">
                                    <td class="px-4 py-2 text-xs uppercase tracking-wide">DNS</td>
                                    <td class="py-2 tabular-nums">{{ $rider->rider_number }}</td>
                                    <td class="py-2" colspan="4">
                                        {{ $rider->name }}
                                        @if ($rider->note)
                                            <span class="text-xs text-zinc-700">— {{ $rider->note }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            @if ($board['placed']->isEmpty() && $board['dnf']->isEmpty() && $board['nc']->isEmpty() && $board['dns']->isEmpty())
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
