<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Surat Pengajuan Barang (gap #2 dari 3 feature report 2026-11-05,
     * keputusan desain 2026-11-06) — dokumen formal buat minta barang yang
     * BELUM ada di gudang (bukan Permintaan Stok Cabang→Pusat yang sudah ada
     * di `stock_requests`, itu buat barang yang SUDAH ada di katalog tapi
     * stoknya kosong). Header aja, baris barangnya di
     * `pengajuan_barang_items` — barangnya diketik manual (bukan FK ke
     * `items`), karena justru belum terdaftar di katalog.
     *
     * Satu keputusan approval: pihak FOP approve/reject
     * (`decided_by`/`decided_at`/`rejection_reason`). Tanda tangan "Pengaju"
     * di dokumen cetak otomatis dari `pengaju_id` (submit = tanda tangan
     * dia) — TIDAK ada kolom approval terpisah buat pengaju.
     */
    public function up(): void
    {
        Schema::create('pengajuan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_request', 30)->unique();

            $table->foreignId('pengaju_id')->constrained('users')->restrictOnDelete();
            $table->string('nama_kegiatan', 255);
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();

            $table->string('status', 20)->default('menunggu_fop');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('pop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_barang');
    }
};
