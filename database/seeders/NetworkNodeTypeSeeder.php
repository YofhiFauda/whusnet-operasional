<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Models\NetworkNodeType;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

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
            ['code' => 'core_router', 'name' => 'Core Router/Switch', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'backbone', 'name' => 'Backbone/Transport', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'aggregation_switch', 'name' => 'Router/Switch Agregasi POP', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'pon_port', 'name' => 'PON Port/Card', 'has_ports' => false, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null],
            ['code' => 'odc', 'name' => 'ODC', 'has_ports' => true, 'monitors_capacity' => true, 'max_ports' => 128, 'red_zone_threshold' => 80],
            ['code' => 'odp', 'name' => 'ODP', 'has_ports' => true, 'monitors_capacity' => true, 'max_ports' => 128, 'red_zone_threshold' => 80],
        ];

        foreach ($types as $index => $type) {
            NetworkNodeType::updateOrCreate(
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
        }
    }
}
