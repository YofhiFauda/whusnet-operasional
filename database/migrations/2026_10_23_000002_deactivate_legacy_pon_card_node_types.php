<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keputusan user 2026-10-23 (ADHOC-209, lanjutan ADHOC-208): audit
     * 2026-10-21 (`docs/plan/infrastruktur/audit-transisi-hierarki-infrastruktur.md`
     * §7) menyisakan 7 mapping ambigu yang menunggu operator — TAPI database
     * aplikasi saat ini sudah nol baris legacy sama sekali (diverifikasi
     * lewat tinker sebelum migration ini ditulis: 0 node `pon_port`/
     * `olt_card`/`aggregation_switch`, 0 `infrastructure_legacy_mappings`
     * berstatus ambiguous). Tidak ada data nyata yang kehilangan jalur
     * pemetaan dengan menonaktifkan tipe ini.
     *
     * Nonaktifkan, BUKAN hapus baris — histori/audit lama yang merujuk
     * kode ini tetap harus terbaca (pola yang sama dengan `aggregation_switch`
     * dan migration 2026_10_23_000001).
     *
     * Ini TIDAK sama dengan menutup saklar `InfrastructureCutover` secara
     * resmi (`infrastructure:close-legacy-writes` — butuh bukti tujuh
     * item + satu siklus tutup periode rekonsiliasi nol dari scheduler,
     * belum ada di database ini). is_active=false sudah cukup menolak
     * pembuatan node baru bertipe ini lewat form/endpoint biasa; saklar
     * formal tetap dibiarkan terbuka sebagai jaring pengaman kalau ada
     * impor/migrasi data lama susulan yang butuh jalur legacy lagi.
     */
    private const CODES = ['pon_port', 'olt_card'];

    public function up(): void
    {
        DB::table('network_node_types')->whereIn('code', self::CODES)->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('network_node_types')->whereIn('code', self::CODES)->update(['is_active' => true]);
    }
};
