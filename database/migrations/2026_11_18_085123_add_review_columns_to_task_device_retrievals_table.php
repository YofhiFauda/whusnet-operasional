<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review Penarikan Alat — laporan DEAC gagal (modem tidak berhasil
     * diambil) menunggu keputusan FOP. Kolom review ditaruh di baris laporan
     * itu sendiri, BUKAN tabel kasus induk: satu laporan = satu percobaan,
     * dan nomor percobaan dihitung dari urutan laporan per pelanggan sejak
     * `customers.terminated_at` (tidak disimpan, supaya tidak bisa menyimpang).
     *
     * `review_status` NULL = laporan `diambil` (diverifikasi gudang lewat
     * Terima Retur, bukan di sini) ATAU laporan gagal dari sebelum fitur ini
     * rilis — keduanya sengaja tidak masuk antrian dan tidak dihitung
     * (keputusan user K3 & Q2).
     *
     * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.1
     */
    public function up(): void
    {
        Schema::table('task_device_retrievals', function (Blueprint $table) {
            $table->string('review_status', 20)->nullable()->after('notes');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            // Jejak retensi foto 30 hari — UI menulis "foto dihapus" alih-alih
            // gambar rusak, dan command prune tidak memproses baris dua kali.
            $table->timestamp('photo_purged_at')->nullable()->after('review_note');

            $table->index(['review_status', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('task_device_retrievals', function (Blueprint $table) {
            $table->dropIndex(['review_status', 'reviewed_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note', 'photo_purged_at']);
        });
    }
};
