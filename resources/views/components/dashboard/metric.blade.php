{{--
    Metric card KPI baris atas (Design.md §5.2): radius 8px, padding 20px,
    label 14px muted, nilai JetBrains Mono 24px tabular. Status ditandai
    garis kiri 3px (`accent`), BUKAN background berwarna. Footer = slot
    bebas berisi teks keterangan (warna status hanya di teks).
--}}
@props(['label', 'value', 'accent' => null, 'valueTone' => 'default'])

@php
    $accentClass = match ($accent) {
        'error' => 'border-l-[3px] border-l-rose-500',
        'warning' => 'border-l-[3px] border-l-amber-500',
        'success' => 'border-l-[3px] border-l-emerald-500',
        default => '',
    };
    $valueClass = match ($valueTone) {
        'danger' => 'text-rose-700 dark:text-rose-300',
        'good' => 'text-emerald-700 dark:text-emerald-300',
        default => 'text-text-main',
    };
@endphp

<div class="bg-surface border border-border rounded-md p-5 flex flex-col gap-2 min-w-0 {{ $accentClass }}">
    <span class="text-sm font-medium text-text-muted">{{ $label }}</span>
    <span class="font-mono text-2xl font-semibold tabular-nums leading-tight truncate {{ $valueClass }}">{{ $value }}</span>
    @isset($footer)
        <div class="text-xs text-text-muted flex items-center justify-between gap-2 flex-wrap">{{ $footer }}</div>
    @endisset
</div>
