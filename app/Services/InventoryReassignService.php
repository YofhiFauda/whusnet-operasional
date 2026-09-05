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
