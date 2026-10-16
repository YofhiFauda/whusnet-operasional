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

        $types = [
            ['code' => 'server', 'name' => 'Server Utama', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'core_router', 'name' => 'Core Router/Switch', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'backbone', 'name' => 'Backbone/Transport', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'aggregation_switch', 'name' => 'Router/Switch Agregasi POP', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'pon_port', 'name' => 'PON Port/Card', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'olt_chassis', 'name' => 'Chassis OLT', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'olt_card', 'name' => 'Kartu OLT', 'has_ports' => true, 'monitors_capacity' => false, 'max_ports' => 128, 'red_zone_threshold' => null],
            ['code' => 'feeder', 'name' => 'Kabel Feeder', 'has_ports' => true, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'odc', 'name' => 'ODC', 'has_ports' => true, 'monitors_capacity' => true, 'max_ports' => 128, 'red_zone_threshold' => 80],
            ['code' => 'odp', 'name' => 'ODP', 'has_ports' => true, 'monitors_capacity' => true, 'max_ports' => 128, 'red_zone_threshold' => 80],
            ['code' => 'drop_cable', 'name' => 'Drop Cable', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
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
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
            if ($createdType->wasRecentlyCreated) {
                $createdCodes[] = $type['code'];
            }
        }

        $defaultRules = [
            'server' => ['pops' => ['pusat'], 'nodes' => []],
            'core_router' => ['pops' => ['pusat', 'cabang'], 'nodes' => []],
            'backbone' => ['pops' => ['pusat', 'cabang'], 'nodes' => ['core_router']],
            'aggregation_switch' => ['pops' => ['cabang'], 'nodes' => ['backbone', 'core_router']],
            // Mini POP sekarang sebuah LOKASI fisik, bisa menaungi beberapa
            // chassis OLT sekaligus — jadi chassis boleh anchor Mini POP ATAU
            // Cabang langsung (bukan 1:1 lagi). Kartu OLT WAJIB jadi anak
            // chassis (bukan anchor POP langsung) karena satu chassis bisa
            // punya banyak kartu dan kartu tanpa chassis tidak bermakna.
            'olt_chassis' => ['pops' => ['mini_pop', 'cabang'], 'nodes' => []],
            'olt_card' => ['pops' => [], 'nodes' => ['olt_chassis']],
            'pon_port' => ['pops' => ['mini_pop'], 'nodes' => ['olt_card']],
            'feeder' => ['pops' => ['mini_pop'], 'nodes' => ['olt_chassis']],
            'odc' => ['pops' => ['mini_pop'], 'nodes' => ['olt_card', 'pon_port', 'feeder']],
            'odp' => ['pops' => ['cabang', 'mini_pop'], 'nodes' => ['odc']],
            'drop_cable' => ['pops' => [], 'nodes' => []],
        ];
        NetworkNodeType::query()->where('code', 'ont')
            ->whereNotIn('id', DB::table('network_nodes')->select('node_type_id'))
            ->update(['is_active' => false]);

        $ids = NetworkNodeType::query()->pluck('id', 'code');
        foreach ([
            ['backbone', 'server'],
            ['olt_chassis', 'aggregation_switch'],
            ['pon_port', 'olt_chassis'],
            ['feeder', 'pon_port'],
            ['odp', 'feeder'],
            ['drop_cable', 'odp'],
        ] as [$childCode, $parentCode]) {
            DB::table('network_node_type_rules')->insertOrIgnore([
                'child_type_id' => $ids[$childCode],
                'parent_type_id' => $ids[$parentCode],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($createdCodes as $code) {
            foreach ($defaultRules[$code]['pops'] as $popType) {
                DB::table('network_node_type_rules')->insert([
                    'child_type_id' => $ids[$code],
                    'anchor_pop_type' => $popType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($defaultRules[$code]['nodes'] as $parentCode) {
                DB::table('network_node_type_rules')->insert([
                    'child_type_id' => $ids[$code],
                    'parent_type_id' => $ids[$parentCode],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
