<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

/**
 * Isi awal master barang — katalog barang RIIL yang dipakai ISP lokal
 * sehari-hari (modem/ONT, radio wireless, kabel, splitter/ODP, patch cord,
 * aksesoris pasang), bukan daftar generik. Diperbaiki 2026-09-02 — versi
 * lama cuma 11 barang generik tanpa `tracking_type`/`ownership_mode` sama
 * sekali, jadi gak ada satupun barang aktif (modem dst) yang beneran bisa
 * masuk dropdown SN Laporan Pemasangan dari hasil seed default.
 *
 * Kategori 7 bawaan (`code`-nya kontrak, ditanam migrasi — lihat
 * `create_item_categories_table`) TIDAK ditambah/diubah di sini. 6 kategori
 * TAMBAHAN non-system (`modem_ont`, `olt_module`, `switch_jaringan`,
 * `sfp_transceiver`, `router_gateway`, `access_point`) datang dari
 * `ItemCategorySeeder`, dipanggil sebelum seeder ini di `DatabaseSeeder`.
 * Klasifikasi per barang:
 * - `tracking_type`: SERIALIZED buat barang aktif per-unit (modem/ONT/radio/
 *   router) dan barang drum/roll (BATCH, kabel feeder) — QUANTITY buat
 *   sisanya.
 * - `ownership_mode`: cuma relevan buat SERIALIZED. INSTALLABLE = dipasang
 *   ke pelanggan (boleh transisi ke `SerialStatus::INSTALLED`). COMPANY_ASSET
 *   = alat/infra perusahaan (mis. router Mikrotik gateway PPPoE, radio
 *   backbone PtP antar tower) — TIDAK PERNAH terpasang ke pelanggan.
 * - `equipment_class_override`: null di semua barang KECUALI antena grid —
 *   kategori `antena_radio` defaultnya AKTIF (dua radio wireless di bawah
 *   memang aktif), tapi antena grid piringan itu sendiri PASIF (gak ada
 *   elektronik, gak pernah dapet SN riil dari pabrik) — override eksplisit,
 *   contoh nyata kenapa mekanisme override ini ada (§3.1 rancangan-ui.md).
 *
 * Idempotent (updateOrCreate by code) — aman dijalankan ulang. `tracking_type`
 * cuma diterapkan/diganti buat barang yang BELUM py pergerakan ledger — barang
 * yang udah py `inventory_transactions` dikunci di level `ItemController`
 * (form Master Barang), tapi seeder ini nulis LANGSUNG ke model (bypass
 * controller) jadi tetap re-writeable tiap run. Itu memang perilaku yang
 * diinginkan buat seed data awal (bukan re-run di data produksi berjalan).
 */
