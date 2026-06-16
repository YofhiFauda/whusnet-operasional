<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('internet_packages', 'old_package_id')) {
                $table->string('old_package_id', 50)->nullable()->after('package_code');
            }
        });

        Schema::table('customer_services', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_services', 'old_request_id')) {
                $table->string('old_request_id', 50)->nullable()->after('internet_package_id');
            }
            if (!Schema::hasColumn('customer_services', 'old_cost_id')) {
                $table->string('old_cost_id', 50)->nullable()->after('old_request_id');
            }
            if (!Schema::hasColumn('customer_services', 'request_status')) {
                $table->string('request_status', 50)->nullable()->after('old_cost_id');
            }
            if (!Schema::hasColumn('customer_services', 'installation_status')) {
                $table->string('installation_status', 100)->nullable()->after('request_status');
            }
            if (!Schema::hasColumn('customer_services', 'network_type')) {
                $table->string('network_type', 50)->nullable()->after('installation_status');
            }
            if (!Schema::hasColumn('customer_services', 'member_type')) {
                $table->string('member_type', 50)->nullable()->after('network_type');
            }
            if (!Schema::hasColumn('customer_services', 'reason')) {
                $table->text('reason')->nullable()->after('member_type');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'old_invoice_id')) {
                $table->string('old_invoice_id', 50)->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('invoices', 'old_cost_id')) {
                $table->string('old_cost_id', 50)->nullable()->after('old_invoice_id');
            }
            if (!Schema::hasColumn('invoices', 'old_request_id')) {
                $table->string('old_request_id', 50)->nullable()->after('old_cost_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'old_payment_id')) {
                $table->string('old_payment_id', 50)->nullable()->after('payment_number');
            }
            if (!Schema::hasColumn('payments', 'old_transaction_id')) {
                $table->string('old_transaction_id', 50)->nullable()->after('old_payment_id');
            }
            if (!Schema::hasColumn('payments', 'old_request_id')) {
                $table->string('old_request_id', 50)->nullable()->after('old_transaction_id');
            }
            if (!Schema::hasColumn('payments', 'billing_period')) {
                $table->string('billing_period', 50)->nullable()->after('old_request_id');
            }
            if (!Schema::hasColumn('payments', 'received_by_old')) {
                $table->string('received_by_old', 50)->nullable()->after('billing_period');
            }
            if (!Schema::hasColumn('payments', 'deposited_by_old')) {
                $table->string('deposited_by_old', 50)->nullable()->after('received_by_old');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'customer_type')) {
                $table->string('customer_type', 50)->nullable()->after('gender');
            }
            if (!Schema::hasColumn('customers', 'company_name')) {
                $table->string('company_name', 150)->nullable()->after('customer_type');
            }
            if (!Schema::hasColumn('customers', 'npwp')) {
                $table->string('npwp', 50)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('customers', 'old_account_status')) {
                $table->string('old_account_status', 50)->nullable()->after('npwp');
            }
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_addresses', 'old_region_id')) {
                $table->string('old_region_id', 50)->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('customer_addresses', 'old_branch_id')) {
                $table->string('old_branch_id', 50)->nullable()->after('old_region_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->dropColumn(['old_package_id']);
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropColumn(['old_request_id', 'old_cost_id', 'request_status', 'installation_status', 'network_type', 'member_type', 'reason']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['old_invoice_id', 'old_cost_id', 'old_request_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['old_payment_id', 'old_transaction_id', 'old_request_id', 'billing_period', 'received_by_old', 'deposited_by_old']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['customer_type', 'company_name', 'npwp', 'old_account_status']);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['old_region_id', 'old_branch_id']);
        });
    }
};
