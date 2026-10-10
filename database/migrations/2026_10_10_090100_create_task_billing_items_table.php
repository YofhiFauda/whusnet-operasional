<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris breakdown biaya per task (C-REQ & MTN). Satu-satunya rincian biaya;
 * tabel invoices tetap flat (subtotal + total_amount + 1 description).
 * Lihat docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md §3.2.
 *
 * Semua FK di tabel ini restrictOnDelete — tabel finansial. Baris ditulis di
 * transaksi yang sama sesudah invoice terbit, jadi invoice_id selalu terisi.
 * inventory_serial_sale_id nullable: hanya baris "Jual Modem" yang punya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('name', 150);
            $table->decimal('amount', 12, 2);
            $table->foreignId('inventory_serial_sale_id')->nullable()->constrained('inventory_serial_sales')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_billing_items');
    }
};
