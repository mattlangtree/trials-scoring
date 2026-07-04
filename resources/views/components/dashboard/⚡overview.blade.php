<?php

use App\Models\Event;
use App\Models\Score;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Event $event;

    /** Id of the score that just arrived over the websocket, for the flash. */
    public ?int $flashId = null;

    /** Rider number from that score, to highlight their lap pill. */
    public ?int $flashRider = null;

    public bool $editingTimes = false;

    public string $ridingEndsAt = '';

    public string $cardsInAt = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->fillTimeInputs($event);
    }

    #[On('echo:event.{event.id},ScoreRecorded')]
    public function onScoreRecorded(array $payload): void
    {
        // Re-rendering recomputes every widget; remember what to highlight.
        $this->flashId = $payload['id'] ?? null;
        $this->flashRider = $payload['rider']['number'] ?? null;
    }

    public function saveTimes(): void
    {
        $this->validate([
            'ridingEndsAt' => ['nullable', 'date'],
            'cardsInAt' => ['nullable', 'date', 'after_or_equal:ridingEndsAt'],
        ]);

        $tz = config('app.display_timezone');

        $this->event->update([
            'riding_ends_at' => $this->ridingEndsAt ? Carbon::parse($this->ridingEndsAt, $tz)->utc() : null,
            'cards_in_at' => $this->cardsInAt ? Carbon::parse($this->cardsInAt, $tz)->utc() : null,
        ]);

        $this->editingTimes = false;
    }

    private function fillTimeInputs(Event $event): void
    {
        $tz = config('app.display_timezone');
        $this->ridingEndsAt = $event->riding_ends_at?->timezone($tz)->format('Y-m-d\TH:i') ?? '';
        $this->cardsInAt = $event->cards_in_at?->timezone($tz)->format('Y-m-d\TH:i') ?? '';
    }

    #[Computed]
    public function stats(): array
    {
        $scores = Score::where('event_id', $this->event->id);
        $official = (clone $scores)->where('status', Score::STATUS_OFFICIAL);

        $expected = $this->event->riderClasses()
            ->withCount('riders')
            ->get()
            ->sum(fn ($class) => $class->riders_count * $class->laps * $class->section_count);

        return [
            'riders' => $this->event->riders()->count(),
            'scoresTotal' => (clone $scores)->count(),
            'scoresToday' => (clone $scores)->whereDate('created_at', today())->count(),
            'completion' => $expected > 0 ? round((clone $official)->count() / $expected * 100) : 0,
        ];
    }

    #[Computed]
    public function recent()
    {
        return Score::where('event_id', $this->event->id)
            ->with(['rider.riderClass', 'section', 'sectionClaim'])
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /**
     * Which lap each rider is currently on, from their official score
     * count: laps fill in section order, so 15 scores into a 15-section
     * course puts you at the start of lap 2.
     */
    #[Computed]
    public function lapBoard(): array
    {
        $laps = [];
        $finished = [];
        $dns = [];
        $nc = [];
        $dnf = [];
        $maxLaps = (int) $this->event->riderClasses()->max('laps');

        // A DNF rider stays in the lap rows while their replay is still
        // delivering scores; once nothing is left to release they have
        // retired for good and move to the DNF box.
        $pendingByRider = \App\Models\StagedScore::where('event_id', $this->event->id)
            ->whereNull('released_at')
            ->selectRaw('rider_id, count(*) as pending')
            ->groupBy('rider_id')
            ->pluck('pending', 'rider_id');

        $riders = $this->event->riders()
            ->with('riderClass')
            ->withCount(['scores as scored' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)])
            ->orderBy('rider_number')
            ->get();

        foreach ($riders as $rider) {
            $sections = $rider->riderClass->section_count;
            $entry = [
                'number' => $rider->rider_number,
                'name' => $rider->name,
                'class' => $rider->riderClass->name,
                'status' => $rider->status,
                'done_in_lap' => $rider->scored % $sections,
                'sections' => $sections,
            ];

            if ($rider->status === \App\Models\Rider::STATUS_DNS) {
                $dns[] = $entry;
            } elseif ($rider->status === \App\Models\Rider::STATUS_NC) {
                $nc[] = $entry;
            } elseif ($rider->status === \App\Models\Rider::STATUS_DNF && ($pendingByRider[$rider->id] ?? 0) === 0) {
                $dnf[] = $entry;
            } elseif ($rider->scored >= $rider->riderClass->laps * $sections) {
                $finished[] = $entry;
            } else {
                $laps[intdiv($rider->scored, $sections) + 1][] = $entry;
            }
        }

        // Within a lap: least progressed first, rider number as the stable
        // tie-break so pills don't jump around between refreshes.
        foreach ($laps as &$riders) {
            usort($riders, fn ($a, $b) => [$a['done_in_lap'], $a['number']] <=> [$b['done_in_lap'], $b['number']]);
        }

        return [
            'laps' => $laps,
            'finished' => $finished,
            'dns' => $dns,
            'nc' => $nc,
            'dnf' => $dnf,
            'maxLaps' => $maxLaps,
            'maxSections' => (int) $this->event->riderClasses()->max('section_count'),
        ];
    }
};
?>

