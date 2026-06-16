<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Owner',
                'guard_name' => 'web',
                'description' => 'Owner Perusahaan (Akses Penuh)',
            ],
            [
                'name' => 'Admin',
                'guard_name' => 'web',
                'description' => 'Admin Operasional (Akses Penuh)',
            ],
            [
                'name' => 'Admin Pusat',
                'guard_name' => 'web',
                'description' => 'Administrator Pusat',
            ],
            [
                'name' => 'Admin Cabang',
                'guard_name' => 'web',
                'description' => 'Administrator Cabang / Wilayah',
            ],
            [
                'name' => 'Finance/Kasir',
                'guard_name' => 'web',
                'description' => 'Keuangan dan Kasir',
            ],
            [
                'name' => 'Teknisi',
                'guard_name' => 'web',
                'description' => 'Teknisi Lapangan dan Jaringan',
            ],
            [
                'name' => 'Customer Service',
                'guard_name' => 'web',
                'description' => 'Layanan Pelanggan dan Kontak',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                [
                    'guard_name' => $role['guard_name'],
                    'description' => $role['description']
                ]
            );
        }
    }
}
