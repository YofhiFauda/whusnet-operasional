<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guards against the same retry/duplicate-submit bug found in the legacy
     * data (same invoice, same date, same amount inserted many times). Only
     * applied to `payments`: the equivalent guard for `invoices`
     * (customer_id + invoice_type + billing_period) currently has pre-existing
     * violations in this database from invoices imported before the migration
     * command's dedup fix, so it's enforced at the application layer
     * (CustomerController::storeManualInvoice) instead of as a hard DB
     * constraint until that historical data is cleaned up.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['invoice_id', 'payment_date', 'amount'], 'payments_invoice_date_amount_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_invoice_date_amount_unique');
        });
    }
};
