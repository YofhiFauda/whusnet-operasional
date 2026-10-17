<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda langkah Perangkat Tambahan sudah dilaporkan (ADHOC-190 task 4)
     * — dibaca gerbang storeSpeedtest() supaya laporan pemasangan paket
     * dengan perangkat tambahan gak bisa diselesaikan tanpa keputusan per
     * perangkat (spec §4.2).
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->timestamp('extra_devices_reported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn('extra_devices_reported_at');
        });
    }
};
