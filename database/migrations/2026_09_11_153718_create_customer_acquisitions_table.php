<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Customer Acquisition (dipakai tim Busdev): satu baris per
     * pelanggan yang PERTAMA KALI diverifikasi admin (transisi
     * WorkflowTransition VERIFICATION_ADMIN → ACTIVE).
     *
     * `customer_id` UNIQUE — sengaja cuma satu baris seumur hidup per
     * pelanggan, walau nanti pelanggan sempat suspended lalu diaktifkan
     * ulang. Modul ini nyatet "akuisisi pelanggan baru" buat pemantauan
     * Busdev, bukan tiap kali status balik ke active.
     *
     * `periode` (YYYY-MM, dari bulan verifikasi) yang jadi penentu "reset
     * tanggal 1" — tabel bulan berjalan otomatis kosong lagi begitu bulan
     * berganti (query default di controller), bulan lalu tetap bisa dibuka
     * lewat filter buat monitoring/arsip.
     *
     * TANPA kolom harga manual — "Harga Dikurangi PPN" dihitung on-the-fly
     * dari `customer_services.total_monthly_bill` (lihat
     * CustomerAcquisition::getHargaDikurangiPpnAttribute()), bukan diinput
     * Busdev. Awalnya dirancang sebagai kolom manual, dikoreksi user:
     * rumusnya baku (Biaya Langganan - PPN 11%), jadi gak perlu disimpan/
     * diedit terpisah — nyimpennya cuma bikin dua sumber kebenaran yang bisa
     * menyimpang begitu Biaya Langganan berubah.
     */
    public function up(): void
    {
        Schema::create('customer_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('periode', 7)->index(); // format YYYY-MM
            $table->dateTime('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_acquisitions');
    }
};
