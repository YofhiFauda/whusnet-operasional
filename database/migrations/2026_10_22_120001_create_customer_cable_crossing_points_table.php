<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik koordinat rute kabel melintang (ADHOC-205). `urutan` 1 = titik
     * awal (tiang / ODP / titik sambung), angka terakhir = pelanggan. Rute
     * dibaca berurutan, jadi urutan wajib unik per rute.
     *
     * Cascade dari header: titik tidak punya nilai keuangan atau riwayat
     * sendiri — hapus rute berarti hapus lintasannya.
     */
    public function up(): void
    {
        Schema::create('customer_cable_crossing_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_cable_crossing_id')
                ->constrained('customer_cable_crossings', indexName: 'crossing_points_crossing_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('urutan');
            $table->string('label', 150)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();

            $table->unique(['customer_cable_crossing_id', 'urutan'], 'cable_crossing_points_urutan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cable_crossing_points');
    }
};
