<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_termination_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Prefill/titik awal nominal denda untuk pelanggan masa langganan
            // <=1 tahun (ADHOC-69 §3.1) — TIDAK pernah dipakai otomatis,
            // admin wajib konfirmasi/ubah sebelum submit.
            $table->decimal('default_penalty_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_termination_reasons');
    }
};
