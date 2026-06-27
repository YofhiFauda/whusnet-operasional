<?php

namespace Database\Seeders;

use App\Models\TaskChecklistTemplate;
use Illuminate\Database\Seeder;

class TaskChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ── Survey ────────────────────────────────────────────────
            ['task_type' => 'survey', 'item' => 'Konfirmasi lokasi dengan pelanggan', 'is_required' => true,  'sort_order' => 1],
            ['task_type' => 'survey', 'item' => 'Cek sinyal ODC ke ODP',              'is_required' => true,  'sort_order' => 2],
            ['task_type' => 'survey', 'item' => 'Estimasi kebutuhan kabel (meter)',   'is_required' => true,  'sort_order' => 3],
            ['task_type' => 'survey', 'item' => 'Foto lokasi pemasangan',             'is_required' => true,  'sort_order' => 4],
            ['task_type' => 'survey', 'item' => 'Foto ODP terdekat',                  'is_required' => false, 'sort_order' => 5],
            ['task_type' => 'survey', 'item' => 'Catat ODP port yang akan digunakan', 'is_required' => false, 'sort_order' => 6],

            // ── Pemasangan ────────────────────────────────────────────
            ['task_type' => 'pemasangan', 'item' => 'Pasang kabel fiber ke ODP',           'is_required' => true,  'sort_order' => 1],
            ['task_type' => 'pemasangan', 'item' => 'Pasang ONT/modem di lokasi pelanggan', 'is_required' => true,  'sort_order' => 2],
            ['task_type' => 'pemasangan', 'item' => 'Konfigurasi PPPoE',                    'is_required' => true,  'sort_order' => 3],
            ['task_type' => 'pemasangan', 'item' => 'Uji koneksi internet aktif',           'is_required' => true,  'sort_order' => 4],
            ['task_type' => 'pemasangan', 'item' => 'Foto instalasi selesai',               'is_required' => true,  'sort_order' => 5],
            ['task_type' => 'pemasangan', 'item' => 'Tandatangan BAP pelanggan',            'is_required' => true,  'sort_order' => 6],
            ['task_type' => 'pemasangan', 'item' => 'Input SN ONT ke sistem',               'is_required' => false, 'sort_order' => 7],

            // ── Maintenance ───────────────────────────────────────────
            ['task_type' => 'maintenance', 'item' => 'Identifikasi keluhan pelanggan',      'is_required' => true,  'sort_order' => 1],
            ['task_type' => 'maintenance', 'item' => 'Cek sinyal RX power di ONT',          'is_required' => true,  'sort_order' => 2],
            ['task_type' => 'maintenance', 'item' => 'Cek kondisi fisik kabel dan konektor', 'is_required' => true,  'sort_order' => 3],
            ['task_type' => 'maintenance', 'item' => 'Perbaikan selesai dan internet aktif', 'is_required' => true,  'sort_order' => 4],
            ['task_type' => 'maintenance', 'item' => 'Foto kondisi sebelum dan sesudah',     'is_required' => true,  'sort_order' => 5],
            ['task_type' => 'maintenance', 'item' => 'Konfirmasi kepuasan pelanggan',        'is_required' => false, 'sort_order' => 6],

            // ── Ambil Modem ───────────────────────────────────────────
            ['task_type' => 'ambil_modem', 'item' => 'Verifikasi identitas pelanggan',      'is_required' => true,  'sort_order' => 1],
            ['task_type' => 'ambil_modem', 'item' => 'Catat SN modem yang diambil',         'is_required' => true,  'sort_order' => 2],
            ['task_type' => 'ambil_modem', 'item' => 'Foto modem sebelum diambil',          'is_required' => true,  'sort_order' => 3],
            ['task_type' => 'ambil_modem', 'item' => 'Putuskan koneksi dari ODP',           'is_required' => true,  'sort_order' => 4],
            ['task_type' => 'ambil_modem', 'item' => 'Tanda terima pengambilan modem',      'is_required' => false, 'sort_order' => 5],

            // ── Relokasi ──────────────────────────────────────────────
            ['task_type' => 'relokasi', 'item' => 'Survey lokasi baru',                   'is_required' => true,  'sort_order' => 1],
            ['task_type' => 'relokasi', 'item' => 'Cabut instalasi di lokasi lama',       'is_required' => true,  'sort_order' => 2],
            ['task_type' => 'relokasi', 'item' => 'Pasang di lokasi baru',                'is_required' => true,  'sort_order' => 3],
            ['task_type' => 'relokasi', 'item' => 'Uji koneksi di lokasi baru',           'is_required' => true,  'sort_order' => 4],
            ['task_type' => 'relokasi', 'item' => 'Update alamat pelanggan di sistem',    'is_required' => true,  'sort_order' => 5],
            ['task_type' => 'relokasi', 'item' => 'Foto lokasi baru selesai dipasang',    'is_required' => true,  'sort_order' => 6],
        ];

        foreach ($templates as $t) {
            TaskChecklistTemplate::updateOrCreate(
                ['task_type' => $t['task_type'], 'item' => $t['item']],
                [
                    'is_required' => $t['is_required'],
                    'sort_order'  => $t['sort_order'],
                    'is_active'   => true,
                ]
            );
        }

        $this->command->info('TaskChecklistTemplateSeeder: ' . count($templates) . ' checklist templates seeded.');
    }
}
