<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_port_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_node_port_id')->constrained('network_node_ports')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('target_node_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
            $table->string('manual_label', 150)->nullable();
            $table->unsignedTinyInteger('active_key')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['network_node_port_id', 'active_key'], 'network_port_active_unique');
            $table->unique(['customer_id', 'active_key'], 'network_customer_active_unique');
            $table->unique(['target_node_id', 'active_key'], 'network_target_active_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_port_assignments');
    }
};
