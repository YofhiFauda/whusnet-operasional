<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tutup buku bulanan per POP cabang (ADHOC-90).
     *
     * Tidak ada baris = periode itu masih terbuka, laporan dihitung live dari
     * invoices + payments. Ada baris = laporan dibaca dari `figures` (snapshot
     * beku), jadi pembayaran susulan tidak menggeser angka bulan yang sudah
     * ditutup. Satu tabel + JSON dipilih ketimbang kolom per angka: bentuk
     * laporan (4 blok) masih mungkin berubah, dan snapshot tidak pernah di-query
     * per kolom.
     *
     * `pop_id` = POP cabang/pusat (mini-POP sudah digabung ke induknya).
     */
    public function up(): void
    {
        Schema::create('period_closings', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7);
            $table->foreignId('pop_id')->constrained('pops');
            $table->json('figures');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['period', 'pop_id'], 'period_closings_period_pop_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_closings');
    }
};
