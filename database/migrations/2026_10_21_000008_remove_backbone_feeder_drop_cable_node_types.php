<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keputusan user 21-10-2026: Backbone, Feeder, dan Drop Cable bukan lagi
     * tingkat pohon. Hierarki baru (§1): Server → POP → Mini POP → OLT → Slot →
     * PON → ODP → ONT dan Mini POP → Switch → Slot → Port. Ketiganya kini:
     * - Backbone  → garis uplink antar POP (`pops.uplink_node_id`).
     * - Feeder    → koneksi PON → ODP (`infrastructure_connections`) + pemakaian roll kabel.
     * - Drop core → assignment pelanggan di port ODP + pemakaian roll kabel.
     *
     * Tipe dihapus HANYA bila tidak ada node yang memakainya — migration ini
     * menolak (bukan diam-diam melewati) supaya data lama dibereskan dulu.
     * Aturan placement terkait ikut terhapus (FK cascade).
     */
    private const CODES = ['backbone', 'feeder', 'drop_cable'];

    public function up(): void
    {
        $typeIds = DB::table('network_node_types')->whereIn('code', self::CODES)->pluck('id');
        if ($typeIds->isEmpty()) {
            return;
        }

        if (DB::table('network_nodes')->whereIn('node_type_id', $typeIds)->exists()) {
            throw new RuntimeException('Masih ada node bertipe backbone/feeder/drop_cable. Pindahkan atau hapus node tersebut sebelum migrasi ini.');
        }
        if (DB::table('items')->whereIn('infrastructure_node_type_id', $typeIds)->exists()) {
            throw new RuntimeException('Masih ada Master Barang yang menunjuk tipe backbone/feeder/drop_cable. Ubah dulu Tipe Node Tujuan-nya.');
        }

        DB::table('network_node_type_rules')->whereIn('child_type_id', $typeIds)->orWhereIn('parent_type_id', $typeIds)->delete();
        DB::table('network_node_types')->whereIn('id', $typeIds)->delete();
    }

    public function down(): void
    {
        foreach ([
            ['code' => 'backbone', 'name' => 'Backbone/Transport', 'has_ports' => false],
            ['code' => 'feeder', 'name' => 'Kabel Feeder', 'has_ports' => true],
            ['code' => 'drop_cable', 'name' => 'Drop Cable', 'has_ports' => false],
        ] as $type) {
            DB::table('network_node_types')->insertOrIgnore([
                ...$type, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null,
                'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
