<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * User demo role Sales — dipakai buat coba alur Registrasi Pelanggan +
 * Skip Survey (`customers.registration.skip_survey`, izin default role ini
 * lewat RolePermissionSeeder). Scope `all_pop` sengaja dipakai di sini biar
 * gampang dites lintas POP tanpa perlu tahu POP mana yang sudah di-seed —
 * BUKAN rekomendasi buat data produksi (lihat CLAUDE.md § RBAC: sales
 * seharusnya `own_created`/`selected_pop`, atur manual lewat Role Matrix
 * kalau mau replikasi ke user Sales sungguhan).
 */
class SalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('code', 'sales')->first();

        if (! $role) {
            $this->command->error('Role sales tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.');

            return;
        }

        $sales = User::updateOrCreate(
            ['email' => 'sales@whusnet.com'],
            [
                'name' => 'Sales Demo',
                'phone' => '081200000001',
                'password' => Hash::make('password'),
                'status' => 'active',
                'role_id' => $role->id,
                'email_verified_at' => now(),
            ]
        );

        $scope = UserRoleScope::updateOrCreate(
            ['user_id' => $sales->id, 'role_id' => $role->id],
            ['scope_type' => 'all_pop']
        );

        // all_pop gak butuh target spesifik — bersihkan sisa target lama kalau
        // sebelumnya user ini pernah di-scope selected_pop.
        UserRoleScopeTarget::where('user_role_scope_id', $scope->id)->delete();

        $this->command->info('✅ User Sales demo berhasil dibuat!');
        $this->command->info('Email: sales@whusnet.com');
        $this->command->info('Password: password');
        $this->command->info('Permission customers.registration.skip_survey aktif via RolePermissionSeeder.');
    }
}
