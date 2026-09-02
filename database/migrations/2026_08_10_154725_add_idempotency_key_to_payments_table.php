<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penahan submit dobel untuk pembayaran dari menu Tagihan.
     *
     * Jalur kolektor sudah lama punya `payment_batches.idempotency_key`; jalur
     * Tagihan tidak pernah punya apa pun. Yang menahannya selama ini cuma pola
     * PRG — dan itu hanya mencegah refresh, bukan klik dobel maupun retry saat
     * koneksi terputus.
     *
     * Pengaman `remaining_amount <= 0` di dalam transaksi menahan payment kedua
     * HANYA kalau yang pertama sudah melunasi. Untuk cicilan sebagian, dua kali
     * "bayar Rp 50.000" atas tagihan Rp 110.000 tersimpan dua-duanya, dan
     * keduanya sah menurut sistem — tak ada yang bisa membedakannya dari dua
     * cicilan yang memang benar-benar terjadi.
     *
     * Nullable: seluruh pembayaran lama (termasuk hasil migrasi legacy dan
     * jalur batch kolektor) tidak punya kunci ini, dan tidak boleh dipaksa
     * punya. Unique memperlakukan NULL sebagai berbeda satu sama lain, jadi
     * baris lama tidak saling bertabrakan.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->unique()->after('payment_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
