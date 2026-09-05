<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RECEIVE — barang masuk dari supplier ke Gudang Pusat. SATU-SATUNYA titik
 * masuk barang baru ke sistem (Cabang gak pernah RECEIVE langsung dari
 * supplier, cuma lewat Transfer — lihat InventoryTransferService).
 *
 * Harga (`unit_price_snapshot`) WAJIB diisi di sini — ini titik "last-cost"
 * yang nanti dibaca ulang `InventoryIssueService` buat nyalin harga ke custody
 * teknisi (§16.4/§29.8 doc analisa, §3.5 rancangan-ui.md).
 */
class InventoryReceiveService
{
    /**
     * Terima barang QUANTITY/BATCH (RJ45, kabel per drum). `lotNo` WAJIB
     * diisi kalau `item.tracking_type === BATCH`, dan HARUS kosong buat
     * QUANTITY biasa — dua axis ini gampang ketuker kalau gak divalidasi di
     * titik masuk.
     */
    public function receiveQuantity(
        Pop $pusat,
        Item $item,
        float $qty,
        float $unitPrice,
        ?string $lotNo = null,
        ?User $actor = null,
        ?string $notes = null,
        ?string $referenceNumber = null,
    ): InventoryTransaction {
        $this->assertPusat($pusat);
        $this->assertPositiveQty($qty);
        $this->assertPositivePrice($unitPrice);
        $this->assertQuantityOrBatchTracking($item);
        $lotNo = $this->normalizeLotNo($item, $lotNo);

        return DB::transaction(function () use ($pusat, $item, $qty, $unitPrice, $lotNo, $actor, $notes, $referenceNumber) {
            $balance = InventoryBalance::query()
                ->firstOrCreate(
                    ['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => $lotNo],
                    ['qty' => 0]
                );
            $balance->increment('qty', $qty);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RECEIVE,
                'reference_number' => $referenceNumber,
                'item_id' => $item->id,
                'lot_no' => $lotNo === '' ? null : $lotNo,
                'qty' => $qty,
                'unit_price_snapshot' => $unitPrice,
                'to_pop_id' => $pusat->id,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);
        });
    }

    /**
     * Terima barang SERIALIZED (modem, ONT, router, OTDR) — satu SN = satu
     * unit fisik, satu baris `inventory_serials` + satu baris ledger PER SN
     * (bukan digabung satu baris agregat), biar Asset Traceability (§2.8
     * rancangan-ui.md) bisa nunjukin RECEIVE sebagai titik pertama riwayat
     * SN itu.
     *
     * @param  list<string>  $serialNumbers
     * @return list<InventorySerial>
     */
    public function receiveSerialized(
        Pop $pusat,
        Item $item,
        array $serialNumbers,
        float $unitPrice,
        ?User $actor = null,
        ?string $notes = null,
        ?string $referenceNumber = null,
    ): array {
        $this->assertPusat($pusat);
        $this->assertPositivePrice($unitPrice);

        if ($item->tracking_type !== TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Item {$item->name} bukan tracking_type SERIALIZED.");
        }

        if ($serialNumbers === []) {
            throw new InvalidArgumentException('Daftar serial number tidak boleh kosong.');
        }

        // Sebelumnya gak ada guard di sini — konstrain unique DB
        // (`inventory_serials.serial_number`) satu-satunya penjaga, jadi
        // SN dobel (baik nempel dobel dalam SATU submit textarea manual,
        // maupun beneran udah pernah ke-input sebelumnya) ngelempar
        // `UniqueConstraintViolationException` MENTAH ke user — 500 blank,
        // bukan pesan yang bisa ditindaklanjuti (laporan user, 2026-09-04).
        // Endpoint scan (`storeScanned`) udah divalidasi di controller
        // (Rule::unique + distinct), tapi form manual multi-baris textarea
        // (`store()`/`normalizeLines()`) gak lewat validasi terstruktur
        // sama — guard di sini nutup dua-duanya sekaligus di SATU tempat
        // (Service, bukan diulang tiap controller pemanggil).
        $this->assertSerialNumbersUsable($serialNumbers);

        return DB::transaction(function () use ($pusat, $item, $serialNumbers, $unitPrice, $actor, $notes, $referenceNumber) {
            $serials = [];

            foreach ($serialNumbers as $serialNumber) {
                $serial = InventorySerial::create([
                    'item_id' => $item->id,
                    'serial_number' => $serialNumber,
                    'status' => SerialStatus::AVAILABLE,
                    'current_pop_id' => $pusat->id,
                ]);

                InventoryTransaction::create([
                    'type' => InventoryTransactionType::RECEIVE,
                    'reference_number' => $referenceNumber,
                    'item_id' => $item->id,
                    'serial_id' => $serial->id,
                    'qty' => 1,
                    'unit_price_snapshot' => $unitPrice,
                    'to_pop_id' => $pusat->id,
                    'notes' => $notes,
                    'created_by' => $actor?->id,
                ]);

                $serials[] = $serial;
            }

            return $serials;
        });
    }

