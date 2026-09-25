<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADHOC-95:
 * - `bank_account_id` — rekening tujuan dari Master Rekening Bank. Kolom
 *   `bank_name`/`account_number` yang sudah ada TIDAK dihapus: keduanya
 *   jadi SNAPSHOT yang diisi PaymentService dari master saat payment
 *   dicatat, supaya riwayat tetap utuh kalau rekening di master diedit/
 *   dinonaktifkan belakangan. `nullOnDelete` cuma jaring pengaman —
 *   master rekening tidak punya aksi hapus.
 * - `sender_name` — nama pengirim FAKTUAL (bisa beda dari nama pelanggan),
 *   cuma relevan untuk Transfer & Kolektor. Payment lama dibiarkan NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('bank_accounts')
                ->nullOnDelete();
            $table->string('sender_name', 150)->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('sender_name');
        });
    }
};
