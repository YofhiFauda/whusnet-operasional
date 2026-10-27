<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keputusan user 2026-10-23 (ADHOC-211, setelah penolakan percobaan
     * hapus total di ADHOC-210): HANYA `core_router` yang benar-benar aman
     * dihapus total, beda dari `odc`/`pon_port`/`olt_card`/
     * `aggregation_switch` yang tetap `is_active=false` (bukan dihapus).
     *
     * Beda nyata, bukan sekadar hati-hati berlebihan:
     * - `core_router`: NOL referensi di kode produksi (`grep -rn "'core_router'" app/`
     *   kosong). Hanya dipakai test sebagai tipe generik "tanpa port" —
     *   semua titik pakainya sudah diganti tipe buatan sendiri.
     * - `pon_port`: dicek literal di `InfrastructureLegacyDecisionService.php`
     *   (`$ancestor->nodeType?->code === 'pon_port'`) — bagian NYATA dari
     *   alat keputusan data lama (ADHOC-202 Task 11), bukan sisa kode mati.
     * - `odc`/`olt_card`/`aggregation_switch`: dipakai bareng `pon_port`
     *   sebagai satu rangkaian fixture topologi lama di
     *   `InfrastructureLegacyDecisionTest`/`InfrastructureLegacyMappingTest`
     *   (chassis→pon_port→odc→...) — tidak bisa dipisah sebagian.
     * Menghapus keempat itu mematikan alat & test legacy-mapping yang
     * sengaja dibangun buat menangani data lama kalau muncul lagi dari
     * impor/migrasi susulan (dibuktikan: percobaan ADHOC-210 merusak 51 test).
     */
    private const CODE = 'core_router';

    public function up(): void
    {
        $typeId = DB::table('network_node_types')->where('code', self::CODE)->value('id');
        if ($typeId === null) {
            return;
        }

        if (DB::table('network_nodes')->where('node_type_id', $typeId)->exists()) {
            throw new RuntimeException('Masih ada node bertipe core_router. Pindahkan atau hapus node tersebut sebelum migrasi ini.');
        }
        if (DB::table('items')->where('infrastructure_node_type_id', $typeId)->exists()) {
            throw new RuntimeException('Masih ada Master Barang yang menunjuk tipe core_router.');
        }

        DB::table('network_node_type_rules')->where('child_type_id', $typeId)->orWhere('parent_type_id', $typeId)->delete();
        DB::table('network_node_types')->where('id', $typeId)->delete();
    }

    public function down(): void
    {
        DB::table('network_node_types')->insertOrIgnore([
            'code' => self::CODE, 'name' => 'Core Router/Switch', 'has_ports' => false,
            'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null,
            'is_active' => false, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
