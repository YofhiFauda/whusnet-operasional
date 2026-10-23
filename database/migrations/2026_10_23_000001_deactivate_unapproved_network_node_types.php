<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keputusan user 2026-10-23
     * (docs/plan/infrastruktur/rancangan-hierarki-infrastruktur-server-pop-mini-pop.md
     * §17): hierarki node resmi HANYA Server/POP/Mini POP/OLT/Switch/ODP
     * (+ Slot/PON/Port lewat `infrastructure_slots`/`infrastructure_ports`,
     * bukan `network_node_types`). `core_router` dan `odc` bukan bagian
     * hierarki itu — dinonaktifkan, BUKAN dihapus, karena baris lama di
     * `network_node_type_rules` dan histori audit yang merujuk kode ini
     * harus tetap terbaca (beda dari migration 2026_10_21_000008 yang
     * menghapus backbone/feeder/drop_cable — ketiganya tidak punya baris
     * rule yang masih relevan).
     *
     * `pon_port`/`olt_card`/`aggregation_switch` ikut dinonaktifkan lewat
     * migration susulan 2026_10_23_000002, setelah diverifikasi bahwa
     * database aplikasi sudah nol baris legacy (lihat migration itu).
     */
    private const CODES = ['core_router', 'odc'];

    public function up(): void
    {
        DB::table('network_node_types')->whereIn('code', self::CODES)->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('network_node_types')->whereIn('code', self::CODES)->update(['is_active' => true]);
    }
};
