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

        $this->assertSame(6, Role::query()->count());

        $expectedRoles = [
            'Owner',
            'Admin Pusat',
            'Admin Cabang',
            'Finance/Kasir',
            'Teknisi',
            'Customer Service',
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

        $cs = Role::where('name', 'Customer Service')->firstOrFail();
        $this->assertEquals('Layanan Pelanggan dan Kontak', $cs->description);
    }
}
