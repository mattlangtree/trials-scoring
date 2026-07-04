@props(['points'])

@php
    $classes = match ((int) $points) {
        0 => 'bg-zinc-800/60 text-zinc-400 border-zinc-700',
        1, 2 => 'bg-sky-500/15 text-sky-400 border-sky-500/30',
        3 => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
        default => 'bg-red-500/15 text-red-400 border-red-500/30',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold tabular-nums $classes"]) }}>{{ $points }}</span>
