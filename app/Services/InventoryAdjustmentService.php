<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Koreksi stok non-alur-normal: LOST/DAMAGED/SCRAPPED/opname. `$reason` WAJIB
 * diisi buat semuanya (kontrol-anti-manipulasi.md §1-2).
 *
 * TANPA gerbang approval bertingkat (`PENDING_APPROVAL` + threshold nominal)
 * — keputusan eksplisit user: masih rancangan awal, threshold nominal belum
 * bisa ditentukan tanpa data operasional riil. Monitoring buat sekarang
 * berbasis STATUS BARANG yang tercatat di ledger (visible di Dashboard HQ),
 * bukan gerbang pre-approval. Lihat kontrol-anti-manipulasi.md §1 (revisi).
 */
class InventoryAdjustmentService
{
    /**
     * Kategori `reason` terarah (Fase 2 P1, kontrol-anti-manipulasi.md §2) —
     * dropdown di UI, BUKAN enum baru (keputusan eksplisit di
     * fase-2-adaptasi-wms.md: "supaya bisa direkap terpisah, tanpa nambah
     * enum baru"). Cuma kategori KERUGIAN (`lost`, `damaged`) yang mewajibkan
     * bukti fisik — `quarantine` cuma status tahan sementara (bukan klaim
     * rugi), `shrinkage_on_return`/`stock_opname_diff` levelnya beda (custody
     * partial-return / opname saldo gudang, bukan barang yang HILANG dari
     * teknisi tertentu).
     */
    public const REASON_CATEGORIES = [
        'lost' => 'Hilang',
        'damaged' => 'Rusak',
        'quarantine' => 'Ditahan / Dicek Ulang (Quarantine)',
        'shrinkage_on_return' => 'Selisih Saat Return',
        'stock_opname_diff' => 'Selisih Stock Opname',
    ];

    /**
     * Kategori yang WAJIB bukti fisik — klaim kerugian nyata, beda dari
     * quarantine (status sementara) atau selisih administratif.
     */
    private const EVIDENCE_REQUIRED_REASONS = ['lost', 'damaged'];

