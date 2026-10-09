<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skema 1 — gap yang diakui: toggle `roles.is_package_restricted` sekarang
 * bisa diatur Owner lewat form Role Management (bukan cuma seeder/tinker).
 */
class RolePackageRestrictedToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_can_create_role_with_package_restricted_flag(): void
    {
        $this->loginAsAdmin(); // Owner

        $response = $this->post(route('roles.store'), [
            'name' => 'Reseller',
            'code' => 'reseller',
            'is_package_restricted' => '1',
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['code' => 'reseller', 'is_package_restricted' => true]);
    }

    public function test_owner_can_toggle_flag_on_existing_system_role(): void
    {
        $this->loginAsAdmin(); // Owner
        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        $this->assertTrue((bool) $teknisi->is_package_restricted);

        // Lepas restriksi dari Teknisi lewat form, bukan seeder.
        $response = $this->put(route('roles.update_role', $teknisi), [
            'name' => $teknisi->name,
            'code' => $teknisi->code,
            'description' => $teknisi->description,
            // is_package_restricted sengaja tidak dikirim (checkbox unchecked).
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertFalse((bool) $teknisi->fresh()->is_package_restricted);
    }

    public function test_owner_can_flag_a_role_that_was_not_restricted_before(): void
    {
        $this->loginAsAdmin();
        $atasan = Role::where('code', 'atasan')->firstOrFail();
        $this->assertFalse((bool) $atasan->is_package_restricted);

        $response = $this->put(route('roles.update_role', $atasan), [
            'name' => $atasan->name,
            'code' => $atasan->code,
            'description' => $atasan->description,
            'is_package_restricted' => '1',
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertTrue((bool) $atasan->fresh()->is_package_restricted);
    }
}
