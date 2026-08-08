<?php

namespace Database\Seeders;

use App\Models\WorkTool;
use Illuminate\Database\Seeder;

/**
 * Isi awal master alat kerja.
 *
 * Diambil dari isi `required_tools` yang benar-benar dipakai di data survey
 * lama (`'Tang, Splicer, OPM, Dropcore'`) — dikurangi "Dropcore", yang itu
 * material dan tempatnya di master barang, bukan di sini. Sengaja pendek;
 * sisanya ditambahkan admin lewat Master Data.
 *
 * Idempotent (updateOrCreate by code) — aman dijalankan ulang.
 */
class WorkToolSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            ['code' => 'TANGGA', 'name' => 'Tangga', 'note' => 'Sebutkan panjang di catatan bila perlu ekstra'],
            ['code' => 'SPLICER', 'name' => 'Fusion Splicer', 'note' => null],
            ['code' => 'OPM', 'name' => 'OPM (Optical Power Meter)', 'note' => null],
            ['code' => 'OTDR', 'name' => 'OTDR', 'note' => 'Untuk penelusuran gangguan kabel'],
            ['code' => 'VFL', 'name' => 'VFL (Visual Fault Locator)', 'note' => null],
            ['code' => 'BOR', 'name' => 'Bor Beton', 'note' => 'Dinding tebal / tembus cor'],
            ['code' => 'TANG-KRIMPING', 'name' => 'Tang Krimping', 'note' => null],
            ['code' => 'TOOLKIT', 'name' => 'Toolkit Standar', 'note' => 'Obeng, tang, cutter, isolasi'],
            ['code' => 'GENSET', 'name' => 'Genset / Power Bank Besar', 'note' => 'Lokasi tanpa sumber listrik'],
            ['code' => 'SAFETY', 'name' => 'Alat Keselamatan', 'note' => 'Helm, sarung tangan, body harness'],
        ];

        foreach ($tools as $index => $tool) {
            WorkTool::updateOrCreate(
                ['code' => $tool['code']],
                [
                    'name' => $tool['name'],
                    'note' => $tool['note'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }
}
