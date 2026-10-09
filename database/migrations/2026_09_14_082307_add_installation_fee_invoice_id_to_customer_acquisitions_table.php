<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menautkan "Biaya Instalasi" (Busdev) ke tagihan SUNGGUHAN — dikonfirmasi
     * ulang user: `installation_fee` bukan cuma catatan internal, harus ikut
     * masuk tagihan/invoice kayak `extra_installation_fee` (CS).
     *
     * SENGAJA invoice BARU & TERPISAH (tipe INSIDENTAL, kategori Jasa
     * Instalasi), BUKAN menimpa Invoice Awal yang sudah dibuat CS —
     * Invoice Awal itu lazimnya sudah dibayar di tempat saat aktivasi
     * (lihat catatan `finalVerify()`); mengubah nominalnya belakangan
     * berisiko bikin rekonsiliasi pembayaran berantakan. Invoice terpisah =
     * nol risiko ke tagihan yang sudah ada, walau kapan pun Busdev
     * mengisinya.
     *
     * Begitu invoice ini terbit, `installation_fee` baris ini TERKUNCI
     * (lihat CustomerAcquisitionController::updateInstallationFee()) —
     * koreksi nominal lewat menu Tagihan biasa (batalkan lalu tagih ulang),
     * bukan diam-diam menimpa angka di sini.
     */
    public function up(): void
    {
        Schema::table('customer_acquisitions', function (Blueprint $table) {
            $table->foreignId('installation_fee_invoice_id')
                ->nullable()
                ->after('installation_fee')
                ->constrained('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_acquisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installation_fee_invoice_id');
        });
    }
};
