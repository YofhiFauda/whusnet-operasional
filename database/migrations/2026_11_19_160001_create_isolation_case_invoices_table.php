<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menautkan kasus isolir ke invoice yang jadi dasarnya
     * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.2). Satu kasus
     * bisa menautkan >1 invoice (tunggakan lintas periode); `amount_at_link`
     * snapshot nilai piutang saat ditautkan, buat audit — saldo TERBARU tetap
     * dihitung dari `invoices.remaining_amount`, bukan kolom ini.
     */
    public function up(): void
    {
        Schema::create('isolation_case_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolation_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_at_link', 15, 2);
            $table->timestamp('created_at');

            $table->unique(['isolation_case_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isolation_case_invoices');
    }
};
