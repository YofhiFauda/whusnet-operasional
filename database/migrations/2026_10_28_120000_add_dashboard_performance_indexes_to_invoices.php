<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil EXPLAIN `perf:pages` di 15k pelanggan (36k invoice), dashboard `/`:
 * - tren keuangan `sum(total_amount) group by billing_period` → full scan +
 *   temporary table (400 ms). Index penutup (billing_period, total_amount)
 *   membuatnya index-only scan.
 * - daftar piutang `invoice_status in (...) and billing_period < ? order by
 *   billing_period, due_date` → full scan + filesort. Index
 *   (invoice_status, billing_period, due_date) melayani filter sekaligus urutannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['billing_period', 'total_amount'], 'invoices_period_total_idx');
            $table->index(['invoice_status', 'billing_period', 'due_date'], 'invoices_status_period_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_period_total_idx');
            $table->dropIndex('invoices_status_period_due_idx');
        });
    }
};
