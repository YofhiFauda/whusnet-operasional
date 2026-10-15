<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batas jumlah perangkat tambahan (AP, STB) yang boleh diberikan paket ini
     * di luar perangkat utama. Default 0 supaya paket yang sudah ada tidak
     * berubah perilakunya.
     */
    public function up(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->unsignedInteger('max_additional_devices')->default(0)->after('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->dropColumn('max_additional_devices');
        });
    }
};
