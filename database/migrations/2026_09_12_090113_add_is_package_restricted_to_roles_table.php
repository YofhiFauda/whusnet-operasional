<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Flag role mana yang cuma boleh pilih paket dari daftar
            // `restricted_packages` (Skema 1 — Restriksi Paket per Role,
            // 2026-09-12). Default false — role manapun yang tidak
            // ditandai tetap lihat SEMUA paket aktif seperti sekarang.
            // Ditoggle lewat halaman Role Management yang sudah ada, BUKAN
            // hardcode di kode — biar Business Development/Owner bisa
            // menambah role lain ke daftar restriksi ke depan tanpa migrasi.
            $table->boolean('is_package_restricted')->default(false)->after('is_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_package_restricted');
        });
    }
};
