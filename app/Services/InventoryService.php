<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Exceptions\InsufficientCustodyException;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
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

        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        if ($technicianIds === []) {
            throw new InvalidArgumentException('Daftar teknisi (anggota tim) tidak boleh kosong.');
        }

        return DB::transaction(function () use ($technicianIds, $item, $qtyUsed, $fopTask, $customer, $actor) {
            $lots = TechnicianCustody::query()
                ->whereIn('technician_id', $technicianIds)
                ->where('item_id', $item->id)
                ->where('qty_remaining', '>', 0)
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
    public function installSerial(
        InventorySerial $serial,
        Customer $customer,
        FopTask $fopTask,
        iterable $technicians,
        User $actor,
    ): InventoryTransaction {
        $technicianIds = Collection::make($technicians)->pluck('id')->all();

        return DB::transaction(function () use ($serial, $customer, $fopTask, $actor, $technicianIds) {
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

            $fromTechnicianId = $serial->current_technician_id;

            $serial->update([
                'status' => SerialStatus::INSTALLED,
                'customer_id' => $customer->id,
                'fop_task_id' => $fopTask->id,
                'installed_at' => now(),
                'current_technician_id' => null,
            ]);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::INSTALL,
                'item_id' => $serial->item_id,
                'serial_id' => $serial->id,
                'qty' => 1,
                'from_technician_id' => $fromTechnicianId,
                'fop_task_id' => $fopTask->id,
                'created_by' => $actor->id,
            ]);
        });
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

                if (! $item || $item->tracking_type === TrackingType::SERIALIZED) {
                    continue;
                }

                $totalQty = (float) $itemRows->sum('qty');

                TaskMaterial::whereIn('id', $itemRows->pluck('id'))->delete();

                $this->consumeFromCustody($technicians, $item, $totalQty, $fopTask, $customer, $actor);
            }
        });
    }
}