class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // Kategori bawaan ditanam migrasi (bukan seeder) karena code-nya jadi
        // kontrak; di sini tinggal dirujuk.
        $categoryIds = ItemCategory::pluck('id', 'code');

        $items = [
            // ── Splitter / ODP — passive, quantity ──────────────────────
            ['code' => 'SPL-1X2', 'name' => 'Splitter 1:2', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'SPL-1X4', 'name' => 'Splitter 1:4', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'SPL-1X8', 'name' => 'Splitter 1:8', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'SPL-1X16', 'name' => 'Splitter 1:16', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'SPL-1X32', 'name' => 'Splitter 1:32', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'ODP-8', 'name' => 'ODP 8 Port', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'ODP-16', 'name' => 'ODP 16 Port', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'ODC-24', 'name' => 'ODC 24 Port', 'category' => 'splitter_odp', 'unit' => 'pcs'],

            // ── Kabel Dropcore — passive, quantity/batch ────────────────
            ['code' => 'DC-1C', 'name' => 'Kabel Dropcore 1 Core', 'category' => 'kabel_dropcore', 'unit' => 'meter'],
            ['code' => 'DC-2C', 'name' => 'Kabel Dropcore 2 Core', 'category' => 'kabel_dropcore', 'unit' => 'meter'],
            ['code' => 'KBL-FEEDER-12C', 'name' => 'Kabel Fiber Optik Feeder 12 Core', 'category' => 'kabel_dropcore', 'unit' => 'meter', 'tracking_type' => 'batch'],

            // ── Patch Cord — passive, quantity ──────────────────────────
            ['code' => 'PC-SCUPC-1M', 'name' => 'Patch Cord SC/UPC 1 Meter', 'category' => 'patch_cord', 'unit' => 'pcs'],
            ['code' => 'PC-SCUPC-3M', 'name' => 'Patch Cord SC/UPC 3 Meter', 'category' => 'patch_cord', 'unit' => 'pcs'],
            ['code' => 'PC-SCAPC-1M', 'name' => 'Patch Cord SC/APC 1 Meter', 'category' => 'patch_cord', 'unit' => 'pcs'],
            ['code' => 'PIGTAIL-SC', 'name' => 'Pigtail SC/UPC', 'category' => 'patch_cord', 'unit' => 'pcs'],

            // ── Modem/ONT Pelanggan — kategori TAMBAHAN (`ItemCategorySeeder`,
            //    dipanggil sebelum seeder ini di DatabaseSeeder), dipisah dari
            //    Media Converter generik biar laporan bisa bedain "berapa ONT
            //    terpasang" vs "berapa media converter dipakai".
            ['code' => 'ONT-ZTE-F609', 'name' => 'Modem ONT ZTE F609', 'category' => 'modem_ont', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'ONT-ZTE-F660', 'name' => 'Modem ONT ZTE F660', 'category' => 'modem_ont', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'ONT-HUAWEI-8245H', 'name' => 'Modem ONT Huawei HG8245H', 'category' => 'modem_ont', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'ONT-FIBERHOME-5506', 'name' => 'Modem ONT Fiberhome AN5506', 'category' => 'modem_ont', 'unit' => 'pcs', 'tracking_type' => 'serialized'],

            // ── Media Converter — kategori BAWAAN, dipersempit ke media
            //    converter murni (kotak fiber-ke-ethernet tanpa WiFi/router).
            ['code' => 'MC-100', 'name' => 'Media Converter Fiber to UTP 100 Mbps', 'category' => 'media_converter', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'MC-1000', 'name' => 'Media Converter Fiber to UTP Gigabit', 'category' => 'media_converter', 'unit' => 'pcs', 'tracking_type' => 'serialized'],

            // ── OLT / OLT Module — kategori TAMBAHAN. Hulu FTTH di Gudang
            //    Pusat/POP, TIDAK PERNAH dipasang ke pelanggan → company_asset.
            ['code' => 'OLT-ZTE-C320', 'name' => 'OLT ZTE C320 Chassis', 'category' => 'olt_module', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],
            ['code' => 'OLT-MODULE-GPON-8P', 'name' => 'OLT Module GPON 8 Port', 'category' => 'olt_module', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],

            // ── Switch Jaringan — kategori TAMBAHAN. Switch akses/distribusi
            //    di gudang/tower — infra, bukan barang pelanggan.
            ['code' => 'SW-TPLINK-24P', 'name' => 'Switch TP-Link 24 Port Gigabit', 'category' => 'switch_jaringan', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],
            ['code' => 'SW-CISCO-8P', 'name' => 'Switch Cisco Managed 8 Port', 'category' => 'switch_jaringan', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],

            // ── SFP/SFP+ Transceiver — kategori TAMBAHAN. Module optik
            //    per-unit, terpasang di OLT/Switch — infra, bukan barang
            //    pelanggan.
            ['code' => 'SFP-1G-SM', 'name' => 'SFP Transceiver 1G Single Mode', 'category' => 'sfp_transceiver', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],
            ['code' => 'SFP-10G-SM', 'name' => 'SFP+ Transceiver 10G Single Mode', 'category' => 'sfp_transceiver', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],

            // ── Router/Gateway — kategori TAMBAHAN. Router Mikrotik infra
            //    (gateway/PPPoE server) — company_asset (§16.2 doc advanced,
            //    contoh eksplisit "Router Mikrotik infra"), TIDAK PERNAH
            //    dipasang ke pelanggan.
            ['code' => 'RTR-MIKROTIK-750', 'name' => 'Router Mikrotik RB750Gr3 (Gateway PPPoE)', 'category' => 'router_gateway', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],

            // ── Access Point WiFi — kategori TAMBAHAN. AP murni (beda dari
            //    Router/Gateway di atas) — dipasang di rumah/tempat pelanggan
            //    buat perluasan sinyal WiFi, boleh transisi ke pelanggan.
            ['code' => 'AP-TPLINK-INDOOR', 'name' => 'Access Point TP-Link Indoor', 'category' => 'access_point', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'AP-UBNT-OUTDOOR', 'name' => 'Access Point Ubiquiti Outdoor', 'category' => 'access_point', 'unit' => 'pcs', 'tracking_type' => 'serialized'],

            // ── Antena / Radio — kategori default AKTIF. Radio wireless
            //    beneran aktif (elektronik, py SN pabrik) — PowerBeam dipakai
            //    backbone PtP antar tower (company_asset), NanoStation/LiteBeam
            //    dipasang di rumah pelanggan wireless (installable). Antena
            //    grid piringan DIKECUALIKAN via override — cuma logam, gak ada
            //    elektronik/SN, salah kalau ikut default kategori (aktif).
            ['code' => 'RD-UBNT-NSM5', 'name' => 'Radio Wireless Ubiquiti NanoStation M5', 'category' => 'antena_radio', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'RD-UBNT-LBM5', 'name' => 'Radio Wireless Ubiquiti LiteBeam M5', 'category' => 'antena_radio', 'unit' => 'pcs', 'tracking_type' => 'serialized'],
            ['code' => 'RD-UBNT-PBM5', 'name' => 'Radio Wireless Ubiquiti PowerBeam M5 (Backbone)', 'category' => 'antena_radio', 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'company_asset'],
            ['code' => 'ANT-GRID-24', 'name' => 'Antena Grid 24dBi', 'category' => 'antena_radio', 'unit' => 'pcs', 'equipment_class_override' => 'pasif'],

            // ── Aksesoris Pemasangan — passive, quantity ─────────────────
            ['code' => 'FC-SC', 'name' => 'Fast Connector SC', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'SLV-SPLICE', 'name' => 'Sleeve Pelindung Sambungan (Fusion Splice Protector)', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-KLEM', 'name' => 'Klem Kabel (S-Clamp)', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-TRAY', 'name' => 'Tray Kabel', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-TIANG-7M', 'name' => 'Tiang Penyangga 7 Meter', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-ISOLASI', 'name' => 'Isolasi/Lakban Listrik', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-CABLETIE', 'name' => 'Cable Tie/Tywrap', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],

            // ── Lainnya — catch-all, sengaja minimal (bukan tempat nampung
            //    daftar panjang, itu tujuannya kategori sendiri-sendiri).
            ['code' => 'KONEKTOR-RJ45', 'name' => 'Konektor RJ45', 'category' => 'lainnya', 'unit' => 'pcs'],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'item_category_id' => $categoryIds[$item['category']] ?? null,
                    'unit' => $item['unit'],
                    'tracking_type' => $item['tracking_type'] ?? 'quantity',
                    'ownership_mode' => $item['ownership_mode'] ?? 'installable',
                    'equipment_class_override' => $item['equipment_class_override'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
