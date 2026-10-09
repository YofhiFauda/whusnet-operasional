<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok gudang SAAT INI — proyeksi yang diturunkan dari `inventory_transactions`
     * (ledger), BUKAN sumber kebenaran sendiri (§25 analisa pertama). Tiap kali
     * Service menulis baris ledger, baris ini di-increment/decrement mengikuti —
     * kalau suatu saat meragukan, hitung ulang dari SUM(inventory_transactions),
     * bukan percaya angka di sini begitu saja.
     *
     * Gudang = `pops` yang `type` pusat/cabang — SENGAJA TIDAK ADA tabel
     * `warehouses` terpisah (reuse `pops.id`, sesuai §29.9 analisa pertama).
     * mini_pop TIDAK PERNAH muncul sebagai `pop_id` di sini (ditegakkan
     * Service, bukan constraint DB — migration gak bisa cek `pops.type` di
     * level FK).
     *
     * `lot_no` NOT NULL dengan default '' (bukan nullable) — item non-BATCH
     * selalu '' secara konsisten, biar constraint UNIQUE di bawah bekerja
     * benar. Kalau nullable, banyak NULL dianggap "beda" oleh sebagian besar
     * DB engine (unique constraint gak menjaga apa pun), padahal yang
     * dimaksud justru "satu baris per (gudang, barang) buat non-lot".
     */
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_no', 50)->default('');

            $table->decimal('qty', 12, 2)->default(0);

            // Per-gudang, BUKAN per-produk global (§16.4 doc advanced) — POP-PON
            // butuh minimum beda dari POP-MADIUN.
            $table->decimal('minimum_stock', 12, 2)->nullable();
            $table->decimal('maximum_stock', 12, 2)->nullable();

            $table->timestamps();

            $table->unique(['pop_id', 'item_id', 'lot_no']);
            $table->index(['item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
