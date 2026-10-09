<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paralel `serial_id` — BUKAN ganti nama kolom lama, banyak kode baca
     * `serial_id` eksplisit. RECEIVE/TRANSFER/ISSUE/RETURN/ADJUSTMENT roll
     * nulis ledger di sini (qty = length_total/length_remaining saat
     * kejadian); pemakaian harian (potong meter) TIDAK menulis baris ledger
     * di sini — lihat docblock inventory_rolls.
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('roll_id')->nullable()->after('serial_id')
                ->constrained('inventory_rolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roll_id');
        });
    }
};
