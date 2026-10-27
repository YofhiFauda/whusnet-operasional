<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda kategori yang boleh dipasang sebagai perangkat tambahan pelanggan
     * (AP, STB) dari stok Gudang. Default false supaya kategori yang sudah ada
     * tidak ikut muncul di dropdown perangkat tambahan tanpa keputusan admin.
     */
    public function up(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->boolean('is_customer_extra_device')->default(false)->after('equipment_class');
        });
    }

    public function down(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->dropColumn('is_customer_extra_device');
        });
    }
};
