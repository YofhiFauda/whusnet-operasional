<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buat item BATCH (kabel fiber per drum) — kalau konsumsi motong lebih
     * dari satu drum sekaligus, tiap drum jadi BARIS TERPISAH di
     * `task_materials`, masing-masing bawa `lot_no`-nya sendiri +
     * `unit_price_snapshot` PER-LOT (bisa beda harga antar drum) — bukan satu
     * baris gabungan. Lihat rancangan-ui.md §3.8.
     *
     * Nullable — item QUANTITY/SERIALIZED biasa gak punya lot sama sekali.
     */
    public function up(): void
    {
        Schema::table('task_materials', function (Blueprint $table) {
            $table->string('lot_no', 50)->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_materials', function (Blueprint $table) {
            $table->dropColumn('lot_no');
        });
    }
};
