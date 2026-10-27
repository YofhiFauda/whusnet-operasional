<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asal modem pelanggan (App\Enums\DeviceAcquisitionSource). Default `sewa`
     * karena seluruh device yang ada sebelum fitur ini memang modem sewa
     * (atau data migrasi legacy yang tidak punya info pembelian).
     */
    public function up(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->string('acquisition_source', 20)->default('sewa')->after('device_retrieved_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->dropColumn('acquisition_source');
        });
    }
};
