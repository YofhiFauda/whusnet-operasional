<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branch_inventory_sales')
            ->whereIn('payment_status', ['pending', 'unpaid'])
            ->update(['payment_status' => 'not_tracked']);

        Schema::table('branch_inventory_sales', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('not_tracked')->change();
        });
    }

    public function down(): void
    {
        Schema::table('branch_inventory_sales', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('pending')->change();
        });
    }
};
