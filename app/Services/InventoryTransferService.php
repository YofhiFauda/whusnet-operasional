<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TRANSFER Pusat→Cabang — dua fase (§3.6 rancangan-ui.md, §29.5 doc analisa
 * pertama): `createTransfer()` (dispatch, Pusat) lalu `receiveTransfer()`
 * (confirm, Cabang). Header `InventoryTransfer` MUTABLE nampung status
 * `in_transit`→`received`/`received_partial`; baris `inventory_transactions`
 * yang ditulis dua fase ini immutable & independen (lihat docblock
 * `InventoryTransfer`/migration `create_inventory_transactions_table`).
 *
 * Stok Pusat berkurang SAAT DISPATCH (barang udah fisik keluar gudang), stok
 * Cabang bertambah SAAT CONFIRM (bukan pas dispatch — kalau ada mismatch/
 * hilang di jalan, gak boleh keburu nambah stok Cabang yang ternyata gak
 * beneran nyampe).
 */
class InventoryTransferService
{
    /**
     * @param  list<array{item_id:int, qty?:float, lot_no?:?string, serial_numbers?:list<string>}>  $lines
     */
    public function createTransfer(Pop $fromPusat, Pop $toCabang, array $lines, ?User $actor = null): InventoryTransfer
    {
        if (! $fromPusat->isPusat()) {
            throw new InvalidArgumentException("Transfer cuma boleh dikirim DARI Gudang Pusat — {$fromPusat->name} bertipe '{$fromPusat->type}'.");
        }

        if (! $toCabang->isCabang()) {
            throw new InvalidArgumentException("Transfer cuma boleh dikirim KE Gudang Cabang — {$toCabang->name} bertipe '{$toCabang->type}'.");
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Transfer wajib py minimal 1 baris barang.');
        }

        // Urutkan per item_id — cegah deadlock lock-ordering (lihat catatan
        // sama di InventoryReceiveService::receiveBatch()).
        usort($lines, fn ($a, $b) => $a['item_id'] <=> $b['item_id']);

        return DB::transaction(function () use ($fromPusat, $toCabang, $lines, $actor) {
            $transfer = InventoryTransfer::create([
                'reference_number' => $this->generateReferenceNumber(),
                'from_pop_id' => $fromPusat->id,
                'to_pop_id' => $toCabang->id,
                'status' => TransferStatus::IN_TRANSIT,
                'created_by' => $actor?->id,
            ]);

            foreach ($lines as $line) {
                $item = Item::findOrFail($line['item_id']);

                if ($item->tracking_type === TrackingType::SERIALIZED) {
                    $this->dispatchSerialized($transfer, $fromPusat, $item, $line['serial_numbers'] ?? [], $actor);
                } else {
                    $this->dispatchQuantity($transfer, $fromPusat, $item, (float) ($line['qty'] ?? 0), $line['lot_no'] ?? null, $actor);
                }
            }

            return $transfer;
        });
    }

    private function dispatchSerialized(InventoryTransfer $transfer, Pop $fromPusat, Item $item, array $serialNumbers, ?User $actor): void
    {
        if ($serialNumbers === []) {
            throw new InvalidArgumentException("Item {$item->name} SERIALIZED — daftar serial number wajib diisi.");
        }

        foreach ($serialNumbers as $serialNumber) {
            // lockForUpdate() — cegah 2 transfer/issue bersamaan ngambil SN
            // fisik yang sama (lihat catatan sama di InventoryIssueService).
            $serial = InventorySerial::query()
                ->where('item_id', $item->id)
                ->where('serial_number', $serialNumber)
                ->where('status', SerialStatus::AVAILABLE->value)
                ->where('current_pop_id', $fromPusat->id)
                ->lockForUpdate()
                ->first();

            if (! $serial) {
                throw new InvalidArgumentException("SN {$serialNumber} tidak tersedia di {$fromPusat->name} (sudah dipakai/dipindah/salah lokasi).");
            }

            $serial->update([
                'status' => SerialStatus::TRANSFERRED,
                'current_pop_id' => null,
            ]);

            InventoryTransaction::create([
                'type' => InventoryTransactionType::TRANSFER,
                'reference_number' => $transfer->reference_number,
                'inventory_transfer_id' => $transfer->id,
                'item_id' => $item->id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'unit_price_snapshot' => $this->resolveLastCost($item, null),
                'from_pop_id' => $fromPusat->id,
                'created_by' => $actor?->id,
            ]);
        }
    }

