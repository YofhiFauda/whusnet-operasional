<?php

namespace Database\Seeders;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder demo: 2 pengguna untuk setiap role yang tersedia di sistem.
 *
 * Seeder ini idempotent (updateOrCreate) dan aman dijalankan ulang.
 * Semua user menggunakan password: password
 *
 * Role yang di-cover:
 *   owner, atasan, admin, noc, helpdesk, customer_service,
 *   fop, teknisi, sales, business_development, pop_admin, kolektor
 *
 * Jalankan: php artisan db:seed --class=DemoUsersSeeder
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $pops = Pop::all();
        $hasPops = $pops->count() > 0;

        /**
         * Definisi pengguna demo per role.
         *
         * scope_type:
         *   - all_pop       → akses semua POP (Owner, Atasan, Admin, NOC, Helpdesk, CS, BD)
         *   - selected_pop  → terbatas pada POP tertentu (FOP, Teknisi, Sales, POP Admin, Kolektor)
         *
         * Catatan: Sales & Teknisi idealnya `own_created`/`selected_pop` di produksi —
         * di sini `all_pop` supaya mudah di-demo lintas POP.
         */
        $roleDefinitions = [
            // ── 1. Owner ────────────────────────────────────────────────────
            [
                'role_code' => 'owner',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'owner1@whusnet.com',
                        'name' => 'Owner Utama',
                        'phone' => '081100000001',
                    ],
                    [
                        'email' => 'owner2@whusnet.com',
                        'name' => 'Owner Pendamping',
                        'phone' => '081100000002',
                    ],
                ],
            ],

            // ── 2. Atasan ────────────────────────────────────────────────────
            [
                'role_code' => 'atasan',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'atasan1@whusnet.com',
                        'name' => 'Atasan Satu',
                        'phone' => '081200000001',
                    ],
                    [
                        'email' => 'atasan2@whusnet.com',
                        'name' => 'Atasan Dua',
                        'phone' => '081200000002',
                    ],
                ],
            ],

            // ── 3. Admin ─────────────────────────────────────────────────────
            [
                'role_code' => 'admin',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'admin1@whusnet.com',
                        'name' => 'Admin Satu',
                        'phone' => '081300000001',
                    ],
                    [
                        'email' => 'admin2@whusnet.com',
                        'name' => 'Admin Dua',
                        'phone' => '081300000002',
                    ],
                ],
            ],

            // ── 4. NOC ───────────────────────────────────────────────────────
            [
                'role_code' => 'noc',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'noc.demo1@whusnet.com',
                        'name' => 'NOC Demo Satu',
                        'phone' => '081400000001',
                    ],
                    [
                        'email' => 'noc.demo2@whusnet.com',
                        'name' => 'NOC Demo Dua',
                        'phone' => '081400000002',
                    ],
                ],
            ],

            // ── 5. Helpdesk ──────────────────────────────────────────────────
            [
                'role_code' => 'helpdesk',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'helpdesk.demo1@whusnet.com',
                        'name' => 'Helpdesk Demo Satu',
                        'phone' => '081500000001',
                    ],
                    [
                        'email' => 'helpdesk.demo2@whusnet.com',
                        'name' => 'Helpdesk Demo Dua',
                        'phone' => '081500000002',
                    ],
                ],
            ],

            // ── 6. Customer Service ──────────────────────────────────────────
            [
                'role_code' => 'customer_service',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'cs1@whusnet.com',
                        'name' => 'Customer Service Satu',
                        'phone' => '081600000001',
                    ],
                    [
                        'email' => 'cs2@whusnet.com',
                        'name' => 'Customer Service Dua',
                        'phone' => '081600000002',
                    ],
                ],
            ],

            // ── 7. FOP (Field Operations) ────────────────────────────────────
            [
                'role_code' => 'fop',
                'scope_type' => $hasPops ? 'selected_pop' : 'all_pop',
                'users' => [
                    [
                        'email' => 'fop.demo1@whusnet.com',
                        'name' => 'FOP Demo Satu',
                        'phone' => '081700000001',
                        'pop_index' => 0,
                    ],
                    [
                        'email' => 'fop.demo2@whusnet.com',
                        'name' => 'FOP Demo Dua',
                        'phone' => '081700000002',
                        'pop_index' => 1,
                    ],
                ],
            ],

            // ── 8. Teknisi ───────────────────────────────────────────────────
            [
                'role_code' => 'teknisi',
                'scope_type' => $hasPops ? 'selected_pop' : 'all_pop',
                'users' => [
                    [
                        'email' => 'teknisi.demo1@whusnet.com',
                        'name' => 'Teknisi Demo Satu',
                        'phone' => '081800000001',
                        'pop_index' => 0,
                    ],
                    [
                        'email' => 'teknisi.demo2@whusnet.com',
                        'name' => 'Teknisi Demo Dua',
                        'phone' => '081800000002',
                        'pop_index' => 1,
                    ],
                ],
            ],

            // ── 9. Sales ─────────────────────────────────────────────────────
            [
                'role_code' => 'sales',
                'scope_type' => 'all_pop', // bisa ubah ke selected_pop di produksi
                'users' => [
                    [
                        'email' => 'sales1@whusnet.com',
                        'name' => 'Sales Satu',
                        'phone' => '081900000001',
                    ],
                    [
                        'email' => 'sales2@whusnet.com',
                        'name' => 'Sales Dua',
                        'phone' => '081900000002',
                    ],
                ],
            ],

            // ── 10. Business Development ─────────────────────────────────────
            [
                'role_code' => 'business_development',
                'scope_type' => 'all_pop',
                'users' => [
                    [
                        'email' => 'busdev1@whusnet.com',
                        'name' => 'Business Development Satu',
                        'phone' => '082000000001',
                    ],
                    [
                        'email' => 'busdev2@whusnet.com',
                        'name' => 'Business Development Dua',
                        'phone' => '082000000002',
                    ],
                ],
            ],

            // ── 11. POP Admin ────────────────────────────────────────────────
            [
                'role_code' => 'pop_admin',
                'scope_type' => $hasPops ? 'selected_pop' : 'all_pop',
                'users' => [
                    [
                        'email' => 'popadmin1@whusnet.com',
                        'name' => 'POP Admin Satu',
                        'phone' => '082100000001',
                        'pop_index' => 0,
                    ],
                    [
                        'email' => 'popadmin2@whusnet.com',
                        'name' => 'POP Admin Dua',
                        'phone' => '082100000002',
                        'pop_index' => 1,
                    ],
                ],
            ],

            // ── 12. Kolektor ─────────────────────────────────────────────────
            [
                'role_code' => 'kolektor',
                'scope_type' => $hasPops ? 'selected_pop' : 'all_pop',
                'users' => [
                    [
                        'email' => 'kolektor.demo1@whusnet.com',
                        'name' => 'Kolektor Demo Satu',
                        'phone' => '082200000001',
                        'pop_index' => 0,
                    ],
                    [
                        'email' => 'kolektor.demo2@whusnet.com',
                        'name' => 'Kolektor Demo Dua',
                        'phone' => '082200000002',
                        'pop_index' => 1,
                    ],
                ],
            ],
        ];

        $totalCreated = 0;

        foreach ($roleDefinitions as $def) {
            $role = Role::where('code', $def['role_code'])->first();

            if (! $role) {
                $this->command?->warn("⚠️  Role [{$def['role_code']}] tidak ditemukan — dilewati. Pastikan RoleSeeder sudah dijalankan.");

                continue;
            }

            foreach ($def['users'] as $userData) {
                $user = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name' => $userData['name'],
                        'phone' => $userData['phone'],
                        'password' => Hash::make('password'),
                        'status' => 'active',
                        'role_id' => $role->id,
                        'email_verified_at' => now(),
                    ]
                );

                $scope = UserRoleScope::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                    ],
                    [
                        'scope_type' => $def['scope_type'],
                    ]
                );

                if ($def['scope_type'] === 'selected_pop' && $hasPops) {
                    $popIndex = ($userData['pop_index'] ?? 0) % $pops->count();
                    $pop = $pops->values()->get($popIndex);

                    UserRoleScopeTarget::firstOrCreate([
                        'user_role_scope_id' => $scope->id,
                        'pop_id' => $pop->id,
                    ]);
                } else {
                    // all_pop — tidak butuh target spesifik, bersihkan sisa target lama
                    UserRoleScopeTarget::where('user_role_scope_id', $scope->id)->delete();
                }

                $totalCreated++;
            }

            $this->command?->info("✅ 2 user [{$role->name}] berhasil dibuat/diperbarui.");
        }

        $this->command?->newLine();
        $this->command?->info("🎉 Total {$totalCreated} akun demo berhasil dibuat!");
        $this->command?->info('🔑 Password semua akun: password');
        $this->command?->newLine();
        $this->command?->table(
            ['Role', 'Email 1', 'Email 2'],
            array_map(fn ($d) => [
                $d['role_code'],
                $d['users'][0]['email'],
                $d['users'][1]['email'],
            ], $roleDefinitions)
        );
    }
}
