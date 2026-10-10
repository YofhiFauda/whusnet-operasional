<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean billing task MTN polos (bukan C-REQ). Mirror subset generik
 * `task_creq_details` tanpa field spesifik C-REQ (kategori, tikor, target POP).
 * Lihat docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md §3.1.
 *
 * FK induk (task) cascade karena baris ini milik task; FK ke users & invoices
 * nullOnDelete supaya pembersihan user/tagihan legacy tidak ikut menghapus
 * jejak verifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_maintenance_billing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained('tasks')->cascadeOnDelete();
            $table->boolean('is_billable')->default(false);
            $table->text('billing_note')->nullable();
            // Reuse CReqVerificationStatus — value-nya generik (pending/verified/rejected).
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_maintenance_billing_details');
    }
};
