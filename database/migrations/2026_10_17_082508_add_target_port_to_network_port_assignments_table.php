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
        Schema::table('network_port_assignments', function (Blueprint $table) {
            $table->foreignId('target_port_id')->nullable()->constrained('network_node_ports')->restrictOnDelete();
            $table->unique(['target_port_id', 'active_key'], 'network_assignment_target_port_active_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_port_assignments', function (Blueprint $table) {
            $table->dropUnique('network_assignment_target_port_active_unique');
            $table->dropConstrainedForeignId('target_port_id');
        });
    }
};
