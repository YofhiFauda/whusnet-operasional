<?php

namespace App\Services;

use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Menentukan SN perangkat tambahan yang tersedia pada custody tim
 * (Pemasangan & Maintenance/C-REQ).
 * Kecukupan barang diperiksa saat laporan pemasangan, setelah FOP bebas
 * meng-assign task dan Gudang dapat melakukan issue ke teknisi.
 */
class AdditionalDeviceCustodyService
{
    /**
     * SN yang boleh dipilih sebagai perangkat tambahan — langkah Perangkat
     * Tambahan Laporan PEMASANGAN dan "Ganti Perangkat Tambahan" Laporan
     * MAINTENANCE/C-REQ. SATU query untuk dropdown & validasi `Rule::in` di
     * kedua laporan supaya tidak menyimpang lagi.
     *
     * SEMUA perangkat ber-SN installable di custody tim, apa pun kategorinya
     * (keputusan user 2026-11-14) — dulu cuma kategori
     * `is_customer_extra_device` (AP/STB), padahal perangkat tambahan
     * pelanggan tidak selalu AP/STB.
     *
     * `$mainDeviceSerialId`: SN yang sudah dipilih jadi perangkat UTAMA di
     * Step 5 Pemasangan (`installations.selected_inventory_serial_id`) —
     * dikeluarkan karena baru di-install di storeSpeedtest(); kalau ikut
     * terpasang di sini duluan, Laporan Speedtest gagal (SN sudah INSTALLED).
     * Maintenance memilih modem utama di form yang sama, jadi bentrokannya
     * dijaga lewat validasi di TaskMaintenanceController, bukan di sini.
     *
     * @return Collection<int, InventorySerial>
     */
    public function eligibleSerialsForExtraDevice(?Task $task, ?int $mainDeviceSerialId = null): Collection
    {
        $teamTechnicianIds = $task?->teamMembers->pluck('user_id')->all() ?? [];

        if ($teamTechnicianIds === []) {
            return collect();
        }

        return InventorySerial::query()
            ->whereIn('current_technician_id', $teamTechnicianIds)
            ->where('status', SerialStatus::ISSUED->value)
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INSTALLABLE->value))
            ->when($mainDeviceSerialId, fn ($q) => $q->whereKeyNot($mainDeviceSerialId))
            ->with('item.category')
            ->orderBy('serial_number')
            ->get();
    }
}