    /**
     * Satu event Barang Masuk bisa berisi banyak item sekaligus (mis. 100 SN
     * modem ZTE + 500m kabel dalam satu faktur/surat jalan) — dibungkus SATU
     * `reference_number` (RCV-...) biar bisa direview lagi sebagai satu bon,
     * pola sama `InventoryIssueService::issue()`. `receiveQuantity()`/
     * `receiveSerialized()` di atas TETAP dipertahankan sebagai primitif
     * per-item (dipakai langsung oleh test existing) — method ini cuma
     * orkestrasi tambahan, bukan pengganti.
     *
     * @param  list<array{item_id:int, qty?:float, lot_no?:?string, serial_numbers?:list<string>, unit_price:float}>  $lines
     * @return string reference_number buat halaman show()
     */
    public function receiveBatch(Pop $pusat, array $lines, User $actor, ?string $notes = null): string
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Barang masuk wajib py minimal 1 baris barang.');
        }

        // Urutkan per item_id SEBELUM lock diambil (bukan urutan input form
        // apa adanya) — dua batch yang nyebut 2 item sama tapi urutan
        // kebalik bisa saling nunggu lock kalau urutan lock beda-beda per
        // transaction (deadlock DB), ketauan audit 2026-09-02. Sama
        // diterapkan di `InventoryTransferService::createTransfer()`/
        // `InventoryIssueService::issue()`.
        usort($lines, fn ($a, $b) => $a['item_id'] <=> $b['item_id']);

        return DB::transaction(function () use ($pusat, $lines, $actor, $notes) {
            $referenceNumber = $this->generateReferenceNumber();

            foreach ($lines as $line) {
                $item = Item::findOrFail($line['item_id']);

                if (isset($line['serial_numbers'])) {
                    $this->receiveSerialized($pusat, $item, $line['serial_numbers'], (float) $line['unit_price'], $actor, $notes, $referenceNumber);
                } else {
                    $this->receiveQuantity($pusat, $item, (float) ($line['qty'] ?? 0), (float) $line['unit_price'], $line['lot_no'] ?? null, $actor, $notes, $referenceNumber);
                }
            }

            return $referenceNumber;
        });
    }

    /**
     * `RCV-YYYYMMDD-NNNNNN` — stempel tanggal PENUH (buat dibaca manusia:
     * "kapan persisnya"), tapi lingkup reset counter-nya per BULAN (`YYYYMM`),
     * BUKAN per hari — nomor terus nambah tiap hari dalam bulan yang sama,
     * baru balik ke 000001 begitu bulan berganti (keputusan eksplisit user,
     * 2026-09-03). LIKE `RCV-{yearMonth}%` sengaja gak py hyphen penutup —
     * "202609" adalah prefix literal dari "20260903...", jadi otomatis
     * nyakup semua tanggal di bulan itu tanpa perlu date range query.
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransaction::where('reference_number', 'like', "RCV-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('RCV-%s-%06d', $today, $lastNum + 1);
    }

    private function assertPusat(Pop $pop): void
    {
        if (! $pop->isPusat()) {
            throw new InvalidArgumentException(
                "RECEIVE cuma boleh di Gudang Pusat — {$pop->name} bertipe '{$pop->type}'. Cabang terima barang lewat Transfer."
            );
        }
    }

    private function assertPositiveQty(float $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty yang diterima harus lebih besar dari nol.');
        }
    }

    private function assertPositivePrice(float $unitPrice): void
    {
        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('Harga satuan wajib diisi dan lebih besar dari nol — ini titik last-cost yang dipakai ulang di ISSUE.');
        }
    }

    /**
     * Dua kelas masalah, dua pesan beda — biar user tau PERSIS mana yang
     * perlu dibenerin (SN ketik dobel di textarea vs SN yang emang udah
     * ada), bukan satu pesan generik "gagal simpan".
     *
     * @param  list<string>  $serialNumbers
     */
    private function assertSerialNumbersUsable(array $serialNumbers): void
    {
        $seenLower = [];
        $duplicates = [];

        foreach ($serialNumbers as $serialNumber) {
            $key = mb_strtolower(trim($serialNumber));

            if (isset($seenLower[$key])) {
                $duplicates[$serialNumber] = true;
            }

            $seenLower[$key] = true;
        }

        if ($duplicates !== []) {
            throw new InvalidArgumentException(
                'SN dobel dalam satu submit ini (gak boleh SN yang sama muncul 2x): '.implode(', ', array_keys($duplicates)).'.'
            );
        }

        $existing = InventorySerial::whereIn('serial_number', $serialNumbers)->pluck('serial_number');

        if ($existing->isNotEmpty()) {
            throw new InvalidArgumentException(
                'SN berikut sudah terdaftar di sistem (cek salah ketik, atau SN ini udah pernah di-input sebelumnya): '.$existing->implode(', ').'.'
            );
        }
    }

    private function assertQuantityOrBatchTracking(Item $item): void
    {
        if ($item->tracking_type === TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Item {$item->name} SERIALIZED — pakai receiveSerialized(), bukan receiveQuantity().");
        }
    }

    /**
     * BATCH wajib py lot_no (drum/roll), QUANTITY biasa wajib KOSONG (sentinel
     * string kosong — lihat komentar unique constraint di migration
     * `create_inventory_balances_table`, kenapa bukan null).
     */
    private function normalizeLotNo(Item $item, ?string $lotNo): string
    {
        if ($item->tracking_type === TrackingType::BATCH) {
            if (blank($lotNo)) {
                throw new InvalidArgumentException("Item {$item->name} tracking_type BATCH — lot_no wajib diisi (nomor drum/roll).");
            }

            return $lotNo;
        }

        if (filled($lotNo)) {
            throw new InvalidArgumentException("Item {$item->name} bukan BATCH — lot_no harus kosong.");
        }

        return '';
    }
}
