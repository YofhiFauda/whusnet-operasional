<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rute kabel melintang jalan (ADHOC-205). Header rute: satu baris = satu
     * lintasan kabel untuk satu pelanggan. Titik koordinat ada di tabel
     * `customer_cable_crossing_points`.
     *
     * POP TIDAK disimpan di sini — diambil dari `customers.pop_id` supaya ikut
     * berpindah saat pelanggan pindah Cabang (CustomerRelocationService) dan
     * tidak ada dua sumber kebenaran POP.
     *
     * Roll & meter TIDAK dicatat ke stok (penarikan tidak masuk gudang), jadi
     * `inventory_roll_id` murni penanda roll yang dipakai saat pemasangan.
     */
    public function up(): void
    {
        Schema::create('customer_cable_crossings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_installation_id')->nullable()->constrained('customer_installations')->restrictOnDelete();
            $table->foreignId('inventory_roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->string('nama_jalan', 191);
            $table->string('jenis_jalan', 100)->nullable();
            $table->decimal('meter_terpakai', 8, 2)->nullable();
            $table->string('status', 30)->default('terpasang');
            $table->string('foto_bukti');
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dicatat_at')->nullable();
            $table->foreignId('deact_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('diselesaikan_at')->nullable();
            $table->string('foto_penarikan')->nullable();
            $table->text('alasan_tidak_ditarik')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cable_crossings');
    }
};
