<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default `equipment_class` per kategori (App\Enums\EquipmentClass) —
     * level PERTAMA dari resolusi dua-level (§3.1 rancangan-ui.md). Level
     * kedua (override per-item) ada di `items.equipment_class_override`,
     * migration sebelumnya.
     *
     * Default kolom = 'pasif' (mayoritas kategori existing itu material
     * pasif). Cuma 2 dari 7 kategori bawaan yang perangkat aktif —
     * di-backfill eksplisit di sini, BUKAN dibiarkan admin isi manual satu
     * satu, biar dari hari pertama datanya udah bener.
     *
     * `lainnya` SENGAJA tetap default pasif (catch-all) — item aktif yang
     * kebetulan masuk situ pakai `equipment_class_override`, bukan bikin
     * kategori baru.
     */
    public function up(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->string('equipment_class', 10)->default('pasif')->after('default_unit');
        });

        DB::table('item_categories')
            ->whereIn('code', ['media_converter', 'antena_radio'])
            ->update(['equipment_class' => 'aktif']);
    }

    public function down(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->dropColumn('equipment_class');
        });
    }
};
