<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemulihan hapus buku di periode yang sudah tutup buku (ADHOC-105, opsi A2).
     *
     * Hapus buku yang jatuh di periode terkunci TIDAK boleh menghapus jejak
     * `written_off_*` — Laporan Bulanan periode itu harus tetap memuatnya.
     * Pemulihannya (tombol Kembalikan di List Putus Langganan) cukup mengisi
     * dua kolom ini, dan laporan membacanya "sah per tanggal": hapus buku
     * berlaku pada tanggal T bila `written_off_at < T` dan pemulihannya null
     * atau `>= T`. Pola yang sama dengan `payments.rejected_at`.
     *
     * Hapus buku di periode berjalan (belum terkunci) tetap dibatalkan dengan
     * mengosongkan `written_off_*` seperti sebelumnya. Kolom ini TETAP diisi
     * sebagai penanda "pernah dikembalikan admin" — job hapus buku otomatis
     * pelanggan putus melewati invoice bertanda ini (opsi A, ADHOC-105).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('write_off_reversed_at')->nullable()->after('write_off_reason');
            $table->foreignId('write_off_reversed_by')->nullable()->after('write_off_reversed_at')
                ->constrained('users')->nullOnDelete();

            $table->index('write_off_reversed_at', 'invoices_write_off_reversed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_write_off_reversed_at_idx');
            $table->dropConstrainedForeignId('write_off_reversed_by');
            $table->dropColumn('write_off_reversed_at');
        });
    }
};
