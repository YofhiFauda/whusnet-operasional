<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\DeviceRetrievalSource;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\Customer;
use App\Models\DeviceRetrievalLog;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reassign custody teknisi resign/cuti/rotasi sebelum barang balik (§3.6
 * rancangan-ui.md). `$reason` WAJIB diisi (kontrol-anti-manipulasi.md §1-2 —
 * "notes wajib diisi") — dua tujuan:
 *   - RETURN     : custody → Gudang Cabang.
 *   - TRANSFER_CUSTODY : custody → teknisi lain, LANGSUNG (tanpa approval
 *     gate — konsisten keputusan Fase 1 lain), tapi tercatat penuh di ledger.
 *
 * SENGAJA cuma bisa reassign SELURUH sisa custody (bukan qty parsial pilihan)
 * — di dunia nyata teknisi mengembalikan/dialihkan APA YANG TERSISA saat itu,
 * bukan sebagian sisa dan sebagian ditahan. Kalau nanti ada kebutuhan riil
 * partial reassign, itu perluasan API terpisah, bukan default sekarang.
 *
 * `created_by`/`from_*` di ledger SENGAJA `$actor` (admin yang eksekusi),
 * BUKAN teknisi lama — teknisi lama mungkin sudah resign, gak bisa/gak perlu
 * diminta konfirmasi apa pun buat SISI INI. Sisi penerima (teknisi baru, jalur
 * TRANSFER_CUSTODY) idealnya tetap ack sendiri — itu bagian UI/Controller
 * (belum dibangun fase Service ini, dicatat sebagai TODO eksplisit).
 */
class InventoryReassignService
{
    /**
     * Catatan ledger untuk SN yang baru didaftarkan dari pengambilan alat —
     * penanda modem legacy (belum pernah tercatat di Gudang).
     */
    private const LEGACY_SERIAL_NOTE = 'SN pelanggan lama — didaftarkan otomatis dari pengambilan alat (belum pernah tercatat di Gudang).';

