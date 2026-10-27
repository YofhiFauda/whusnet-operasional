@props([
    'device' => null, // App\Models\CustomerDevice|null — biasanya $customer->customerDevice
])

{{--
    Badge asal modem pelanggan (Sewa/Beli Sendiri/Beli dari Gudang) — satu
    sumber kebenaran `CustomerDevice::$acquisition_source`
    (App\Enums\DeviceAcquisitionSource). Label & warna dihitung di enum
    (`label()`/`badgeVariant()`), komponen ini CUMA render, pola identik
    `origin-badge.blade.php`.

    Null (device belum ada / acquisition_source belum pernah diisi — data
    legacy sebelum kolom ini ada tetap dapat default `sewa` di DB, jadi null
    cuma kejadian kalau device-nya sendiri null) → tidak render apa-apa,
    bukan badge kosong.
--}}
@if($device?->acquisition_source)
<x-ui.badge :variant="$device->acquisition_source->badgeVariant()" {{ $attributes }}>{{ $device->acquisition_source->label() }}</x-ui.badge>
@endif
