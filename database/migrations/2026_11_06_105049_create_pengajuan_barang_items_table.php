<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris barang Surat Pengajuan Barang — `nama_barang`/`spesifikasi`
     * teks bebas (SENGAJA bukan FK ke `items`, barangnya justru belum ada
     * di katalog gudang). `keterangan_link` URL manual opsional, dirender
     * jadi `<a href>` di PDF (dompdf support).
     */
    public function up(): void
    {
        Schema::create('pengajuan_barang_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_barang_id')->constrained('pengajuan_barang')->cascadeOnDelete();

            $table->string('nama_barang', 255);
            $table->string('spesifikasi', 255)->nullable();
            $table->decimal('jumlah', 12, 2);
            $table->string('satuan', 30);
            $table->string('keterangan_link', 500)->nullable();
            $table->unsignedInteger('urutan')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_barang_items');
    }
};
