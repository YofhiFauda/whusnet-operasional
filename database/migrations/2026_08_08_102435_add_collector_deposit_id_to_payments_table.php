<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "payment ini sudah ikut disetorkan di setoran mana".
     *
     * Setoran menyimpan DAFTAR PAYMENT (relasi ini), bukan angka beku. Alasan:
     * kolektor bisa terus menagih selagi setorannya menunggu verifikasi —
     * kalau yang tersimpan cuma total, penagihan sesudah submit ikut terhitung
     * ke setoran yang sedang diperiksa, dan angkanya berubah di belakang admin
     * yang sedang menghitung uang.
     *
     * `null` = belum disetor = masih jadi saldo di tangan kolektor. Itulah
     * satu-satunya definisi saldo di sistem ini (tidak ada kolom saldo).
     *
     * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.1, §11.4.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('collector_deposit_id')->nullable()->after('payment_batch_id')
                ->constrained('collector_deposits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collector_deposit_id');
        });
    }
};
