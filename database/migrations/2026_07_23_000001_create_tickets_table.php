<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            return;
        }

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('type', 20);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // Snapshot POP pelanggan saat tiket dibuat — dipakai HasPopScope
            // (scope-nya baca kolom `pop_id` di tabel ini, bukan lewat join ke
            // customers) dan tetap valid walau POP pelanggan dipindah nanti.
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();

            $table->text('detail_keluhan');
            $table->text('catatan_teknis')->nullable();
            $table->string('priority', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // FopTask hasil auto-sync. nullOnDelete: tiket adalah catatan
            // pengirim dan harus tetap ada walau FOP menghapus task-nya —
            // status tiket jatuh ke "Terputus" (lihat Ticket::statusLabel()).
            $table->foreignId('fop_task_id')->nullable()->constrained('fop_tasks')->nullOnDelete();

            $table->timestamps();

            $table->index(['pop_id', 'created_at']);
            $table->index('created_by');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
