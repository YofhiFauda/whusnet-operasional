<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

/**
 * Kategori TAMBAHAN (non-system) — bukan pengganti 7 kategori bawaan yang
 * ditanam migrasi `create_item_categories_table` (`code`-nya kontrak, dikunci
 * `is_system=true`, dipakai `CustomerSurveyController` hardcode). 2 kategori
 * di sini `is_system=false` (default kolom) — admin BOLEH edit/nonaktifkan/
 * hapus lewat Master Kategori Barang, beda dari 7 bawaan.
 *
 * Kenapa perlu, bukan numpang `media_converter` bawaan: sebelum ini, ONT
 * pelanggan (modem), Media Converter murni (kotak fiber-ke-ethernet TANPA
 * WiFi/router), DAN router Mikrotik gateway internal semua numpuk jadi satu
 * kategori generik "Media Converter" — laporan/filter per jenis barang jadi
 * gak bisa bedain "berapa ONT terpasang" dari "berapa router infra dipakai".
 * Dipisah eksplisit atas keputusan user (2026-09-02) — bukan gantiin
 * `media_converter` (barang non-ONT/non-router TETAP di situ), cuma
 * mengeluarkan 2 kelompok yang beda urusan.
 *
 * `equipment_class = aktif` buat dua-duanya — barang di sini SELALU perangkat
 * aktif per-unit (py SN), sama alasannya `media_converter`/`antena_radio`
 * bawaan di-backfill aktif di migration `add_equipment_class_to_item_categories_table`.
 *
 * Idempotent (updateOrCreate by code) — aman dijalankan ulang.
 *
 * 4 kategori TAMBAHAN lagi (2026-09-04, permintaan user "seeder buat
 * Perangkat Aktif") — melengkapi infra jaringan yang sebelumnya belum
 * punya kategori sendiri: OLT/OLT Module (hulu FTTH), Switch Jaringan,
 * SFP/SFP+ Transceiver (module optik per-unit), Access Point WiFi
 * (beda dari `router_gateway` yang gateway/PPPoE server). Sama pola —
 * `equipment_class=aktif`, `is_system=false`.
 */
class ItemCategorySeeder extends Seeder
{
    public const CODE_MODEM_ONT = 'modem_ont';

    public const CODE_ROUTER_GATEWAY = 'router_gateway';

    public const CODE_OLT_MODULE = 'olt_module';

    public const CODE_SWITCH_JARINGAN = 'switch_jaringan';

    public const CODE_SFP_TRANSCEIVER = 'sfp_transceiver';

    public const CODE_ACCESS_POINT = 'access_point';

    public function run(): void
    {
        $categories = [
            // sort_order 35 — antara Patch Cord (30) dan Media Converter (40),
            // ONT itu titik akhir instalasi pelanggan, logis dekat urutan situ.
            ['code' => self::CODE_MODEM_ONT, 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs', 'sort_order' => 35, 'equipment_class' => 'aktif'],
            // sort_order 37 — OLT itu hulu FTTH (Gudang Pusat/POP), logis
            // sebelum Media Converter (40).
            ['code' => self::CODE_OLT_MODULE, 'name' => 'OLT / OLT Module', 'default_unit' => 'pcs', 'sort_order' => 37, 'equipment_class' => 'aktif'],
            // sort_order 42-43 — Switch & SFP Transceiver, sekelompok infra
            // jaringan, antara Media Converter (40) dan Router/Gateway (45).
            ['code' => self::CODE_SWITCH_JARINGAN, 'name' => 'Switch Jaringan', 'default_unit' => 'pcs', 'sort_order' => 42, 'equipment_class' => 'aktif'],
            ['code' => self::CODE_SFP_TRANSCEIVER, 'name' => 'SFP/SFP+ Transceiver', 'default_unit' => 'pcs', 'sort_order' => 43, 'equipment_class' => 'aktif'],
            // sort_order 45 — antara Media Converter (40) dan Antena/Radio (50).
            ['code' => self::CODE_ROUTER_GATEWAY, 'name' => 'Router/Gateway', 'default_unit' => 'pcs', 'sort_order' => 45, 'equipment_class' => 'aktif'],
            // sort_order 47 — Access Point WiFi, dekat Router/Gateway (45) tapi
            // beda barang (AP murni, bukan gateway/PPPoE server).
            ['code' => self::CODE_ACCESS_POINT, 'name' => 'Access Point WiFi', 'default_unit' => 'pcs', 'sort_order' => 47, 'equipment_class' => 'aktif'],
        ];

        foreach ($categories as $category) {
            ItemCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'default_unit' => $category['default_unit'],
                    'sort_order' => $category['sort_order'],
                    'equipment_class' => $category['equipment_class'],
                    'is_active' => true,
                ]
            );
        }

        ItemCategory::flushLabelCache();

        $this->command?->info('ItemCategorySeeder: 6 kategori tambahan (modem_ont, olt_module, switch_jaringan, sfp_transceiver, router_gateway, access_point) tersedia.');
    }
}
