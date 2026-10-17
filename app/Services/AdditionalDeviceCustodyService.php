<?php

namespace App\Services;

use App\Enums\SerialStatus;
use App\Enums\TaskType;
use App\Models\InventorySerial;
use App\Models\Pop;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Guard preventif ADHOC-190 (task 3): sebelum FOP assign tim + jadwal ke Task
 * Pemasangan yang paketnya butuh perangkat tambahan (AP/STB, dll. — lihat
 * `InternetPackage.max_additional_devices`), dicek dulu apakah tim yang mau
 * diassign BENAR-BENAR sedang pegang custody barang kategori
 * `is_customer_extra_device` yang cukup. Tanpa guard ini, Task bisa jadi
 * TERJADWAL lalu teknisi berangkat tanpa alat — ketahuannya baru pas laporan
 * instalasi, telat & bikin PP/jadwal ulang. Dipakai lagi di Task 4 (laporan
 * instalasi) buat penegakan SEBENARNYA saat SN benar-benar dikonsumsi.
 */
class AdditionalDeviceCustodyService
{
    /**
     * Jumlah `InventorySerial` kategori perangkat tambahan yang SEDANG
     * dipegang (status `ISSUED`) salah satu teknisi di `$technicianIds`,
     * dibatasi ke cabang `$cabangId` (null = gak dibatasi — dipakai pemanggil
     * yang gak punya POP/cabang buat dibandingkan).
     *
     * Pola filter cabang SAMA dengan `CustomerInstallationController::
     * eligibleSerialsForTeam()` — SN yang `issued_from_pop_id`-nya null
     * (data legacy / belum pernah tercatat asalnya) TETAP dihitung tersedia,
     * cuma SN yang asal cabangnya BEDA dari task ini yang dikecualikan.
     */
    public function countAvailable(Collection $technicianIds, ?int $cabangId): int
    {
        if ($technicianIds->isEmpty()) {
            return 0;
        }

        return InventorySerial::query()
            ->whereIn('current_technician_id', $technicianIds)
            ->where('status', SerialStatus::ISSUED->value)
            ->whereHas('item.category', fn ($q) => $q->where('is_customer_extra_device', true))
            ->when($cabangId !== null, fn ($q) => $q->where(function ($q2) use ($cabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $cabangId);
            }))
            ->count();
    }

    /**
     * No-op kalau task bukan Pemasangan, atau paket pelanggan tidak butuh
     * perangkat tambahan (`max_additional_devices` 0/null) — guard ini
     * SENGAJA tidak menyentuh task_type lain (MTN, C-REQ, dst.) karena
     * perangkat tambahan cuma relevan saat instalasi baru.
     *
     * Lempar langsung `\RuntimeException` (tidak ditangkap di sini) —
     * pemanggil (controller assign FOP) yang menangkapnya dan redirect
     * dengan pesan error, pola yang sama dipakai
     * `CustomerPackageService::assertAdditionalDevicesWithinLimit()`.
     *
     * @param  array<int>  $technicianIds  Teknisi yang (akan) diassign ke task ini.
     *
     * @throws \RuntimeException
     */
    public function assertSufficientForTask(Task $task, array $technicianIds): void
    {
        if ($task->task_type !== TaskType::PEMASANGAN) {
            return;
        }

        $required = (int) ($task->customer?->customerService?->internetPackage?->max_additional_devices ?? 0);

        if ($required === 0) {
            return;
        }

        $cabangId = Pop::resolveCabangId($task->pop_id);
        $available = $this->countAvailable(collect($technicianIds), $cabangId);

        if ($available < $required) {
            throw new \RuntimeException(
                "Barang perangkat tambahan belum di-issue cukup — teknisi butuh {$required}, tersedia {$available}. Issue dulu dari Gudang."
            );
        }
    }
}
