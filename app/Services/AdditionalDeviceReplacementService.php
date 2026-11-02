<?php

namespace App\Services;

use App\Enums\DeviceOwnership;
use App\Enums\DeviceRetrievalSource;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\DeviceRetrievalLog;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ganti perangkat tambahan (AP/STB) pelanggan yang SUDAH terpasang — dipakai
 * laporan Maintenance & C-REQ untuk AP rusak/bermasalah pasca-pemasangan.
 *
 * Satu kejadian fisik, tiga efek yang harus jalan bareng atau tidak sama
 * sekali (satu transaksi):
 *   1. SN pengganti dari custody tim dipasang (`InventoryService::installSerial`
 *      dengan `returnExistingSerial: false` — true akan meretur ONT utama).
 *   2. SN lama dicabut jadi RETURNED di tangan teknisi (menunggu Terima Retur
 *      Gudang, kondisi belum diperiksa) + jejak `DeviceRetrievalLog`.
 *   3. Baris `customer_additional_devices` lama ditandai 'ditarik', baris baru
 *      'terpasang' — Detail Pelanggan langsung akurat, jatah paket tetap
 *      (hitungan jatah memakai scope `active()`).
 *
 * Sengaja tidak menyentuh `customer_devices`/`ont_sn`: AP bukan perangkat utama.
 */
class AdditionalDeviceReplacementService
{
    public const CONDITION_BAIK = 'baik';

    public const CONDITION_RUSAK = 'rusak';

