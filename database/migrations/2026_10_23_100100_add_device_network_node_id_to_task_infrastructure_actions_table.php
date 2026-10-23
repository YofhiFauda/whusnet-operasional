<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_infrastructure_actions', function (Blueprint $table): void {
            $table->foreignId('device_network_node_id')->nullable()->after('network_node_id')
                ->constrained('network_nodes')->restrictOnDelete();
        });

        // Baris lama hanya punya network_node_id (lokasi node langsung). Aksi slot/port
        // lama tidak punya jejak perangkat induknya di kolom mana pun, jadi tidak diisi.
        DB::table('task_infrastructure_actions')
            ->whereNotNull('network_node_id')
            ->update(['device_network_node_id' => DB::raw('network_node_id')]);
    }

    public function down(): void
    {
        Schema::table('task_infrastructure_actions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('device_network_node_id');
        });
    }
};
