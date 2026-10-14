<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\DeviceAcquisitionSource;
use App\Enums\DeviceOwnership;
use App\Enums\DeviceRetrievalSource;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\MaterialKind;
use App\Enums\OwnershipMode;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Exceptions\InsufficientCustodyException;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\DeviceRetrievalLog;
use App\Models\FopTask;
use App\Models\InfrastructureBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\TaskMaterial;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Konsumsi material dari custody teknisi saat Laporan Pemasangan/Maintenance/
 * INFR/O-REQ disubmit — TIDAK mengurangi stok gudang lagi (udah kepotong pas
 * ISSUE, lihat `InventoryIssueService`). Yang berkurang di sini CUMA custody
 * teknisi (§3.7 rancangan-ui.md — dua neraca beda, jangan disamain).
 *
 * `consumeFromCustody()` khusus item QUANTITY/BATCH. Barang SERIALIZED
 * (modem/ONT dipasang ke pelanggan) lewat `installSerial()` di bawah.
 */
class InventoryService
{
    /**
     * Structural constraint (§7 kontrol-anti-manipulasi.md) — teknisi CUMA
     * bisa klaim sejumlah yang ADA di custody-nya, ditegakkan sistem (lempar
     * `InsufficientCustodyException`), bukan sekadar dicurigai belakangan.
     *
     * FIFO diurutkan `issued_at` ASC — bukan `lot_no` ASC kayak draf awal:
     * `lot_no` cuma label drum, `issued_at` yang beneran mencerminkan "custody
     * mana yang diambil duluan" (berlaku juga buat item QUANTITY biasa yang
     * `lot_no`-nya selalu null — urut by lot_no gak ada gunanya buat itu).
     *
     * Kalau potongan jatuh di lebih dari satu baris custody (beda lot/waktu
     * ambil), `task_materials` jadi BEBERAPA baris — satu per baris custody
     * yang kepotong, masing-masing bawa `unit_price_snapshot`-nya SENDIRI
     * (disalin dari custody, BUKAN diquery ulang — §3.5 rancangan-ui.md).
     *
     * `$technicians` — SATU TASK BISA PUNYA BEBERAPA ANGGOTA TIM (keputusan
     * user): FIFO jalan LINTAS custody SEMUA anggota, diurut `issued_at`
     * global (bukan per-orang dulu baru gabung) — barang bisa diambil siapa
     * aja di tim, siapa pun boleh submit laporan, jadi gak ada alasan
     * membatasi ke satu teknisi spesifik.
     *
     * @param  iterable<User>  $technicians
     * @return list<array{custody_id:int, lot_no:?string, qty:float}>
     */
    public function consumeFromCustody(
        iterable $technicians,
        Item $item,
        float $qtyUsed,
        FopTask $fopTask,
        ?Customer $customer,
        User $actor,
    ): array {
        if ($qtyUsed <= 0) {
            throw new InvalidArgumentException('Qty terpakai harus lebih besar dari nol.');
        }

        if ($item->tracking_type === TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Item {$item->name} SERIALIZED — pakai jalur instalasi SN, bukan consumeFromCustody().");
        }

        // Cermin guard di deployQuantity() (yang nolak non-infrastruktur):
        // barang infrastruktur yang "terpakai ke pelanggan" bikin saldo
        // titik jaringan (InfrastructureBalance) gak pernah tercatat — stok
        // fisik terpasang di tiang hilang dari jejak lokasi.
        if ($item->ownership_mode === OwnershipMode::INFRASTRUCTURE_ASSET) {
            throw new InvalidArgumentException("Item {$item->name} aset infrastruktur (ownership_mode) — pakai jalur deployQuantity(), bukan consumeFromCustody().");
        }

        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        if ($technicianIds === []) {
            throw new InvalidArgumentException('Daftar teknisi (anggota tim) tidak boleh kosong.');
        }

        return DB::transaction(function () use ($technicianIds, $item, $qtyUsed, $fopTask, $customer, $actor) {
            // Barang yang diissue dari Cabang A gak boleh dipasang/dipakai di
            // Task Cabang B (laporan user: modem Jetis kepakai di Sandya) —
            // lot custody dari cabang lain SENGAJA di-skip dari FIFO di sini
            // (bukan dilempar begitu nemu baris pertama), bukan dianggap
            // "tidak ada" — kalau total custody cabang yang cocok gak cukup,
            // `InsufficientCustodyException` di bawah yang jalan, pesannya
            // sama seperti kasus custody beneran kurang. `issued_from_pop_id`
            // NULL (data legacy sebelum kolom ini ada) tetap lolos — fail-open,
            // gak retroaktif ngeblokir data lama yang gak kebaca cabangnya.
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);

            $lots = TechnicianCustody::query()
                ->whereIn('technician_id', $technicianIds)
                ->where('item_id', $item->id)
                ->where('qty_remaining', '>', 0)
                ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                    $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
                }))
                ->orderBy('issued_at')
                ->lockForUpdate()
                ->get();

            $remaining = $qtyUsed;
            $consumed = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min((float) $lot->qty_remaining, $remaining);
                $newRemaining = (float) $lot->qty_remaining - $take;

                $lot->qty_remaining = $newRemaining;
                $lot->status = $newRemaining <= 0 ? CustodyStatus::CONSUMED : CustodyStatus::PARTIALLY_USED;
                $lot->save();

                TaskMaterial::create([
                    'fop_task_id' => $fopTask->id,
                    'customer_id' => $customer?->id,
                    'kind' => MaterialKind::TERPAKAI,
                    'item_id' => $item->id,
                    'item_category_id' => $item->item_category_id,
                    'item_type' => $item->category?->code,
                    'item_name' => $item->name,
                    'lot_no' => $lot->lot_no,
                    'qty' => $take,
                    'unit' => $item->unit,
                    'unit_price_snapshot' => $lot->unit_price_snapshot,
                    'recorded_by' => $actor->id,
                ]);

                $consumed[] = [
                    'custody_id' => $lot->id,
                    'lot_no' => $lot->lot_no,
                    'qty' => $take,
                ];

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InsufficientCustodyException($technicianIds, $item, $qtyUsed, $qtyUsed - $remaining);
            }

            return $consumed;
        });
    }

    /**
     * Unit SERIALIZED terpasang ke pelanggan — dipanggil dari `storeSpeedtest()`
     * (titik penyelesaian tunggal, sama alasan `consumeFromCustody()` gak
     * boleh di `storePemasangan()` — lihat komentar di titik pemanggilannya).
     *
     * Guard `OwnershipMode::COMPANY_ASSET` DITEGAKKAN DI SINI (§16.2 doc
     * advanced) — bukan cuma predikat `InventorySerial::isEligibleForInstall()`
     * yang bisa diabaikan pemanggilnya, method ini yang jadi satu-satunya
     * pintu transisi ke `SerialStatus::INSTALLED`.
     */
    /**
     * `$returnExistingSerial` (ADHOC-108, koreksi 2026-10-08) — default
     * `true` (perilaku lama: GANTI modem, SN lain yang `INSTALLED` milik
     * pelanggan ini otomatis diretur). Set `false` buat kategori C-REQ
     * `TAMBAH_MODEM` — pelanggan beneran NAMBAH (lebih dari satu modem
     * aktif bersamaan, mis. repeater/ONT kedua), modem lama TIDAK BOLEH
     * ketarik. Caller (`TaskMaintenanceController`) yang nentuin — method
     * ini gak bisa nebak sendiri dari task_type/kategori.
     *
     * `$ownership` (rancangan ownership modem sewa/beli, 2026-10-09) — default
     * `COMPANY` (Sewa, perilaku lama). `CUSTOMER` dipakai kalau SN ini dijual
     * ke pelanggan (Beli dari Gudang) — caller yang bertanggung jawab juga
     * membuat baris `InventorySerialSale` lewat `recordSerialSale()`, method
     * ini CUMA menulis flag `ownership`/`acquisition_source`, bukan sale-nya.
     */
    public function installSerial(
        InventorySerial $serial,
        Customer $customer,
        FopTask $fopTask,
        iterable $technicians,
        User $actor,
        bool $returnExistingSerial = true,
        DeviceOwnership $ownership = DeviceOwnership::COMPANY,
    ): InventoryTransaction {
        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        return DB::transaction(function () use ($serial, $customer, $fopTask, $actor, $technicianIds, $returnExistingSerial, $ownership) {
            // Re-fetch + lockForUpdate() DI DALAM transaction — semua guard
            // di bawah dicek dari kopi yang di-lock, bukan $serial parameter
            // yang bisa stale. Dua submit "selesaikan instalasi" bersamaan
            // buat SN yang sama sebelumnya bisa dua-duanya lolos cek ISSUED
            // lalu dua-duanya nulis INSTALL, satu nimpa customer_id/fop_task_id
            // punya yang lain diam-diam (ketauan audit 2026-09-02).
            $serial = InventorySerial::query()->with('item')->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::ISSUED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->value}', bukan ISSUED — gak bisa diinstall.");
            }

            if (! in_array($serial->current_technician_id, $technicianIds, true)) {
                throw new InvalidArgumentException("SN {$serial->serial_number} bukan custody tim ini.");
            }

            if ($serial->item?->ownership_mode !== OwnershipMode::INSTALLABLE) {
                throw new InvalidArgumentException("Item {$serial->item?->name} berstatus aset perusahaan (company_asset) — gak boleh diinstall ke pelanggan.");
            }

            // Barang yang diissue dari Cabang A gak boleh dipasang di Task
            // Cabang B (laporan user: modem Jetis kepakai di Sandya).
            // `issued_from_pop_id`/`$fopTask->pop_id` NULL (data legacy) tetap
            // lolos — fail-open, gak retroaktif ngeblokir data lama.
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);
            if ($targetCabangId !== null && $serial->issued_from_pop_id !== null && $serial->issued_from_pop_id !== $targetCabangId) {
                throw new InvalidArgumentException("SN {$serial->serial_number} berasal dari cabang lain — gak bisa diinstall untuk task di cabang ini.");
            }

            $fromTechnicianId = $serial->current_technician_id;

            // Transisikan modem lama jika pelanggan sebelumnya sudah memiliki
            // SN lain yang INSTALLED — CUMA kalau ini GANTI (`$returnExistingSerial`).
            // Kategori TAMBAH_MODEM lewat sini dengan `false`: modem lama
            // dibiarkan tetap INSTALLED, pelanggan punya >1 modem aktif.
            $oldSerials = $returnExistingSerial
                ? InventorySerial::query()
                    ->where('customer_id', $customer->id)
                    ->where('id', '!=', $serial->id)
                    ->where('status', SerialStatus::INSTALLED->value)
                    // Modem milik pelanggan (Beli dari Gudang) BUKAN hak perusahaan
                    // untuk ditarik lewat tukar modem yang tak berhubungan — kalau
                    // ikut diretur, SN-nya balik ke stok padahal `InventorySerialSale`
                    // masih mencatatnya terjual ke pelanggan ini.
                    ->where('ownership', '!=', DeviceOwnership::CUSTOMER->value)
                    ->lockForUpdate()
                    ->get()
                : collect();

            foreach ($oldSerials as $oldSerial) {
                $oldSerial->update([
                    'status' => SerialStatus::RETURNED->value,
                    'current_technician_id' => $fromTechnicianId,
                    'current_pop_id' => null,
                    'customer_id' => null,
                ]);

                // Transit dulu — BUKAN langsung ke_pop_id (C1, ADHOC-108). Dulu
                // baris ini nulis `to_pop_id` = gudang asal, jadi langsung
                // keitung stok gudang (`WarehouseStockAsOfService`) PADAHAL
                // fisik modem masih di tangan teknisi, belum dicek, belum ada
                // konfirmasi gudang — beda sama jalur DEAC yang bener. Samakan:
                // `to_technician_id`, stok baru nambah setelah lewat Terima
                // Retur → Kirim ke Pusat → Pusat konfirmasi (lihat
                // InventoryReassignService::confirmReturnedSerial() dan
                // InventoryReturnTransferService).
                InventoryTransaction::create([
                    'type' => InventoryTransactionType::RETURN,
                    'reference_number' => $this->generateReferenceNumber('RET'),
                    'item_id' => $oldSerial->item_id,
                    'serial_id' => $oldSerial->id,
                    'qty' => 1,
                    'from_technician_id' => $fromTechnicianId,
                    'to_technician_id' => $fromTechnicianId,
                    'fop_task_id' => $fopTask->id,
                    'reason' => "Modem dicabut saat pekerjaan {$fopTask->task_number} (digantikan oleh SN {$serial->serial_number})",
                    'notes' => "Pelanggan: {$customer->display_id} - {$customer->full_name}",
                    'created_by' => $actor->id,
                ]);

                // Jejak per SN (ADHOC-108) — supaya "modem ini dari pelanggan
                // siapa, kapan dicabut, siapa yang pegang" tetap terlacak
                // sampai diterima gudang, sama seperti jalur DEAC (ADHOC-88).
                // Tanpa ini, modem hasil Ganti Modem/Migrasi hilang jejak
                // begitu customer_id dikosongkan di atas.
                DeviceRetrievalLog::create([
                    'customer_id' => $customer->id,
                    'serial_id' => $oldSerial->id,
                    'serial_number' => $oldSerial->serial_number,
                    'item_id' => $oldSerial->item_id,
                    'source' => DeviceRetrievalSource::CREQ_SWAP,
                    'task_id' => $fopTask->task_id,
                    'retrieved_by' => $actor->id,
                    'condition' => ItemCondition::USED_GOOD,
                    'retrieved_at' => now(),
                ]);
            }

            $serial->update([
                'status' => SerialStatus::INSTALLED,
                'customer_id' => $customer->id,
                'fop_task_id' => $fopTask->id,
                'installed_at' => now(),
                'current_technician_id' => null,
                'ownership' => $ownership,
            ]);

            // Sinkronisasi data perangkat ke Master Pelanggan — CUMA kalau SN
            // ini jadi SATU-SATUNYA/UTAMA (`$returnExistingSerial`). Kalau
            // TAMBAH_MODEM (`false`), `customer_devices` TETAP nunjuk ke
            // modem pertama/utama — tabel ini cuma 1 baris per pelanggan
            // (`hasOne`), gak ada tempat buat nyimpen detail modem kedua.
            // Modem tambahan tetap tertrack benar di `inventory_serials`
            // (status INSTALLED, customer_id terisi) — cukup buat inventory,
            // cuma gak muncul sebagai "device utama" di Detail Pelanggan.
            if ($returnExistingSerial) {
                $customer->update(['ont_sn' => $serial->serial_number]);

                if ($customer->customerTechnicalDetail) {
                    $customer->customerTechnicalDetail->update(['router_or_ont_serial' => $serial->serial_number]);
                }

                $brand = $serial->item?->brand ?: ($customer->customerDevice?->brand ?: 'ZTE');
                $model = $serial->item?->model ?: ($customer->customerDevice?->model ?: $serial->item?->name);

                $customer->customerDevice()->updateOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'device_type' => 'ONT',
                        'brand' => $brand,
                        'model' => $model,
                        'serial_number' => $serial->serial_number,
                        'mac_address' => $serial->mac_address ?: $customer->customerDevice?->mac_address,
                        'device_retrieved_at' => null,
                        'acquisition_source' => $ownership === DeviceOwnership::CUSTOMER
                            ? DeviceAcquisitionSource::BELI_GUDANG
                            : DeviceAcquisitionSource::SEWA,
                    ]
                );
            }

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::INSTALL,
                'reference_number' => $this->generateReferenceNumber('INS'),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'from_technician_id' => $fromTechnicianId,
                'fop_task_id' => $fopTask->id,
                'notes' => "Pemasangan SN {$serial->serial_number} pada Task {$fopTask->task_number} untuk Pelanggan: {$customer->display_id} - {$customer->full_name}",
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Modem "Beli Sendiri" — pelanggan pakai unit milik sendiri, TIDAK PERNAH
     * lewat gudang (§DeviceOwnership). Tidak ada `InventorySerial` yang
     * disentuh sama sekali, cuma `customer_devices` ditulis manual — pola
     * `updateOrCreate` sama blok existing di `installSerial()` di atas,
     * supaya device lama (hasil Sewa/Beli dari Gudang sebelumnya, kalau
     * pelanggan pernah ganti cara pemilikan) tertimpa, bukan dobel baris.
     *
     * @param  array{brand: string, model: string, serial_number: string}  $deviceAttributes
     */
    public function recordCustomerOwnedDevice(Customer $customer, array $deviceAttributes, User $actor): CustomerDevice
    {
        return $customer->customerDevice()->updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'device_type' => 'ONT',
                'brand' => $deviceAttributes['brand'],
                'model' => $deviceAttributes['model'],
                'serial_number' => $deviceAttributes['serial_number'],
                'acquisition_source' => DeviceAcquisitionSource::BELI_SENDIRI,
                'device_retrieved_at' => null,
            ]
        );
    }

    /**
     * Catat satu baris penjualan SN gudang ke pelanggan (Beli dari Gudang).
     * Method ini CUMA menulis ledger penjualan — flag `ownership` di
     * `inventory_serials` sendiri ditulis `installSerial()` (dua tanggung
     * jawab beda, dipanggil berurutan oleh caller, bukan digabung di sini
     * supaya sale tetap bisa dicatat ulang terpisah dari install kalau
     * dibutuhkan alur lain nanti).
     *
     * `$price` harus > 0 — guard ini NGANGGEP bug pemanggil (form validasi
     * harga wajib sudah lolos sebelum sampai sini), bukan error input biasa,
     * makanya `InvalidArgumentException`, bukan `ValidationException`,
     * sejalan pola guard existing lain di method ini.
     */
    public function recordSerialSale(InventorySerial $serial, Customer $customer, ?FopTask $fopTask, float $price, User $actor): InventorySerialSale
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('Harga jual SN harus lebih besar dari nol.');
        }

        return InventorySerialSale::create([
            'inventory_serial_id' => $serial->id,
            'customer_id' => $customer->id,
            'fop_task_id' => $fopTask?->id,
            'price' => $price,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Generate reference number untuk transaksi ledger (INSTALL/RETURN).
     */
    private function generateReferenceNumber(string $prefix = 'INS'): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransaction::where('reference_number', 'like', "{$prefix}-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('%s-%s-%06d', $prefix, $today, $lastNum + 1);
    }

    /**
     * Potong sebagian meter dari roll kabel (App\Enums\TrackingType::ROLL) —
     * partial, BUKAN atomik seperti `installSerial()`: roll TETAP di custody
     * teknisi (status ISSUED/IN_USE) sampai `length_remaining` habis, cuma
     * transisi ke DEPLETED kalau sisa 0. Sejalan `consumeFromCustody()` soal
     * gak nulis baris ledger — cukup `TaskMaterial` (kind=terpakai,
     * `lot_no`=roll_code, `unit`=meter, `unit_price_snapshot` disalin dari
     * roll — lihat docblock `InventoryRoll`).
     */
    public function consumeFromRoll(
        InventoryRoll $roll,
        float $metersUsed,
        iterable $technicians,
        FopTask $fopTask,
        ?Customer $customer,
        User $actor,
    ): TaskMaterial {
        if ($metersUsed <= 0) {
            throw new InvalidArgumentException('Meter terpakai harus lebih besar dari nol.');
        }

        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        return DB::transaction(function () use ($roll, $metersUsed, $technicianIds, $fopTask, $customer, $actor) {
            $roll = InventoryRoll::query()->with('item')->lockForUpdate()->findOrFail($roll->id);

            if (! in_array($roll->status, [RollStatus::ISSUED, RollStatus::IN_USE], true)) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} statusnya '{$roll->status->value}', bukan ISSUED/IN_USE — gak bisa dipotong.");
            }

            if (! in_array($roll->current_technician_id, $technicianIds, true)) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} bukan custody tim ini.");
            }

            // Cermin guard deployRoll() — roll infrastruktur (feeder) wajib
            // lewat jalur deploy supaya meter terpasang tercatat di titik
            // jaringan, bukan hilang sebagai "terpakai pelanggan".
            if ($roll->item?->ownership_mode === OwnershipMode::INFRASTRUCTURE_ASSET) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} aset infrastruktur (ownership_mode) — pakai jalur deployRoll(), bukan consumeFromRoll().");
            }

            // Sama guard lintas-cabang dengan `installSerial()` — roll yang
            // diissue dari Cabang A gak boleh dipakai di Task Cabang B.
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);
            if ($targetCabangId !== null && $roll->issued_from_pop_id !== null && $roll->issued_from_pop_id !== $targetCabangId) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} berasal dari cabang lain — gak bisa dipakai untuk task di cabang ini.");
            }

            if ($metersUsed > (float) $roll->length_remaining) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} sisa ".$roll->length_remaining." meter, diminta {$metersUsed} meter.");
            }

            $newRemaining = (float) $roll->length_remaining - $metersUsed;

            $roll->update([
                'length_remaining' => $newRemaining,
                'status' => $newRemaining <= 0 ? RollStatus::DEPLETED : RollStatus::IN_USE,
            ]);

            return TaskMaterial::create([
                'fop_task_id' => $fopTask->id,
                'customer_id' => $customer?->id,
                'kind' => MaterialKind::TERPAKAI,
                'item_id' => $roll->item_id,
                'item_category_id' => $roll->item?->item_category_id,
                'item_type' => $roll->item?->category?->code,
                'item_name' => $roll->item?->name,
                'lot_no' => $roll->roll_code,
                'qty' => $metersUsed,
                'unit' => 'meter',
                'unit_price_snapshot' => $roll->unit_price_snapshot,
                'recorded_by' => $actor->id,
            ]);
        });
    }

    /**
     * Unit SERIALIZED dipasang netap di titik infrastruktur jaringan
     * (ODC/ODP/splitter) — kembaran `installSerial()` tapi tujuannya
     * `NetworkNode`, bukan pelanggan. Pola re-fetch + `lockForUpdate()` dan
     * urutan guard SENGAJA sama persis dengan `installSerial()` (alasan
     * race-nya sama: dua submit bersamaan buat SN yang sama).
     *
     * `to_pop_id` di ledger = Cabang node SAAT INI (snapshot), bukan
     * diturunkan belakangan lewat join ke `network_nodes` — node bisa
     * dipindah cabang (`NetworkNodeService::move()`), dan laporan "barang
     * Cabang A dipasang di mana" gak boleh ikut berubah retroaktif.
     *
     * @param  iterable<User>  $technicians
     */
    public function deploySerial(InventorySerial $serial, NetworkNode $node, FopTask $fopTask, iterable $technicians, User $actor): InventoryTransaction
    {
        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        return DB::transaction(function () use ($serial, $node, $fopTask, $technicianIds, $actor) {
            $serial = InventorySerial::query()->with('item')->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::ISSUED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->value}', bukan ISSUED — gak bisa di-deploy.");
            }

            if (! in_array($serial->current_technician_id, $technicianIds, true)) {
                throw new InvalidArgumentException("SN {$serial->serial_number} bukan custody tim ini.");
            }

            // Kebalikan guard `installSerial()`: modem pelanggan (INSTALLABLE)
            // yang ke-deploy ke ODC bakal hilang dari jalur install/retur
            // pelanggan, alat kerja (COMPANY_ASSET) gak pernah boleh netap.
            if ($serial->item?->ownership_mode !== OwnershipMode::INFRASTRUCTURE_ASSET) {
                throw new InvalidArgumentException("Item {$serial->item?->name} bukan aset infrastruktur (ownership_mode) — gak bisa di-deploy ke titik jaringan.");
            }

            // Sama guard lintas-cabang dengan `installSerial()` — SN yang
            // diissue dari Cabang A gak boleh dipasang untuk Task Cabang B.
            // NULL (data legacy) tetap lolos — fail-open.
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);
            if ($targetCabangId !== null && $serial->issued_from_pop_id !== null && $serial->issued_from_pop_id !== $targetCabangId) {
                throw new InvalidArgumentException("SN {$serial->serial_number} berasal dari cabang lain — gak bisa di-deploy untuk task di cabang ini.");
            }

            $node = $this->freshNode($node);
            $this->assertNodeAcceptsSerialItem($serial->item, $node);
            $toPopId = $node->resolvePop()?->id;
            if ($targetCabangId !== null && $toPopId !== $targetCabangId) {
                throw new InvalidArgumentException('Node tujuan berada di cabang berbeda dari task.');
            }
            $fromTechnicianId = $serial->current_technician_id;

            $serial->update([
                'status' => SerialStatus::DEPLOYED,
                'current_network_node_id' => $node->id,
                'current_technician_id' => null,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::DEPLOY,
                'reference_number' => $this->generateReferenceNumber('DPL'),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'from_technician_id' => $fromTechnicianId,
                'to_network_node_id' => $node->id,
                'to_pop_id' => $toPopId,
                'fop_task_id' => $fopTask->id,
                'notes' => "SN {$serial->serial_number} dipasang netap di {$node->breadcrumbLabel()} (Task {$fopTask->task_number}).",
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Registrasi SN aset infrastruktur yang sudah lama berdiri di lapangan
     * (mis. chassis OLT yang sudah netap sejak tahun lalu) tapi belum pernah
     * masuk ledger sama sekali — BEDA SENGAJA dari `deploySerial()`: SN di
     * sini TIDAK pernah lewat RECEIVE/ISSUE, jadi TIDAK ADA custody teknisi
     * atau `FopTask` buat disyaratkan. Satu-satunya jalur yang boleh membuat
     * `InventorySerial` baru langsung berstatus `DEPLOYED` dalam satu
     * transaksi dengan baris ledgernya (`InventoryTransactionType::REGISTER_INFRA`).
     *
     * `reason` WAJIB diisi (alasan registrasi, mis. "opname awal, chassis
     * sudah berdiri sejak 2023") — beda dari `notes` di DEPLOY yang cuma
     * pointer, di sini `reason` itu audit trail utama kenapa SN ini muncul
     * tiba-tiba tanpa riwayat RECEIVE/ISSUE sebelumnya.
     */
    public function registerExistingInfraSerial(string $serialNumber, Item $item, NetworkNode $node, string $reason, User $actor): InventorySerial
    {
        return DB::transaction(function () use ($serialNumber, $item, $node, $reason, $actor) {
            $reason = trim($reason);

            if ($reason === '') {
                throw new InvalidArgumentException('Alasan registrasi wajib diisi.');
            }

            if ($item->ownership_mode !== OwnershipMode::INFRASTRUCTURE_ASSET) {
                throw new InvalidArgumentException("Item {$item->name} bukan aset infrastruktur (ownership_mode) — gak bisa diregistrasi sebagai aset lama.");
            }

            if ($item->tracking_type !== TrackingType::SERIALIZED) {
                throw new InvalidArgumentException("Item {$item->name} bukan tracking_type serialized — gak bisa diregistrasi lewat registerExistingInfraSerial().");
            }

            if (InventorySerial::where('serial_number', $serialNumber)->exists()) {
                throw new InvalidArgumentException("SN {$serialNumber} sudah terdaftar.");
            }

            $node = $this->freshNode($node);
            $this->assertNodeAcceptsSerialItem($item, $node);

            $serial = InventorySerial::create([
                'item_id' => $item->id,
                'serial_number' => $serialNumber,
                'status' => SerialStatus::DEPLOYED,
                'current_network_node_id' => $node->id,
                'current_technician_id' => null,
            ]);

            InventoryTransaction::create([
                'type' => InventoryTransactionType::REGISTER_INFRA,
                'serial_id' => $serial->id,
                'item_id' => $item->id,
                'qty' => 1,
                'to_network_node_id' => $node->id,
                'to_pop_id' => $node->resolvePop()?->id,
                'reason' => $reason,
                'fop_task_id' => null,
                'created_by' => $actor->id,
                'notes' => "SN {$serialNumber} diregistrasi sebagai aset lama di {$node->breadcrumbLabel()}.",
            ]);

            return $serial;
        });
    }

    /**
     * Item QUANTITY/BATCH dipasang netap di titik infrastruktur — FIFO lintas
     * custody tim, SAMA PERSIS dengan `consumeFromCustody()` (urut
     * `issued_at`, lot cabang lain di-skip, `InsufficientCustodyException`
     * kalau total cabang yang cocok gak cukup). Bedanya: bukan nulis
     * `task_materials`, tapi satu baris ledger DEPLOY per lot (bawa
     * `unit_price_snapshot` lot itu sendiri, disalin, bukan diquery ulang)
     * + akumulasi ke SATU baris `infrastructure_balances` per (item, node)
     * dengan `lot_no=''` — lot custody cuma relevan buat harga di ledger,
     * proyeksi stok per node gak perlu dipecah per lot.
     *
     * @param  iterable<User>  $technicians
     * @return list<array{custody_id:int, lot_no:?string, qty:float, transaction_id:int}>
     */
    public function deployQuantity(iterable $technicians, Item $item, float $qtyUsed, NetworkNode $node, FopTask $fopTask, User $actor): array
    {
        if ($qtyUsed <= 0) {
            throw new InvalidArgumentException('Qty yang di-deploy harus lebih besar dari nol.');
        }

        if ($item->tracking_type === TrackingType::SERIALIZED || $item->tracking_type === TrackingType::ROLL) {
            throw new InvalidArgumentException("Item {$item->name} {$item->tracking_type->value} — pakai deploySerial()/deployRoll(), bukan deployQuantity().");
        }

        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        if ($technicianIds === []) {
            throw new InvalidArgumentException('Daftar teknisi (anggota tim) tidak boleh kosong.');
        }

        return DB::transaction(function () use ($technicianIds, $item, $qtyUsed, $node, $fopTask, $actor) {
            // Lot dari cabang lain di-skip dari FIFO (bukan dilempar) — alasan
            // & perilaku fail-open NULL sama dengan `consumeFromCustody()`.
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);

            $lots = TechnicianCustody::query()
                ->whereIn('technician_id', $technicianIds)
                ->where('item_id', $item->id)
                ->where('qty_remaining', '>', 0)
                ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                    $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
                }))
                ->orderBy('issued_at')
                ->lockForUpdate()
                ->get();

            $available = (float) $lots->sum('qty_remaining');

            // Dicek SEBELUM nulis apa pun — bukan mengandalkan rollback di
            // akhir loop kayak `consumeFromCustody()`: caller yang menangkap
            // exception ini di dalam transaction-nya sendiri (savepoint
            // nested) tetap aman, tapi gak ada alasan nulis ledger+balance
            // dulu buat dibuang.
            if ($available < $qtyUsed) {
                throw new InsufficientCustodyException($technicianIds, $item, $qtyUsed, $available);
            }

            $node = $this->freshNode($node);
            $toPopId = $node->resolvePop()?->id;
            $location = $node->breadcrumbLabel();

            $remaining = $qtyUsed;
            $deployed = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min((float) $lot->qty_remaining, $remaining);
                $newRemaining = (float) $lot->qty_remaining - $take;

                $lot->qty_remaining = $newRemaining;
                $lot->status = $newRemaining <= 0 ? CustodyStatus::CONSUMED : CustodyStatus::PARTIALLY_USED;
                $lot->save();

                $transaction = InventoryTransaction::create([
                    'type' => InventoryTransactionType::DEPLOY,
                    'reference_number' => $this->generateReferenceNumber('DPL'),
                    'item_id' => $item->id,
                    'lot_no' => $lot->lot_no,
                    'qty' => $take,
                    'unit_price_snapshot' => $lot->unit_price_snapshot,
                    'from_technician_id' => $lot->technician_id,
                    'to_network_node_id' => $node->id,
                    'to_pop_id' => $toPopId,
                    'fop_task_id' => $fopTask->id,
                    'notes' => "{$take} {$item->unit} {$item->name} dipasang netap di {$location} (Task {$fopTask->task_number}).",
                    'created_by' => $actor->id,
                ]);

                $deployed[] = [
                    'custody_id' => $lot->id,
                    'lot_no' => $lot->lot_no,
                    'qty' => $take,
                    'transaction_id' => $transaction->id,
                ];

                $remaining -= $take;
            }

            $this->incrementInfrastructureBalance($item->id, $node->id, '', $qtyUsed);

            return $deployed;
        });
    }

    /**
     * Potong meter roll kabel buat dipasang netap di titik infrastruktur —
     * pola & urutan guard sama `consumeFromRoll()` (status, custody, lintas
     * cabang, sisa cukup). Roll TETAP di custody teknisi (cuma
     * `length_remaining`/`status` yang berubah); yang "pindah" ke node cuma
     * meteran yang dipotong, tercatat di `infrastructure_balances` dengan
     * `lot_no = roll_code` — bukan `''` kayak item QUANTITY, supaya
     * tampilan node bisa bedain kabel dari roll mana.
     *
     * Status roll tetap vocabulary `RollStatus` (IN_USE/DEPLETED) — enum itu
     * melacak HABISNYA roll fisik, bukan lokasi pemasangan, jadi gak ada
     * (dan gak perlu) case DEPLOYED di sana.
     *
     * @param  iterable<User>  $technicians
     */
    public function deployRoll(InventoryRoll $roll, float $metersUsed, NetworkNode $node, iterable $technicians, FopTask $fopTask, User $actor): InventoryTransaction
    {
        if ($metersUsed <= 0) {
            throw new InvalidArgumentException('Meter yang di-deploy harus lebih besar dari nol.');
        }

        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        return DB::transaction(function () use ($roll, $metersUsed, $node, $technicianIds, $fopTask, $actor) {
            $roll = InventoryRoll::query()->with('item')->lockForUpdate()->findOrFail($roll->id);

            if (! in_array($roll->status, [RollStatus::ISSUED, RollStatus::IN_USE], true)) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} statusnya '{$roll->status->value}', bukan ISSUED/IN_USE — gak bisa dipotong.");
            }

            if (! in_array($roll->current_technician_id, $technicianIds, true)) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} bukan custody tim ini.");
            }

            if ($roll->item?->ownership_mode !== OwnershipMode::INFRASTRUCTURE_ASSET) {
                throw new InvalidArgumentException("Item {$roll->item?->name} bukan aset infrastruktur (ownership_mode) — gak bisa di-deploy ke titik jaringan.");
            }

            // Sama guard lintas-cabang dengan `consumeFromRoll()`.
            $targetCabangId = Pop::resolveCabangId($fopTask->pop_id);
            if ($targetCabangId !== null && $roll->issued_from_pop_id !== null && $roll->issued_from_pop_id !== $targetCabangId) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} berasal dari cabang lain — gak bisa di-deploy untuk task di cabang ini.");
            }

            if ($metersUsed > (float) $roll->length_remaining) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} sisa ".$roll->length_remaining." meter, diminta {$metersUsed} meter.");
            }

            $newRemaining = (float) $roll->length_remaining - $metersUsed;

            $roll->update([
                'length_remaining' => $newRemaining,
                'status' => $newRemaining <= 0 ? RollStatus::DEPLETED : RollStatus::IN_USE,
            ]);

            $node = $this->freshNode($node);

            $this->incrementInfrastructureBalance($roll->item_id, $node->id, $roll->roll_code, $metersUsed);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::DEPLOY,
                'reference_number' => $this->generateReferenceNumber('DPL'),
                'item_id' => $roll->item_id,
                'roll_id' => $roll->id,
                'lot_no' => $roll->roll_code,
                'qty' => $metersUsed,
                'unit_price_snapshot' => $roll->unit_price_snapshot,
                'from_technician_id' => $roll->current_technician_id,
                'to_network_node_id' => $node->id,
                'to_pop_id' => $node->resolvePop()?->id,
                'fop_task_id' => $fopTask->id,
                'notes' => "{$metersUsed} meter dari roll {$roll->roll_code} dipasang netap di {$node->breadcrumbLabel()} (Task {$fopTask->task_number}).",
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Cabut SN yang `DEPLOYED` dari titik infrastruktur, balik ke custody
     * `$actor` (yang fisik megang setelah dibongkar). Node & Cabang asal
     * dibaca SEBELUM `current_network_node_id` dikosongkan — itu satu-satunya
     * kesempatan buat snapshot `from_pop_id`.
     *
     * Gak ada guard lintas-cabang di sini: retrieve narik barang yang SUDAH
     * terpasang di suatu tempat balik ke tangan teknisi, gak ada "tujuan
     * cabang yang salah" kayak deploy.
     */
    public function retrieveSerialFromLocation(InventorySerial $serial, FopTask $fopTask, User $actor): InventoryTransaction
    {
        return DB::transaction(function () use ($serial, $fopTask, $actor) {
            $serial = InventorySerial::query()->with('item')->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::DEPLOYED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->value}', bukan DEPLOYED — gak bisa dicabut dari lokasi.");
            }

            $fromNodeId = $serial->current_network_node_id;
            $fromPopId = $serial->currentNetworkNode?->resolvePop()?->id;

            $serial->update([
                'status' => SerialStatus::ISSUED,
                'current_technician_id' => $actor->id,
                'current_network_node_id' => null,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RETRIEVE_INFRA,
                'reference_number' => $this->generateReferenceNumber('RTR'),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'from_network_node_id' => $fromNodeId,
                'from_pop_id' => $fromPopId,
                'to_technician_id' => $actor->id,
                'fop_task_id' => $fopTask->id,
                'notes' => "SN {$serial->serial_number} dicabut dari lokasi, kembali ke custody {$actor->name} (Task {$fopTask->task_number}).",
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Instance node segar dari DB — `resolvePop()` di-memo `once()` per
     * instance, jadi `$node` dari caller yang sudah pernah resolve SEBELUM
     * node itu dipindah cabang bakal ngasih snapshot `to_pop_id` basi.
     */
    private function freshNode(NetworkNode $node): NetworkNode
    {
        return NetworkNode::query()->lockForUpdate()->findOrFail($node->id);
    }

    /**
     * Kompatibilitas SKU ↔ tipe node komponen OLT — dipakai BARENG
     * `deploySerial()` (pasang via custody teknisi) dan
     * `registerExistingInfraSerial()` (registrasi aset lama langsung
     * DEPLOYED). Sebelumnya cek ini CUMA ada di `deploySerial()`, jadi
     * jalur registrasi bisa nyelipin SKU yang gak sesuai tipe chassis/card
     * atau numpuk dua aset di satu komponen OLT yang sama — ketemu waktu
     * review lintas-task Fase 0 (bukan dari task 6/7 sendiri-sendiri).
     *
     * - `olt_chassis`/`olt_card`: item wajib kategori `olt_module` DAN
     *   `infrastructure_node_type_id`-nya cocok tipe node tujuan, DAN
     *   komponen itu belum punya aset `DEPLOYED` lain (satu aset per
     *   komponen).
     * - Tipe node lain: kalau item punya `infrastructure_node_type_id`,
     *   wajib cocok tipe node tujuan (item tanpa batasan tipe node lolos).
     */
    private function assertNodeAcceptsSerialItem(Item $item, NetworkNode $node): void
    {
        if (in_array($node->nodeType?->code, ['olt_chassis', 'olt_card'], true)) {
            if ($item->category?->code !== 'olt_module'
                || $item->infrastructure_node_type_id !== $node->node_type_id) {
                throw new InvalidArgumentException('SKU aset tidak sesuai dengan tipe komponen OLT tujuan.');
            }
            if ($node->deployedSerials()->where('status', SerialStatus::DEPLOYED->value)->exists()) {
                throw new InvalidArgumentException('Komponen OLT ini sudah memiliki satu aset terpasang.');
            }
        } elseif ($item->infrastructure_node_type_id !== null
            && $item->infrastructure_node_type_id !== $node->node_type_id) {
            throw new InvalidArgumentException('SKU aset hanya dapat dipasang pada tipe node yang ditetapkan di Master Barang.');
        }
    }

    /**
     * Satu baris proyeksi per (item, node, lot_no) — keunikan ditegakkan di
     * sini (lock + firstOrCreate), bukan unique index, lihat migration
     * `create_infrastructure_balances_table`. Selalu dipanggil di dalam
     * transaction yang SAMA dengan penulisan baris ledger DEPLOY-nya.
     */
    private function incrementInfrastructureBalance(int $itemId, int $nodeId, string $lotNo, float $qty): void
    {
        $balance = InfrastructureBalance::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['item_id' => $itemId, 'network_node_id' => $nodeId, 'lot_no' => $lotNo],
                ['qty' => 0],
            );

        $balance->increment('qty', $qty);
    }

    /**
     * Reconcile baris `task_materials` Pasif generik (ditulis
     * `TaskMaterialService::sync()` — item/qty apa adanya dari form, tanpa
     * lot/harga) ke custody Gudang teknisi. Dipakai `CustomerInstallationController`
     * (dipanggil SEKALI di `storeSpeedtest()` — titik penyelesaian tunggal,
     * `sync()` sendiri di `storePemasangan()` boleh resubmit berkali-kali
     * TANPA rekonsiliasi ini) dan `TaskMaintenanceController` (dipanggil
     * langsung setelah `sync()`, one-shot — `store()` maintenance gak py
     * fase draft terpisah, sync()+complete() dalam satu request).
     *
     * Cuma baris dengan `item_id` terisi DAN tracking_type bukan SERIALIZED
     * yang direkonsiliasi — baris generiknya DIHAPUS, diganti baris hasil
     * `consumeFromCustody()` (detail per-lot+harga). Baris freeform ("lainnya",
     * `item_id` null) DIBIARKAN apa adanya — gak ada custody buat dicek.
     *
     * @param  iterable<User>  $technicians
     */
    public function reconcileMaterialsAgainstCustody(FopTask $fopTask, Customer $customer, iterable $technicians, User $actor): void
    {
        // Dibungkus transaction SENDIRI — sebelumnya DELETE baris generik +
        // consumeFromCustody() dua statement lepas, aman cuma karena 2
        // caller-nya (CustomerInstallationController/TaskMaintenanceController)
        // kebetulan bungkus transaction sendiri. Kalau consumeFromCustody()
        // lempar InsufficientCustodyException di tengah loop multi-item,
        // baris yang UDAH kehapus buat item sebelumnya permanen hilang tanpa
        // pengganti kalau caller gak nge-rollback (ketauan audit 2026-09-02)
        // — method ini sekarang atomik sendiri, gak gantung disiplin caller.
        DB::transaction(function () use ($fopTask, $customer, $technicians, $actor) {
            $rows = $fopTask->materials()->terpakai()->whereNotNull('item_id')->get();

            foreach ($rows->groupBy('item_id') as $itemId => $itemRows) {
                $item = Item::find($itemId);

                if (! $item || $item->tracking_type === TrackingType::SERIALIZED || $item->tracking_type === TrackingType::ROLL) {
                    continue;
                }

                $totalQty = (float) $itemRows->sum('qty');

                TaskMaterial::whereIn('id', $itemRows->pluck('id'))->delete();

                $this->consumeFromCustody($technicians, $item, $totalQty, $fopTask, $customer, $actor);
            }
        });
    }
}
