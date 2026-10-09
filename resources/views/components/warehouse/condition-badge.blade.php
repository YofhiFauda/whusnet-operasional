@props([
    'condition' => null, // App\Enums\ItemCondition|string|null
    'checked' => true,    // sudah dicek fisik? (condition_checked_at !== null)
])

{{--
    Badge kondisi fisik barang — SATU sumber untuk seluruh modul gudang
    (analisa-ui-ux-warehouse.md §U4/V6). Sebelumnya logika & warna di-hardcode
    terpisah di traceability, custody, retrievals dengan hasil tidak seragam
    (rose-300 vs rose-400, bg-950/40 vs 950/50). Warna sekarang dari design
    token .badge-* (app.css), dark-mode otomatis.

    "Belum dicek" bukan case enum (kombinasi used_good + belum dicek), jadi
    ditangani di sini, bukan di ItemCondition.
--}}
@php
    $value = $condition instanceof \App\Enums\ItemCondition ? $condition->value : $condition;

    [$variant, $label] = match (true) {
        $value === null => ['neutral', 'Tidak dilacak'],
        $value === \App\Enums\ItemCondition::USED_DAMAGED->value => ['error', 'Bekas — Rusak'],
        $value === \App\Enums\ItemCondition::NEW->value => ['success', 'Baru'],
        // used_good: pecah berdasar sudah/belum dicek fisik
        $checked => ['info', 'Bekas — Sudah Dicek'],
        default => ['warning', 'Bekas — Belum Dicek'],
    };
@endphp

<x-ui.badge :variant="$variant" {{ $attributes }}>{{ $label }}</x-ui.badge>
