<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Pop;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class TechnicianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('code', 'teknisi')->first();

        if (!$role) {
            $this->command->error('Role teknisi tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.');
            return;
        }

        $faker = Faker::create('id_ID');
        $pops = Pop::all();

        for ($i = 1; $i <= 10; $i++) {
            $user = User::updateOrCreate(
                ['email' => "teknisi{$i}@whusnet.com"],
                [
                    'name' => 'Teknisi ' . $faker->firstName,
                    'phone' => '08' . $faker->randomNumber(8, true),
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'role_id' => $role->id,
                    'email_verified_at' => now(),
                ]
            );

            // Assign scope to technician. Default to selected_pop and assign a random pop if available
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
                 UserRoleScopeTarget::firstOrCreate([
                     'user_role_scope_id' => $scope->id,
                     'pop_id' => $pops->random()->id,
                 ]);
            }
        }

        $this->command->info('✅ 10 Teknisi berhasil dibuat!');
    }
}
