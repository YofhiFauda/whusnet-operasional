@props([
    'variant' => 'info', // success, warning, error, danger, info, neutral
    'title' => null,
    'icon' => true,
])

@php
    $variant = $variant === 'danger' ? 'error' : $variant;

    $styles = match($variant) {
        'success' => [
            'container' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'icon_color' => 'text-emerald-500',
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
        ],
        'warning' => [
            'container' => 'bg-amber-50 border-amber-200 text-amber-800',
            'icon_color' => 'text-amber-500',
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
        ],
        'error' => [
            'container' => 'bg-rose-50 border-rose-200 text-rose-800',
            'icon_color' => 'text-rose-500',
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />',
        ],
        'neutral' => [
            'container' => 'bg-slate-50 border-slate-200 text-slate-800',
            'icon_color' => 'text-slate-500',
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        ],
        default => [
            'container' => 'bg-sky-50 border-sky-200 text-sky-800',
            'icon_color' => 'text-sky-500',
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        ],
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border p-4 text-sm flex items-start gap-3 {$styles['container']}"]) }} role="alert">
    @if($icon)
        <svg class="w-5 h-5 shrink-0 mt-0.5 {{ $styles['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            {!! $styles['svg'] !!}
        </svg>
    @endif
    <div class="flex-1">
        @if($title)
            <div class="font-bold mb-1">{{ $title }}</div>
        @endif
        <div class="leading-relaxed">
            {{ $slot }}
        </div>
    </div>
</div>
