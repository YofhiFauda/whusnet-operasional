<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sambungkan master barang & baris material ke master kategori.
     *
     * Dua tabel diperlakukan BEDA dan itu disengaja:
     *
     * - `items` (master) — kolom `type` DIGANTI `item_category_id`. Master tidak
     *   boleh menyimpan snapshot: kalau nama/kode kategori diubah admin, master
     *   barang harus ikut berubah, bukan mengawetkan nilai lama. Dua sumber
     *   kebenaran di sini persis pola yang sudah bikin masalah di sinkronisasi
     *   Ticket↔FopTask.
     *
     * - `task_materials` (riwayat) — `item_type` DIPERTAHANKAN sebagai snapshot
     *   string, `item_category_id` cuma ditambahkan untuk join/agregasi.
     *   Laporan pemakaian tahun lalu harus tetap terbaca apa adanya walau
     *   kategorinya sudah dihapus admin. Ini kebalikan dari master, dan memang
     *   harus kebalikan.
     *
     * Backfill lewat pencocokan `code` — tujuh kategori bawaan sengaja memakai
     * code yang sama dengan value enum `MaterialType` lama, jadi tidak ada data
     * yang perlu diterjemahkan.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('item_category_id')->nullable()->after('name')->constrained('item_categories')->restrictOnDelete();
        });

        $this->backfill('items', 'type');

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_active']);
            $table->dropColumn('type');
            $table->index(['item_category_id', 'is_active']);
        });

        Schema::table('task_materials', function (Blueprint $table) {
            $table->foreignId('item_category_id')->nullable()->after('item_type')->constrained('item_categories')->nullOnDelete();
        });

        $this->backfill('task_materials', 'item_type');
    }

    /**
     * Isi item_category_id dari kolom string kategori yang sudah ada.
     *
     * Baris dengan code yang tidak dikenal sengaja dibiarkan null, bukan
     * dilempar ke kategori "lainnya": memaksa tebakan di sini bikin data lama
     * yang salah kategori jadi tak bisa dibedakan dari yang memang "lainnya".
     */
    private function backfill(string $table, string $codeColumn): void
    {
        foreach (DB::table('item_categories')->get(['id', 'code']) as $category) {
            DB::table($table)
                ->where($codeColumn, $category->code)
                ->update(['item_category_id' => $category->id]);
        }
    }

    public function down(): void
    {
        Schema::table('task_materials', function (Blueprint $table) {
            $table->dropForeign(['item_category_id']);
            $table->dropColumn('item_category_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('type', 50)->default('lainnya')->after('name');
        });

        DB::table('items as i')
            ->join('item_categories as c', 'c.id', '=', 'i.item_category_id')
            ->update(['i.type' => DB::raw('c.code')]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['item_category_id', 'is_active']);
            $table->dropForeign(['item_category_id']);
            $table->dropColumn('item_category_id');
            $table->index(['type', 'is_active']);
        });
    }
};
