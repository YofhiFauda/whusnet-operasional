<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'invoices_customer_issue_date_index';

    /**
     * Index komposit untuk list tagihan Portal Pelanggan.
     *
     * `PortalInvoiceController::index` query per pelanggan lalu
     * `ORDER BY issue_date DESC, id DESC` dengan paginate. Index `customer_id`
     * (dari foreign key) dan `invoices_customer_period_type_idx` tidak menutup
     * urutan `issue_date`, jadi index ini dipasang.
     *
     * Idempoten: index sudah ada di beberapa database (sisa percobaan migrate
     * yang gagal di tengah — DDL MySQL tidak transaksional), jadi cek dulu.
     *
     * CATATAN: payments TIDAK dipasang di sini — `payments_customer_date_idx`
     * (customer_id, payment_date) sudah ada dari 2026_07_22_164035.
     */
    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['customer_id', 'issue_date'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! $this->indexExists()) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        // Schema::hasIndex, BUKAN `SHOW INDEX` mentah — sintaks itu MySQL-only dan
        // membuat seluruh test (sqlite :memory:) gagal saat migrate.
        return Schema::hasIndex('invoices', self::INDEX);
    }
};
