<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_inserts_expected_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(10, Role::query()->count());

        $expectedRoles = [
            'Owner',
            'Atasan',
            'Admin',
            'NOC',
            'Helpdesk',
            'FOP',
            'Teknisi',
            'Sales',
            'POP Admin',
            'Kolektor',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_role_attributes_can_be_retrieved(): void
    {
        $this->seed(RoleSeeder::class);

        $owner = Role::where('name', 'Owner')->firstOrFail();
        $this->assertEquals('Owner Perusahaan (Akses Penuh)', $owner->description);

        $admin = Role::where('name', 'Admin')->firstOrFail();
        $this->assertEquals('Admin Operasional', $admin->description);

        $cs = Role::where('name', 'Helpdesk')->firstOrFail();
        $this->assertEquals('Layanan Pelanggan dan Bantuan', $cs->description);
    }
}
