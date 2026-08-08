<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah field olt_number dan olt_slot ke customer_technical_details.
     * Migration ini sebelumnya kosong — diisi sekarang agar konsisten dengan
     * kondisi database produksi yang sudah memiliki kolom ini.
     */
    public function up(): void
    {
        Schema::table('customer_technical_details', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_technical_details', 'olt_number')) {
                $table->string('olt_number', 50)->nullable()->after('olt_port');
            }
            if (! Schema::hasColumn('customer_technical_details', 'olt_slot')) {
                $table->string('olt_slot', 20)->nullable()->after('olt_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_technical_details', function (Blueprint $table) {
            if (Schema::hasColumn('customer_technical_details', 'olt_number')) {
                $table->dropColumn('olt_number');
            }
            if (Schema::hasColumn('customer_technical_details', 'olt_slot')) {
                $table->dropColumn('olt_slot');
            }
        });
    }
};
