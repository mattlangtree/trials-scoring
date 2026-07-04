<?php

use App\Models\Event;
use App\Models\Score;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Event $event;

    #[Computed]
    public function sections()
    {
        return $this->event->sections()
            ->with('claims')
            ->withCount(['scores as official_count' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)])
            ->withCount(['scores as self_count' => fn ($q) => $q->where('status', Score::STATUS_SELF)])
            ->withAvg(['scores as avg_points' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)], 'points')
            ->orderBy('number')
            ->get();
    }

    /**
     * Per-section activity over the last 20 minutes: one slot per minute
     * (oldest first), true when at least one score arrived that minute.
     */
    #[Computed]
    public function activity(): array
    {
        $now = now();

        $activity = [];
        Score::where('event_id', $this->event->id)
            ->where('created_at', '>=', $now->copy()->subMinutes(20))
            ->get(['section_id', 'created_at'])
            ->each(function (Score $score) use ($now, &$activity) {
                $minute = 19 - (int) floor($score->created_at->diffInSeconds($now) / 60);
                if ($minute >= 0 && $minute <= 19) {
                    $activity[$score->section_id][$minute] = true;
                }
            });

        return $activity;
    }
};
?>

<div class="p-6 space-y-6" wire:poll.30s>
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Sections</h1>
        <p class="text-sm text-zinc-400 mt-1">
            Organiser view — hand each observer the claim code for their section. They punch it into the app to become the official scorer.
        </p>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 border-b border-zinc-800">
                    <th class="px-4 py-3 font-normal">Section</th>
                    <th class="py-3 font-normal">Claim code</th>
                    <th class="py-3 font-normal">Observers</th>
                    <th class="py-3 font-normal">Activity <span class="normal-case">(last 20 min)</span></th>
                    <th class="py-3 font-normal text-right">Official scores</th>
                    <th class="py-3 font-normal text-right">Self scores</th>
                    <th class="py-3 font-normal text-right pr-4">Avg points</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800/60">
                @foreach ($this->sections as $section)
                    <tr>
                        <td class="px-4 py-3 font-medium">Section {{ $section->number }}</td>
                        <td class="py-3">
                            <code class="rounded-md bg-zinc-800 border border-zinc-700 px-2 py-1 font-mono text-amber-300 tracking-widest">{{ $section->claim_code }}</code>
                        </td>
                        <td class="py-3">
                            @if ($section->claims->isEmpty())
                                <span class="text-zinc-600">Unclaimed</span>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($section->claims as $claim)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/25 px-2.5 py-0.5 text-xs text-emerald-300"
                                              title="Claimed {{ $claim->claimed_at->diffForHumans() }} on device {{ $claim->device_id }}">
                                            {{ $claim->observer_name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="py-3">
                            <div class="flex items-end gap-0.5 h-6" title="One slot per minute, oldest on the left">
                                @foreach (range(0, 19) as $minute)
                                    @if ($this->activity[$section->id][$minute] ?? false)
                                        <span class="w-1.5 h-full rounded-sm bg-emerald-500"></span>
                                    @else
                                        <span class="w-1.5 h-1 rounded-sm bg-zinc-800"></span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="py-3 text-right tabular-nums">{{ $section->official_count }}</td>
                        <td class="py-3 text-right tabular-nums text-zinc-500">{{ $section->self_count }}</td>
                        <td class="py-3 text-right pr-4 tabular-nums">{{ $section->avg_points !== null ? number_format($section->avg_points, 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
