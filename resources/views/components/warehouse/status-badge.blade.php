@props([
    'status' => null, // App\Enums\SerialStatus|App\Enums\RollStatus|string|null
])

{{--
    Badge status SN/roll — satu sumber warna+label untuk modul gudang
    (analisa-ui-ux §U4/V6). Warna dari ->badgeVariant() di enum, label dari
    ->label(). String mentah (mis. data lama) jatuh ke neutral apa adanya.
--}}
@php
    if ($status instanceof \App\Enums\SerialStatus || $status instanceof \App\Enums\RollStatus) {
        $variant = $status->badgeVariant();
        $label = $status->label();
    } else {
        $variant = 'neutral';
        $label = $status === null ? '—' : (string) $status;
    }
@endphp

<x-ui.badge :variant="$variant" {{ $attributes }}>{{ $label }}</x-ui.badge>
