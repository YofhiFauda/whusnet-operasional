<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft pointer langkah Perangkat Tambahan (keputusan user 2026-11-14):
     * daftar SN custody yang DIPILIH teknisi, belum dipasang. Pola sama
     * `selected_inventory_serial_id` Step 5 — bisa disimpan ulang/diganti
     * berkali-kali, baru dieksekusi jadi installSerial() di storeSpeedtest().
     * Kolom (bukan tabel baru): isinya cuma daftar id sementara yang
     * dikosongkan lagi begitu terpasang.
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->json('extra_device_serial_ids')->nullable()->after('extra_devices_reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn('extra_device_serial_ids');
        });
    }
};
