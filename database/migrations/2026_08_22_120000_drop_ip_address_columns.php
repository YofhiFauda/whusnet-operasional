<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus konsep "IP Address jaringan pelanggan" dari sistem — keputusan produk
 * (2026-08-22): field ini tidak lagi dipakai, di fase development, kehilangan
 * data kolom ini disepakati boleh terjadi.
 *
 * TIGA tabel punya kolom `ip_address` terpisah untuk pelanggan (bukan
 * `audit_logs.ip_address`/`users.ip_address` yang IP request HTTP untuk
 * forensik — beda konsep total, TIDAK disentuh):
 * - `customers.ip_address` (kolom legacy langsung di pelanggan)
 * - `customer_devices.ip_address` (kredensial jaringan per perangkat)
 * - `customer_technical_details.ip_address` (data teknis import legacy)
 *
 * `internet_packages.ip_address_type` JUGA tidak disentuh — itu atribut
 * kategori paket ("dinamis"/"statis"), bukan nilai IP tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });

        Schema::table('customer_devices', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });

        Schema::table('customer_technical_details', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('ont_sn');
        });

        Schema::table('customer_devices', function (Blueprint $table) {
            $table->string('ip_address')->nullable();
        });

        Schema::table('customer_technical_details', function (Blueprint $table) {
            $table->string('ip_address', 50)->nullable();
        });
    }
};
