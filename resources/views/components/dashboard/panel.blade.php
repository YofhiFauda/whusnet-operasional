{{--
    Panel dashboard (Design.md §1.3 Type C — satu card per grafik/daftar).
    Satu-satunya elemen di dashboard yang boleh punya bg + border + radius
    sekaligus; isinya WAJIB flat (divider 1px), bukan card lagi (§1.5 "double card").

    - Judul = section title 10px uppercase muted (§6.9), bukan heading tebal.
    - `accent` = garis kiri 3px warna status (§5.2 metric-card.status-*). Warna
      status cuma boleh di garis/teks/badge — tidak pernah background panel.
    - `href` null = user gak punya izin buka halaman tujuan → link gak dirender.
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'href' => null,
    'linkLabel' => 'Detail',
    'accent' => null, // error | warning | success | null
])

@php
    $accentClass = match ($accent) {
        'error' => 'border-l-[3px] border-l-rose-500',
        'warning' => 'border-l-[3px] border-l-amber-500',
        'success' => 'border-l-[3px] border-l-emerald-500',
        default => '',
    };
@endphp

<section {{ $attributes->merge(['class' => "bg-surface border border-border rounded-md p-5 {$accentClass}"]) }}>
    @if($title || isset($actions))
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="min-w-0">
                @if($title)
                    <h3 class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-[13px] text-text-secondary mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0 flex items-center gap-2">{{ $actions }}</div>
            @elseif($href)
                <a href="{{ $href }}" class="shrink-0 text-[11px] font-semibold uppercase tracking-[0.04em] text-primary hover:text-primary-hover hover:underline">{{ $linkLabel }} &rarr;</a>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
