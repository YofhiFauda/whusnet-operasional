<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permintaan Stok Cabang→Pusat (2026-09-03) — jawaban gap "cabang habis
     * stok, Pusat gak sadar" (dulu cuma bisa ketauan lewat badge Stok Rendah
     * PASIF di dashboard, gak ada yang dorong sinyal ke Pusat). Header aja,
     * baris barangnya di `stock_request_items`.
     *
     * BUKAN ledger — gak ada kolom qty pergerakan barang di sini sama
     * sekali. Fulfilled cuma nandain "udah diurus", pergerakan fisiknya
     * TETAP lewat Transfer (`inventory_transactions`) terpisah, dibikin
     * manual oleh admin Pusat setelah lihat request ini.
     */
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();

            $table->foreignId('cabang_pop_id')->constrained('pops')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes', 500)->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('cabang_pop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
