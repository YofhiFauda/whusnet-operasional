<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wadah RINGAN untuk satu sesi submit batch pembayaran kolektor — cuma
     * untuk dedup (idempotency_key) + pengelompokan (siapa kolektor, siapa
     * yang submit, kapan). SENGAJA TANPA `declared_total`/`recorded_total`/
     * `variance`/status selisih.
     *
     * ⚠️ Alasannya BERUBAH (2026-08-08). Dulu: fitur Setoran Kolektor di-drop
     * dari scope (§B-11 ⛔ dokumen lama). Sekarang: Setoran DIHIDUPKAN LAGI di
     * kolektor-2.0, tapi ditaruh di tabelnya sendiri `collector_deposits`
     * (Fase 2) — bukan di sini. Batch = "satu sesi submit", Setoran = "satu
     * serah-terima uang"; satu setoran bisa memuat pembayaran dari banyak
     * batch, jadi menempelkan declared/variance ke tabel ini malah salah
     * kardinalitas. Tabel ini tetap seperti apa adanya.
     *
     * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.4.
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
