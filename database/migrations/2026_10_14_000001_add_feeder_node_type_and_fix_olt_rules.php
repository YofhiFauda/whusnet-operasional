<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * DB yang migration lama (140545, 145033) sudah jalan tidak pernah
     * dapat tipe `feeder` maupun aturan ODC → feeder — seeder/backfill lama
     * tidak tahu tipe ini. Migration ini menyusulkannya dengan pola yang
     * sama seperti 145033 (idempotent, pakai exists-check) supaya DB lama
     * dan DB baru (lewat NetworkNodeTypeSeeder) berakhir di aturan final
     * yang sama.
     */
    public function up(): void
    {
        if (! DB::table('network_node_types')->where('code', 'feeder')->exists()) {
            DB::table('network_node_types')->insert([
                'code' => 'feeder',
                'name' => 'Kabel Feeder',
                'has_ports' => true,
                'max_ports' => null,
                'monitors_capacity' => false,
                'is_active' => true,
                'sort_order' => 36,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $feederTypeId = DB::table('network_node_types')->where('code', 'feeder')->value('id');
        $chassisTypeId = DB::table('network_node_types')->where('code', 'olt_chassis')->value('id');
        $odcTypeId = DB::table('network_node_types')->where('code', 'odc')->value('id');

        if (! DB::table('network_node_type_rules')->where('child_type_id', $feederTypeId)->exists()) {
            // Feeder boleh anchor langsung ke Mini POP (jalur manual, belum
            // ada chassis tercatat) ATAU jadi anak chassis OLT (jalur normal
            // chassis -> feeder -> ODC).
            DB::table('network_node_type_rules')->insert([
                'child_type_id' => $feederTypeId,
                'anchor_pop_type' => 'mini_pop',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('network_node_type_rules')->insert([
                'child_type_id' => $feederTypeId,
                'parent_type_id' => $chassisTypeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tipe `odc` baru digenerate oleh NetworkNodeTypeSeeder (bukan
        // migration manapun) — di DB yang baru di-migrate tapi belum
        // di-seed, baris ini belum ada. Skip, biarkan seeder yang mengisi
        // aturan ODC lengkap (termasuk parent feeder) lewat $defaultRules.
        $odcFeederRuleExists = $odcTypeId !== null && DB::table('network_node_type_rules')
            ->where('child_type_id', $odcTypeId)
            ->where('parent_type_id', $feederTypeId)
            ->exists();
        if ($odcTypeId !== null && ! $odcFeederRuleExists) {
            // ODC yang sudah punya aturan parent olt_card/pon_port (dari
            // seeder/145033) tetap dipertahankan — ini cuma menambah baris
            // baru supaya ODC juga boleh jadi anak feeder.
            DB::table('network_node_type_rules')->insert([
                'child_type_id' => $odcTypeId,
                'parent_type_id' => $feederTypeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Aturan dapat diedit admin; rollback tidak boleh menghapus perubahan operasional.
    }
};