    /**
     * `$qtyDelta` SIGNED — negatif buat kerugian/susut, positif buat
     * penemuan lebih saat opname. SATU-SATUNYA transaction type yang qty-nya
     * boleh negatif (ADJUSTMENT gak punya from/to natural kayak tipe lain,
     * jadi arah perubahan ditulis langsung di angkanya — §18 doc analisa
     * pertama, contoh "ADJUSTMENT -2").
     */
    public function adjustPopBalance(Pop $pop, int $itemId, float $qtyDelta, string $reason, User $actor, ?string $lotNo = null, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        // `InventoryBalance` docblock: pop_id gak boleh mini_pop, ditegakkan
        // "di titik penulisan (Service)" — sebelumnya cuma RECEIVE/TRANSFER/
        // ISSUE yang nurut, Adjustment kelewat (ketauan audit 2026-09-02).
        if (! $pop->isWarehouse()) {
            throw new InvalidArgumentException("Adjustment cuma boleh ke Gudang Pusat/Cabang — {$pop->name} bertipe '{$pop->type}'.");
        }

        if ($qtyDelta == 0.0) {
            throw new InvalidArgumentException('Qty adjustment tidak boleh nol — itu bukan koreksi apa pun.');
        }

        $lotNo = $lotNo ?? '';

        return DB::transaction(function () use ($pop, $itemId, $qtyDelta, $reason, $actor, $lotNo, $notes) {
            $balance = InventoryBalance::query()
                ->where('pop_id', $pop->id)
                ->where('item_id', $itemId)
                ->where('lot_no', $lotNo)
                ->lockForUpdate()
                ->first();

            $currentQty = (float) ($balance?->qty ?? 0);

            if ($currentQty + $qtyDelta < 0) {
                throw new InvalidArgumentException("Adjustment bikin stok jadi negatif: {$currentQty} + ({$qtyDelta}) < 0.");
            }

            if (! $balance) {
                $balance = InventoryBalance::create(['pop_id' => $pop->id, 'item_id' => $itemId, 'lot_no' => $lotNo, 'qty' => 0]);
            }

            $balance->increment('qty', $qtyDelta);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::ADJUSTMENT,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $itemId,
                'lot_no' => $lotNo === '' ? null : $lotNo,
                'qty' => $qtyDelta,
                'to_pop_id' => $pop->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Adjustment di custody teknisi (mis. kabel hilang dari yang dia bawa,
     * bukan dari stok gudang). Sama aturan signed delta.
     *
     * `$reason` WAJIB salah satu `self::REASON_CATEGORIES` (bukan teks bebas
     * lagi, Fase 2 P1) — kalau kategorinya `lost`/`damaged`,
     * `$evidenceFilePath` WAJIB terisi (kontrol-anti-manipulasi.md §2: "tanpa
     * foto, klaim gak bisa disetujui — guard di Service, bukan validasi UI
     * doang").
     */
    public function adjustCustody(TechnicianCustody $custody, float $qtyDelta, string $reason, User $actor, ?string $notes = null, ?string $evidenceFilePath = null): InventoryTransaction
    {
        $this->assertReasonCategory($reason);
        $this->assertEvidenceIfRequired($reason, $evidenceFilePath);

        if ($qtyDelta == 0.0) {
            throw new InvalidArgumentException('Qty adjustment tidak boleh nol.');
        }

        $newRemaining = (float) $custody->qty_remaining + $qtyDelta;

        if ($newRemaining < 0) {
            throw new InvalidArgumentException("Adjustment bikin sisa custody jadi negatif: {$custody->qty_remaining} + ({$qtyDelta}) < 0.");
        }

        return DB::transaction(function () use ($custody, $qtyDelta, $newRemaining, $reason, $actor, $notes, $evidenceFilePath) {
            // Kalau sisa jadi 0 → CONSUMED, gak peduli status asal. Tapi
            // kalau sisa jadi POSITIF ("ditemukan lagi" pas opname) padahal
            // status asalnya RETURNED/CONSUMED (terminal — CustodyStatus
            // sendiri artinya "sisa fisik udah balik ke gudang"/"udah abis"),
            // status HARUS ditarik balik ke PARTIALLY_USED — dibiarkan
            // RETURNED/CONSUMED dgn qty_remaining>0 itu kombinasi yang gak
            // boleh ada (ketauan audit 2026-09-02).
            $newStatus = match (true) {
                $newRemaining <= 0 => CustodyStatus::CONSUMED,
                in_array($custody->status, [CustodyStatus::RETURNED, CustodyStatus::CONSUMED], true) => CustodyStatus::PARTIALLY_USED,
                default => $custody->status,
            };

            $custody->update([
                'qty_remaining' => $newRemaining,
                'status' => $newStatus,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::ADJUSTMENT,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $custody->item_id,
                'lot_no' => $custody->lot_no,
                'qty' => $qtyDelta,
                'from_technician_id' => $custody->technician_id,
                'reason' => $reason,
                'notes' => $notes,
                'evidence_file_path' => $evidenceFilePath,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Ubah status unit SERIALIZED ke LOST/DAMAGED/SCRAPPED/QUARANTINE —
     * SENGAJA cuma 4 tujuan itu yang diterima, bukan status bebas (transisi
     * ke INSTALLED/AVAILABLE/ISSUED lewat jalur masing-masing, bukan sini).
     * Lokasi (`current_pop_id`/`current_technician_id`) SENGAJA TIDAK
     * disentuh — "terakhir ada di mana" itu info forensik berharga, jangan
     * dihapus cuma karena status berubah jadi rusak/hilang.
     *
     * `$evidenceFilePath` WAJIB terisi buat transisi ke LOST/DAMAGED/SCRAPPED
     * (Fase 2 P1, kontrol-anti-manipulasi.md §2) — ketiganya klaim kerugian
     * nyata (SCRAPPED = write-off final, harus ada dasar foto juga).
     * QUARANTINE TETAP boleh tanpa foto — itu status tahan sementara buat
     * dicek, bukan klaim rugi.
     */
    public function adjustSerialStatus(InventorySerial $serial, SerialStatus $newStatus, string $reason, User $actor, ?string $notes = null, ?string $evidenceFilePath = null): InventoryTransaction
    {
        $this->assertReason($reason);

        $allowed = [SerialStatus::LOST, SerialStatus::DAMAGED, SerialStatus::SCRAPPED, SerialStatus::QUARANTINE];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException('adjustSerialStatus() cuma buat LOST/DAMAGED/SCRAPPED/QUARANTINE — transisi status lain lewat Service masing-masing.');
        }

        $evidenceRequiredStatuses = [SerialStatus::LOST, SerialStatus::DAMAGED, SerialStatus::SCRAPPED];
        if (in_array($newStatus, $evidenceRequiredStatuses, true) && blank($evidenceFilePath)) {
            throw new InvalidArgumentException("Transisi ke {$newStatus->label()} wajib disertai bukti fisik (foto kondisi barang / BAP kehilangan) — kontrol-anti-manipulasi.md §2.");
        }

        // SCRAPPED = write-off final, gak ada transisi keluar dari situ —
        // sebelumnya SN yang udah SCRAPPED bisa "diadjust lagi" jadi LOST,
        // bolak-balik status yang harusnya udah selesai (ketauan audit
        // 2026-09-02). Status sama juga ditolak, itu bukan koreksi apa pun.
        if ($serial->status === SerialStatus::SCRAPPED) {
            throw new InvalidArgumentException("SN {$serial->serial_number} udah SCRAPPED (write-off final) — gak bisa diubah status lagi.");
        }

        if ($serial->status === $newStatus) {
            throw new InvalidArgumentException("SN {$serial->serial_number} udah berstatus {$newStatus->value} — gak ada perubahan buat dicatat.");
        }

        return DB::transaction(function () use ($serial, $newStatus, $reason, $actor, $notes, $evidenceFilePath) {
            $updates = ['status' => $newStatus];

            // Serial yang lagi INSTALLED di pelanggan ditarik status lost/
            // damaged/scrapped/quarantine — `customer_id`/`fop_task_id`/
            // `installed_at` dikosongkan karena udah gak lagi BENERAN
            // terpasang di sana (beda dari current_pop_id/current_technician_id
            // yang SENGAJA dibiarkan di semua jalur lain sbg jejak "terakhir
            // ada di mana" — customer_id kalau dibiarkan nyangkut nyesatkan,
            // seolah masih aktif terpasang padahal statusnya udah bukan).
            if ($serial->status === SerialStatus::INSTALLED) {
                $updates['customer_id'] = null;
                $updates['fop_task_id'] = null;
                $updates['installed_at'] = null;
            }

            $serial->update($updates);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::ADJUSTMENT,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => -1,
                'from_pop_id' => $serial->current_pop_id,
                'from_technician_id' => $serial->current_technician_id,
                'reason' => $reason,
                'notes' => $notes,
                'evidence_file_path' => $evidenceFilePath,
                'created_by' => $actor->id,
            ]);
        });
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan adjustment wajib diisi — kontrol-anti-manipulasi.md §1-2.');
        }
    }

    /**
     * Versi terarah buat `adjustCustody()` (Fase 2 P1) — `$reason` harus
     * salah satu kode di `self::REASON_CATEGORIES`, bukan teks bebas lagi.
     * `adjustPopBalance()`/`adjustSerialStatus()` TETAP pakai `assertReason()`
     * biasa (di luar cakupan perubahan ini — lihat fase-2-adaptasi-wms.md P1).
     */
    private function assertReasonCategory(string $reason): void
    {
        if (! array_key_exists($reason, self::REASON_CATEGORIES)) {
            $valid = implode(', ', array_keys(self::REASON_CATEGORIES));
            throw new InvalidArgumentException("Kategori alasan '{$reason}' tidak dikenal — harus salah satu: {$valid}.");
        }
    }

    /**
     * Guard inti Fase 2 P1 (kontrol-anti-manipulasi.md §2) — ditegakkan di
     * SINI (Service), bukan cuma validasi UI, biar gak bisa dilewat lewat
     * request langsung ke endpoint.
     */
    private function assertEvidenceIfRequired(string $reason, ?string $evidenceFilePath): void
    {
        if (in_array($reason, self::EVIDENCE_REQUIRED_REASONS, true) && blank($evidenceFilePath)) {
            $label = self::REASON_CATEGORIES[$reason] ?? $reason;
            throw new InvalidArgumentException("Klaim '{$label}' wajib disertai bukti fisik (foto kondisi barang / BAP kehilangan) — kontrol-anti-manipulasi.md §2.");
        }
    }

    /**
     * `ADJ-YYYY-NNNN` — sama alasan sama pola `InventoryReassignService::generateReferenceNumber()`
     * (RSG-), ketauan audit 2026-09-02 event ADJUSTMENT gak py nomor dokumen
     * sama sekali. Race generate-nya SENGAJA gak dibenahi (lihat catatan
     * pelingkupan P0/P1 docs/TASKS.md).
     */
    /**
     * `ADJ-YYYYMMDD-NNNNNN` — lihat docblock sama persis di
     * `InventoryReceiveService::generateReferenceNumber()` buat alasan
     * lengkap (tanggal penuh buat dibaca, reset counter per BULAN bukan per
     * hari, keputusan eksplisit user 2026-09-03).
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransaction::where('reference_number', 'like', "ADJ-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('ADJ-%s-%06d', $today, $lastNum + 1);
    }

    /**
     * Catat hasil Stock Opname (Fase 2 P1, gap #3 — kontrol-anti-manipulasi.md
     * §5). SATU-SATUNYA jalur yang boleh nulis ledger `qty` NOL: opname yang
     * hasilnya PAS (gak ada selisih) TETAP wajib tercatat — "supaya 'belum
     * pernah opname' vs 'baru saja opname hasilnya pas' tetap beda status
     * yang kelihatan di ledger" (§5). `adjustPopBalance()` sengaja menolak
     * delta nol (itu jalur koreksi manual, delta nol bukan koreksi apa pun)
     * — dua method ini beda tujuan, jangan digabung.
     *
     * `$countedQty` = hasil hitung fisik admin gudang (angka akhir, BUKAN
     * delta) — service yang hitung sendiri selisihnya terhadap saldo
     * sistem, operator gak perlu itung manual dulu sebelum input.
     *
     * Type ledger `STOCK_OPNAME` (bukan `ADJUSTMENT`) — biar "opname
     * terakhir per item per gudang" bisa di-query terpisah dari koreksi
     * rusak/hilang/susut biasa (`WarehouseStockController`).
     */
    public function recordStockOpname(Pop $pop, int $itemId, float $countedQty, User $actor, ?string $lotNo = null, ?string $notes = null): InventoryTransaction
    {
        if (! $pop->isWarehouse()) {
            throw new InvalidArgumentException("Stock Opname cuma boleh di Gudang Pusat/Cabang — {$pop->name} bertipe '{$pop->type}'.");
        }

        if ($countedQty < 0) {
            throw new InvalidArgumentException('Hasil hitung fisik tidak boleh negatif.');
        }

        $lotNo = $lotNo ?? '';

        return DB::transaction(function () use ($pop, $itemId, $countedQty, $actor, $lotNo, $notes) {
            $balance = InventoryBalance::query()
                ->where('pop_id', $pop->id)
                ->where('item_id', $itemId)
                ->where('lot_no', $lotNo)
                ->lockForUpdate()
                ->first();

            $currentQty = (float) ($balance?->qty ?? 0);
            $delta = $countedQty - $currentQty;

            if (! $balance) {
                $balance = InventoryBalance::create(['pop_id' => $pop->id, 'item_id' => $itemId, 'lot_no' => $lotNo, 'qty' => 0]);
            }

            $balance->update(['qty' => $countedQty]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::STOCK_OPNAME,
                'reference_number' => $this->generateOpnameReferenceNumber(),
                'item_id' => $itemId,
                'lot_no' => $lotNo === '' ? null : $lotNo,
                'qty' => $delta,
                'to_pop_id' => $pop->id,
                'reason' => 'stock_opname_diff',
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * `OPN-YYYYMMDD-NNNNNN` — series terpisah dari `ADJ-`, pola sama
     * `generateReferenceNumber()` di atas.
     */
    private function generateOpnameReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransaction::where('reference_number', 'like', "OPN-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('OPN-%s-%06d', $today, $lastNum + 1);
    }
}
