<?php

namespace Database\Seeders;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KolektorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('code', 'kolektor')->first();

        if (! $role) {
            $this->command?->error('Role kolektor tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.');

            return;
        }

        $pops = Pop::all();

        for ($i = 1; $i <= 3; $i++) {
            $user = User::updateOrCreate(
                ['email' => "kolektor{$i}@whusnet.com"],
                [
                    'name' => "Kolektor {$i}",
                    'phone' => '08140000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'role_id' => $role->id,
                    'email_verified_at' => now(),
                ]
            );

            $scopeType = $pops->count() > 0 ? 'selected_pop' : 'all_pop';
            $scope = UserRoleScope::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ],
                [
                    'scope_type' => $scopeType,
                ]
            );

            if ($scopeType === 'selected_pop') {
                $popIndex = ($i - 1) % $pops->count();
                UserRoleScopeTarget::firstOrCreate([
                    'user_role_scope_id' => $scope->id,
                    'pop_id' => $pops->values()->get($popIndex)->id,
                ]);
            } else {
                UserRoleScopeTarget::where('user_role_scope_id', $scope->id)->delete();
            }
        }

        $this->command?->info('✅ 3 Akun Kolektor berhasil dibuat!');
    }
}
