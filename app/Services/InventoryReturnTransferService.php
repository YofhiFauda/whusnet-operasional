<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TAHAP 2 & 3 dari alur retur modem (ADHOC-108) — menyusul TAHAP 1
 * (`InventoryReassignService::confirmReturnedSerial()` /
 * `receiveSerialFromCustomerAtWarehouse()`). SEMUA retur (DEAC/Ganti Modem/
 * Migrasi/walk-in) wajib dikirim Cabang → Pusat dan dikonfirmasi Pusat
 * sebelum jadi stok — keputusan user 2026-10-08. Rancangan:
 * docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md.
 *
 * Header `InventoryTransfer` DIPAKAI ULANG (bukan tabel baru) — arahnya
 * BUKAN lewat kolom, cukup karena Cabang CUMA PERNAH kirim transfer ke Pusat
 * lewat jalur ini (stok Pusat→Cabang biasa SELALU dari `InventoryTransferService`,
 * searah, gak pernah dibalik) — tidak ambigu membedakan dua jenis transfer
 * di tabel yang sama.
 */
class InventoryReturnTransferService
{
    /**
     * Cabang kirim SN hasil retur (sudah lewat Tahap 1, status `RETURNED`
     * + `current_pop_id` = Cabang ini) ke Pusat. Satu header `InventoryTransfer`
     * per pengiriman, bisa banyak SN.
     *
     * @param  list<int>  $serialIds
     */
    public function dispatchToPusat(Pop $cabang, Pop $pusat, array $serialIds, User $actor): InventoryTransfer
    {
        if (! $cabang->isCabang()) {
            throw new InvalidArgumentException("Kirim retur cuma boleh DARI Gudang Cabang — {$cabang->name} bertipe '{$cabang->type}'.");
        }

        if (! $pusat->isPusat()) {
            throw new InvalidArgumentException("Kirim retur cuma boleh KE Gudang Pusat — {$pusat->name} bertipe '{$pusat->type}'.");
        }

        $serialIds = array_values(array_unique($serialIds));

        if ($serialIds === []) {
            throw new InvalidArgumentException('Pilih minimal 1 SN untuk dikirim.');
        }

        return DB::transaction(function () use ($cabang, $pusat, $serialIds, $actor) {
            // lockForUpdate() — cegah 2 staf kirim SN yang sama dalam
            // pengiriman terpisah secara bersamaan (pola sama dispatchSerialized()
            // InventoryTransferService).
            $serials = InventorySerial::query()
                ->whereIn('id', $serialIds)
                ->where('status', SerialStatus::RETURNED->value)
                ->whereNull('current_technician_id')
                ->where('current_pop_id', $cabang->id)
                ->lockForUpdate()
                ->get();

            if ($serials->count() !== count($serialIds)) {
                throw new InvalidArgumentException('Ada SN yang tidak valid, sudah dikirim, atau bukan berada di Cabang ini — muat ulang halaman dan coba lagi.');
            }

            $transfer = InventoryTransfer::create([
                'reference_number' => $this->generateReferenceNumber(),
                'from_pop_id' => $cabang->id,
                'to_pop_id' => $pusat->id,
                'status' => TransferStatus::IN_TRANSIT,
                'created_by' => $actor->id,
            ]);

            foreach ($serials as $serial) {
                $serial->update([
                    'status' => SerialStatus::TRANSFERRED,
                    'current_pop_id' => null,
                ]);

                InventoryTransaction::create([
                    'type' => InventoryTransactionType::TRANSFER,
                    'reference_number' => $transfer->reference_number,
                    'inventory_transfer_id' => $transfer->id,
                    'item_id' => $serial->item_id,
                    'serial_id' => $serial->id,
                    'qty' => 1,
                    'from_pop_id' => $cabang->id,
                    'reason' => 'Kirim retur modem ke Gudang Pusat untuk verifikasi.',
                    'created_by' => $actor->id,
                ]);
            }

            return $transfer;
        });
    }

    /**
     * TAHAP 3 — Pusat konfirmasi SATU SN (per-SN, bukan batch — staf
     * memeriksa fisik satu per satu, pola sama `confirmReturnedSerial()`).
     * Kondisi FINAL ditentukan di sini (boleh menimpa observasi awal Cabang),
     * gate `isClearedForIssue()` dilepas, dan stok Pusat baru bertambah
     * (`to_pop_id` ledger terisi SEKARANG, bukan pas dispatch).
     *
     * Header `InventoryTransfer` ditutup `RECEIVED` begitu SEMUA baris
     * dispatch di dalamnya sudah dikonfirmasi — kalau masih ada SN lain
     * dalam pengiriman yang sama belum dicek, header dibiarkan `IN_TRANSIT`.
     */
    public function confirmAtPusat(InventorySerial $serial, ItemCondition $condition, ?Item $correctedItem, User $actor, ?string $notes = null): InventoryTransaction
    {
        if ($condition === ItemCondition::NEW) {
            throw new InvalidArgumentException('Kondisi modem hasil retur harus "bekas baik" atau "bekas rusak" — bukan baru.');
        }

        if ($correctedItem && $correctedItem->tracking_type !== TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Barang {$correctedItem->name} bukan barang ber-SN.");
        }

        return DB::transaction(function () use ($serial, $condition, $correctedItem, $actor, $notes) {
            $serial = InventorySerial::query()->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::TRANSFERRED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->label()}', bukan menunggu konfirmasi Pusat.");
            }

            $dispatchLine = InventoryTransaction::query()
                ->where('serial_id', $serial->id)
                ->where('type', InventoryTransactionType::TRANSFER->value)
                ->whereNotNull('from_pop_id')
                ->whereNull('to_pop_id')
                ->latest('id')
                ->first();

            if (! $dispatchLine || ! $dispatchLine->inventory_transfer_id) {
                throw new InvalidArgumentException("SN {$serial->serial_number} tidak punya baris pengiriman retur yang menunggu dikonfirmasi.");
            }

            $transfer = InventoryTransfer::query()->lockForUpdate()->findOrFail($dispatchLine->inventory_transfer_id);

            if (! $transfer->isInTransit()) {
                throw new InvalidArgumentException("Pengiriman {$transfer->reference_number} sudah ditutup.");
            }

            $serial->update([
                'item_id' => $correctedItem?->id ?? $serial->item_id,
                'status' => SerialStatus::AVAILABLE,
                'condition' => $condition,
                'condition_checked_at' => now(),
                'condition_checked_by' => $actor->id,
                'current_pop_id' => $transfer->to_pop_id,
            ]);

            $transaction = InventoryTransaction::create([
                'type' => InventoryTransactionType::TRANSFER,
                'reference_number' => $transfer->reference_number,
                'inventory_transfer_id' => $transfer->id,
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'to_pop_id' => $transfer->to_pop_id,
                'reason' => 'Konfirmasi retur modem diterima Gudang Pusat.',
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $dispatchCount = InventoryTransaction::where('inventory_transfer_id', $transfer->id)->whereNotNull('from_pop_id')->count();
            $confirmCount = InventoryTransaction::where('inventory_transfer_id', $transfer->id)->whereNotNull('to_pop_id')->count();

            if ($confirmCount >= $dispatchCount) {
                $transfer->update([
                    'status' => TransferStatus::RECEIVED,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                ]);
            }

            return $transaction;
        });
    }

    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransfer::where('reference_number', 'like', "RTR-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('RTR-%s-%06d', $today, $lastNum + 1);
    }
}
