<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master barang/material minimum.
     *
     * SENGAJA TIDAK ADA di sini: stok, harga, lokasi gudang, minimum stock.
     * Itu wilayah modul Inventory (docs/post-mvp/inventory-fop.md). Tabel ini
     * cuma menjawab "barang apa saja yang boleh dicatat" supaya penamaan seragam
     * sejak baris pertama — tanpa master, data enam bulan ke depan bakal berisi
     * "Dropcore 1 core", "dropcore 1core", "DC 1C" untuk barang yang sama, dan
     * Inventory nanti harus membersihkannya manual. Inventory MENAMBAH kolom/tabel
     * di atas tabel ini, bukan menggantinya.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 50);
            $table->string('unit', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
