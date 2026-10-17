<?php

namespace App\Services;

use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TaskType;
use App\Exceptions\InsufficientExtraDeviceCustodyException;
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
     *
     * Keterbatasan v1 (ADHOC-190 task 4): SN yang dilaporkan rusak/hilang di
     * langkah Perangkat Tambahan TETAP terhitung tersedia (masih ISSUED) di
     * sini maupun di `eligibleSerialsForTask()` sampai Gudang memprosesnya
     * manual lewat alur intake rusak/hilang yang sudah ada — keputusannya
     * cuma tercatat di `customer_installations.installation_note`, lihat
     * task-4-report.md.
     */
    public function countAvailable(Collection $technicianIds, ?int $cabangId): int
    {
        if ($technicianIds->isEmpty()) {
            return 0;
        }

        return InventorySerial::query()
            ->whereIn('current_technician_id', $technicianIds)
            ->where('status', SerialStatus::ISSUED->value)
            // Wajib sama dengan `eligibleSerialsForTask()`: SN item
            // company_asset gak akan pernah bisa di-install
            // (`InventoryService::installSerial()` menolaknya), jadi kalau
            // ikut dihitung di sini guard lolos padahal teknisi gak bisa
            // memasang apa-apa di lapangan.
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INSTALLABLE->value))
            ->whereHas('item.category', fn ($q) => $q->where('is_customer_extra_device', true))
            ->when($cabangId !== null, fn ($q) => $q->where(function ($q2) use ($cabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $cabangId);
            }))
            ->count();
    }

    /**
     * SN perangkat tambahan yang boleh dilaporkan di langkah Perangkat
     * Tambahan Laporan Pemasangan (ADHOC-190 task 4) — custody tim task ini,
     * ISSUED, item installable, cabang asal sama (filter cabang identik
     * `countAvailable()` & `CustomerInstallationController::eligibleSerialsForTeam()`).
     * Satu query untuk dropdown (report()) dan validasi `Rule::in` (store()).
     *
     * @return Collection<int, InventorySerial>
     */
    public function eligibleSerialsForTask(?Task $task): Collection
    {
        $teamTechnicianIds = $task?->teamMembers->pluck('user_id')->all() ?? [];

        if ($teamTechnicianIds === []) {
            return collect();
        }

        $targetCabangId = Pop::resolveCabangId($task?->pop_id);

        return InventorySerial::query()
            ->whereIn('current_technician_id', $teamTechnicianIds)
            ->where('status', SerialStatus::ISSUED->value)
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INSTALLABLE->value))
            ->whereHas('item.category', fn ($q) => $q->where('is_customer_extra_device', true))
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item.category')
            ->orderBy('serial_number')
            ->get();
    }

    /**
     * No-op kalau task bukan Pemasangan, atau paket pelanggan tidak butuh
     * perangkat tambahan (`max_additional_devices` 0/null) — guard ini
     * SENGAJA tidak menyentuh task_type lain (MTN, C-REQ, dst.) karena
     * perangkat tambahan cuma relevan saat instalasi baru.
     *
     * Kebutuhan dikurangi perangkat tambahan dari Gudang yang SUDAH
     * terpasang di pelanggan (`CustomerAdditionalDevice::installedFromWarehouse()`,
     * definisi sama dengan langkah Perangkat Tambahan Laporan Pemasangan).
     * Begitu AP di-install, SN-nya pindah ISSUED → INSTALLED dan keluar dari
     * hitungan custody — tanpa kredit ini reassign tim / jadwal ulang
     * revisi pemasangan setelahnya selalu ditolak "butuh 1, tersedia 0"
     * padahal pelanggan sudah punya perangkatnya.
     *
     * Lempar `InsufficientExtraDeviceCustodyException` (tidak ditangkap di
     * sini) — pemanggil (controller assign FOP / edit task) yang
     * menangkapnya dan redirect dengan pesan error. Sengaja kelas khusus,
     * bukan `\RuntimeException` polos, supaya catch di controller tidak ikut
     * menelan `ModelNotFoundException` & kawan-kawan.
     *
     * @param  array<int>  $technicianIds  Teknisi yang (akan) diassign ke task ini.
     *
     * @throws InsufficientExtraDeviceCustodyException
     */
    public function assertSufficientForTask(Task $task, array $technicianIds): void
    {
        if ($task->task_type !== TaskType::PEMASANGAN) {
            return;
        }

        $customer = $task->customer;
        $packageLimit = (int) ($customer?->customerService?->internetPackage?->max_additional_devices ?? 0);

        if ($packageLimit === 0) {
            return;
        }

        $alreadyInstalled = $customer->additionalDevices()->installedFromWarehouse()->count();
        $required = max(0, $packageLimit - $alreadyInstalled);

        if ($required === 0) {
            return;
        }

        $cabangId = Pop::resolveCabangId($task->pop_id);
        $available = $this->countAvailable(collect($technicianIds), $cabangId);

        if ($available < $required) {
            throw new InsufficientExtraDeviceCustodyException(
                "Barang perangkat tambahan belum di-issue cukup — teknisi butuh {$required}, tersedia {$available}. Issue dulu dari Gudang."
            );
        }
    }
}
