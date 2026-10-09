<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Barang SERIALIZED tanpa SN bawaan pabrik (ODP, Splitter) — SN
     * digenerate sistem sendiri di InventoryReceiveService, bukan diketik
     * manual staf. Default false: barang SERIALIZED lama (modem/ONT/router,
     * yang emang punya SN vendor asli) gak kesenggol, tetap wajib input
     * manual seperti sebelumnya. Lihat
     * docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('auto_generate_serial')->default(false)->after('tracking_type');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('auto_generate_serial');
        });
    }
};
