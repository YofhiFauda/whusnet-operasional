<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('internet_packages')
            ->where('package_code', 'NetSo1G')
            ->where('max_additional_devices', 0)
            ->update(['max_additional_devices' => 4]);
    }

    public function down(): void
    {
        // Batas paket yang telah dipakai pelanggan tidak boleh dikurangi saat rollback.
    }
};
