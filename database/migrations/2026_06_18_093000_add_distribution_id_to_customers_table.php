<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds distribution_id FK to customers table so that each customer
     * is linked to a specific distribution area (part of the CID generation chain:
     * {pop.cid_prefix}{olt_number}{distribution.code}{customer_code}_{village}_{name})
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('distribution_id')
                ->nullable()
                ->after('pop_id')
                ->constrained('distributions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['distribution_id']);
            $table->dropColumn('distribution_id');
        });
    }
};
