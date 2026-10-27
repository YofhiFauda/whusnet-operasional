<?php

namespace App\Services;

use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Pop;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Menentukan SN perangkat tambahan yang tersedia pada custody tim pemasangan.
 * Kecukupan barang diperiksa saat laporan pemasangan, setelah FOP bebas
 * meng-assign task dan Gudang dapat melakukan issue ke teknisi.
 */
class AdditionalDeviceCustodyService
{
    /**
     * SN perangkat tambahan yang boleh dilaporkan di langkah Perangkat
     * Tambahan Laporan Pemasangan (ADHOC-190 task 4) — custody tim task ini,
     * ISSUED, item installable, dan cabang asal sama dengan task.
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
}