    /**
     * Custody QUANTITY/BATCH kembali ke gudang cabang — SELURUH sisa
     * `qty_remaining`, bukan sebagian.
     */
    public function returnToWarehouse(TechnicianCustody $custody, Pop $cabang, string $reason, User $actor, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        // Sama kayak InventoryAdjustmentService::adjustPopBalance() — cegah
        // return custody nyasar nulis balance ke mini_pop.
        if (! $cabang->isWarehouse()) {
            throw new InvalidArgumentException("Return custody cuma boleh ke Gudang Pusat/Cabang — {$cabang->name} bertipe '{$cabang->type}'.");
        }

        if ((float) $custody->qty_remaining <= 0) {
            throw new InvalidArgumentException('Custody ini sudah habis/kosong — tidak ada yang bisa dikembalikan.');
        }

        return DB::transaction(function () use ($custody, $cabang, $reason, $actor, $notes) {
            $qty = (float) $custody->qty_remaining;
            $lotNo = $custody->lot_no ?? '';

            $balance = InventoryBalance::query()
                ->firstOrCreate(
                    ['pop_id' => $cabang->id, 'item_id' => $custody->item_id, 'lot_no' => $lotNo],
                    ['qty' => 0]
                );
            $balance->increment('qty', $qty);

            $custody->update(['qty_remaining' => 0, 'status' => CustodyStatus::RETURNED]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $custody->item_id,
                'lot_no' => $custody->lot_no,
                'qty' => $qty,
                'unit_price_snapshot' => $custody->unit_price_snapshot,
                'from_technician_id' => $custody->technician_id,
                'to_pop_id' => $cabang->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Unit SERIALIZED kembali ke gudang cabang.
     */
    public function returnSerialToWarehouse(InventorySerial $serial, Pop $cabang, string $reason, User $actor, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($serial, $cabang, $reason, $actor, $notes) {
            // Re-fetch + lockForUpdate() DI DALAM transaction — cegah 2
            // admin bersamaan me-return/reassign SN fisik yang sama.
            $serial = InventorySerial::query()->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::ISSUED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->value}', bukan ISSUED — gak bisa direturn dari custody.");
            }

            $fromTechnicianId = $serial->current_technician_id;

            $serial->update([
                'status' => SerialStatus::AVAILABLE,
                'current_pop_id' => $cabang->id,
                'current_technician_id' => null,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'from_technician_id' => $fromTechnicianId,
                'to_pop_id' => $cabang->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Unit SERIALIZED balik ke gudang cabang dari status `INSTALLED` — bukan
     * dari custody teknisi (`returnSerialToWarehouse()` di atas, yang cuma
     * terima asal `ISSUED`). Titik masuk siklus Install → Terminate →
     * Retrieve yang SEBELUMNYA GAK ADA SAMA SEKALI: begitu SN `INSTALLED`,
     * gak ada satupun jalur yang balikin lagi (ketauan audit user,
     * 2026-09-03) — tombol "Ambil Alat"/task Ambil Modem cuma nandain
     * `customer_devices.device_retrieved_at` (tabel legacy), `InventorySerial`
     * permanen macet di INSTALLED walau device fisik udah kembali ke gudang.
     *
     * Cabang tujuan diambil dari `issued_from_pop_id` SN itu sendiri (gudang
     * cabang asal ISSUE terakhir sebelum diinstall) — BUKAN dari POP task
     * retrieval, yang bisa jadi mini_pop/kecamatan (bukan gudang). Kolom ini
     * dijamin terisi karena `installSerial()` cuma nerima SN berstatus
     * ISSUED, dan ISSUED selalu nulis `issued_from_pop_id`.
     *
     * ADHOC-86: `TaskService::complete()` TIDAK lagi memanggil ini — task DEAC
     * sekarang lewat `pickupSerialFromCustomer()` (transit RETURNED) lalu
     * `confirmReturnedSerial()`. Method ini dipertahankan sebagai operasi
     * INSTALLED → AVAILABLE langsung (satu-satunya cara memproduksi SN
     * "bekas belum dicek" untuk test ADHOC-80 dan data DEAC lama).
     */
    public function returnInstalledSerialFromCustomer(InventorySerial $serial, string $reason, User $actor, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($serial, $reason, $actor, $notes) {
            // Re-fetch + lockForUpdate() — sama alasan tiap transisi status
            // InventorySerial lain di Service ini (cegah 2 admin
            // bersamaan me-retrieve SN fisik yang sama).
            $serial = InventorySerial::query()->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::INSTALLED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->value}', bukan INSTALLED — gak bisa diambil-balik dari pelanggan.");
            }

            if (! $serial->issued_from_pop_id) {
                throw new InvalidArgumentException("SN {$serial->serial_number} tidak punya gudang cabang asal (issued_from_pop_id) — data lama/tidak konsisten, gak bisa ditentukan tujuan pengembaliannya.");
            }

            $cabang = Pop::findOrFail($serial->issued_from_pop_id);
            $fopTaskId = $serial->fop_task_id;

            $serial->update([
                'status' => SerialStatus::AVAILABLE,
                // Asumsi awal "bekas kondisi baik" — staf koreksi ke
                // used_damaged pas cek fisik kalau perlu (aksi "Sudah
                // Dicek", WarehouseTraceabilityController::checkCondition()).
                // BELUM dicek (`condition_checked_at` null) — ini yang
                // men-trigger gate `InventorySerial::isClearedForIssue()`,
                // beda dari SN baru yang gak pernah butuh cek ulang.
                'condition' => ItemCondition::USED_GOOD,
                'condition_checked_at' => null,
                'condition_checked_by' => null,
                'current_pop_id' => $cabang->id,
                'current_technician_id' => null,
                'customer_id' => null,
                'fop_task_id' => null,
                'installed_at' => null,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'to_pop_id' => $cabang->id,
                'fop_task_id' => $fopTaskId,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * ADHOC-86 — modem dicabut dari pelanggan (task DEAC), SN dilaporkan
     * teknisi dari form laporan DEAC. Modem BELUM masuk gudang: statusnya
     * `RETURNED` (transit, dipegang teknisi) sampai staf gudang cabang
     * mengonfirmasi lewat `confirmReturnedSerial()`. Polanya sama dengan
     * TRANSFERRED → AVAILABLE di `InventoryTransferService`.
     *
     * Tiga kemungkinan SN yang dilaporkan:
     *  1. `INSTALLED` milik pelanggan ini → jalur normal, jadi RETURNED.
     *  2. Belum ada di `inventory_serials` (modem legacy hasil migrasi — data
     *     lama cuma punya SN, tanpa nama barang) → didaftarkan otomatis dengan
     *     `$item` pilihan teknisi (atau item placeholder). Tanpa ini SN legacy
     *     diabaikan diam-diam dan badge "Sudah Diambil" berbohong.
     *  3. Sudah ada tapi bukan milik pelanggan ini / bukan INSTALLED → DITOLAK,
     *     jangan ditimpa: itu konflik data (SN sama di dua tempat) yang harus
     *     diselesaikan admin, bukan diam-diam dipindah oleh laporan lapangan.
     *
     * Idempoten untuk SN yang SUDAH `RETURNED` dari pelanggan yang sama —
     * teknisi kirim ulang laporan setelah FOP me-reject task; tanpa ini
     * kirim ulang gagal di SN yang sudah diproses.
     *
     * `customer_id`/`fop_task_id`/`installed_at` SENGAJA dibiarkan selama
     * transit (baru dikosongkan saat gudang menerima) supaya jejak "modem ini
     * dari pelanggan siapa" tidak putus di tengah jalan.
     */
    public function pickupSerialFromCustomer(string $serialNumber, ?Item $item, Customer $customer, Task $task, User $technician): InventorySerial
    {
        $serialNumber = trim($serialNumber);

        if ($serialNumber === '') {
            throw new InvalidArgumentException('Nomor seri (SN) tidak boleh kosong.');
        }

        return DB::transaction(function () use ($serialNumber, $item, $customer, $task, $technician) {
            $existing = InventorySerial::query()->where('serial_number', $serialNumber)->lockForUpdate()->first();
            $reason = "Pengambilan alat — Task {$task->task_number} (putus langganan).";
            $fopTaskId = $task->fopTask?->id;

            if ($existing && $existing->status === SerialStatus::RETURNED && $existing->customer_id === $customer->id) {
                return $existing;
            }

            [$serial, $isLegacy] = $this->claimSerialFromCustomer($serialNumber, $existing, $item, $customer, [
                'status' => SerialStatus::RETURNED,
                'condition' => ItemCondition::USED_GOOD,
                'condition_checked_at' => null,
                'condition_checked_by' => null,
                'issued_from_pop_id' => $existing?->issued_from_pop_id ?? $this->resolveWarehouseFor($task->pop)?->id,
                'current_pop_id' => null,
                'current_technician_id' => $technician->id,
            ], [
                'customer_id' => $customer->id,
                'fop_task_id' => $fopTaskId,
            ]);

            $notes = $isLegacy ? self::LEGACY_SERIAL_NOTE : null;

            // Dicek SETELAH tulis, di dalam transaksi: kalau tujuan gudang tidak
            // bisa ditentukan, throw membatalkan baris baru/perubahan status di
            // atas — modem tanpa gudang tujuan tidak akan pernah muncul di
            // halaman Terima Retur mana pun.
            if (! $serial->issued_from_pop_id) {
                throw new InvalidArgumentException("Gudang cabang tujuan untuk SN {$serialNumber} tidak bisa ditentukan dari POP task ini. Hubungi admin untuk melengkapi data POP/gudang cabang.");
            }

            InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'to_technician_id' => $technician->id,
                'fop_task_id' => $fopTaskId,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $technician->id,
            ]);

            // Jejak per SN (ADHOC-88): siapa teknisinya, dari pelanggan mana,
            // kapan. Ditulis di transaksi yang sama supaya tidak ada modem
            // RETURNED tanpa jejak. Foto kondisi tetap milik laporan task
            // (TaskDeviceRetrieval), dibaca lewat DeviceRetrievalLog::photoPath().
            DeviceRetrievalLog::create([
                'customer_id' => $customer->id,
                'serial_id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'source' => DeviceRetrievalSource::DEAC,
                'task_id' => $task->id,
                'retrieved_by' => $technician->id,
                'warehouse_pop_id' => $serial->issued_from_pop_id,
                'retrieved_at' => now(),
                'notes' => $notes,
            ]);

            return $serial;
        });
    }

    /**
     * ADHOC-88 — pelanggan mengantar modem sendiri ke gudang, TANPA task DEAC.
     * Satu langkah: staf gudang sudah memegang fisiknya, jadi tidak ada transit
     * `RETURNED` — langsung `AVAILABLE` dengan kondisi yang dinilai saat itu
     * juga (melepas gate `isClearedForIssue()`).
     *
     * Kenapa bukan `InventoryReceiveService` (Barang Masuk): itu jalur
     * PENGADAAN — kondisi dipaksa `new`, harga wajib > 0, hanya Gudang Pusat,
     * dan tidak tertaut ke pelanggan sehingga riwayat pengambilan tidak terisi.
     *
     * `$estimatedValue` opsional (keputusan user 2026-09-21): SN legacy tidak
     * punya harga beli. Kosong/0 → disimpan null dan dihitung Rp 0 di Laporan
     * Bulanan. Nilai disimpan di baris ledger, bukan menimpa apa pun.
     *
     * @param  array<int, string>  $accessories
     */
    public function receiveSerialFromCustomerAtWarehouse(
        string $serialNumber,
        ?Item $item,
        Customer $customer,
        Pop $cabang,
        ItemCondition $condition,
        ?float $estimatedValue,
        User $actor,
        ?string $notes = null,
        ?string $photoPath = null,
        array $accessories = [],
    ): InventorySerial {
        $serialNumber = trim($serialNumber);

        if ($serialNumber === '') {
            throw new InvalidArgumentException('Nomor seri (SN) tidak boleh kosong.');
        }

        if (! $cabang->isWarehouse()) {
            throw new InvalidArgumentException("Modem hanya bisa diterima di Gudang Pusat/Cabang — {$cabang->name} bertipe '{$cabang->type}'.");
        }

        if ($condition === ItemCondition::NEW) {
            throw new InvalidArgumentException('Kondisi modem dari pelanggan harus "bekas baik" atau "bekas rusak" — bukan baru.');
        }

        return DB::transaction(function () use ($serialNumber, $item, $customer, $cabang, $condition, $estimatedValue, $actor, $notes, $photoPath, $accessories) {
            $existing = InventorySerial::query()->where('serial_number', $serialNumber)->lockForUpdate()->first();

            [$serial, $isLegacy] = $this->claimSerialFromCustomer($serialNumber, $existing, $item, $customer, [
                'status' => SerialStatus::AVAILABLE,
                'condition' => $condition,
                'condition_checked_at' => now(),
                'condition_checked_by' => $actor->id,
                'current_pop_id' => $cabang->id,
                'current_technician_id' => null,
                'customer_id' => null,
                'fop_task_id' => null,
                'installed_at' => null,
            ]);

            $value = $estimatedValue !== null && $estimatedValue > 0 ? $estimatedValue : null;
            $ledgerNotes = collect([$isLegacy ? self::LEGACY_SERIAL_NOTE : null, $notes])->filter()->join(' | ') ?: null;

            InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'unit_price_snapshot' => $value,
                'to_pop_id' => $cabang->id,
                'reason' => 'Modem diantar pelanggan ke gudang (tanpa task pengambilan).',
                'notes' => $ledgerNotes,
                'created_by' => $actor->id,
            ]);

            DeviceRetrievalLog::create([
                'customer_id' => $customer->id,
                'serial_id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'source' => DeviceRetrievalSource::WALK_IN,
                'retrieved_by' => $actor->id,
                'received_by' => $actor->id,
                'warehouse_pop_id' => $cabang->id,
                'condition' => $condition,
                'estimated_value' => $value,
                'condition_photo' => $photoPath,
                'accessories' => $accessories,
                'notes' => $notes,
                'retrieved_at' => now(),
                'received_at' => now(),
            ]);

            // Sama seperti task DEAC selesai: alat pelanggan ini sudah kembali.
            $device = $customer->customerDevice;
            if ($device && ! $device->device_retrieved_at) {
                $device->update(['device_retrieved_at' => now()]);
            }

            return $serial;
        });
    }

    /**
     * Klaim SN dari pelanggan yang sudah putus — dipakai jalur DEAC
     * (`pickupSerialFromCustomer`) dan jalur diantar pelanggan
     * (`receiveSerialFromCustomerAtWarehouse`) supaya aturan konfliknya SATU:
     *  - `INSTALLED` milik pelanggan ini → diperbarui dengan `$attributes`.
     *  - belum ada (modem legacy) → dibuat, butuh `$item` ber-SN.
     *  - selain itu → ditolak, tidak ditimpa.
     *
     * @param  array<string, mixed>  $attributes  dipakai saat update DAN create
     * @param  array<string, mixed>  $newOnly  tambahan khusus saat create
     * @return array{0: InventorySerial, 1: bool} [serial, apakah baru didaftarkan (legacy)]
     */
    private function claimSerialFromCustomer(string $serialNumber, ?InventorySerial $existing, ?Item $item, Customer $customer, array $attributes, array $newOnly = []): array
    {
        if ($existing) {
            if ($existing->status !== SerialStatus::INSTALLED || $existing->customer_id !== $customer->id) {
                $where = $existing->status === SerialStatus::INSTALLED
                    ? 'terpasang di pelanggan lain'
                    : "berstatus '{$existing->status->label()}'";

                throw new InvalidArgumentException("SN {$serialNumber} sudah tercatat di sistem {$where}, bukan terpasang di pelanggan ini. Periksa ulang SN di perangkat, atau hubungi admin gudang untuk menyelesaikan konflik data.");
            }

            $existing->update($attributes);

            return [$existing, false];
        }

        if (! $item) {
            throw new InvalidArgumentException("SN {$serialNumber} belum pernah tercatat di sistem — pilih model modem/perangkat-nya (atau \"Modem Pelanggan Lama\" kalau tidak tahu modelnya).");
        }

        if ($item->tracking_type !== TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Barang {$item->name} bukan barang ber-SN — pilih model modem/perangkat yang benar.");
        }

        $serial = InventorySerial::create($attributes + $newOnly + [
            'item_id' => $item->id,
            'serial_number' => $serialNumber,
        ]);

        return [$serial, true];
    }

    /**
     * ADHOC-86 — staf gudang cabang menerima modem hasil DEAC:
     * RETURNED → AVAILABLE di `issued_from_pop_id`. Kondisi dinilai di sini
     * (`used_good`/`used_damaged`), jadi sekaligus mengisi
     * `condition_checked_*` dan melepas gate `isClearedForIssue()` —
     * tidak perlu langkah "Sudah Dicek" terpisah untuk SN jalur ini.
     *
     * `$correctedItem` untuk SN legacy yang didaftarkan dengan item
     * placeholder/tebakan teknisi: staf yang memegang fisiknya yang tahu
     * model sebenarnya.
     */
    public function confirmReturnedSerial(InventorySerial $serial, ItemCondition $condition, ?Item $correctedItem, User $actor, ?string $notes = null, ?float $estimatedValue = null): InventoryTransaction
    {
        if ($condition === ItemCondition::NEW) {
            throw new InvalidArgumentException('Kondisi modem hasil pengambilan harus "bekas baik" atau "bekas rusak" — bukan baru.');
        }

        if ($correctedItem && $correctedItem->tracking_type !== TrackingType::SERIALIZED) {
            throw new InvalidArgumentException("Barang {$correctedItem->name} bukan barang ber-SN.");
        }

        return DB::transaction(function () use ($serial, $condition, $correctedItem, $actor, $notes, $estimatedValue) {
            $serial = InventorySerial::query()->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::RETURNED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->label()}', bukan menunggu diterima gudang.");
            }

            // Nilai taksiran opsional (ADHOC-88): kosong/0 → null, dihitung Rp 0
            // di Laporan Bulanan. Disimpan di baris ledger, bukan di SN.
            $value = $estimatedValue !== null && $estimatedValue > 0 ? $estimatedValue : null;

            $cabang = Pop::findOrFail($serial->issued_from_pop_id);
            $fromTechnicianId = $serial->current_technician_id;
            $fopTaskId = $serial->fop_task_id;

            $serial->update([
                'item_id' => $correctedItem?->id ?? $serial->item_id,
                'status' => SerialStatus::AVAILABLE,
                'condition' => $condition,
                'condition_checked_at' => now(),
                'condition_checked_by' => $actor->id,
                'current_pop_id' => $cabang->id,
                'current_technician_id' => null,
                'customer_id' => null,
                'fop_task_id' => null,
                'installed_at' => null,
            ]);

            $transaction = InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'unit_price_snapshot' => $value,
                'from_technician_id' => $fromTechnicianId,
                'to_pop_id' => $cabang->id,
                'fop_task_id' => $fopTaskId,
                'reason' => 'Konfirmasi penerimaan retur pengambilan alat.',
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            // Lengkapi jejak per SN: siapa yang menerima, kapan, kondisi akhir,
            // dan model final (bisa dikoreksi dari tebakan teknisi). Log yang
            // dicari = yang masih menunggu diterima; kosong berarti SN ini
            // RETURNED sebelum ADHOC-88 dan tidak punya jejak — dilewati.
            DeviceRetrievalLog::query()
                ->where('serial_id', $serial->id)
                ->whereNull('received_at')
                ->latest('retrieved_at')
                ->first()
                ?->update([
                    'item_id' => $serial->item_id,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                    'condition' => $condition,
                    'estimated_value' => $value,
                ]);

            return $transaction;
        });
    }

    /**
     * Gudang cabang untuk sebuah POP task: POP itu sendiri kalau sudah
     * gudang, kalau tidak naik lewat `parent_id` (task DEAC biasanya
     * ber-POP mini_pop/kecamatan). Batas 5 tingkat = pagar dari siklus data.
     */
    private function resolveWarehouseFor(?Pop $pop): ?Pop
    {
        for ($depth = 0; $pop && $depth < 5; $depth++) {
            if ($pop->isWarehouse()) {
                return $pop;
            }

            $pop = $pop->parent;
        }

        return null;
    }

    /**
     * Aksi "Sudah Dicek" (analisa-gap-kondisi-barang.md rancangan poin 5) —
     * satu-satunya jalur yang boleh mengisi `condition_checked_at`/`_by`,
     * jadi satu-satunya jalur yang bisa melepas gate Issue
     * (`InventorySerial::isClearedForIssue()`) buat SN bekas. TIDAK menulis
     * ledger — ini metadata inspeksi, bukan mutasi stok/lokasi. SN `new`
     * gak butuh dicek (gak pernah dipakai), jadi ditolak eksplisit biar gak
     * dipakai buat hal lain.
     */
    public function markSerialConditionChecked(InventorySerial $serial, ItemCondition $finalCondition, User $actor): InventorySerial
    {
        if ($finalCondition === ItemCondition::NEW) {
            throw new InvalidArgumentException('Hasil cek fisik harus used_good atau used_damaged — SN baru gak butuh aksi "Sudah Dicek".');
        }

        if (($serial->condition ?? ItemCondition::NEW) === ItemCondition::NEW) {
            throw new InvalidArgumentException("SN {$serial->serial_number} berkondisi 'new' — belum pernah balik dari pemakaian, gak ada yang perlu dicek.");
        }

        $serial->update([
            'condition' => $finalCondition,
            'condition_checked_at' => now(),
            'condition_checked_by' => $actor->id,
        ]);

        return $serial;
    }

    /**
     * Roll kabel balik ke gudang cabang dengan SISA METERNYA — BEDA dari
     * `returnSerialToWarehouse()` (SN selalu balik "utuh", qty=1): roll bisa
     * balik PARTIAL (`length_remaining` < `length_total`), status jadi
     * AVAILABLE lagi di cabang tujuan (siap diissue ulang ke teknisi lain
     * dengan sisa meter yang sama). Roll yang sudah DEPLETED (sisa 0) gak
     * ada gunanya dikembalikan — ditolak eksplisit, bukan silently no-op.
     */
    public function returnRollToWarehouse(InventoryRoll $roll, Pop $cabang, string $reason, User $actor, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        if (! $cabang->isWarehouse()) {
            throw new InvalidArgumentException("Return roll cuma boleh ke Gudang Pusat/Cabang — {$cabang->name} bertipe '{$cabang->type}'.");
        }

        return DB::transaction(function () use ($roll, $cabang, $reason, $actor, $notes) {
            $roll = InventoryRoll::query()->lockForUpdate()->findOrFail($roll->id);

            if (! in_array($roll->status, [RollStatus::ISSUED, RollStatus::IN_USE], true)) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} statusnya '{$roll->status->value}', bukan ISSUED/IN_USE — gak bisa dikembalikan dari custody.");
            }

            if ((float) $roll->length_remaining <= 0) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} sudah habis (0 meter) — tidak ada yang bisa dikembalikan.");
            }

            $fromTechnicianId = $roll->current_technician_id;
            $qty = (float) $roll->length_remaining;

            $roll->update([
                'status' => RollStatus::AVAILABLE,
                'current_pop_id' => $cabang->id,
                'current_technician_id' => null,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RETURN,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $roll->item_id,
                'roll_id' => $roll->id,
                'qty' => $qty,
                'unit_price_snapshot' => $roll->unit_price_snapshot,
                'from_technician_id' => $fromTechnicianId,
                'to_pop_id' => $cabang->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Roll kabel pindah LANGSUNG ke teknisi lain — sisa meter ikut roll yang
     * sama (BEDA dari custody QUANTITY/BATCH yang bikin baris baru), karena
     * roll punya identitas fisik tunggal, bukan agregat qty.
     */
    public function transferRollToTechnician(InventoryRoll $roll, User $newTechnician, string $reason, User $actor, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($roll, $newTechnician, $reason, $actor, $notes) {
            $roll = InventoryRoll::query()->lockForUpdate()->findOrFail($roll->id);

            if (! in_array($roll->status, [RollStatus::ISSUED, RollStatus::IN_USE], true)) {
                throw new InvalidArgumentException("Roll {$roll->roll_code} statusnya '{$roll->status->value}', bukan ISSUED/IN_USE — gak bisa dialihkan.");
            }

            $fromTechnicianId = $roll->current_technician_id;

            $roll->update(['current_technician_id' => $newTechnician->id]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::TRANSFER_CUSTODY,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $roll->item_id,
                'roll_id' => $roll->id,
                'qty' => $roll->length_remaining,
                'from_technician_id' => $fromTechnicianId,
                'to_technician_id' => $newTechnician->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Custody QUANTITY/BATCH pindah LANGSUNG ke teknisi lain — TIDAK
     * menyentuh `inventory_balances` gudang sama sekali (beda dari
     * `returnToWarehouse()`). Baris custody lama di-nol-kan (`qty_remaining=0`,
     * status `CONSUMED` — reuse pragmatis, alasan sebenarnya "dialihkan"
     * ada lengkap di `notes` ledger, bukan di status custody), baris baru
     * dibuat buat teknisi penerima.
     */
    public function transferCustodyToTechnician(TechnicianCustody $custody, User $newTechnician, string $reason, User $actor, ?string $notes = null): TechnicianCustody
    {
        $this->assertReason($reason);

        if ((float) $custody->qty_remaining <= 0) {
            throw new InvalidArgumentException('Custody ini sudah habis/kosong — tidak ada yang bisa dialihkan.');
        }

        return DB::transaction(function () use ($custody, $newTechnician, $reason, $actor, $notes) {
            $qty = (float) $custody->qty_remaining;
            $oldTechnicianId = $custody->technician_id;

            $custody->update(['qty_remaining' => 0, 'status' => CustodyStatus::CONSUMED]);

            $newCustody = TechnicianCustody::create([
                'technician_id' => $newTechnician->id,
                'issued_from_pop_id' => $custody->issued_from_pop_id, // asal gudang gak berubah, cuma pindah tangan
                'item_id' => $custody->item_id,
                'lot_no' => $custody->lot_no,
                'qty_remaining' => $qty,
                'unit_price_snapshot' => $custody->unit_price_snapshot,
                'status' => CustodyStatus::ISSUED,
                'issued_at' => now(),
            ]);

            InventoryTransaction::create([
                'type' => InventoryTransactionType::TRANSFER_CUSTODY,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $custody->item_id,
                'lot_no' => $custody->lot_no,
                'qty' => $qty,
                'unit_price_snapshot' => $custody->unit_price_snapshot,
                'from_technician_id' => $oldTechnicianId,
                'to_technician_id' => $newTechnician->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            return $newCustody;
        });
    }

    /**
     * Unit SERIALIZED pindah LANGSUNG ke teknisi lain.
     */
    public function transferSerialToTechnician(InventorySerial $serial, User $newTechnician, string $reason, User $actor, ?string $notes = null): InventoryTransaction
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($serial, $newTechnician, $reason, $actor, $notes) {
            $serial = InventorySerial::query()->lockForUpdate()->findOrFail($serial->id);

            if ($serial->status !== SerialStatus::ISSUED) {
                throw new InvalidArgumentException("SN {$serial->serial_number} statusnya '{$serial->status->value}', bukan ISSUED — gak bisa dialihkan.");
            }

            $fromTechnicianId = $serial->current_technician_id;

            $serial->update(['current_technician_id' => $newTechnician->id]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::TRANSFER_CUSTODY,
                'reference_number' => $this->generateReferenceNumber(),
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'from_technician_id' => $fromTechnicianId,
                'to_technician_id' => $newTechnician->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan reassign wajib diisi (resign/cuti/rotasi/dll) — kontrol-anti-manipulasi.md §1-2.');
        }
    }

    /**
     * `RSG-YYYY-NNNN` — Reassign/Return sebelumnya gak py nomor dokumen sama
     * sekali, beda dari RECEIVE/TRANSFER/ISSUE, jadi event ini gak bisa
     * dicari/di-grouping di Traceability (ketauan audit 2026-09-02). Pola
     * `MAX+1` sama persis `InventoryReceiveService`/`InventoryTransferService`/
     * `InventoryIssueService` — race generate-nya SENGAJA gak dibenahi di sini
     * (lihat catatan pelingkupan P0/P1 di docs/TASKS.md, pola yang sama juga
     * dipakai TicketService/TaskService/FopTaskProvisioningService).
     */
    /**
     * `RSG-YYYYMMDD-NNNNNN` — lihat docblock sama persis di
     * `InventoryReceiveService::generateReferenceNumber()` buat alasan
     * lengkap (tanggal penuh buat dibaca, reset counter per BULAN bukan per
     * hari, keputusan eksplisit user 2026-09-03).
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = InventoryTransaction::where('reference_number', 'like', "RSG-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('RSG-%s-%06d', $today, $lastNum + 1);
    }
}
