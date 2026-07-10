<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah field VLAN ke customer_technical_details.
     *
     * Catatan: olt_number dan olt_slot sudah ada di database (ditambahkan
     * via migration sebelumnya yang kosong tapi dieksekusi manual).
     * Migration ini hanya menambahkan field vlan yang belum ada.
     */
    public function up(): void
    {
        Schema::table('customer_technical_details', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_technical_details', 'vlan')) {
                $table->string('vlan', 20)->nullable()->after('olt_slot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_technical_details', function (Blueprint $table) {
            if (Schema::hasColumn('customer_technical_details', 'vlan')) {
                $table->dropColumn('vlan');
            }
        });
    }
};
