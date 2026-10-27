<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akar scope Task FOP O-REQ/INFR-REQ (§10.3, §13). `fop_tasks.pop_id`
     * TETAP konteks Cabang; akar scope menunjuk TEPAT SATU posisi lewat FK
     * per jenis (POP/Mini POP, node, Slot, atau Port) — semua null = akar
     * jatuh ke `pop_id` (task lama sebelum fitur ini).
     *
     * `scope_snapshot` membekukan breadcrumb + rantai ID saat scope disetel;
     * `task_infrastructure_actions.scope_snapshot` membekukan scope yang
     * berlaku saat aksi disimpan — mengubah scope belakangan tidak boleh
     * mengubah tafsir laporan yang sudah tersimpan.
     */
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->foreignId('scope_pop_id')->nullable()->after('pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('scope_network_node_id')->nullable()->after('scope_pop_id')->constrained('network_nodes')->restrictOnDelete();
            $table->foreignId('scope_infrastructure_slot_id')->nullable()->after('scope_network_node_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->foreignId('scope_infrastructure_port_id')->nullable()->after('scope_infrastructure_slot_id')->constrained('infrastructure_ports')->restrictOnDelete();
            $table->json('scope_snapshot')->nullable()->after('scope_infrastructure_port_id');
        });

        Schema::table('task_infrastructure_actions', function (Blueprint $table) {
            $table->json('scope_snapshot')->nullable()->after('mutation');
        });
    }

    public function down(): void
    {
        Schema::table('task_infrastructure_actions', function (Blueprint $table) {
            $table->dropColumn('scope_snapshot');
        });

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropForeign(['scope_infrastructure_port_id']);
            $table->dropForeign(['scope_infrastructure_slot_id']);
            $table->dropForeign(['scope_network_node_id']);
            $table->dropForeign(['scope_pop_id']);
            $table->dropColumn(['scope_pop_id', 'scope_network_node_id', 'scope_infrastructure_slot_id', 'scope_infrastructure_port_id', 'scope_snapshot']);
        });
    }
};
