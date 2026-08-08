<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master alat kerja — peralatan yang DIBAWA dan DIBAWA PULANG.
     *
     * Beda tegas dari `items` (material): material habis dipakai dan ditinggal
     * di pelanggan, alat kerja tidak. Karena itu tabelnya terpisah dan TIDAK
     * punya qty — yang dijawab cuma "tim perlu bawa ini atau tidak".
     * Menggabungkannya ke `items` akan mencampur dua hal yang cara hitungnya
     * beda, persis kekacauan yang sekarang ada di `required_tools`
     * (`'Tang, Splicer, OPM, Dropcore'` — dropcore itu material yang nyasar).
     *
     * SENGAJA TIDAK ADA: stok, kepemilikan per-teknisi, lokasi penyimpanan.
     * Begitu alat punya pemilik dan lokasi, itu pelacakan aset — wilayah
     * Inventory, bukan checklist persiapan berangkat.
     */
    public function up(): void
    {
        Schema::create('work_tools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_tools');
    }
};
