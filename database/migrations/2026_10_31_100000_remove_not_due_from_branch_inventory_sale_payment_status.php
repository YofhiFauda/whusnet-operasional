<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `not_due` (total nol karena semua SN hilang) pernah dipakai sebagai nilai kedua
     * `payment_status`. Gudang tidak mencatat status bayar, jadi semua baris kembali
     * ke satu nilai. Tidak ada kolom yang diubah, hanya data.
     */
    public function up(): void
    {
        DB::table('branch_inventory_sales')
            ->where('payment_status', 'not_due')
            ->update(['payment_status' => 'not_tracked']);
    }

    public function down(): void
    {
        // Sengaja kosong: nilai lama tidak bisa direkonstruksi dan tidak dibaca di mana pun.
    }
};
