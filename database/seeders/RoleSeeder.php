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
        // 2. Finance/Kasir -> Admin (Opsi A)
        // 3. Admin Cabang -> POP Admin
        //
        // Mapping lama 'Customer Service' -> 'Helpdesk' SENGAJA dibuang
        // (2026-09-29): Customer Service sekarang role sendiri (dibuat lewat
        // UI, disalin ke bawah). Kalau mapping itu dibalikin, tiap db:seed
        // bakal memindah semua user CS ke Helpdesk lalu menghapus role-nya.

        $mappings = [
            'Admin Pusat' => 'Admin',
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
                'description' => 'Owner Perusahaan',
                'is_system' => true,
            ],
            [
                // is_system = true (2026-09-29) supaya code-nya terkunci di UI:
                // kode aplikasi memberi akses semua POP lewat code 'atasan'
                // (HasPopScope, EffectiveAccessService) — code diganti di UI =
                // Atasan diam-diam kehilangan akses lintas cabang.
                'code' => 'atasan',
                'name' => 'Atasan',
                'description' => 'Atasan / Manajemen',
                'is_system' => true,
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
                // Dibuat lewat UI Role Matrix (2026-09-29), disalin ke seeder.
                // Pemegang Verifikasi Registrasi & Verifikasi Biaya C-REQ
                // bareng Helpdesk — lihat RolePermissionSeeder. is_system =
                // true supaya code-nya terkunci: RolePermissionSeeder mencari
                // role ini lewat code.
                'code' => 'customer_service',
                'name' => 'Customer Service',
                'description' => 'Customer Service',
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
                // Restriksi Paket per Role (Skema 1, 2026-09-12).
                'is_package_restricted' => true,
            ],
            [
                // Teknisi yang merangkap PIC gudang cabang. Role GLOBAL —
                // cabang yang dia PIC-i ditentukan tabel `warehouse_pop_pics`
                // (BUKAN scope POP — scope boleh `all_pop` buat teknisi
                // keliling). Dilarang bikin 'pic_gudang_jetis'/'pic_gudang_siman'
                // dsb (role per cabang, lihat CLAUDE.md RBAC). Dihitung sebagai
                // teknisi lewat Role::TECHNICIAN_CODES. is_system = true
                // supaya code-nya terkunci (dirujuk konstanta itu).
                // docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md
                'code' => 'pic_gudang',
                'name' => 'Teknisi PIC Gudang',
                'description' => 'Teknisi lapangan sekaligus PIC gudang cabang',
                'is_system' => true,
                'is_package_restricted' => true,
            ],
            [
                'code' => 'sales',
                'name' => 'Sales',
                'description' => 'Pemasaran di lapangan',
                'is_system' => true,
                // Restriksi Paket per Role (Skema 1, 2026-09-12) — Sales
                // cuma boleh pilih paket dari `restricted_packages`, diatur
                // Business Development. Lihat InternetPackage::scopeAvailableFor().
                'is_package_restricted' => true,
            ],
            [
                // Mengatur daftar paket terbatas Sales & Teknisi + Master
                // Agent + memantau omset Sales (Skema 1-3, 2026-09-12).
                // Role BARU (bukan reuse admin/atasan) — keputusan eksplisit
                // user.
                'code' => 'business_development',
                'name' => 'Business Development',
                'description' => 'Pengembangan dan Pertumbuhan Bisnis',
                'is_system' => true,
            ],
            [
                'code' => 'pop_admin',
                'name' => 'POP Admin',
                'description' => 'Administrator Cabang / POP',
                'is_system' => true,
            ],
            [
                // Dibuat lewat UI Role Matrix (2026-10-02), disalin ke seeder.
                // Role GLOBAL pemegang operasional gudang Pusat (alur transfer
                // kirim, approve/reject stock request, lihat harga transfer) —
                // beda dari 'pic_gudang' (teknisi cabang) & 'pop_admin'
                // (pemeriksa gudang cabangnya sendiri). is_system = true supaya
                // code-nya terkunci: RolePermissionSeeder mencari role ini
                // lewat code.
                'code' => 'admin_gudang',
                'name' => 'Admin Gudang',
                'description' => 'Administrator Gudang Pusat',
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
                'description' => 'Penagih Lapangan',
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
                    'is_package_restricted' => $roleData['is_package_restricted'] ?? false,
                ]
            );
        }
    }
}
