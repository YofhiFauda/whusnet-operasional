<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('source_inventory_transaction_id')->nullable()->constrained('inventory_transactions')->restrictOnDelete();
            $table->foreignId('source_custody_id')->nullable()->constrained('technician_custody')->nullOnDelete();
            $table->string('action_key', 80)->nullable()->unique();
            $table->char('action_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique(['action_key']);
            $table->dropConstrainedForeignId('source_inventory_transaction_id');
            $table->dropConstrainedForeignId('source_custody_id');
            $table->dropColumn('action_fingerprint');
        });
    }
};
