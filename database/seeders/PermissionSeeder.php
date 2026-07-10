<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Modul POP
            [
                'name' => 'manage_pop',
                'module' => 'POP/Cabang',
                'description' => 'Mengelola data POP/Cabang (Tambah, Edit, Nonaktifkan)',
            ],
            [
                'name' => 'view_pop',
                'module' => 'POP/Cabang',
                'description' => 'Melihat daftar dan detail POP/Cabang',
            ],
            // Modul User
            [
                'name' => 'manage_users',
                'module' => 'User Management',
                'description' => 'Mengelola user internal (CRUD user, assign role/POP)',
            ],
            [
                'name' => 'view_users',
                'module' => 'User Management',
                'description' => 'Melihat daftar dan detail user internal',
            ],
            // Modul Role
            [
                'name' => 'manage_roles',
                'module' => 'Role Management',
                'description' => 'Mengelola role dan mapping permission',
            ],
            [
                'name' => 'view_roles',
                'module' => 'Role Management',
                'description' => 'Melihat daftar role dan permission',
            ],
            // Modul Paket
            [
                'name' => 'manage_packages',
                'module' => 'Master Paket',
                'description' => 'Mengelola paket internet (Tambah, Edit, Nonaktifkan)',
            ],
            [
                'name' => 'view_packages',
                'module' => 'Master Paket',
                'description' => 'Melihat daftar paket internet',
            ],
            // Modul Pelanggan
            [
                'name' => 'create_customers',
                'module' => 'Data Pelanggan',
                'description' => 'Menginput pelanggan baru secara manual',
            ],
            [
                'name' => 'import_customers',
                'module' => 'Data Pelanggan',
                'description' => 'Mengimport data pelanggan lama dari Excel/CSV',
            ],
            [
                'name' => 'edit_customers',
                'module' => 'Data Pelanggan',
                'description' => 'Mengubah data profil dan kontak pelanggan',
            ],
            [
                'name' => 'view_customers',
                'module' => 'Data Pelanggan',
                'description' => 'Melihat daftar dan profil lengkap pelanggan',
            ],
            [
                'name' => 'validate_customer_data',
                'module' => 'Data Pelanggan',
                'description' => 'Validasi kelengkapan data pelanggan menjadi siap billing',
            ],
            // Modul Invoice
            [
                'name' => 'create_invoices',
                'module' => 'Billing/Tagihan',
                'description' => 'Membuat invoice tagihan manual untuk pelanggan aktif',
            ],
            [
                'name' => 'view_invoices',
                'module' => 'Billing/Tagihan',
                'description' => 'Melihat daftar dan detail invoice tagihan',
            ],
            // Modul Pembayaran
            [
                'name' => 'create_payments',
                'module' => 'Pembayaran',
                'description' => 'Mencatat pembayaran tagihan invoice',
            ],
            [
                'name' => 'view_payments',
                'module' => 'Pembayaran',
                'description' => 'Melihat riwayat pembayaran tagihan',
            ],
            [
                'name' => 'edit_payments',
                'module' => 'Pembayaran',
                'description' => 'Memvalidasi atau mengubah status pembayaran',
            ],
            // Modul Data Teknis
            [
                'name' => 'fill_survey',
                'module' => 'Data Teknis',
                'description' => 'Mengisi hasil survey teknis pelanggan',
            ],
            [
                'name' => 'fill_installation',
                'module' => 'Data Teknis',
                'description' => 'Mengisi laporan pemasangan/instalasi baru',
            ],
            [
                'name' => 'fill_device',
                'module' => 'Data Teknis',
                'description' => 'Mengelola data perangkat modem/ONT/router pelanggan',
            ],
            [
                'name' => 'view_customer_documents',
                'module' => 'Data Teknis',
                'description' => 'Melihat dokumen teknis dan pendukung pelanggan',
            ],
            [
                'name' => 'upload_customer_documents',
                'module' => 'Data Teknis',
                'description' => 'Mengupload dokumen teknis dan pendukung pelanggan',
            ],
            // Modul Laporan
            [
                'name' => 'view_reports_all',
                'module' => 'Laporan',
                'description' => 'Melihat laporan secara nasional/seluruh POP',
            ],
            [
                'name' => 'view_reports_own_pop',
                'module' => 'Laporan',
                'description' => 'Melihat laporan terbatas hanya untuk POP yang ditugaskan',
            ],
            // Modul Audit
            [
                'name' => 'view_audit_logs',
                'module' => 'Audit Log',
                'description' => 'Melihat catatan log aktivitas penting sistem',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'module' => $permission['module'],
                    'description' => $permission['description'],
                ]
            );
        }
    }
}
