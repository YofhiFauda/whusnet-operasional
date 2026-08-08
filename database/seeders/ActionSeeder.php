<?php

namespace Database\Seeders;

use App\Enums\ActionCode;
use App\Models\Action;
use Illuminate\Database\Seeder;

class ActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            [
                'code' => ActionCode::VIEW,
                'name' => 'View',
                'description' => 'Melihat daftar data atau detail halaman',
            ],
            [
                'code' => ActionCode::CREATE,
                'name' => 'Create',
                'description' => 'Menambah data baru',
            ],
            [
                'code' => ActionCode::UPDATE,
                'name' => 'Update',
                'description' => 'Mengubah data existing',
            ],
            [
                'code' => ActionCode::DELETE,
                'name' => 'Delete',
                'description' => 'Menghapus data (soft delete)',
            ],
            [
                'code' => ActionCode::IMPORT,
                'name' => 'Import',
                'description' => 'Mengimpor data dari file Excel/CSV',
            ],
            [
                'code' => ActionCode::EXPORT,
                'name' => 'Export',
                'description' => 'Mengekspor data ke file PDF/Excel',
            ],
            [
                'code' => ActionCode::PRINT,
                'name' => 'Print',
                'description' => 'Mencetak data atau dokumen',
            ],
            [
                'code' => ActionCode::APPROVE,
                'name' => 'Approve',
                'description' => 'Menyetujui langkah workflow',
            ],
            [
                'code' => ActionCode::REJECT,
                'name' => 'Reject',
                'description' => 'Menolak langkah workflow',
            ],
            [
                'code' => ActionCode::ACTIVATE,
                'name' => 'Activate',
                'description' => 'Mengaktifkan layanan atau status',
            ],
            [
                'code' => ActionCode::DEACTIVATE,
                'name' => 'Deactivate',
                'description' => 'Menonaktifkan layanan atau status',
            ],
            [
                'code' => ActionCode::ASSIGN,
                'name' => 'Assign',
                'description' => 'Menugaskan data kepada user',
            ],
            [
                'code' => ActionCode::VALIDATE,
                'name' => 'Validate',
                'description' => 'Memvalidasi data atau status',
            ],
            [
                'code' => ActionCode::CANCEL,
                'name' => 'Cancel',
                'description' => 'Membatalkan tagihan atau status',
            ],
            [
                'code' => ActionCode::UPLOAD,
                'name' => 'Upload',
                'description' => 'Mengunggah file lampiran',
            ],
            [
                'code' => ActionCode::DOWNLOAD,
                'name' => 'Download',
                'description' => 'Mengunduh file template atau lampiran',
            ],
            [
                'code' => ActionCode::VIEW_SENSITIVE,
                'name' => 'View Sensitive',
                'description' => 'Melihat data sensitif (password PPPoE/WiFi, detail billing)',
            ],
            [
                'code' => ActionCode::UPDATE_SENSITIVE,
                'name' => 'Update Timer SLA',
                'description' => 'Mengubah data sensitif',
            ],
            [
                'code' => ActionCode::RETRIEVE,
                'name' => 'Retrieve',
                'description' => 'Mengambil kembali alat/aset dari pelanggan',
            ],
        ];

        foreach ($actions as $action) {
            Action::updateOrCreate(
                ['code' => $action['code']],
                [
                    'name' => $action['name'],
                    'description' => $action['description'],
                ]
            );
        }
    }
}
