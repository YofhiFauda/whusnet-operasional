<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custody barang QUANTITY/BATCH yang dipegang teknisi — TIDAK dilacak
     * `inventory_serials` (khusus serialized per-unit). Status pakai
     * App\Enums\CustodyStatus, vocabulary SENGAJA terpisah dari SerialStatus
     * (qty custody bisa parsial, unit serial gak bisa) — lihat
     * kontrol-anti-manipulasi.md §7.
     *
     * SENGAJA TIDAK unique(technician_id, item_id, lot_no) — beda dari
     * `inventory_balances`. Satu teknisi bisa punya BEBERAPA baris custody
     * aktif buat item+lot yang sama dari ISSUE yang berbeda waktu (mis. ambil
     * drum LOT-001 hari Senin, ambil lagi drum LOT-001 hari Rabu setelah yang
     * pertama abis) — tiap ISSUE bikin baris baru, bukan menambah baris lama.
     * FIFO consumption (`InventoryService::consumeFromCustody()`, Fase Service
     * nanti) jalan lintas baris `qty_remaining > 0` diurutkan `lot_no` ASC.
     *
     * `issued_at` dipakai badge durasi custody di UI (§3 kontrol-anti-manipulasi.md
     * — badge informasional, BUKAN alert ambang waktu otomatis).
     */
    public function up(): void
    {
        Schema::create('technician_custody', function (Blueprint $table) {
            $table->id();

            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_no', 50)->nullable();

            $table->decimal('qty_remaining', 12, 2);
            $table->string('status', 20)->default('issued');

            $table->timestamp('issued_at');

            $table->timestamps();

            $table->index(['technician_id', 'status']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_custody');
    }
};
