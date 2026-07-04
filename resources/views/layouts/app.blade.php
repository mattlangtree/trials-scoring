<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Trials Scoring' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 antialiased">
    @php $event = request()->route('event'); @endphp
    <div class="flex min-h-screen">
        <aside class="w-56 shrink-0 border-r border-zinc-800 bg-zinc-900/40 flex flex-col">
            <div class="px-5 py-5 border-b border-zinc-800">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-zinc-950 font-black text-sm">TS</span>
                    <span class="font-semibold tracking-tight">Trials Scoring</span>
                </a>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
                <a href="{{ route('home') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 transition
                          {{ request()->routeIs('home') ? 'bg-zinc-800 text-white font-medium' : 'text-zinc-400 hover:text-white hover:bg-zinc-900' }}">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Events
                </a>

                @if ($event)
                    <div class="pt-3 pb-1 px-3 text-xs uppercase tracking-wide text-zinc-600 truncate">{{ $event->name }}</div>
                    @foreach ([
                        ['route' => 'event.overview', 'label' => 'Overview', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                        ['route' => 'event.standings', 'label' => 'Standings', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                        ['route' => 'event.sections', 'label' => 'Sections', 'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
                        ['route' => 'event.riders', 'label' => 'Riders', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z'],
                    ] as $item)
                        <a href="{{ route($item['route'], $event) }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2 transition
                                  {{ request()->routeIs($item['route']) ? 'bg-zinc-800 text-white font-medium' : 'text-zinc-400 hover:text-white hover:bg-zinc-900' }}">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                            </svg>
                            {{ $item['label'] }}
                            @if ($item['route'] === 'event.sections')
                                @php
                                    $claimedSections = $event->sections()->whereHas('claims')->count();
                                    $totalSections = $event->sections()->count();
                                @endphp
                                <span class="ml-auto rounded-full bg-zinc-800 px-2 py-0.5 text-xs tabular-nums text-zinc-400"
                                      title="{{ $claimedSections }} of {{ $totalSections }} sections claimed by observers">
                                    {{ $claimedSections }}/{{ $totalSections }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                @endif
            </nav>
            <div class="px-5 py-4 border-t border-zinc-800 text-xs text-zinc-500">
                @if ($event)
                    <div class="font-medium text-zinc-300 truncate">{{ $event->name }}</div>
                    <div>{{ $event->event_date->format('j M Y') }}</div>
                @else
                    Self-service trials scoring demo
                @endif
            </div>
        </aside>
        <main class="flex-1 min-w-0">
            {{ $slot }}
            @if ($event)
                <livewire:simulation-ticker :$event />
            @endif
        </main>
    </div>
</body>
</html>
