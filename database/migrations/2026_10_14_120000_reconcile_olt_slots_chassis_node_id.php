<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move legacy POP slots only when each POP has exactly one possible
     * chassis. Ambiguous records require an explicit data decision.
     */
    public function up(): void
    {
        if (! Schema::hasTable('olt_slots') || ! Schema::hasColumn('olt_slots', 'pop_id')) {
            return;
        }

        if (Schema::hasColumn('olt_slots', 'chassis_node_id')) {
            throw new RuntimeException('olt_slots has both owner columns. Inspect the partial migration before retrying.');
        }

        $chassisBySlot = [];

        foreach (DB::table('olt_slots')->get(['id', 'pop_id', 'number', 'card_node_id']) as $slot) {
            $chassisIds = DB::table('network_nodes')
                ->join('network_node_types', 'network_nodes.node_type_id', '=', 'network_node_types.id')
                ->where('network_node_types.code', 'olt_chassis')
                ->where('network_nodes.anchor_pop_id', $slot->pop_id)
                ->whereNull('network_nodes.parent_id')
                ->pluck('network_nodes.id');

            if ($chassisIds->count() !== 1) {
                throw new RuntimeException(
                    "Slot OLT #{$slot->id} pada POP #{$slot->pop_id} memiliki {$chassisIds->count()} kandidat chassis. Tentukan pemiliknya sebelum migrasi."
                );
            }

            $chassisId = (int) $chassisIds->first();

            if ($slot->card_node_id !== null) {
                $card = DB::table('network_nodes')
                    ->where('id', $slot->card_node_id)
                    ->first(['parent_id', 'anchor_pop_id']);

                if ($card === null
                    || ($card->parent_id !== null && (int) $card->parent_id !== $chassisId)
                    || ($card->anchor_pop_id !== null && (int) $card->anchor_pop_id !== (int) $slot->pop_id)) {
                    throw new RuntimeException(
                        "Kartu pada slot OLT #{$slot->id} tidak cocok dengan chassis POP #{$slot->pop_id}. Periksa relasinya sebelum migrasi."
                    );
                }
            }

            $chassisBySlot[$slot->id] = $chassisId;
        }

        Schema::table('olt_slots', function (Blueprint $table): void {
            $table->dropForeign(['pop_id']);
            $table->dropUnique(['pop_id', 'number']);
        });

        Schema::table('olt_slots', function (Blueprint $table): void {
            $table->foreignId('chassis_node_id')
                ->after('id')
                ->nullable()
                ->constrained('network_nodes')
                ->restrictOnDelete();
        });

        foreach ($chassisBySlot as $slotId => $chassisId) {
            DB::table('olt_slots')->where('id', $slotId)->update(['chassis_node_id' => $chassisId]);
        }

        Schema::table('olt_slots', function (Blueprint $table): void {
            $table->dropColumn('pop_id');
            $table->unsignedBigInteger('chassis_node_id')->nullable(false)->change();
            $table->unique(['chassis_node_id', 'number']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('olt_slots')
            || ! Schema::hasColumn('olt_slots', 'chassis_node_id')
            || Schema::hasColumn('olt_slots', 'pop_id')) {
            return;
        }

        $popBySlot = [];
        $legacyNumbers = [];

        foreach (DB::table('olt_slots')->get(['id', 'chassis_node_id', 'number']) as $slot) {
            $popId = DB::table('network_nodes')
                ->join('network_node_types', 'network_nodes.node_type_id', '=', 'network_node_types.id')
                ->where('network_nodes.id', $slot->chassis_node_id)
                ->where('network_node_types.code', 'olt_chassis')
                ->value('network_nodes.anchor_pop_id');

            if ($popId === null) {
                throw new RuntimeException(
                    "Slot OLT #{$slot->id} tidak memiliki POP chassis yang dapat dipulihkan. Rollback dihentikan."
                );
            }

            $legacyKey = "{$popId}:{$slot->number}";

            if (isset($legacyNumbers[$legacyKey])) {
                throw new RuntimeException(
                    "POP #{$popId} memiliki nomor slot {$slot->number} pada lebih dari satu chassis. Rollback dihentikan."
                );
            }

            $legacyNumbers[$legacyKey] = true;
            $popBySlot[$slot->id] = (int) $popId;
        }

        Schema::table('olt_slots', function (Blueprint $table): void {
            $table->dropForeign(['chassis_node_id']);
            $table->dropUnique(['chassis_node_id', 'number']);
        });

        Schema::table('olt_slots', function (Blueprint $table): void {
            $table->foreignId('pop_id')
                ->after('id')
                ->nullable()
                ->constrained('pops')
                ->restrictOnDelete();
        });

        foreach ($popBySlot as $slotId => $popId) {
            DB::table('olt_slots')->where('id', $slotId)->update(['pop_id' => $popId]);
        }

        Schema::table('olt_slots', function (Blueprint $table): void {
            $table->dropColumn('chassis_node_id');
            $table->unsignedBigInteger('pop_id')->nullable(false)->change();
            $table->unique(['pop_id', 'number']);
        });
    }
};
