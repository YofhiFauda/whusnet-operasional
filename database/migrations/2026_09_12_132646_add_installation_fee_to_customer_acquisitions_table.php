<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Biaya Instalasi" khusus paket Bisnis — diisi manual oleh Busdev
     * SETELAH alur verifikasi normal (prorate dkk, divalidasi CS) selesai
     * seperti biasa. TIDAK menyentuh WorkflowTransition/invoice awal sama
     * sekali — nomor ini murni catatan tambahan Busdev, terpisah dari
     * `extra_installation_fee` di invoice (yang diisi CS/admin saat
     * `CustomerVerificationController::finalVerify()`).
     *
     * Kategori paket mana yang butuh field ini diisi ditentukan dinamis
     * lewat `package_categories.installation_fee_approval_permission` —
     * lihat CustomerAcquisition::needsInstallationFeeValidation().
     */
    public function up(): void
    {
        Schema::table('customer_acquisitions', function (Blueprint $table) {
            $table->decimal('installation_fee', 15, 2)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_acquisitions', function (Blueprint $table) {
            $table->dropColumn('installation_fee');
        });
    }
};
