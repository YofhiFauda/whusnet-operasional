<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Models\NetworkNodeType;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NetworkNodeTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Feature + generate permission WAJIB sebelum baris master dibuat —
        // config/rbac.php sudah mendaftarkan 'master_network_node_types' di
        // allowed_actions, tapi PermissionGeneratorService::generate() skip
        // diam-diam (cuma nyatet error di summary) kalau baris Feature-nya
        // belum ada. Tanpa ini, permission master_network_node_types.*
        // gak akan pernah terbentuk.
        Feature::updateOrCreate(
            ['code' => 'master_network_node_types'],
            [
                'name' => 'Master Tipe Node Jaringan',
                'type' => FeatureType::ROOT,
                'sort_order' => 32,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        // Feature `network_nodes` (master node ber-port, dulu `network_odps`).
        // DB lama: migration reshape 2026_10_12_110300 sudah me-rename baris
        // `network_odps` jadi kode ini, jadi updateOrCreate cuma menyentuh baris
        // itu. Instalasi baru tidak pernah punya `network_odps` — di sini
        // dibuat. Dua jalur, satu hasil akhir: tepat satu baris `network_nodes`.
        // Wajib sebelum generate() (sekali saja) supaya permission-nya terbentuk.
        Feature::updateOrCreate(
            ['code' => 'network_nodes'],
            [
                'name' => 'Master Pohon Infrastruktur Jaringan',
                'type' => FeatureType::ROOT,
                'sort_order' => 33,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        // core_router, aggregation_switch, pon_port, olt_card, odc DIHAPUS
        // TOTAL (migration 2026_10_23_000003/000004 — ADHOC-211/212,
        // keputusan user): bukan bagian hierarki resmi
        // `Server → POP → Mini POP → OLT/Switch → Slot → PON/Port → ODP → ONT`
        // (§17 rancangan hierarki), dan database aplikasi dipastikan nol
        // baris legacy yang memakainya. JANGAN ditambah balik ke sini —
        // reseed bakal menghidupkannya lagi padahal sudah dihapus permanen.
        //
        // `pon_port` dicek literal di kode produksi
        // (`InfrastructureLegacyDecisionService.php`:
        // `$ancestor->nodeType?->code === 'pon_port'`) dan kelimanya dipakai
        // sebagai fixture topologi lama di `InfrastructureLegacyDecisionTest`/
        // `InfrastructureLegacyMappingTest`/`InfrastructureCutoverEvidenceTest`/
        // `LegacyInfrastructureWriteCutoverTest`/`InfrastructureReconciliationTest`/
        // `NetworkNodePlacementRuleTest` — tes itu TIDAK ikut dihapus, cuma
        // diubah supaya provisioning tipe-nya sendiri (`firstOrCreate` lokal
        // per tes), bukan gantung ke baris seeder permanen. Jangan hapus
        // baris itu di test tanpa paham polanya — lihat komentar di tiap
        // helper `node()` masing-masing file.
        $types = [
            ['code' => 'server', 'name' => 'Server Utama', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'switch_mini_pop', 'name' => 'Switch Mini POP', 'has_ports' => true, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'olt_chassis', 'name' => 'Chassis OLT', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'odp', 'name' => 'ODP', 'has_ports' => true, 'monitors_capacity' => true, 'max_ports' => 128, 'red_zone_threshold' => 80],
            // Posisi fisik jadi node (ADHOC-213): Slot/PON/Port bukan lagi tabel terpisah.
            ['code' => 'olt_slot', 'name' => 'Slot OLT', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'olt_pon', 'name' => 'PON', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'switch_slot', 'name' => 'Slot Switch', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'switch_port', 'name' => 'Port Switch', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
        ];

        // firstOrCreate, BUKAN updateOrCreate (beda dari dua Feature di atas,
        // yang memang harus terus direkonsiliasi): tipe ini master data
        // yang boleh diubah admin lewat halaman Master Tipe Node (spec §3.1 —
        // "nilai awal, bisa diubah admin"). updateOrCreate bakal mereset
        // red_zone_threshold/max_ports/dst hasil edit admin tiap kali seeder
        // dijalankan ulang (deploy, db:seed rutin). Nilai di bawah cuma
        // dipakai saat baris pertama kali dibuat.
        $createdCodes = [];
        foreach ($types as $index => $type) {
            $createdType = NetworkNodeType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'has_ports' => $type['has_ports'],
                    'monitors_capacity' => $type['monitors_capacity'],
                    'max_ports' => $type['max_ports'],
                    'red_zone_threshold' => $type['red_zone_threshold'],
                    'is_active' => $type['is_active'] ?? true,
                    'sort_order' => $index + 1,
                ]
            );
            if ($createdType->wasRecentlyCreated) {
                $createdCodes[] = $type['code'];
            }
        }

        $defaultRules = [
            'server' => ['pops' => ['pusat'], 'nodes' => []],
            'switch_mini_pop' => ['pops' => ['mini_pop'], 'nodes' => []],
            // Mini POP sekarang sebuah LOKASI fisik, bisa menaungi beberapa
            // chassis OLT sekaligus — jadi chassis boleh anchor Mini POP ATAU
            // Cabang langsung (bukan 1:1 lagi).
            'olt_chassis' => ['pops' => ['mini_pop', 'cabang'], 'nodes' => []],
            // ODP wajib di bawah PON (anchor Cabang/Mini POP dicabut, ADHOC-213).
            'odp' => ['pops' => [], 'nodes' => ['olt_pon']],
            'olt_slot' => ['pops' => [], 'nodes' => ['olt_chassis']],
            'olt_pon' => ['pops' => [], 'nodes' => ['olt_slot']],
            'switch_slot' => ['pops' => [], 'nodes' => ['switch_mini_pop']],
            'switch_port' => ['pops' => [], 'nodes' => ['switch_slot']],
        ];
        NetworkNodeType::query()->where('code', 'ont')
            ->whereNotIn('id', DB::table('network_nodes')->select('node_type_id'))
            ->update(['is_active' => false]);

        $ids = NetworkNodeType::query()->pluck('id', 'code');
        foreach ($createdCodes as $code) {
            foreach ($defaultRules[$code]['pops'] as $popType) {
                DB::table('network_node_type_rules')->insertOrIgnore([
                    'child_type_id' => $ids[$code],
                    'anchor_pop_type' => $popType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($defaultRules[$code]['nodes'] as $parentCode) {
                DB::table('network_node_type_rules')->insertOrIgnore([
                    'child_type_id' => $ids[$code],
                    'parent_type_id' => $ids[$parentCode],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
