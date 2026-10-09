<?php

namespace App\Console\Commands;

use App\Enums\TrackingType;
use App\Models\InventoryRoll;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi `inventory_rolls.unit_price_snapshot` yang tersimpan mentah harga
 * PER ROLL (bug `InventoryReceiveService::receiveRoll()` sebelum
 * 2026-09-18 — lihat docblock `InventoryRoll`) jadi harga PER METER yang
 * benar (÷ `meter_per_roll`).
 *
 * SENGAJA per-item (wajib `--item=`), BUKAN "semua barang ROLL sekaligus"
 * — gak ada cara sistem bedain baris yang KEBETULAN kepencet bug ini dari
 * baris yang harganya emang udah bener (mis. data demo/seeder yang ditulis
 * dengan asumsi per-meter sejak awal). Staf review manual per item, satu
 * per satu, baru jalankan.
 *
 * CUMA nyentuh `InventoryRoll` (proyeksi state TERKINI, mutable — sejalan
 * `InventoryBalance`/`InventorySerial`) — `inventory_transactions` (ledger
 * RECEIVE/TRANSFER historis) SENGAJA TIDAK disentuh sama sekali, itu
 * append-only, `InventoryTransactionObserver` nolak `update()` apa pun,
 * dan `docs/plan/warehouse/kontrol-anti-manipulasi.md` §6 eksplisit
 * melarang bulk-update lewat query builder buat "lewatin" guard itu.
 * Konsekuensinya: laporan yang MERUJUK LANGSUNG ke `unit_price_snapshot`
 * baris RECEIVE lama (bukan state InventoryRoll terkini) akan tetap
 * kebaca harga lama yang salah — itu batasan arsitektur yang disadari,
 * bukan celah command ini.
 *
 * Jalankan `--dry-run` dulu (default), review tabelnya, baru
 * `--confirm` buat nulis.
 */
class BackfillRollUnitPriceSnapshotCommand extends Command
{
    protected $signature = 'warehouse:backfill-roll-price
        {--item= : Kode item (items.code) yang mau dikoreksi, wajib diisi}
        {--confirm : Tulis perubahan. Tanpa flag ini cuma tampil dry-run.}';

    protected $description = 'Koreksi unit_price_snapshot roll yang kesimpen mentah per-roll (bug sebelum 2026-09-18) jadi per-meter';

    public function handle(): int
    {
        $itemCode = $this->option('item');

        if (! $itemCode) {
            $this->error('Wajib isi --item=<kode item>. Command ini SENGAJA gak bisa jalan buat "semua barang roll" sekaligus — lihat docblock command ini kenapa.');

            return self::FAILURE;
        }

        $item = Item::where('code', $itemCode)->first();

        if (! $item) {
            $this->error("Item dengan kode '{$itemCode}' tidak ditemukan.");

            return self::FAILURE;
        }

        if ($item->tracking_type !== TrackingType::ROLL) {
            $this->error("Item '{$item->name}' bukan tracking_type ROLL ({$item->tracking_type->value}).");

            return self::FAILURE;
        }

        $meterPerRoll = (float) $item->meter_per_roll;
        if ($meterPerRoll <= 0) {
            $this->error("Item '{$item->name}' meter_per_roll-nya {$meterPerRoll} — gak masuk akal buat dijadiin pembagi.");

            return self::FAILURE;
        }

        $rolls = InventoryRoll::where('item_id', $item->id)->orderBy('roll_code')->get();

        if ($rolls->isEmpty()) {
            $this->info("Item '{$item->name}' belum punya roll sama sekali. Tidak ada yang dikoreksi.");

            return self::SUCCESS;
        }

        $this->info("Item: {$item->name} (meter_per_roll={$meterPerRoll})");
        $this->info('Konversi: harga_saat_ini ÷ '.$meterPerRoll.' = harga_per_meter_baru');
        $this->newLine();

        $rows = $rolls->map(function (InventoryRoll $roll) use ($meterPerRoll) {
            $current = (float) $roll->unit_price_snapshot;
            $corrected = round($current / $meterPerRoll, 2);

            return [$roll->roll_code, number_format($current, 2), number_format($corrected, 2)];
        });

        $this->table(['Roll Code', 'Harga Saat Ini (per unit tersimpan)', 'Harga Setelah Koreksi (per meter)'], $rows->all());

        if (! $this->option('confirm')) {
            $this->warn('Ini DRY-RUN — belum ada yang ditulis. Jalankan lagi dengan --confirm buat benerin beneran.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Yakin koreksi {$rolls->count()} roll di atas? unit_price_snapshot bakal ditimpa, tidak ada undo otomatis (bukan ledger, tapi tetap data live).")) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rolls, $meterPerRoll) {
            foreach ($rolls as $roll) {
                $roll->update(['unit_price_snapshot' => round((float) $roll->unit_price_snapshot / $meterPerRoll, 2)]);
            }
        });

        $this->info("Selesai — {$rolls->count()} roll dikoreksi ke harga per-meter.");
        $this->warn('CATATAN: baris inventory_transactions (RECEIVE/TRANSFER) historis TIDAK ikut dikoreksi — ledger append-only. Kalau ada laporan yang merujuk langsung ke unit_price_snapshot baris ledger lama (bukan state InventoryRoll terkini), angka lama tetap kebaca di situ.');

        return self::SUCCESS;
    }
}
