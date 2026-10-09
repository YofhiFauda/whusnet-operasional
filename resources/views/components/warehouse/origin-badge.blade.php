@props([
    'log' => null, // App\Models\DeviceRetrievalLog|null — biasanya $serial->latestRetrievalLog
])

{{--
    Badge "bekas apa" (ADHOC-108) — satu sumber label asal modem retur
    (Migrasi/Ganti Modem/Deaktivasi/Diantar Pelanggan), dipakai di semua
    halaman yang nampilin SN hasil retur: Lacak Barang, Custody, Terima
    Retur, Kirim ke Pusat, Terima di Pusat, Modem Rusak. Label-nya sendiri
    dihitung `DeviceRetrievalLog::originLabel()` — jangan duplikasi logic
    match di sini, komponen ini CUMA render.

    Null (SN gak punya log retur — barang baru dari RECEIVE biasa, bukan
    hasil retur) → tidak render apa-apa, bukan badge "Tidak dilacak".
--}}
@php
    $variant = match ($log?->source?->value) {
        'deac' => 'warning',
        'creq_swap' => 'info',
        'walk_in' => 'neutral',
        default => null,
    };
@endphp

@if($log && $variant)
<x-ui.badge :variant="$variant" {{ $attributes }}>{{ $log->originLabel() }}</x-ui.badge>
@endif
