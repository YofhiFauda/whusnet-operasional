<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.1 (ANALISA_INDEX_DATABASE.md §9 F.3 / §13) — kolom nyata `rejected_at`
 * & `terminated_at` di `customers`.
 *
 * Menghapus subquery JSON BERKORELASI di `ORDER BY` daftar pelanggan tab
 * "Gagal"/"Putus". Versi lama mengurutkan pakai
 * `AuditLog::MAX(created_at) WHERE ... whereJsonContains(new_values->status,'rejected')`
 * yang dieksekusi SEKALI PER BARIS pelanggan sambil scan+parse JSON audit_logs —
 * biaya O(pelanggan × audit_logs), timeout begitu audit_logs membesar. Dengan
 * kolom nyata, ORDER BY jadi kolom biasa yang bisa di-index.
 *
 * Diisi oleh: CustomerWorkflowService (transisi ke 'rejected'),
 * CustomerTerminationController (terminate), jalur import legacy, dan command
 * backfill `customers:backfill-status-timestamps` untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('status');
            $table->timestamp('terminated_at')->nullable()->after('rejected_at');

            // Melayani ORDER BY per tab (status difilter dulu, lalu urut tanggal).
            $table->index(['status', 'rejected_at'], 'customers_status_rejected_idx');
            $table->index(['status', 'terminated_at'], 'customers_status_terminated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_status_rejected_idx');
            $table->dropIndex('customers_status_terminated_idx');
            $table->dropColumn(['rejected_at', 'terminated_at']);
        });
    }
};
