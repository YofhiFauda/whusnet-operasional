<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update user's old roles to new roles based on instructions
        // 1. Admin Pusat -> Admin
        // 2. Customer Service -> Helpdesk
        // 3. Finance/Kasir -> Admin (Opsi A)
        // 4. Admin Cabang -> POP Admin

        $mappings = [
            'Admin Pusat' => 'Admin',
            'Customer Service' => 'Helpdesk',
            'Finance/Kasir' => 'Admin',
            'Admin Cabang' => 'POP Admin',
        ];

        foreach ($mappings as $oldName => $newName) {
            $oldRole = Role::where('name', $oldName)->first();
            if ($oldRole) {
                // Ensure new role exists so we can map users
                $newRole = Role::firstOrCreate(
                    ['name' => $newName],
                    ['guard_name' => 'web']
                );

                if ($oldRole->id !== $newRole->id) {
                    DB::table('users')->where('role_id', $oldRole->id)->update(['role_id' => $newRole->id]);
                    $oldRole->delete(); // Remove old role since users are migrated
                }
            }
        }

        // Define New Hierarchical Advanced Roles
        $roles = [
            [
                'code' => 'owner',
                'name' => 'Owner',
                'description' => 'Owner Perusahaan (Akses Penuh)',
                'is_system' => true,
            ],
            [
                'code' => 'atasan',
                'name' => 'Atasan',
                'description' => 'Atasan / Manajemen',
                'is_system' => false,
            ],
            [
                'code' => 'admin',
                'name' => 'Admin',
                'description' => 'Admin Operasional',
                'is_system' => true,
            ],
            [
                'code' => 'noc',
                'name' => 'NOC',
                'description' => 'Network Operations Center',
                'is_system' => true,
            ],
            [
                'code' => 'helpdesk',
                'name' => 'Helpdesk',
                'description' => 'Layanan Pelanggan dan Bantuan',
                'is_system' => true,
            ],
            [
                'code' => 'fop',
                'name' => 'FOP',
                'description' => 'Field Operations',
                'is_system' => true,
            ],
            [
                'code' => 'teknisi',
                'name' => 'Teknisi',
                'description' => 'Teknisi Lapangan dan Jaringan',
                'is_system' => true,
            ],
            [
                'code' => 'sales',
                'name' => 'Sales',
                'description' => 'Sales dan Pendaftaran',
                'is_system' => true,
            ],
            [
                'code' => 'pop_admin',
                'name' => 'POP Admin',
                'description' => 'Administrator Cabang / POP',
                'is_system' => true,
            ],
            [
                // Penagih lapangan — role RBAC global (bukan per-cabang,
                // dibatasi lewat scope POP), berbeda dari Admin POP.
                // Admin POP boleh merangkap kolektor, sebaliknya tidak.
                // Kolektor TIDAK boleh input pembayaran sama sekali.
                // docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-8 no. 4.
                'code' => 'kolektor',
                'name' => 'Kolektor',
                'description' => 'Penagih Lapangan (Worklist Read-Only, Tidak Bisa Input Pembayaran)',
                'is_system' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['name' => $roleData['name']],
                [
                    'code' => $roleData['code'],
                    'guard_name' => 'web',
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system'],
                ]
            );
        }
    }
}
