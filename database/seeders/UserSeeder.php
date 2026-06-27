<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\UserRoleScope;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pastikan Role Owner sudah ada
        $roleOwner = Role::firstOrCreate(
            ['code' => 'owner'],
            [
                'name' => 'Owner',
                'guard_name' => 'web',
                'description' => 'Owner Perusahaan (Akses Penuh)',
                'is_system' => true,
            ]
        );

        // (Opsional) Sinkronisasi permission '*' khusus untuk role Owner jika belum dijalankan oleh RolePermissionSeeder
        $allPermissions = \App\Models\Permission::pluck('id')->toArray();
        $roleOwner->permissions()->sync($allPermissions);

        // 2. Buat User Super Admin / Owner
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@whusnet.com'],
            [
                'name' => 'Super Admin (Owner)',
                'phone' => '080000000000',
                'password' => Hash::make('password'),
                'status' => 'active',
                'role_id' => $roleOwner->id,
                'email_verified_at' => now(),
            ]
        );

        $owner = User::updateOrCreate(
            ['email' => 'owner@whusnet.net'],
            [
                'name' => 'Owner Whusnet',
                'phone' => '081234567890',
                'password' => Hash::make('password'),
                'status' => 'active',
                'role_id' => $roleOwner->id,
                'email_verified_at' => now(),
            ]
        );

        // 3. Berikan Aksesibilitas Scope "All POP" (Dapat melihat seluruh data cabang/nasional)
        UserRoleScope::updateOrCreate(
            ['user_id' => $superAdmin->id, 'role_id' => $roleOwner->id],
            [
                'scope_type' => 'all_pop', // Bebas sekat wilayah
            ]
        );

        UserRoleScope::updateOrCreate(
            ['user_id' => $owner->id, 'role_id' => $roleOwner->id],
            [
                'scope_type' => 'all_pop', // Bebas sekat wilayah
            ]
        );

        // Kosongkan target scope jika sebelumnya ada, karena all_pop tidak butuh target spesifik
        $userRoleScope = UserRoleScope::where('user_id', $superAdmin->id)->first();
        if ($userRoleScope) {
            \App\Models\UserRoleScopeTarget::where('user_role_scope_id', $userRoleScope->id)->delete();
        }

        $userRoleScope2 = UserRoleScope::where('user_id', $owner->id)->first();
        if ($userRoleScope2) {
            \App\Models\UserRoleScopeTarget::where('user_role_scope_id', $userRoleScope2->id)->delete();
        }

        $this->command->info('✅ Super Admin (Aksesibilitas All) berhasil dibuat!');
        $this->command->info('Email: superadmin@whusnet.com');
        $this->command->info('Password: password');
    }
}
