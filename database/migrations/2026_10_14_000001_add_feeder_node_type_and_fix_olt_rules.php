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
     *
     * Juga menyusulkan aturan `olt_chassis`/`olt_card` ke bentuk final Fase 0
     * (`olt_chassis` boleh anchor Cabang langsung, `olt_card` WAJIB anak
     * `olt_chassis` — lihat NetworkNodeTypeSeeder) — ini AMAN diotomasi,
     * cuma nambah/hapus baris aturan.
     *
     * KETERBATASAN YANG DIKETAHUI (SENGAJA TIDAK diotomasi di sini): kalau
     * DB ini sempat menjalankan migration `olt_slots` versi LAMA (ADHOC-166/
     * 168) yang masih menyimpan `pop_id` (bukan `chassis_node_id`), migration
     * ini TIDAK merekonsiliasi kolom itu — memetakan slot lama ke chassis
     * node yang benar butuh keputusan data-dependent yang gak bisa ditebak
     * otomatis dengan aman. Lihat §11 poin 6
     * `docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md`.
     * DB semacam itu wajib `migrate:fresh` atau migration reconciliation
     * manual tersendiri.
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

        $cardTypeId = DB::table('network_node_types')->where('code', 'olt_card')->value('id');

        // Rekonsiliasi aturan `olt_chassis`/`olt_card` ke bentuk final Fase 0
        // (lihat NetworkNodeTypeSeeder `$defaultRules`). DB yang migration
        // 145033/ADHOC-166-168 sudah jalan sebelumnya masih punya aturan
        // LAMA: chassis cuma anchor Mini POP, kartu anchor POP langsung.
        // Idempotent (exists-check per baris) — aman dijalankan di DB baru
        // (belum ada baris apa pun, seeder yang isi) maupun DB lama (baris
        // lama dikoreksi, bukan didobel).
        if ($chassisTypeId !== null) {
            // `olt_chassis` WAJIB boleh anchor Cabang langsung (chassis
            // nempel di gedung Cabang tanpa Mini POP) — baris anchor Mini
            // POP yang sudah ada TIDAK disentuh, cuma nambah Cabang.
            $chassisAnchorsCabang = DB::table('network_node_type_rules')
                ->where('child_type_id', $chassisTypeId)
                ->where('anchor_pop_type', 'cabang')
                ->exists();
            if (! $chassisAnchorsCabang) {
                DB::table('network_node_type_rules')->insert([
                    'child_type_id' => $chassisTypeId,
                    'anchor_pop_type' => 'cabang',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($cardTypeId !== null) {
            // Baris LAMA "`olt_card` anchor Mini POP langsung" sudah gak
            // berlaku — kartu OLT sekarang WAJIB jadi anak `olt_chassis`
            // (satu chassis bisa punya banyak kartu, kartu tanpa chassis
            // gak bermakna). Hapus baris stale-nya kalau masih ada.
            DB::table('network_node_type_rules')
                ->where('child_type_id', $cardTypeId)
                ->where('anchor_pop_type', 'mini_pop')
                ->delete();

            if ($chassisTypeId !== null) {
                $cardHasChassisParent = DB::table('network_node_type_rules')
                    ->where('child_type_id', $cardTypeId)
                    ->where('parent_type_id', $chassisTypeId)
                    ->exists();
                if (! $cardHasChassisParent) {
                    DB::table('network_node_type_rules')->insert([
                        'child_type_id' => $cardTypeId,
                        'parent_type_id' => $chassisTypeId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Aturan dapat diedit admin; rollback tidak boleh menghapus perubahan operasional.
    }
};
