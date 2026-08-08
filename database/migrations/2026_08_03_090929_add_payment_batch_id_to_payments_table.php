<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable — cuma terisi untuk payment yang lahir dari batch kolektor
     * (Fase 2). Jalur single-payment (bayar langsung dari halaman Tagihan)
     * tetap null, sesuai `collected_by` yang juga null di jalur itu
     * (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-3).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_batch_id')->nullable()->after('invoice_id')
                ->constrained('payment_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_batch_id');
        });
    }
};