    private function dispatchQuantity(InventoryTransfer $transfer, Pop $fromPusat, Item $item, float $qty, ?string $lotNo, ?User $actor): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException("Qty transfer {$item->name} harus lebih besar dari nol.");
        }

        $lotNo = $this->normalizeLotNo($item, $lotNo);

        $balance = InventoryBalance::query()
            ->where('pop_id', $fromPusat->id)
            ->where('item_id', $item->id)
            ->where('lot_no', $lotNo)
            ->lockForUpdate()
            ->first();

        if (! $balance || $balance->qty < $qty) {
            $available = $balance?->qty ?? 0;
            throw new InvalidArgumentException("Stok {$item->name} di {$fromPusat->name} tidak cukup: diminta {$qty}, tersedia {$available}.");
        }

        $balance->decrement('qty', $qty);

        InventoryTransaction::create([
            'type' => InventoryTransactionType::TRANSFER,
            'reference_number' => $transfer->reference_number,
            'inventory_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'lot_no' => $lotNo === '' ? null : $lotNo,
            'qty' => $qty,
            'unit_price_snapshot' => $this->resolveLastCost($item, $lotNo === '' ? null : $lotNo),
            'from_pop_id' => $fromPusat->id,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Cabang konfirmasi terima. `$confirmedSerialNumbers` = SN yang FISIK
     * cocok diterima (subset dari yang dikirim — sisanya otomatis dianggap
     * mismatch/hilang, TETAP berstatus TRANSFERRED, gak nyasar jadi AVAILABLE
     * di Cabang begitu saja). `$confirmedQuantities` = qty aktual diterima
     * per item (+lot kalau BATCH), format `[item_id => qty]` buat non-lot
     * atau `[item_id => [lot_no => qty]]` kalau item itu py lot di dispatch.
     *
     * Partial diperbolehkan (§2.3 rancangan-ui.md) — TIDAK block seluruh
     * transfer, cuma ditandai `RECEIVED_PARTIAL`.
     *
     * @param  list<string>  $confirmedSerialNumbers
     * @param  array<int, float|array<string, float>>  $confirmedQuantities
     */
    public function receiveTransfer(
        InventoryTransfer $transfer,
        array $confirmedSerialNumbers,
        array $confirmedQuantities,
        User $actor,
    ): InventoryTransfer {
        return DB::transaction(function () use ($transfer, $confirmedSerialNumbers, $confirmedQuantities, $actor) {
            // Re-fetch + lockForUpdate() DI DALAM transaction, cek status
            // dari hasil fetch ini (bukan parameter $transfer yang bisa
            // stale) — dobel-klik "Terima"/2 request bersamaan sebelumnya
            // bisa dua-duanya lolos cek isInTransit() lalu dua-duanya
            // ngekredit stok Cabang (ketauan audit 2026-09-02).
            $transfer = InventoryTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isInTransit()) {
                throw new InvalidArgumentException("Transfer {$transfer->reference_number} sudah dikonfirmasi sebelumnya ({$transfer->status->label()}).");
            }

            $dispatchLines = $transfer->transactions()->whereNotNull('from_pop_id')->with('serial')->get();
            $isPartial = false;

            foreach ($dispatchLines as $line) {
                if ($line->serial_id !== null) {
                    $matched = in_array($line->serial->serial_number, $confirmedSerialNumbers, true);

                    if (! $matched) {
                        $isPartial = true;

                        continue; // SN tetap TRANSFERRED — limbo, butuh investigasi manual.
                    }

                    $line->serial->update([
                        'status' => SerialStatus::AVAILABLE,
                        'current_pop_id' => $transfer->to_pop_id,
                    ]);

                    InventoryTransaction::create([
                        'type' => InventoryTransactionType::TRANSFER,
                        'reference_number' => $transfer->reference_number,
                        'inventory_transfer_id' => $transfer->id,
                        'item_id' => $line->item_id,
                        'serial_id' => $line->serial_id,
                        'qty' => 1,
                        'unit_price_snapshot' => $line->unit_price_snapshot,
                        'to_pop_id' => $transfer->to_pop_id,
                        'created_by' => $actor->id,
                    ]);

                    continue;
                }

                $lotNo = $line->lot_no ?? '';
                $dispatchedQty = (float) $line->qty;
                $confirmedForItem = $confirmedQuantities[$line->item_id] ?? 0;
                $confirmedQty = is_array($confirmedForItem) ? (float) ($confirmedForItem[$lotNo] ?? 0) : (float) $confirmedForItem;

                if ($confirmedQty > $dispatchedQty) {
                    throw new InvalidArgumentException("Qty diterima ({$confirmedQty}) buat item #{$line->item_id} melebihi yang dikirim ({$dispatchedQty}).");
                }

                // Negatif itu input rusak (bug form/typo), bukan "0 diterima"
                // — sebelumnya diserap diam-diam jadi skip via `<= 0` di
                // bawah, gak ada error apa pun ke admin (ketauan audit
                // 2026-09-02). 0 TETAP sah (memang belum ada yang diterima
                // buat baris ini, jalur partial receive normal).
                if ($confirmedQty < 0) {
                    throw new InvalidArgumentException("Qty diterima buat item #{$line->item_id} tidak boleh negatif ({$confirmedQty}).");
                }

                if ($confirmedQty < $dispatchedQty) {
                    $isPartial = true;
                }

                if ($confirmedQty <= 0) {
                    continue;
                }

                $balance = InventoryBalance::query()
                    ->firstOrCreate(
                        ['pop_id' => $transfer->to_pop_id, 'item_id' => $line->item_id, 'lot_no' => $lotNo],
                        ['qty' => 0]
                    );
                $balance->increment('qty', $confirmedQty);

                InventoryTransaction::create([
                    'type' => InventoryTransactionType::TRANSFER,
                    'reference_number' => $transfer->reference_number,
                    'inventory_transfer_id' => $transfer->id,
                    'item_id' => $line->item_id,
                    'lot_no' => $lotNo === '' ? null : $lotNo,
                    'qty' => $confirmedQty,
                    'unit_price_snapshot' => $line->unit_price_snapshot,
                    'to_pop_id' => $transfer->to_pop_id,
                    'created_by' => $actor->id,
                ]);
            }

            $transfer->update([
                'status' => $isPartial ? TransferStatus::RECEIVED_PARTIAL : TransferStatus::RECEIVED,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }

    private function normalizeLotNo(Item $item, ?string $lotNo): string
    {
        if ($item->tracking_type === TrackingType::BATCH) {
            if (blank($lotNo)) {
                throw new InvalidArgumentException("Item {$item->name} tracking_type BATCH — lot_no wajib diisi.");
            }

            return $lotNo;
        }

        if (filled($lotNo)) {
            throw new InvalidArgumentException("Item {$item->name} bukan BATCH — lot_no harus kosong.");
        }

        return '';
    }

    /**
     * Harga terakhir tercatat buat item ini (+lot kalau diisi) — "last-**purchase**-cost",
     * dibaca dari ledger, BUKAN tabel harga terpisah (§16.4/§29.8). Null kalau
     * belum pernah ada histori harga (mis. admin lupa isi saat RECEIVE) —
     * dibiarkan null, bukan dipaksa 0 yang menyesatkan.
     *
     * DIFILTER `type = RECEIVE` — sebelumnya `latest('id')` tanpa filter type
     * bisa kebaca snapshot dari RETURN/TRANSFER_CUSTODY (custody teknisi
     * balik/pindah tangan, ikut bawa harga LAMA dari saat pertama di-issue)
     * yang kebetulan baris ledger-nya lebih baru dari RECEIVE terakhir yang
     * beneran nyatet harga baru — jadinya "harga terakhir" malah nunjukin
     * harga LEBIH LAMA daripada RECEIVE yang lebih baru (ketauan audit
     * 2026-09-02). RECEIVE satu-satunya titik "beli beneran", jadi
     * satu-satunya sumber valid buat "biaya pembelian terakhir".
     */
    private function resolveLastCost(Item $item, ?string $lotNo): ?float
    {
        $query = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereNotNull('unit_price_snapshot')
            ->latest('id');

        if ($lotNo !== null) {
            $query->where('lot_no', $lotNo);
        }

        $price = $query->value('unit_price_snapshot');

        return $price !== null ? (float) $price : null;
    }

    /**
     * `TRF-YYYYMMDD-NNNNNN` — lihat docblock sama persis di
     * `InventoryReceiveService::generateReferenceNumber()` buat alasan
     * lengkap (tanggal penuh buat dibaca, reset counter per BULAN bukan per
     * hari, keputusan eksplisit user 2026-09-03).
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransfer::where('reference_number', 'like', "TRF-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('TRF-%s-%06d', $today, $lastNum + 1);
    }
}