    /**
     * @param  iterable<User>  $technicians  tim task — SN pengganti wajib custody salah satunya
     */
    public function replace(
        Customer $customer,
        CustomerAdditionalDevice $oldDevice,
        InventorySerial $newSerial,
        FopTask $fopTask,
        iterable $technicians,
        User $actor,
        string $oldCondition,
        ?string $note = null,
    ): CustomerAdditionalDevice {
        return DB::transaction(function () use ($customer, $oldDevice, $newSerial, $fopTask, $technicians, $actor, $oldCondition, $note) {
            // Lock baris lama: dua laporan bersamaan yang menunjuk AP yang sama
            // tidak boleh sama-sama lolos cek 'terpasang' di bawah.
            $oldDevice = CustomerAdditionalDevice::query()->lockForUpdate()->findOrFail($oldDevice->id);

            if ($oldDevice->customer_id !== $customer->id) {
                throw new InvalidArgumentException('Perangkat tambahan yang diganti bukan milik pelanggan task ini.');
            }

            if ($oldDevice->status !== 'terpasang') {
                throw new InvalidArgumentException('Hanya perangkat tambahan yang masih terpasang yang bisa diganti.');
            }

            // Baris teks bebas (legacy/`Tanpa Stok`, tanpa `inventory_serial_id`)
            // juga boleh diganti: data legacy sering tidak cocok dengan
            // perangkat nyata di lapangan, dan teknisi harus bisa
            // menyesuaikannya. Bedanya, tidak ada stok yang dicabut — unit lama
            // memang tidak pernah tercatat di Gudang, jadi tidak ada RETURN dan
            // tidak ada DeviceRetrievalLog; barisnya cukup ditandai 'ditarik'.
            $oldSerial = null;
            if ($oldDevice->inventory_serial_id !== null) {
                $oldSerial = InventorySerial::query()->with('item.category')->lockForUpdate()->findOrFail($oldDevice->inventory_serial_id);

                if ($oldSerial->status !== SerialStatus::INSTALLED || $oldSerial->customer_id !== $customer->id) {
                    throw new InvalidArgumentException("SN {$oldSerial->serial_number} tidak tercatat terpasang di pelanggan ini — cek dengan admin Gudang.");
                }
            }

            $newSerial = InventorySerial::query()->with('item.category')->lockForUpdate()->findOrFail($newSerial->id);

            // AP diganti AP, STB diganti STB. Beda kategori berarti kebutuhan
            // paket (mis. "1 AP") bergeser diam-diam. Hanya bisa dicek untuk
            // perangkat Gudang; baris teks bebas tidak punya kategori.
            if ($oldSerial && $oldSerial->item?->item_category_id !== $newSerial->item?->item_category_id) {
                throw new InvalidArgumentException("Pengganti harus sejenis: SN {$oldSerial->serial_number} ({$oldSerial->item?->category?->name}) tidak bisa diganti SN {$newSerial->serial_number} ({$newSerial->item?->category?->name}).");
            }

            $fromTechnicianId = $newSerial->current_technician_id;

            // Pasang pengganti DULU: guard custody/cabang/status ada di sini dan
            // melempar sebelum SN lama sempat dicabut.
            app(InventoryService::class)->installSerial(
                $newSerial,
                $customer,
                $fopTask,
                $technicians,
                $actor,
                returnExistingSerial: false,
                ownership: DeviceOwnership::COMPANY,
            );

            $isDamaged = $oldCondition === self::CONDITION_RUSAK;
            $condition = $isDamaged ? ItemCondition::USED_DAMAGED : ItemCondition::USED_GOOD;
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);

            if ($oldSerial) {
                // Pola sama dengan retur modem lama di installSerial(): transit ke
                // teknisi dulu (BUKAN langsung stok gudang) — kondisi baru dicek
                // Gudang saat Terima Retur.
                $oldSerial->update([
                    'status' => SerialStatus::RETURNED->value,
                    'current_technician_id' => $fromTechnicianId,
                    'current_pop_id' => null,
                    'customer_id' => null,
                    'condition' => $condition,
                    'condition_checked_at' => null,
                    'condition_checked_by' => null,
                ]);

                $reason = "Perangkat tambahan diganti saat pekerjaan {$fopTask->task_number} (digantikan oleh SN {$newSerial->serial_number}), kondisi: ".($isDamaged ? 'rusak' : 'baik').'.';

                InventoryTransaction::create([
                    'type' => InventoryTransactionType::RETURN,
                    'reference_number' => app(NumberSequenceService::class)->ledgerNumber('RET'),
                    'item_id' => $oldSerial->item_id,
                    'serial_id' => $oldSerial->id,
                    'qty' => 1,
                    'from_technician_id' => $fromTechnicianId,
                    'to_technician_id' => $fromTechnicianId,
                    'fop_task_id' => $fopTask->id,
                    'reason' => $reason,
                    'notes' => "Pelanggan: {$customer->display_id} - {$customer->full_name}",
                    'created_by' => $actor->id,
                ]);

                DeviceRetrievalLog::create([
                    'customer_id' => $customer->id,
                    'serial_id' => $oldSerial->id,
                    'serial_number' => $oldSerial->serial_number,
                    'item_id' => $oldSerial->item_id,
                    'source' => DeviceRetrievalSource::CREQ_SWAP,
                    'task_id' => $fopTask->task_id,
                    'retrieved_by' => $actor->id,
                    'warehouse_pop_id' => $oldSerial->issued_from_pop_id ?? $targetCabangId,
                    'condition' => $condition,
                    'retrieved_at' => now(),
                ]);
            }

            $replacedNote = trim("Diganti pada {$fopTask->task_number} dengan SN {$newSerial->serial_number} (".($isDamaged ? 'rusak' : 'baik').').'
                .($oldSerial ? '' : ' Unit lama tidak tercatat di Gudang (data legacy), tidak masuk alur Terima Retur.')
                .($note ? " Catatan: {$note}" : ''));
            $oldDevice->update([
                'status' => 'ditarik',
                'retrieved_at' => now(),
                'technical_note' => trim(($oldDevice->technical_note ? $oldDevice->technical_note."\n" : '').$replacedNote),
            ]);

            return CustomerAdditionalDevice::create([
                'customer_id' => $customer->id,
                'inventory_serial_id' => $newSerial->id,
                'device_type' => $newSerial->item?->category?->name ?? $oldDevice->device_type,
                'brand' => $newSerial->item?->brand,
                'model' => $newSerial->item?->model,
                'serial_number' => $newSerial->serial_number,
                'status' => 'terpasang',
            ]);
        });
    }
}