<div class="p-6 space-y-6" wire:poll.10s>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Overview</h1>
                <p class="text-sm text-zinc-400 mt-1">{{ $this->event->name }}</p>
            </div>

            <div class="flex items-center gap-4">
                {{-- Countdown to end of riding, then to cards-in deadline --}}
                <div wire:key="countdown-{{ $this->event->riding_ends_at?->timestamp }}-{{ $this->event->cards_in_at?->timestamp }}"
                     x-data="{
                        ends: @js($this->event->riding_ends_at?->getTimestampMs()),
                        cards: @js($this->event->cards_in_at?->getTimestampMs()),
                        now: Date.now(),
                        fmt(ms) {
                            const s = Math.max(0, Math.floor(ms / 1000));
                            const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
                            return (h ? h + ':' : '') + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
                        },
                     }"
                     x-init="setInterval(() => now = Date.now(), 1000)"
                     class="text-right">
                    <template x-if="ends && now < ends">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Riding ends in</div>
                            <div class="text-2xl font-semibold tabular-nums text-amber-400" x-text="fmt(ends - now)"></div>
                        </div>
                    </template>
                    <template x-if="ends && now >= ends && cards && now < cards">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Riding ended — cards in within</div>
                            <div class="text-2xl font-semibold tabular-nums text-red-400" x-text="fmt(cards - now)"></div>
                        </div>
                    </template>
                    <template x-if="ends && now >= ends && (! cards || now >= cards)">
                        <div>
                            <div class="text-2xl font-semibold text-zinc-400">Riding ended</div>
                        </div>
                    </template>
                    <template x-if="! ends">
                        <div class="text-sm text-zinc-500">No riding end time set</div>
                    </template>
                </div>

                <button wire:click="$toggle('editingTimes')"
                        class="rounded-lg border border-zinc-700 px-3 py-2 text-xs text-zinc-300 hover:bg-zinc-800 transition">
                    {{ $editingTimes ? 'Close' : 'Set times' }}
                </button>
            </div>
        </div>

        @if ($editingTimes)
            <form wire:submit="saveTimes"
                  class="flex flex-wrap items-end gap-4 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
                <label class="text-sm">
                    <span class="block text-xs text-zinc-500 mb-1">Riding ends ({{ config('app.display_timezone') }})</span>
                    <input type="datetime-local" wire:model="ridingEndsAt"
                           class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm [color-scheme:dark]">
                </label>
                <label class="text-sm">
                    <span class="block text-xs text-zinc-500 mb-1">Last cards in</span>
                    <input type="datetime-local" wire:model="cardsInAt"
                           class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm [color-scheme:dark]">
                </label>
                <button type="submit"
                        class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-amber-400 transition">
                    Save
                </button>
                @error('cardsInAt') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </form>
        @endif

        {{-- Stat widgets --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                ['label' => 'Riders', 'value' => $this->stats['riders'], 'sub' => 'entered'],
                ['label' => 'Scores today', 'value' => $this->stats['scoresToday'], 'sub' => $this->stats['scoresTotal'].' total'],
                ['label' => 'Event completion', 'value' => $this->stats['completion'].'%', 'sub' => 'of expected official scores', 'span' => 'col-span-2'],
            ] as $stat)
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 {{ $stat['span'] ?? '' }}">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">{{ $stat['label'] }}</div>
                    <div class="mt-1.5 text-2xl font-semibold tabular-nums">{{ $stat['value'] }}</div>
                    <div class="text-xs text-zinc-500 mt-0.5">{{ $stat['sub'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid md:grid-cols-3 gap-6 items-start">
            {{-- Lap progress — who is where on the course --}}
            <div class="md:col-span-2 rounded-xl border border-zinc-800 bg-zinc-900/50 overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800">
                    <h2 class="font-medium">Lap progress</h2>
                </div>
                <div class="divide-y divide-zinc-800/60">
                    @foreach (['dns' => 'DNS', 'nc' => 'NC', 'dnf' => 'DNF'] as $group => $label)
                        @if ($this->lapBoard[$group] !== [])
                            <div class="px-4 py-4 flex gap-4">
                                <span class="w-14 shrink-0 pt-0.5 text-xs uppercase tracking-wide text-zinc-600">{{ $label }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($this->lapBoard[$group] as $rider)
                                        <span wire:key="pill-{{ $rider['number'] }}"
                                              title="{{ $rider['class'] }} — {{ $label }}"
                                              class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 px-2.5 py-0.5 text-xs text-zinc-600">
                                            <span class="tabular-nums">{{ $rider['number'] }}</span>{{ $rider['name'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @foreach (range(1, max(1, $this->lapBoard['maxLaps'])) as $lap)
                        <div class="px-4 py-4 flex gap-4 {{ $lap === 1 && ($this->lapBoard['dns'] !== [] || $this->lapBoard['nc'] !== [] || $this->lapBoard['dnf'] !== []) ? 'border-t-2! border-t-zinc-700!' : '' }}">
                            <span class="w-14 shrink-0 pt-0.5 text-xs uppercase tracking-wide text-zinc-500">Lap {{ $lap }}</span>
                            @php $groups = collect($this->lapBoard['laps'][$lap] ?? [])->groupBy('done_in_lap'); @endphp
                            <div class="flex-1 min-w-0 space-y-1">
                                @foreach (range(0, max(1, $this->lapBoard['maxSections']) - 1) as $bucket)
                                    <div wire:key="bucket-{{ $lap }}-{{ $bucket }}" class="flex items-center gap-2">
                                        <span class="w-14 shrink-0 text-[10px] uppercase tracking-wide text-zinc-600 tabular-nums">{{ $bucket }} done</span>
                                        <div class="flex-1 min-w-0 overflow-x-auto">
                                            <div class="flex flex-nowrap items-center gap-1.5 py-0.5 min-h-6">
                                                @forelse ($groups[$bucket] ?? [] as $rider)
                                                    @php $pct = (int) round($rider['done_in_lap'] / $rider['sections'] * 100); @endphp
                                                    <span wire:key="pill-{{ $rider['number'] }}"
                                                          title="{{ $rider['class'] }} — {{ $rider['done_in_lap'] }}/{{ $rider['sections'] }} sections this lap"
                                                          style="background: linear-gradient(90deg, {{ $rider['number'] === $flashRider ? 'rgb(245 158 11 / 0.25)' : 'rgb(82 82 91 / 0.45)' }} {{ $pct }}%, transparent {{ $pct }}%)"
                                                          class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs whitespace-nowrap transition
                                                                 {{ $rider['number'] === $flashRider ? 'border-amber-400 text-amber-300' : 'border-zinc-700 text-zinc-300' }}">
                                                        <span class="text-zinc-500 tabular-nums">{{ $rider['number'] }}</span>{{ $rider['name'] }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-zinc-800">—</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <div class="px-4 py-4 flex gap-4 bg-emerald-500/5">
                        <span class="w-14 shrink-0 pt-0.5 text-xs uppercase tracking-wide text-emerald-500">Finished</span>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($this->lapBoard['finished'] as $rider)
                                <span wire:key="pill-{{ $rider['number'] }}"
                                      title="{{ $rider['class'] }} — finished"
                                      class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2.5 py-0.5 text-xs text-emerald-300">
                                    <span class="text-emerald-500/70 tabular-nums">{{ $rider['number'] }}</span>{{ $rider['name'] }}
                                </span>
                            @empty
                                <span class="text-xs text-zinc-600 pt-0.5">—</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent scores — compact side column, pushed over websockets --}}
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800 flex items-center justify-between">
                    <h2 class="font-medium">Recent scores</h2>
                    <div x-data="{ state: 'connecting…' }"
                         x-init="
                            const conn = window.Echo.connector.pusher.connection;
                            state = conn.state;
                            conn.bind('state_change', (states) => state = states.current);
                         "
                         class="flex items-center gap-1.5 text-xs">
                        <span class="h-2 w-2 rounded-full"
                              :class="state === 'connected' ? 'bg-emerald-500' : 'bg-red-500 animate-pulse'"></span>
                        <span class="text-zinc-500" x-text="state === 'connected' ? 'live' : state"></span>
                    </div>
                </div>
                <div class="divide-y divide-zinc-800/60">
                    @forelse ($this->recent as $score)
                        <div wire:key="recent-{{ $score->id }}"
                             class="px-4 py-2 flex items-center gap-3 text-sm {{ $score->id === $flashId ? 'score-flash' : '' }}">
                            <x-points-badge :points="$score->points"/>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">
                                    {{ $score->rider->name }}
                                    @if ($score->status === App\Models\Score::STATUS_SELF)
                                        <span class="text-xs text-purple-300">(self)</span>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500 truncate">
                                    S{{ $score->section->number }} · lap {{ $score->lap }} · {{ $score->created_at->timezone(config('app.display_timezone'))->format('H:i:s') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center text-zinc-500 text-sm">Waiting for the first score…</div>
                    @endforelse
                </div>
            </div>
        </div>
</div>
