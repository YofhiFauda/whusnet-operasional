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
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['type', 'serial_id'], 'inv_tx_map_type_serial_idx');
            $table->index(['to_network_node_id', 'serial_id', 'type', 'item_id'], 'inv_tx_map_node_serial_type_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inv_tx_map_type_serial_idx');
            $table->dropIndex('inv_tx_map_node_serial_type_item_idx');
        });
    }
};
