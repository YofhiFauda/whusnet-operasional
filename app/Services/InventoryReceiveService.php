<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RECEIVE — barang masuk dari distributor ke Gudang Pusat. SATU-SATUNYA titik
 * masuk barang baru ke sistem (Cabang gak pernah RECEIVE langsung dari
 * distributor, cuma lewat Transfer — lihat InventoryTransferService).
 *
 * Harga (`unit_price_snapshot`) WAJIB diisi di sini — ini titik "last-cost"
 * yang nanti dibaca ulang `InventoryIssueService` buat nyalin harga ke custody
 * teknisi (§16.4/§29.8 doc analisa, §3.5 rancangan-ui.md).
 */
class InventoryReceiveService
{
    /**
     * Terima barang QUANTITY (RJ45, splitter, connector, kabel per drum
     * non-roll). Gak ada parameter lot_no — staf gak pernah isi lot manual
     * (ADHOC-75, 2026-09-16). Sistem otomatis nentuin lot lewat
     * `resolveQuantityLot()`: maksimal 2 lot aktif per (gudang, barang) —
     * satu per harga (Lama/Baru), niru pola pembukuan real admin gudang
     * (`docs/plan/warehouse/analisa-2-slot-harga-quantity.md`).
     */
    public function receiveQuantity(
        Pop $pusat,
        Item $item,
        float $qty,
        float $unitPrice,
        ?User $actor = null,
        ?string $notes = null,
        ?string $referenceNumber = null,
    ): InventoryTransaction {
        $this->assertPusat($pusat);
        $this->assertPositiveQty($qty);
        $this->assertPositivePrice($unitPrice);
        $this->assertQuantityTracking($item);

        return DB::transaction(function () use ($pusat, $item, $qty, $unitPrice, $actor, $notes, $referenceNumber) {
            $lotNo = $this->resolveQuantityLot($pusat, $item, $unitPrice);

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
     * `$item->auto_generate_serial=true` (ODP, Splitter — gak punya SN
     * vendor) WAJIB manggil ini lewat `receiveSerializedAuto()`, bukan di
     * sini langsung — guard di bawah nolak biar gak ketuker sumbernya diam-
     * diam. Lihat docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
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

        if ($item->auto_generate_serial) {
            throw new InvalidArgumentException("Item {$item->name} SN-nya digenerate sistem — pakai receiveSerializedAuto(), bukan kirim daftar SN manual.");
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
        // Form manual multi-baris textarea (`store()`/`normalizeLines()`)
        // gak lewat validasi terstruktur — guard di sini nutupnya di
        // Service (bukan diulang tiap controller pemanggil).
        $this->assertSerialNumbersUsable($serialNumbers);

        return DB::transaction(function () use ($pusat, $item, $serialNumbers, $unitPrice, $actor, $notes, $referenceNumber) {
            $serials = [];

            foreach ($serialNumbers as $serialNumber) {
                $serial = InventorySerial::create([
                    'item_id' => $item->id,
                    'serial_number' => $serialNumber,
                    'status' => SerialStatus::AVAILABLE,
                    'condition' => ItemCondition::NEW,
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
     * Terima barang SERIALIZED yang GAK punya SN vendor (ODP, Splitter) —
     * `$item->auto_generate_serial=true`. Beda dari `receiveSerialized()`:
     * input-nya cuma "berapa unit" (bukan daftar SN), ID digenerate sistem
     * lewat `generateSerialCode()` (pola sama `generateRollCode()`), biar
     * tiap unit tetap ketrace individual + bisa dicetak barcode-nya kayak
     * roll kabel. Lihat
     * docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
     *
     * @return list<InventorySerial>
     */
    public function receiveSerializedAuto(
        Pop $pusat,
        Item $item,
        int $count,
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

        if (! $item->auto_generate_serial) {
            throw new InvalidArgumentException("Item {$item->name} SN-nya manual — pakai receiveSerialized(), bukan receiveSerializedAuto().");
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Jumlah unit harus minimal 1.');
        }

        return DB::transaction(function () use ($pusat, $item, $count, $unitPrice, $actor, $notes, $referenceNumber) {
            $serials = [];

            for ($i = 0; $i < $count; $i++) {
                $serial = InventorySerial::create([
                    'item_id' => $item->id,
                    'serial_number' => $this->generateSerialCode($item),
                    'status' => SerialStatus::AVAILABLE,
                    'condition' => ItemCondition::NEW,
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
     * Terima kabel per-roll (App\Enums\TrackingType::ROLL) — beda dari
     * `receiveSerialized()`: ID roll BUKAN dari vendor, digenerate sistem di
     * sini (`generateRollCode()`), jadi input-nya cuma "berapa roll" +
     * vendor + harga, bukan daftar ID. Tiap roll = 1 `InventoryRoll` +
     * 1 baris ledger (qty = panjang roll, snapshot `item->meter_per_roll`
     * saat ini — bukan dibaca ulang belakangan).
     *
     * @return list<InventoryRoll>
     */
    public function receiveRoll(
        Pop $pusat,
        Item $item,
        int $rollCount,
        ?string $vendor,
        float $unitPrice,
        ?User $actor = null,
        ?string $notes = null,
        ?string $referenceNumber = null,
    ): array {
        $this->assertPusat($pusat);
        $this->assertPositivePrice($unitPrice);

        if ($item->tracking_type !== TrackingType::ROLL) {
            throw new InvalidArgumentException("Item {$item->name} bukan tracking_type ROLL.");
        }

        if ($rollCount < 1) {
            throw new InvalidArgumentException('Jumlah roll harus minimal 1.');
        }

        $meterPerRoll = (float) $item->meter_per_roll;
        if ($meterPerRoll <= 0) {
            throw new InvalidArgumentException("Item {$item->name} belum py meter_per_roll di Master Barang — isi dulu sebelum Receive.");
        }

        // `$unitPrice` yang diinput staf itu HARGA BELI PER ROLL (mis.
        // Rp 777.000/roll @ 1.000 meter) — tapi `unit_price_snapshot` di
        // seluruh sistem (InventoryRoll, tiap InventoryTransaction turunan,
        // TaskMaterial pas consumeFromRoll()) SELALU dipasangkan sama qty
        // BER-SATUAN METER (bukan roll). Simpan mentah harga-per-roll ke
        // kolom yang dikali qty-meter bikin nilai kekali `meterPerRoll`
        // (di kasus 1.000 meter/roll, 1000x lipat) di SETIAP kalkulasi nilai
        // hilir — custody, nilai rugi Adjustment, Laporan Bulanan, Invoice.
        // Dikonversi ke harga-per-meter DI SINI, satu-satunya titik masuk
        // roll ke ledger, biar seluruh rantai hilir otomatis benar tanpa
        // perlu tau soal konversi roll↔meter sama sekali.
        $pricePerMeter = $unitPrice / $meterPerRoll;

        return DB::transaction(function () use ($pusat, $item, $rollCount, $vendor, $pricePerMeter, $meterPerRoll, $actor, $notes, $referenceNumber) {
            $rolls = [];

            for ($i = 0; $i < $rollCount; $i++) {
                $roll = InventoryRoll::create([
                    'item_id' => $item->id,
                    'roll_code' => $this->generateRollCode($item),
                    'vendor' => $vendor,
                    'length_total' => $meterPerRoll,
                    'length_remaining' => $meterPerRoll,
                    'unit_price_snapshot' => $pricePerMeter,
                    'status' => RollStatus::AVAILABLE,
                    'current_pop_id' => $pusat->id,
                    'received_at' => now(),
                ]);

                InventoryTransaction::create([
                    'type' => InventoryTransactionType::RECEIVE,
                    'reference_number' => $referenceNumber,
                    'item_id' => $item->id,
                    'roll_id' => $roll->id,
                    'qty' => $meterPerRoll,
                    'unit_price_snapshot' => $pricePerMeter,
                    'to_pop_id' => $pusat->id,
                    'notes' => $notes,
                    'created_by' => $actor?->id,
                ]);

                $rolls[] = $roll;
            }

            return $rolls;
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
     * @param  list<array{item_id:int, qty?:float, serial_numbers?:list<string>, serial_count?:int, roll_count?:int, vendor?:?string, unit_price:float}>  $lines
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
                } elseif (isset($line['serial_count'])) {
                    $this->receiveSerializedAuto($pusat, $item, (int) $line['serial_count'], (float) $line['unit_price'], $actor, $notes, $referenceNumber);
                } elseif (isset($line['roll_count'])) {
                    $this->receiveRoll($pusat, $item, (int) $line['roll_count'], $line['vendor'] ?? null, (float) $line['unit_price'], $actor, $notes, $referenceNumber);
                } else {
                    $this->receiveQuantity($pusat, $item, (float) ($line['qty'] ?? 0), (float) $line['unit_price'], $actor, $notes, $referenceNumber);
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

    /**
     * `{item.code}-{YYYYMMDD}-{6 digit}` — prefix = kode barang (identitas
     * jenis kabelnya), tanggal penuh buat dibaca manusia, counter per BULAN
     * per prefix (sama pola `generateReferenceNumber()` di atas, keputusan
     * 2026-09-03 dipakai ulang di sini biar konsisten satu modul). Race
     * kondisi MAX+1 gak locked — risiko diterima SENGAJA sama seperti nomor
     * referensi lain di seluruh Gudang (unique constraint `roll_code` jadi
     * jaring pengaman terakhir).
     */
    private function generateRollCode(Item $item): string
    {
        $prefix = $item->code;
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryRoll::where('roll_code', 'like', "{$prefix}-{$yearMonth}%")
            ->pluck('roll_code')
            ->map(fn ($code) => (int) substr($code, strrpos($code, '-') + 1))
            ->max() ?? 0;

        return sprintf('%s-%s-%06d', $prefix, $today, $lastNum + 1);
    }

    /**
     * `{item.code}-{YYYYMMDD}-{6 digit}` — pola sama persis `generateRollCode()`
     * di bawah, counter per bulan per prefix TAPI dicek ke `inventory_serials`
     * (bukan `inventory_rolls`). Race MAX+1 gak locked, sama alasan
     * `generateRollCode()` — unique constraint `serial_number` jaring
     * pengaman terakhir.
     */
    private function generateSerialCode(Item $item): string
    {
        $prefix = $item->code;
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventorySerial::where('serial_number', 'like', "{$prefix}-{$yearMonth}%")
            ->pluck('serial_number')
            ->map(fn ($code) => (int) substr($code, strrpos($code, '-') + 1))
            ->max() ?? 0;

        return sprintf('%s-%s-%06d', $prefix, $today, $lastNum + 1);
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

    private function assertQuantityTracking(Item $item): void
    {
        if ($item->tracking_type === TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Item {$item->name} SERIALIZED — pakai receiveSerialized(), bukan receiveQuantity().");
        }

        if ($item->tracking_type === TrackingType::ROLL) {
            throw new InvalidArgumentException("Item {$item->name} ROLL — pakai receiveRoll(), bukan receiveQuantity().");
        }
    }

    /**
     * Inti ADHOC-75 (2026-09-16) — auto-tentuin `lot_no` buat RECEIVE
     * QUANTITY, staf gak pernah isi manual. Maksimal 2 lot AKTIF (qty>0) per
     * (gudang, barang) sekaligus, niru persis pola pembukuan real admin
     * gudang: satu lot "Harga Lama", satu lot "Harga Baru" — bukan genealogy
     * lot tak terbatas ala BATCH lama (lihat
     * docs/plan/warehouse/analisa-2-slot-harga-quantity.md §3-4).
     *
     * - 0 lot aktif → lot sentinel `''` (barang pertama kali diterima —
     *   TIDAK generate kode, sengaja SAMA PERSIS perilaku lama, supaya
     *   mayoritas barang berharga stabil — kaos, mug, baut — gak pernah
     *   kelihatan py "kode lot" sama sekali, cuma numpuk 1 baris polos kayak
     *   sebelum ADHOC-75).
     * - 1-2 lot aktif, salah satunya harganya SAMA persis `$unitPrice` → nimbun
     *   ke lot itu (bukan bikin lot baru cuma gara-gara kedatangan ke-2/3
     *   kalau harganya kebetulan gak berubah).
     * - 1 lot aktif, harga beda → lot BARU digenerate (lot lama — entah `''`
     *   atau kode — otomatis jadi "Harga Lama", lot baru ini jadi "Harga
     *   Baru" — TANPA mindahin data, dua baris `inventory_balances` ini emang
     *   udah kepisah sejak awal).
     * - 2 lot aktif, harga beda dari KEDUANYA → DITOLAK. Kedatangan harga
     *   ke-3 sebelum salah satu slot habis belum pernah terjadi di data real
     *   (dikonfirmasi user 2026-09-16) — sengaja belum didukung, daripada
     *   diam-diam salah hitung. Habiskan salah satu lot (Issue/Adjustment)
     *   dulu sebelum RECEIVE harga baru lagi.
     */
    private function resolveQuantityLot(Pop $pusat, Item $item, float $unitPrice): string
    {
        $activeLots = InventoryBalance::query()
            ->where('pop_id', $pusat->id)
            ->where('item_id', $item->id)
            ->where('qty', '>', 0)
            ->orderBy('id')
            ->pluck('lot_no');

        if ($activeLots->isEmpty()) {
            return '';
        }

        foreach ($activeLots as $lotNo) {
            if ($this->priceForLot($item, $lotNo) === $unitPrice) {
                return $lotNo;
            }
        }

        if ($activeLots->count() >= 2) {
            $hargaAktif = $activeLots->map(fn ($lotNo) => $this->priceForLot($item, $lotNo))->filter()->implode(', ');

            throw new InvalidArgumentException(
                "Item {$item->name} sudah punya 2 harga aktif di {$pusat->name} ({$hargaAktif}) — sistem belum mendukung harga ke-3 sebelum salah satu lot habis. Habiskan salah satu lot (Issue/Transfer/Adjustment) dulu sebelum menerima harga baru."
            );
        }

        return $this->generateQuantityLotCode($item);
    }

    /**
     * Harga sebuah lot = harga RECEIVE TERAKHIR ke lot itu (last-cost per
     * lot, pola sama `resolveLastCost()` di Issue/Transfer Service).
     * `inventory_transactions.lot_no` NULL buat lot sentinel `''`
     * (`inventory_balances.lot_no` NOT NULL default '' — lihat komentar
     * migration `create_inventory_balances_table` kenapa beda dari ledger).
     */
    private function priceForLot(Item $item, string $lotNo): ?float
    {
        $price = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->where('lot_no', $lotNo === '' ? null : $lotNo)
            ->latest('id')
            ->value('unit_price_snapshot');

        return $price !== null ? (float) $price : null;
    }

    /**
     * `{item.code}-{YYYYMMDD}-{6 digit}` — pola sama persis
     * `generateRollCode()` di bawah (konsisten satu modul), scoped ke barang
     * (bukan gudang) karena lot QUANTITY bisa lintas-pop lewat Transfer.
     */
    private function generateQuantityLotCode(Item $item): string
    {
        $prefix = $item->code;
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryBalance::where('item_id', $item->id)
            ->where('lot_no', 'like', "{$prefix}-{$yearMonth}%")
            ->pluck('lot_no')
            ->map(fn ($code) => (int) substr($code, strrpos($code, '-') + 1))
            ->max() ?? 0;

        return sprintf('%s-%s-%06d', $prefix, $today, $lastNum + 1);
    }
}
