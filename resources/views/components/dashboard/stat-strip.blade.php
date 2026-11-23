{{--
    Strip angka FLAT di dalam panel (Design.md §1.6 summary-strip) — pengganti
    deretan kotak kecil ber-border di dalam panel (pola "card dalam card" yang
    dilarang §1.5). Trik gap-px + bg-border: garis pembatas 1px tetap rapi di
    dua arah walau grid patah baris di layar sempit. Menempel ke tepi panel
    (-mx-5) supaya garisnya terbaca sebagai pembagi panel, bukan kotak baru.
--}}
@props(['cols' => 4])

@php
    $colClass = match ((int) $cols) {
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        default => 'grid-cols-2 sm:grid-cols-4',
    };
@endphp

<div {{ $attributes->merge(['class' => "-mx-5 grid {$colClass} gap-px bg-border border-y border-border"]) }}>
    {{ $slot }}
</div>
