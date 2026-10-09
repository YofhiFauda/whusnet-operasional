<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HelpdeskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('code', 'helpdesk')->first();

        if (! $role) {
            $this->command?->error('Role helpdesk tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.');

            return;
        }

        for ($i = 1; $i <= 3; $i++) {
            $user = User::updateOrCreate(
                ['email' => "helpdesk{$i}@whusnet.com"],
                [
                    'name' => "Helpdesk {$i}",
                    'phone' => '08180000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
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
                    'scope_type' => 'all_pop',
                ]
            );

            UserRoleScopeTarget::where('user_role_scope_id', $scope->id)->delete();
        }

        $this->command?->info('✅ 3 Akun Helpdesk berhasil dibuat!');
    }
}
