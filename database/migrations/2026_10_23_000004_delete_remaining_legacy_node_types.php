<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keputusan user 2026-10-23 (ADHOC-212, setelah ADHOC-210 ditolak dan
     * ADHOC-211 menghapus core_router): user tegas minta keempatnya ikut
     * dihapus total, bukan sekadar nonaktif — dianggap sampah, bukan data
     * yang masih dipakai alat apa pun.
     *
     * Beda dari percobaan ADHOC-210: kali ini test file yang bergantung
     * (`InfrastructureLegacyDecisionTest`, `InfrastructureLegacyMappingTest`,
     * `InfrastructureCutoverEvidenceTest`, `LegacyInfrastructureWriteCutoverTest`,
     * `InfrastructureReconciliationTest`, `NetworkNodePlacementRuleTest`)
     * SUDAH diubah duluan supaya provisioning tipe ini sendiri
     * (`NetworkNodeType::firstOrCreate` lokal per tes), bukan gantung ke
     * baris seeder permanen. `InfrastructureLegacyDecisionService.php`
     * yang mengecek literal `->code === 'pon_port'` TIDAK disentuh — itu
     * membaca kode dari node yang ADA, bukan query baris master, jadi
     * tetap jalan walau baris default-nya dihapus.
     *
     * Database aplikasi dipastikan nol baris legacy sebelum migration ini
     * (migration 2026_10_23_000001/000002 sebelumnya hanya nonaktifkan;
     * sekarang dihapus total). Pola guard sama dengan migration
     * 2026_10_21_000008 dan 2026_10_23_000003.
     */
    private const CODES = ['odc', 'pon_port', 'olt_card', 'aggregation_switch'];

    public function up(): void
    {
        $typeIds = DB::table('network_node_types')->whereIn('code', self::CODES)->pluck('id');
        if ($typeIds->isEmpty()) {
            return;
        }

        if (DB::table('network_nodes')->whereIn('node_type_id', $typeIds)->exists()) {
            throw new RuntimeException('Masih ada node bertipe '.implode('/', self::CODES).'. Pindahkan atau hapus node tersebut sebelum migrasi ini.');
        }

        // olt_card: satu Master Barang (OLT-MODULE-GPON-8P) menunjuknya.
        // Kolom itu sudah tidak dipakai jalur deploy kartu OLT yang benar
        // (lewat infrastructure_slots + kategori olt_module, lihat
        // InventoryService::deploySerial() ~baris 711 — tidak mengecek
        // infrastructure_node_type_id untuk jalur Slot). Di-null-kan,
        // bukan item-nya yang dihapus.
        DB::table('items')->whereIn('infrastructure_node_type_id', $typeIds)->update(['infrastructure_node_type_id' => null]);
        DB::table('network_node_type_rules')->whereIn('child_type_id', $typeIds)->orWhereIn('parent_type_id', $typeIds)->delete();
        DB::table('network_node_types')->whereIn('id', $typeIds)->delete();
    }

    public function down(): void
    {
        foreach ([
            ['code' => 'odc', 'name' => 'ODC', 'has_ports' => true, 'monitors_capacity' => true, 'max_ports' => 128, 'red_zone_threshold' => 80],
            ['code' => 'pon_port', 'name' => 'PON Port/Card', 'has_ports' => false],
            ['code' => 'olt_card', 'name' => 'Kartu OLT', 'has_ports' => true, 'max_ports' => 128],
            ['code' => 'aggregation_switch', 'name' => 'Router/Switch Agregasi POP', 'has_ports' => false],
        ] as $type) {
            DB::table('network_node_types')->insertOrIgnore([
                ...$type,
                'monitors_capacity' => $type['monitors_capacity'] ?? false,
                'max_ports' => $type['max_ports'] ?? null,
                'red_zone_threshold' => $type['red_zone_threshold'] ?? null,
                'is_active' => false, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // items.infrastructure_node_type_id (olt_card) TIDAK dikembalikan —
        // baris tipe yang dibuat ulang punya ID baru, relasi lama tidak
        // bisa ditebak ulang.
    }
};
