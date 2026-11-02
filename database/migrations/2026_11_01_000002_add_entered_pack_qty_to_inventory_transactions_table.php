<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot jumlah Pack yang diketik staf saat transaksi (Receive/Issue/
     * dll) — murni audit tampilan, BUKAN sumber kebenaran stok. `qty` (pcs)
     * tetap satu-satunya kolom yang dipakai saldo/perhitungan — kolom ini
     * cuma dibaca balik buat nampilin "2 Pack (100 pcs)" di histori supaya
     * staf yang entry pakai Pack gak perlu mengira-ngira lagi berapa Pack
     * yang dia ketik waktu itu. Nullable — transaksi yang diinput langsung
     * dalam pcs (atau item non-quantity) gak pernah mengisi kolom ini.
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('entered_pack_qty', 12, 2)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('entered_pack_qty');
        });
    }
};
