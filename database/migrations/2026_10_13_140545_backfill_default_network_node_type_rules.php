<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('network_node_types')->pluck('id', 'code');
        $defaultRules = [
            'core_router' => ['pops' => ['pusat', 'cabang'], 'nodes' => []],
            'backbone' => ['pops' => ['pusat', 'cabang'], 'nodes' => ['core_router']],
            'aggregation_switch' => ['pops' => ['cabang'], 'nodes' => ['backbone', 'core_router']],
            // Lihat catatan di NetworkNodeTypeSeeder: Mini POP sekarang lokasi
            // fisik yang bisa menaungi beberapa chassis OLT, jadi chassis
            // boleh anchor Mini POP ATAU Cabang; kartu OLT wajib jadi anak
            // chassis, bukan anchor POP langsung.
            'olt_chassis' => ['pops' => ['mini_pop', 'cabang'], 'nodes' => []],
            'olt_card' => ['pops' => [], 'nodes' => ['olt_chassis']],
            'pon_port' => ['pops' => ['mini_pop'], 'nodes' => ['olt_card']],
            'feeder' => ['pops' => ['mini_pop'], 'nodes' => ['olt_chassis']],
            'odc' => ['pops' => ['mini_pop'], 'nodes' => ['olt_card', 'pon_port', 'feeder']],
            'odp' => ['pops' => ['cabang', 'mini_pop'], 'nodes' => ['odc']],
        ];

        foreach ($defaultRules as $childCode => $allowed) {
            $childId = $ids[$childCode] ?? null;
            if ($childId === null || DB::table('network_node_type_rules')->where('child_type_id', $childId)->exists()) {
                continue;
            }

            foreach ($allowed['pops'] as $popType) {
                DB::table('network_node_type_rules')->insert([
                    'child_type_id' => $childId,
                    'anchor_pop_type' => $popType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($allowed['nodes'] as $parentCode) {
                if (! isset($ids[$parentCode])) {
                    continue;
                }

                DB::table('network_node_type_rules')->insert([
                    'child_type_id' => $childId,
                    'parent_type_id' => $ids[$parentCode],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Aturan dapat diedit admin; rollback tidak boleh menghapus perubahan operasional.
    }
};
