<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
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
        // Kategori bawaan ditanam migrasi (bukan seeder) karena code-nya jadi
        // kontrak; di sini tinggal dirujuk.
        $categoryIds = ItemCategory::pluck('id', 'code');

        $items = [
            ['code' => 'DC-1C', 'name' => 'Kabel Dropcore 1 Core', 'category' => 'kabel_dropcore', 'unit' => 'meter'],
            ['code' => 'DC-2C', 'name' => 'Kabel Dropcore 2 Core', 'category' => 'kabel_dropcore', 'unit' => 'meter'],
            ['code' => 'SPL-1X8', 'name' => 'Splitter 1:8', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'SPL-1X16', 'name' => 'Splitter 1:16', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'ODP-8', 'name' => 'ODP 8 Port', 'category' => 'splitter_odp', 'unit' => 'pcs'],
            ['code' => 'PC-SCUPC-1M', 'name' => 'Patch Cord SC/UPC 1 Meter', 'category' => 'patch_cord', 'unit' => 'pcs'],
            ['code' => 'PC-SCUPC-3M', 'name' => 'Patch Cord SC/UPC 3 Meter', 'category' => 'patch_cord', 'unit' => 'pcs'],
            ['code' => 'MC-100', 'name' => 'Media Converter 100 Mbps', 'category' => 'media_converter', 'unit' => 'pcs'],
            ['code' => 'AKS-TRAY', 'name' => 'Tray Kabel', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-KLEM', 'name' => 'Klem Kabel', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
            ['code' => 'AKS-TIANG', 'name' => 'Tiang Penyangga', 'category' => 'aksesoris_pasang', 'unit' => 'pcs'],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'item_category_id' => $categoryIds[$item['category']] ?? null,
                    'unit' => $item['unit'],
                    'is_active' => true,
                ]
            );
        }
    }
}
