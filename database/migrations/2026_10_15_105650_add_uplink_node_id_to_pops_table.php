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
        Schema::table('pops', function (Blueprint $table) {
            $table->foreignId('uplink_node_id')->nullable()->after('parent_id')->constrained('network_nodes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            $table->dropForeign(['uplink_node_id']);
            $table->dropColumn('uplink_node_id');
        });
    }
};
