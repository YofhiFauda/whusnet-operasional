<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Teknisi Whusnet',
                'email' => 'teknisi@whusnet.com',
                'email_verified_at' => now(),
                'phone' => '081234567890',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_name' => 'Teknisi',
            ],
            [
                'name' => 'Admin Whusnet',
                'email' => 'admin@whusnet.com',
                'email_verified_at' => now(),
                'phone' => '081234567890',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_name' => 'Admin',
            ],
            [
                'name' => 'Owner Whusnet',
                'email' => 'owner@whusnet.com',
                'email_verified_at' => now(),
                'phone' => '081234567890',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_name' => 'Owner',
            ]

        ];

        foreach ($users as $userData) {
            $roleName = $userData['role_name'];
            unset($userData['role_name']);

            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $userData['role_id'] = $role->id;
            }

            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
