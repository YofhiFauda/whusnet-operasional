<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Rekening Bank (ADHOC-95) — rekening resmi perusahaan yang dipilih
 * saat mencatat pembayaran Transfer, pengganti input teks bebas.
 *
 * GLOBAL, sengaja tanpa `pop_id`: satu daftar rekening company-wide, semua
 * POP pakai daftar yang sama (docs/plan/billing/analisa-rancangan-master-
 * rekening-transfer.md §1 keputusan #1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->string('account_holder_name', 150);
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Rekening yang sama tak boleh terdaftar dua kali — dropdown
            // form bayar jadi punya dua opsi kembar yang membingungkan
            // kasir, dan riwayat payment terpecah ke dua FK berbeda.
            $table->unique(['bank_name', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
