<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructure_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_transaction_id')->constrained('inventory_transactions')->restrictOnDelete();
            $table->foreignId('retrieve_transaction_id')->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('roll_id')->nullable()->constrained('inventory_rolls')->restrictOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->string('status', 30)->default('pending_inspection');
            $table->text('inspection_note')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('recovered_custody_id')->nullable()->constrained('technician_custody')->nullOnDelete();
            $table->foreignId('recovered_roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_recoveries');
    }
};
