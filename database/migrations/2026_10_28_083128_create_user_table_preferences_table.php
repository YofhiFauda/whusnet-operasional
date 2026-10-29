<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferensi tampilan tabel per user (kolom terpilih + urutan). Generik by
     * `table_key` supaya tabel lain bisa ikut, tapi sekarang cuma
     * `customers.list` yang memakainya (docs/plan/rancangan-kolom-list-
     * pelanggan.md). Cascade aman: ini preferensi pribadi, bukan riwayat
     * keuangan/audit.
     */
    public function up(): void
    {
        Schema::create('user_table_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('table_key', 64);
            $table->json('columns');
            $table->timestamps();

            $table->unique(['user_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_table_preferences');
    }
};
