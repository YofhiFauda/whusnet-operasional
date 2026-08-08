<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 dari docs/plan/analisa-optimasi-performa.md.
 *
 * `tickets.handler` + `tickets.status` (ditambah migration
 * 2026_07_25_000003) belum pernah punya index sejak awal, padahal
 * `handler` adalah filter utama NocDashboardController & NocWorksheetController
 * (worksheet Tiket Masuk/Assign FOP, kartu statistik NOC) — hampir selalu
 * dikombinasikan dengan `status`. Tanpa index, tiap query itu full scan
 * tabel `tickets`.
 *
 * `payments.invoice_id` sudah ada index FK implisit, tapi
 * InvoiceController::index() (query "pembayaran valid per invoice") selalu
 * menyertakan filter `payment_status` juga — composite di bawah menutup
 * kombinasi itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['handler', 'status'], 'tickets_handler_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['invoice_id', 'payment_status'], 'payments_invoice_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_handler_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_invoice_status_idx');
        });
    }
};
