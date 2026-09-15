<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori Bisnis: Invoice AWAL tidak boleh terbit lagi di
     * `CustomerVerificationController::finalVerify()` (CS) — baru sah
     * terbit begitu Business Development juga menyetujui
     * (`BusinessDevelopmentVerificationController::verify()`). Snapshot
     * hasil `InitialInvoiceService::calculate()` yang sudah dikonfirmasi CS
     * ke pelanggan dititipkan di sini supaya BD menerbitkan invoice dengan
     * angka PERSIS yang sama — bukan dihitung ulang saat BD verifikasi
     * (harga paket/diskon bisa berubah di antara dua titik waktu itu).
     * Ditimpa null lagi begitu invoice-nya terbit.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('pending_initial_invoice')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('pending_initial_invoice');
        });
    }
};
