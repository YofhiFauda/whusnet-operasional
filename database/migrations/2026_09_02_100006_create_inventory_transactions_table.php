<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger APPEND-ONLY — satu-satunya sumber histori inventory (§25 analisa
     * pertama). `inventory_balances`/`technician_custody`/`inventory_serials`
     * itu proyeksi yang DITURUNKAN dari tabel ini, bukan sebaliknya.
     *
     * TIDAK BOLEH di-update/dihapus siapa pun termasuk owner — ditegakkan
     * `InventoryTransactionObserver` (Fase berikutnya), bukan cuma konvensi.
     * Salah catat dilawan baris koreksi baru (`ADJUSTMENT`), bukan edit baris
     * lama.
     *
     * Kolom `from_*`/`to_*` semua NULLABLE karena artinya beda per `type`
     * (App\Enums\InventoryTransactionType) — cuma kombinasi yang relevan yang
     * keisi:
     *   RECEIVE            : to_pop_id saja (dari supplier, entitas eksternal — gak dicatat).
     *   TRANSFER (dispatch) : from_pop_id saja, inventory_transfer_id keisi.
     *   TRANSFER (confirm)  : to_pop_id saja, inventory_transfer_id keisi (baris KEDUA,
     *                         independen dari baris dispatch — dua baris per transfer).
     *   ISSUE               : from_pop_id + to_technician_id.
     *   RETURN              : from_technician_id + to_pop_id.
     *   TRANSFER_CUSTODY    : from_technician_id + to_technician_id (gak nyentuh pop mana pun).
     *   ADJUSTMENT/STOCK_OPNAME : to_pop_id ATAU to_technician_id (lokasi yang diaudit), reason wajib.
     *
     * Validasi kombinasi mana yang valid per `type` itu tugas Service, bukan
     * DB constraint (portabilitas SQLite/MySQL + kombinasi terlalu banyak
     * buat CHECK constraint yang enak dibaca).
     */
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('type', 20);
            $table->string('reference_number', 30)->nullable()->comment('TRF-/ISS-/REQ- — grouping label, BUKAN unique (satu transfer/issue bisa multi-lot/multi-baris)');
            $table->foreignId('inventory_transfer_id')->nullable()->constrained('inventory_transfers')->nullOnDelete();

            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_no', 50)->nullable()->comment('cuma kepake item tracking_type=batch — lihat rancangan-ui.md §3.8');
            $table->foreignId('serial_id')->nullable()->constrained('inventory_serials')->nullOnDelete();

            $table->decimal('qty', 12, 2);

            // Harga SAAT transaksi ini terjadi (last-cost, per-lot kalau BATCH)
            // — bukan harga master saat ini. Disalin ke task_materials pas
            // submit laporan, gak diquery ulang (§3.5 rancangan-ui.md).
            $table->decimal('unit_price_snapshot', 12, 2)->nullable();

            $table->foreignId('from_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('to_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('from_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_technician_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('fop_task_id')->nullable()->constrained('fop_tasks')->nullOnDelete();

            // `reason` kode singkat (mis. shrinkage_on_return, resign, cuti,
            // rotasi) buat ADJUSTMENT/TRANSFER_CUSTODY — wajib diisi di level
            // Service buat dua type itu (kontrol-anti-manipulasi.md §1-2, §7).
            $table->string('reason', 255)->nullable();
            $table->string('notes', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('type');
            $table->index('item_id');
            $table->index('reference_number');
            $table->index('fop_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
