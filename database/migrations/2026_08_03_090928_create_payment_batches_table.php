<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wadah RINGAN untuk satu sesi submit batch pembayaran kolektor — cuma
     * untuk dedup (idempotency_key) + pengelompokan (siapa kolektor, siapa
     * admin, kapan disubmit). SENGAJA TANPA `declared_total`/`recorded_total`/
     * `variance`/status selisih — itu bagian dari fitur Setoran Kolektor
     * (rekonsiliasi kas) yang di-drop dari scope (lihat §B-11 di
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md, ditandai
     * DILUAR SCOPE 2026-08-01). Kalau fitur itu diaktifkan lagi nanti, tabel
     * ini yang diperluas — jangan bikin tabel baru lagi.
     *
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-7 #6,
     * §D-9 no. 2 (disederhanakan).
     */
    public function up(): void
    {
        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_batches');
    }
};
