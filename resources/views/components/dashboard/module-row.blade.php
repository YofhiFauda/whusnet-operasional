{{--
    Satu baris angka di dalam <x-dashboard.module-card>. Nilai selalu
    JetBrains Mono + tabular-nums (Design.md §2.2). `tone` cuma 4 nilai —
    warna status hanya di teks nilai, bukan background baris.
    `href` opsional: label jadi link ke halaman modul (izin dicek pemanggil).
--}}
@props(['label', 'value', 'tone' => 'default', 'href' => null])

@php
    $toneClass = match ($tone) {
        'danger' => 'text-rose-700 dark:text-rose-300',
        'warning' => 'text-amber-700 dark:text-amber-300',
        'good' => 'text-emerald-700 dark:text-emerald-300',
        default => 'text-text-main',
    };
@endphp

<div class="flex items-center justify-between gap-3 py-2">
    <dt class="text-text-secondary min-w-0">
        @if($href)
            <a href="{{ $href }}" class="hover:text-primary hover:underline">{{ $label }}</a>
        @else
            {{ $label }}
        @endif
    </dt>
    <dd class="font-mono font-semibold tabular-nums shrink-0 {{ $toneClass }}">{{ $value }}</dd>
</div>
