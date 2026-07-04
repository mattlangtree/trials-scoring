<?php

use App\Models\Event;
use App\Services\DemoEvent;
use App\Services\EventTemplates;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('nullable|string|max:60')]
    public string $name = '';

    #[Validate('required|integer|in:5,10,15,20,30,60')]
    public int $minutes = 10;

    public string $template = 'random';

    public function startEvent(DemoEvent $demo)
    {
        $this->validate();

        if ($this->template !== 'random' && ! EventTemplates::exists($this->template)) {
            $this->addError('template', 'Unknown template.');

            return null;
        }

        $event = $demo->create(
            $this->name,
            $this->minutes,
            template: $this->template === 'random' ? null : $this->template,
        );

        return $this->redirect(route('event.overview', $event), navigate: false);
    }

    #[Computed]
    public function events()
    {
        return Event::withCount(['riders', 'scores'])
            ->withCount(['stagedScores as pending_count' => fn ($q) => $q->whereNull('released_at')])
            ->latest('created_at')
            ->limit(30)
            ->get();
    }
};
?>

<div class="p-6 space-y-6 max-w-4xl">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Events</h1>
        <p class="text-sm text-zinc-400 mt-1">Start your own simulated trial and watch it run live, or drop in on one that's already going.</p>
    </div>

    {{-- Start a new event --}}
    <form wire:submit="startEvent" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 flex flex-wrap items-end gap-4">
        <label class="text-sm grow max-w-xs">
            <span class="block text-xs text-zinc-500 mb-1">Event name (optional)</span>
            <input type="text" wire:model="name" placeholder="{{ \App\Services\DemoEvent::suggestName() }}"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm placeholder-zinc-600 focus:border-amber-500 focus:outline-none">
        </label>
        <label class="text-sm">
            <span class="block text-xs text-zinc-500 mb-1">Field</span>
            <select wire:model="template"
                    class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none">
                <option value="random">Random field — 60 riders</option>
                @foreach (\App\Services\EventTemplates::options() as $key => $option)
                    <option value="{{ $key }}" title="{{ $option['detail'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="block text-xs text-zinc-500 mb-1">Event runs over</span>
            <select wire:model="minutes"
                    class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none">
                @foreach ([5, 10, 15, 20, 30, 60] as $option)
                    <option value="{{ $option }}">{{ $option }} minutes</option>
                @endforeach
            </select>
        </label>
        <button type="submit" wire:loading.attr="disabled"
                class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-amber-400 transition disabled:opacity-50">
            <span wire:loading.remove wire:target="startEvent">Start event</span>
            <span wire:loading wire:target="startEvent">Setting up…</span>
        </button>
        <p class="w-full text-xs text-zinc-500 -mt-1">
            60 riders across five classes ride every section live over your chosen timespan. Demo events clean themselves up a day after finishing.
        </p>
    </form>

    {{-- Current and recent events --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800/60 overflow-hidden" wire:poll.10s>
        @forelse ($this->events as $event)
            <a href="{{ route('event.overview', $event) }}" class="flex items-center gap-4 px-4 py-3 hover:bg-zinc-900 transition">
                <div class="min-w-0 flex-1">
                    <div class="font-medium truncate">{{ $event->name }}</div>
                    <div class="text-xs text-zinc-500">
                        {{ $event->riders_count }} riders · {{ $event->scores_count }} scores · started {{ $event->created_at->diffForHumans() }}
                    </div>
                </div>
                @if ($event->isRunning())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-0.5 text-xs text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Running — ends {{ $event->riding_ends_at->timezone(config('app.display_timezone'))->format('H:i') }}
                    </span>
                @elseif ($event->pending_count > 0)
                    <span class="rounded-full bg-amber-500/10 border border-amber-500/30 px-2.5 py-0.5 text-xs text-amber-300">Catching up</span>
                @else
                    <span class="rounded-full bg-zinc-800 px-2.5 py-0.5 text-xs text-zinc-400">Finished</span>
                @endif
            </a>
        @empty
            <div class="px-4 py-10 text-center text-zinc-500">No events yet — start the first one above.</div>
        @endforelse
    </div>
</div>
