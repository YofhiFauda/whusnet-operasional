<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_infrastructure_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fop_task_id')->constrained()->restrictOnDelete();
            $table->uuid('action_key');
            $table->string('action_type', 32);
            $table->foreignId('network_node_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mutation_hash', 64);
            $table->json('mutation');
            $table->text('result_note')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();
            $table->unique(['task_id', 'action_key']);
        });

        Schema::create('task_infrastructure_action_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_infrastructure_action_id')
                ->constrained('task_infrastructure_actions', indexName: 'tiat_action_id_foreign')
                ->cascadeOnDelete();
            $table->foreignId('inventory_transaction_id')
                ->constrained('inventory_transactions', indexName: 'tiat_inv_trans_id_foreign')
                ->restrictOnDelete();
            $table->unique('inventory_transaction_id', 'tiat_inv_trans_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_infrastructure_action_transactions');
        Schema::dropIfExists('task_infrastructure_actions');
    }
};
