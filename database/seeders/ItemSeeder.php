<?php

namespace Database\Seeders;

use App\Enums\MaterialType;
use App\Models\Item;
use Illuminate\Database\Seeder;

/**
 * Isi awal master barang.
 *
 * Daftar minimum yang cukup buat pemasangan standar — sengaja pendek. Barang
 * riil ditambahkan admin lewat Master Data; kalau seeder ini kepanjangan,
 * yang terjadi justru dua daftar (seeder vs realita gudang) yang gak pernah
 * sinkron.
 *
 * Idempotent (updateOrCreate by code) — aman dijalankan ulang.
 */
class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'DC-1C', 'name' => 'Kabel Dropcore 1 Core', 'type' => MaterialType::KABEL_DROPCORE, 'unit' => 'meter'],
            ['code' => 'DC-2C', 'name' => 'Kabel Dropcore 2 Core', 'type' => MaterialType::KABEL_DROPCORE, 'unit' => 'meter'],
            ['code' => 'SPL-1X8', 'name' => 'Splitter 1:8', 'type' => MaterialType::SPLITTER_ODP, 'unit' => 'pcs'],
            ['code' => 'SPL-1X16', 'name' => 'Splitter 1:16', 'type' => MaterialType::SPLITTER_ODP, 'unit' => 'pcs'],
            ['code' => 'ODP-8', 'name' => 'ODP 8 Port', 'type' => MaterialType::SPLITTER_ODP, 'unit' => 'pcs'],
            ['code' => 'PC-SCUPC-1M', 'name' => 'Patch Cord SC/UPC 1 Meter', 'type' => MaterialType::PATCH_CORD, 'unit' => 'pcs'],
            ['code' => 'PC-SCUPC-3M', 'name' => 'Patch Cord SC/UPC 3 Meter', 'type' => MaterialType::PATCH_CORD, 'unit' => 'pcs'],
            ['code' => 'MC-100', 'name' => 'Media Converter 100 Mbps', 'type' => MaterialType::MEDIA_CONVERTER, 'unit' => 'pcs'],
            ['code' => 'AKS-TRAY', 'name' => 'Tray Kabel', 'type' => MaterialType::AKSESORIS_PASANG, 'unit' => 'pcs'],
            ['code' => 'AKS-KLEM', 'name' => 'Klem Kabel', 'type' => MaterialType::AKSESORIS_PASANG, 'unit' => 'pcs'],
            ['code' => 'AKS-TIANG', 'name' => 'Tiang Penyangga', 'type' => MaterialType::AKSESORIS_PASANG, 'unit' => 'pcs'],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'type' => $item['type']->value,
                    'unit' => $item['unit'],
                    'is_active' => true,
                ]
            );
        }
    }
}
