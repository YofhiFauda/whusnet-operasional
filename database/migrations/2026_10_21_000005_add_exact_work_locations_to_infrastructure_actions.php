<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi kerja tepat per baris aksi laporan (§10.1–10.2, Task 8).
     * `network_node_id` yang sudah ada tetap = lokasi kerja tingkat node;
     * POP/Mini POP, Slot, dan Port mendapat FK sendiri. Ujung sambungan
     * kabel (sumber/tujuan) dicatat TERPISAH dari lokasi kerja — bukan saldo.
     * `work_location_snapshot` membekukan breadcrumb + rantai ID saat aksi
     * disimpan supaya tafsir laporan lama tidak berubah bila topologi diubah.
     */
    public function up(): void
    {
        Schema::table('task_infrastructure_actions', function (Blueprint $table) {
            $table->foreignId('work_pop_id')->nullable()->after('network_node_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('work_infrastructure_slot_id')->nullable()->after('work_pop_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->foreignId('work_infrastructure_port_id')->nullable()->after('work_infrastructure_slot_id')->constrained('infrastructure_ports')->restrictOnDelete();
            $table->decimal('work_latitude', 10, 7)->nullable()->after('work_infrastructure_port_id');
            $table->decimal('work_longitude', 10, 7)->nullable()->after('work_latitude');
            $table->json('work_location_snapshot')->nullable()->after('work_longitude');
            $table->foreignId('end_from_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('end_from_network_node_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
            $table->foreignId('end_from_infrastructure_port_id')->nullable()->constrained('infrastructure_ports', indexName: 'tia_end_from_port_foreign')->restrictOnDelete();
            $table->foreignId('end_to_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('end_to_network_node_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
            $table->foreignId('end_to_infrastructure_port_id')->nullable()->constrained('infrastructure_ports', indexName: 'tia_end_to_port_foreign')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $defaultForeignColumns = [
            'work_pop_id',
            'work_infrastructure_slot_id',
            'work_infrastructure_port_id',
            'end_from_pop_id',
            'end_from_network_node_id',
            'end_to_pop_id',
            'end_to_network_node_id',
        ];

        Schema::table('task_infrastructure_actions', function (Blueprint $table) use ($defaultForeignColumns) {
            foreach ($defaultForeignColumns as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropForeign('tia_end_from_port_foreign');
            $table->dropForeign('tia_end_to_port_foreign');

            $table->dropColumn([
                ...$defaultForeignColumns,
                'end_from_infrastructure_port_id',
                'end_to_infrastructure_port_id',
                'work_latitude',
                'work_longitude',
                'work_location_snapshot',
            ]);
        });
    }
};
