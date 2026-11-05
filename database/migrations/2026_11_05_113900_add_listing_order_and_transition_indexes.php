<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_transition_permissions', function (Blueprint $table) {
            $table->unique(['from_status', 'to_status'], 'wtp_from_to_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('issue_date', 'invoices_issue_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date', 'payments_payment_date_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('updated_at', 'customers_updated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_updated_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_payment_date_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_issue_date_idx');
        });

        Schema::table('workflow_transition_permissions', function (Blueprint $table) {
            $table->dropUnique('wtp_from_to_unique');
        });
    }
};
