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
        // Pelanggan terdampak di tiket batch (mis. 30 pelanggan kena satu ODP
        // LOS) — SENGAJA BUKAN tabel Ticket lagi (tidak ada TKT-/TFOP- sendiri
        // per baris, tidak lewat sync FopTask/Task per pelanggan). Satu ODP
        // rusak = satu perbaikan lapangan (satu FopTask lewat tiket parent),
        // baris di sini murni catatan siapa saja yang terdampak — lihat
        // CLAUDE.md § Sinkronisasi Ticket ↔ FopTask ↔ Task (jangan bikin
        // entitas tiket/FopTask baru per child tanpa alasan baru).
        Schema::create('ticket_batch_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // customer_id nullable — staf boleh catat pelanggan terdampak
            // yang belum ketemu lewat lookup CID (input manual nama/HP).
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('cid')->nullable();
            $table->string('customer_name');
            $table->string('phone')->nullable();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_batch_members');
    }
};
