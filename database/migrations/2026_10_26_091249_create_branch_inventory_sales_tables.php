<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_inventory_sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->foreignId('inventory_transfer_id')->unique()->constrained('inventory_transfers')->restrictOnDelete();
            $table->foreignId('from_pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('to_pop_id')->constrained('pops')->restrictOnDelete();
            $table->decimal('posted_total', 16, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['from_pop_id', 'created_at']);
            $table->index(['to_pop_id', 'created_at']);
        });

        Schema::create('branch_inventory_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_inventory_sale_id')->constrained('branch_inventory_sales')->restrictOnDelete();
            $table->foreignId('inventory_serial_id')->constrained('inventory_serials')->restrictOnDelete();
            $table->decimal('sale_price', 12, 2);
            $table->timestamps();
            $table->unique(['branch_inventory_sale_id', 'inventory_serial_id'], 'branch_sale_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_inventory_sale_lines');
        Schema::dropIfExists('branch_inventory_sales');
    }
};
