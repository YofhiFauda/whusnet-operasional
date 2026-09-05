<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ISSUE — Gudang Cabang → custody Teknisi. Stok Cabang berkurang DI SINI,
 * bukan pas teknisi submit laporan pemakaian (§29.4 poin 2 doc analisa
 * pertama, §3.7 rancangan-ui.md — dua neraca beda: stok gudang vs custody
 * teknisi).
 *
 * SETIAP issue barang QUANTITY/BATCH bikin baris `technician_custody` BARU —
 * gak digabung ke baris existing teknisi yang sama buat item yang sama,
 * biar FIFO consumption (`InventoryService::consumeFromCustody()`) bisa
 * jalan per-lot per-waktu-ambil (lihat docblock `TechnicianCustody`).
 */
class InventoryIssueService
{
    /**
     * @param  list<array{item_id:int, qty?:float, lot_no?:?string, serial_numbers?:list<string>}>  $lines
     * @return list<InventoryTransaction>
     */
    public function issue(Pop $cabang, User $technician, array $lines, User $actor): array
    {
        if (! $cabang->isCabang()) {
            throw new InvalidArgumentException("Issue cuma boleh dari Gudang Cabang — {$cabang->name} bertipe '{$cabang->type}'.");
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Issue wajib py minimal 1 baris barang.');
        }

        // Urutkan per item_id — cegah deadlock lock-ordering (lihat catatan
        // sama di InventoryReceiveService::receiveBatch()).
        usort($lines, fn ($a, $b) => $a['item_id'] <=> $b['item_id']);

        return DB::transaction(function () use ($cabang, $technician, $lines, $actor) {
            $referenceNumber = $this->generateReferenceNumber();
            $transactions = [];

            foreach ($lines as $line) {
                $item = Item::findOrFail($line['item_id']);

                $transactions[] = $item->tracking_type === TrackingType::SERIALIZED
                    ? $this->issueSerialized($referenceNumber, $cabang, $technician, $item, $line['serial_numbers'] ?? [], $actor)
                    : $this->issueQuantity($referenceNumber, $cabang, $technician, $item, (float) ($line['qty'] ?? 0), $line['lot_no'] ?? null, $actor);
            }

            return array_merge(...$transactions);
        });
    }

    /**
     * @return list<InventoryTransaction>
     */
    private function issueSerialized(string $referenceNumber, Pop $cabang, User $technician, Item $item, array $serialNumbers, User $actor): array
    {
        if ($serialNumbers === []) {
            throw new InvalidArgumentException("Item {$item->name} SERIALIZED — daftar serial number wajib diisi.");
        }

        $transactions = [];

        foreach ($serialNumbers as $serialNumber) {
            // lockForUpdate() — dua issue() bersamaan buat SN yang sama gak
            // boleh dua-duanya lolos cek AVAILABLE lalu dua-duanya nulis
            // ISSUED (ketauan audit 2026-09-02: qty path selalu lock,
            // serial path sebelumnya enggak — satu SN fisik bisa "ke-issue"
            // ke 2 teknisi kalau race).
            $serial = InventorySerial::query()
                ->where('item_id', $item->id)
                ->where('serial_number', $serialNumber)
                ->where('status', SerialStatus::AVAILABLE->value)
                ->where('current_pop_id', $cabang->id)
                ->lockForUpdate()
                ->first();

            if (! $serial) {
                throw new InvalidArgumentException("SN {$serialNumber} tidak tersedia di {$cabang->name}.");
            }

            $serial->update([
                'status' => SerialStatus::ISSUED,
                'current_pop_id' => null,
                'current_technician_id' => $technician->id,
                'issued_from_pop_id' => $cabang->id,
            ]);

            $transactions[] = InventoryTransaction::create([
                'type' => InventoryTransactionType::ISSUE,
                'reference_number' => $referenceNumber,
                'item_id' => $item->id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'unit_price_snapshot' => $this->resolveLastCost($item, null),
                'from_pop_id' => $cabang->id,
                'to_technician_id' => $technician->id,
                'created_by' => $actor->id,
            ]);
        }

        return $transactions;
    }

    /**
     * @return list<InventoryTransaction>
     */
    private function issueQuantity(string $referenceNumber, Pop $cabang, User $technician, Item $item, float $qty, ?string $lotNo, User $actor): array
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException("Qty issue {$item->name} harus lebih besar dari nol.");
        }

        $lotNo = $this->normalizeLotNo($item, $lotNo);

        $balance = InventoryBalance::query()
            ->where('pop_id', $cabang->id)
            ->where('item_id', $item->id)
            ->where('lot_no', $lotNo)
            ->lockForUpdate()
            ->first();

        if (! $balance || $balance->qty < $qty) {
            $available = $balance?->qty ?? 0;
            throw new InvalidArgumentException("Stok {$item->name} di {$cabang->name} tidak cukup: diminta {$qty}, tersedia {$available}.");
        }

        $balance->decrement('qty', $qty);

        $unitPrice = $this->resolveLastCost($item, $lotNo === '' ? null : $lotNo);

        TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $cabang->id,
            'item_id' => $item->id,
            'lot_no' => $lotNo === '' ? null : $lotNo,
            'qty_remaining' => $qty,
            'unit_price_snapshot' => $unitPrice,
            'status' => CustodyStatus::ISSUED,
            'issued_at' => now(),
        ]);

        return [InventoryTransaction::create([
            'type' => InventoryTransactionType::ISSUE,
            'reference_number' => $referenceNumber,
            'item_id' => $item->id,
            'lot_no' => $lotNo === '' ? null : $lotNo,
            'qty' => $qty,
            'unit_price_snapshot' => $unitPrice,
            'from_pop_id' => $cabang->id,
            'to_technician_id' => $technician->id,
            'created_by' => $actor->id,
        ])];
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
     * Sama persis `InventoryTransferService::resolveLastCost()` (baca
     * docblock di sana buat alasan filter `type=RECEIVE`) — duplikasi sadar
     * (dua pemanggil, belum sepadan diabstraksi jadi service/trait baru —
     * YAGNI). Kalau nanti muncul pemanggil ketiga, baru pantas ditarik jadi
     * satu tempat.
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
     * `ISS-YYYYMMDD-NNNNNN` — lihat docblock sama persis di
     * `InventoryReceiveService::generateReferenceNumber()` buat alasan
     * lengkap (tanggal penuh buat dibaca, reset counter per BULAN bukan per
     * hari, keputusan eksplisit user 2026-09-03).
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransaction::where('reference_number', 'like', "ISS-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('ISS-%s-%06d', $today, $lastNum + 1);
    }
}
