<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger ikut titik infrastruktur jaringan — tipe DEPLOY keisi
     * `to_network_node_id` saja, RETRIEVE_INFRA keisi `from_network_node_id`
     * saja (simetris dengan pola `from_pop_id`/`to_pop_id` yang sudah ada).
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('to_network_node_id')->nullable()->after('to_pop_id')
                ->constrained('network_nodes')->nullOnDelete();
            $table->foreignId('from_network_node_id')->nullable()->after('from_pop_id')
                ->constrained('network_nodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_network_node_id');
            $table->dropConstrainedForeignId('from_network_node_id');
        });
    }
};
