<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type', 30)->default('bulanan')->after('invoice_number');
        });

        // Backfill data lama: jika ada biaya instalasi atau prorata, set sebagai 'awal'
        DB::table('invoices')
            ->where('extra_installation_fee', '>', 0)
            ->orWhere('prorate_amount', '>', 0)
            ->update(['invoice_type' => 'awal']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_type');
        });
    }
};
