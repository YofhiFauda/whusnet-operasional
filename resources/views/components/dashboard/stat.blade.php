{{--
    Satu kolom di <x-dashboard.stat-strip>: label 10px uppercase, nilai
    JetBrains Mono 18px, keterangan 11px (Design.md §1.6). `href` membuat
    seluruh kolom bisa diklik ke halaman modul — tanpa border/kotak tambahan.
--}}
@props(['label', 'value', 'sub' => null, 'tone' => 'default', 'href' => null])

@php
    $toneClass = match ($tone) {
        'danger' => 'text-rose-700 dark:text-rose-300',
        'warning' => 'text-amber-700 dark:text-amber-300',
        'good' => 'text-emerald-700 dark:text-emerald-300',
        'primary' => 'text-primary',
        'muted' => 'text-text-muted',
        default => 'text-text-main',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif class="bg-surface px-5 py-3 flex flex-col gap-1 min-w-0 {{ $href ? 'hover:bg-surface-muted transition-colors' : '' }}">
    <span class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted truncate">{{ $label }}</span>
    <span class="font-mono text-lg font-semibold tabular-nums leading-tight {{ $toneClass }}">{{ $value }}</span>
    @if($sub)
        <span class="text-[11px] text-text-muted">{{ $sub }}</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
