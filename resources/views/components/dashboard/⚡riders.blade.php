<?php

use App\Models\Event;
use App\Models\Rider;
use App\Models\Score;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $rider = null;

    public Event $event;

    #[Computed]
    public function riders()
    {
        return $this->event->riders()
            ->with('riderClass')
            ->withSum(['scores as official_points' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)], 'points')
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('rider_number', (int) $this->search ?: -1)
            ))
            ->orderBy('rider_number')
            ->get();
    }

    #[Computed]
    public function selected(): ?Rider
    {
        if (! $this->rider) {
            return null;
        }

        return $this->event->riders()
            ->with(['riderClass', 'scores.section'])
            ->where('rider_number', $this->rider)
            ->first();
    }

    /** lap => [section number => Score] grid of official scores. */
    #[Computed]
    public function grid(): array
    {
        $rider = $this->selected;
        $grid = [];

        foreach (range(1, $rider->riderClass->laps) as $lap) {
            foreach (range(1, $rider->riderClass->section_count) as $number) {
                $grid[$lap][$number] = $rider->scores
                    ->first(fn (Score $s) => $s->isOfficial() && $s->lap === $lap && $s->section->number === $number);
            }
        }

        return $grid;
    }

    public function select(int $riderNumber): void
    {
        $this->rider = $riderNumber;
    }
};
?>

<div class="p-6 space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Riders</h1>
        <p class="text-sm text-zinc-400 mt-1">Search by name or number, click a rider for their scorecard. Only official scores count.</p>
    </div>

    <div class="grid md:grid-cols-5 gap-6">
        <div class="md:col-span-2 space-y-4">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search riders…"
                   class="w-full rounded-lg border border-zinc-800 bg-zinc-900/60 px-3.5 py-2.5 text-sm placeholder-zinc-500 focus:border-amber-500 focus:outline-none">

            <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800/60 overflow-hidden">
                @forelse ($this->riders as $r)
                    <button wire:click="select({{ $r->rider_number }})"
                            class="w-full px-4 py-2.5 flex items-center gap-3 text-left text-sm transition
                                   {{ $this->rider === $r->rider_number ? 'bg-zinc-800/80' : 'hover:bg-zinc-900' }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-800 text-zinc-300 font-semibold tabular-nums text-xs">{{ $r->rider_number }}</span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-medium truncate">{{ $r->name }}</span>
                            <span class="block text-xs text-zinc-500">{{ $r->riderClass->name }}</span>
                        </span>
                        <span class="tabular-nums font-medium">{{ $r->official_points ?? 0 }} pts</span>
                    </button>
                @empty
                    <div class="px-4 py-8 text-center text-zinc-500 text-sm">No riders match.</div>
                @endforelse
            </div>
        </div>

        <div class="md:col-span-3">
            @if ($this->selected)
                @php $rider = $this->selected; @endphp
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50">
                    <div class="px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">#{{ $rider->rider_number }} {{ $rider->name }}</h2>
                            <p class="text-sm text-zinc-400">{{ $rider->riderClass->name }} — {{ $rider->riderClass->laps }} laps × {{ $rider->riderClass->section_count }} sections</p>
                        </div>
                        @php $official = $rider->scores->filter->isOfficial(); @endphp
                        <div class="text-right">
                            <div class="text-2xl font-semibold tabular-nums">{{ $official->sum('points') }} pts</div>
                            <div class="text-xs text-zinc-500">{{ $official->where('points', 0)->count() }} cleans</div>
                        </div>
                    </div>
                    <div class="p-5 overflow-x-auto">
                        <table class="text-sm">
                            <thead>
                                <tr>
                                    <th class="pr-3 py-1.5 text-left text-xs text-zinc-500 font-normal">Lap</th>
                                    @foreach (range(1, $rider->riderClass->section_count) as $number)
                                        <th class="px-1 py-1.5 text-center text-xs text-zinc-500 font-normal w-9">S{{ $number }}</th>
                                    @endforeach
                                    <th class="pl-3 py-1.5 text-right text-xs text-zinc-500 font-normal">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->grid as $lap => $row)
                                    <tr>
                                        <td class="pr-3 py-1 text-zinc-400">{{ $lap }}</td>
                                        @foreach ($row as $score)
                                            <td class="px-1 py-1 text-center">
                                                @if ($score)
                                                    <x-points-badge :points="$score->points"/>
                                                @else
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-zinc-800 text-zinc-700">·</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="pl-3 py-1 text-right font-medium tabular-nums">{{ collect($row)->filter()->sum('points') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @php $selfCount = $rider->scores->where('status', Score::STATUS_SELF)->count(); @endphp
                        @if ($selfCount)
                            <p class="mt-4 text-xs text-zinc-500">{{ $selfCount }} self-recorded score(s) not shown — they do not contribute to results.</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-zinc-800 p-10 text-center text-zinc-500">
                    Select a rider to see their scorecard.
                </div>
            @endif
        </div>
    </div>
</div>
