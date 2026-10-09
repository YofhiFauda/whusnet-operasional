<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header MUTABLE buat Transfer Pusat→Cabang — SENGAJA BUKAN bagian dari
     * `inventory_transactions` (ledger append-only, gak boleh diedit sama
     * sekali, §6 kontrol-anti-manipulasi.md). Transfer py fase "dikirim, tapi
     * belum dikonfirmasi diterima" (`in_transit`) yang bisa berlangsung
     * berhari-hari sebelum status berubah — itu keadaan mutable, gak bisa
     * ditaruh di ledger yang immutable.
     *
     * Preseden pola ini SUDAH ADA di repo: `Ticket` (mutable `status`) selalu
     * berdampingan dengan `ticket_histories` (append-only) — bukan pola baru,
     * dipakai ulang persis buat konteks Transfer.
     *
     * `inventory_transactions` (migration berikutnya) nunjuk balik ke sini
     * lewat `inventory_transfer_id` — baris DISPATCH (decrement Pusat, dibuat
     * saat Transfer dibuat) dan baris CONFIRM (increment Cabang, dibuat saat
     * diterima) dua-duanya immutable & independen; yang berubah cuma
     * `status` di tabel INI begitu proses confirm selesai — pola satu kali
     * transisi (`in_transit` → `received`/`received_partial`), bukan
     * berulang-ulang diedit.
     */
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();

            $table->string('reference_number', 30)->unique()->comment('TRF-{tahun}-{4 digit}, global — lihat CLAUDE.md § Penomoran & ID');

            $table->foreignId('from_pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('to_pop_id')->constrained('pops')->restrictOnDelete();

            $table->string('status', 20)->default('in_transit')->comment('in_transit|received|received_partial');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
