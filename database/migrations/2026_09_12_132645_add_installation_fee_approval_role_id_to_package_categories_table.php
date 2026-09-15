<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menentukan SIAPA yang wajib validasi "Biaya Instalasi" (modul Busdev,
     * `customer_acquisitions.installation_fee`) buat pelanggan dengan paket
     * di kategori ini.
     *
     * FK ke `roles` (BUKAN kode permission mentah) — desain awal (kolom
     * string berisi permission code) DIKOREKSI: dropdown ratusan permission
     * ("packages.view", "warehouse_transfer.receive", dst) terlalu teknis,
     * cuma developer yang paham. Nama role ("Business Development", "Admin")
     * jauh lebih dikenali admin non-teknis yang ngatur Master Kategori Paket.
     *
     * Ini BUKAN pengganti permission — `CustomerAcquisition::
     * canValidateInstallationFee()` tetap cek permission
     * `customer_acquisitions.installation_fee.update` LEBIH DULU (jalur
     * teknis buat admin/owner via Role Matrix biasa), role di sini cuma
     * jalur pintas praktis buat kasus umum "kategori X → role Y".
     *
     * NULL = kategori ini TIDAK butuh validasi biaya instalasi terpisah
     * (alur tetap seperti sekarang — default buat kategori non-Bisnis
     * seperti Home Broadband).
     */
    public function up(): void
    {
        Schema::table('package_categories', function (Blueprint $table) {
            $table->foreignId('installation_fee_approval_role_id')
                ->nullable()
                ->after('is_active')
                ->constrained('roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('package_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installation_fee_approval_role_id');
        });
    }
};
