<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `PaymentStatus::PENDING` dihapus (permintaan user 2026-08-03) — sistem
     * ini tidak punya alur verifikasi bertahap untuk pembayaran, semua jalur
     * insert (single-payment, batch kolektor) selalu menulis VALID langsung.
     * Kolom default `pending` jadi nilai yang TIDAK LAGI valid di enum PHP
     * (Payment::casts() akan ValueError kalau ada baris ke-insert tanpa
     * `payment_status` eksplisit dan jatuh ke default lama). Sudah dicek:
     * 0 baris `payment_status='pending'` di data produksi sebelum migration
     * ini dibuat.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_status', 50)->default('valid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_status', 50)->default('pending')->change();
        });
    }
};
