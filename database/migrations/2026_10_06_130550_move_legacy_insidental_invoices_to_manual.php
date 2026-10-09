<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enum `InvoiceType::INSIDENTAL` sudah digabung ke `MANUAL`. Tanpa migrasi data,
 * baris invoice yang masih bertipe `insidental` akan gagal di-cast saat dibaca
 * (Invoice::casts() memakai InvoiceType::class).
 *
 * Down() sengaja kosong: setelah digabung, tidak ada cara membedakan invoice
 * yang dulu INSIDENTAL dari yang memang MANUAL sejak awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where('invoice_type', 'insidental')
            ->update(['invoice_type' => 'manual']);
    }

    public function down(): void
    {
        // Tidak bisa dibalik: lihat komentar di atas.
    }
};
