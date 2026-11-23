{{--
    Satu butir "Perlu Segera Ditindak". Baris flat dipisah divider di dalam
    panel — dulu tiap butir kotak ber-background merah/kuning, yang dilarang
    Design.md §1.3 no. 4 (warna status cuma di badge/teks) dan §1.5 (card
    dalam card). Tingkat urgensi dibawa badge + warna judul, bukan latar.
--}}
@props(['tone' => 'warning', 'title', 'href' => null, 'linkLabel' => 'Buka'])

@php
    [$badgeVariant, $badgeLabel, $titleClass] = match ($tone) {
        'error' => ['error', 'Mendesak', 'text-rose-700 dark:text-rose-300'],
        'warning' => ['warning', 'Perhatian', 'text-amber-700 dark:text-amber-300'],
        default => ['neutral', 'Antrean', 'text-text-main'],
    };
@endphp

<div class="py-3 first:pt-0 last:pb-0 flex items-start justify-between gap-3">
    <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <x-ui.badge :variant="$badgeVariant">{{ $badgeLabel }}</x-ui.badge>
            <span class="text-[13px] font-semibold {{ $titleClass }}">{{ $title }}</span>
        </div>
        @if(trim($slot) !== '')
            <p class="text-xs text-text-muted mt-1">{{ $slot }}</p>
        @endif
    </div>
    @if($href)
        <a href="{{ $href }}" class="shrink-0 text-[11px] font-semibold uppercase tracking-[0.04em] text-primary hover:text-primary-hover hover:underline">{{ $linkLabel }} &rarr;</a>
    @endif
</div>
