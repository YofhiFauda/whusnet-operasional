<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_role(): void
    {
        $role = Role::create([
            'name' => 'Test Role',
            'guard_name' => 'web',
            'description' => 'Test Role Description',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertTrue($user->role->is($role));
        $this->assertTrue($role->users->contains($user));
    }

    public function test_role_has_many_permissions_and_vice_versa(): void
    {
        $role = Role::create([
            'name' => 'Test Role',
            'guard_name' => 'web',
            'description' => 'Test Role Description',
        ]);

        $permission1 = Permission::create([
            'name' => 'test_permission_1',
            'module' => 'Test Module',
            'description' => 'Test Permission 1 Description',
        ]);

        $permission2 = Permission::create([
            'name' => 'test_permission_2',
            'module' => 'Test Module',
            'description' => 'Test Permission 2 Description',
        ]);

        // Attach permissions to role
        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains($permission1));
        $this->assertTrue($role->permissions->contains($permission2));

        $this->assertTrue($permission1->roles->contains($role));
        $this->assertTrue($permission2->roles->contains($role));
    }

    public function test_has_permission_helper_for_owner_admin_and_admin_pusat(): void
    {
        $ownerRole = Role::create([
            'name' => 'Owner',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::create([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $adminPusatRole = Role::create([
            'name' => 'Admin Pusat',
            'guard_name' => 'web',
        ]);

        $ownerUser = User::factory()->create([
            'role_id' => $ownerRole->id,
        ]);

        $adminPusatUser = User::factory()->create([
            'role_id' => $adminPusatRole->id,
        ]);

        $adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        // Owner, Admin, and Admin Pusat should return true for any permission, even if it doesn't exist in the database
        $this->assertTrue($ownerUser->hasPermission('non_existent_permission'));
        $this->assertTrue($adminUser->hasPermission('non_existent_permission'));
        $this->assertTrue($adminPusatUser->hasPermission('non_existent_permission'));
    }

    public function test_has_permission_helper_for_other_roles_based_on_pivot(): void
    {
        $csRole = Role::create([
            'name' => 'Customer Service',
            'guard_name' => 'web',
        ]);

        $permission = Permission::create([
            'name' => 'view_customers',
            'module' => 'Pelanggan',
        ]);

        $csUser = User::factory()->create([
            'role_id' => $csRole->id,
        ]);

        // Initially should be false
        $this->assertFalse($csUser->hasPermission('view_customers'));

        // Attach and test again
        $csRole->permissions()->attach($permission->id);
        
        // Clear relations cache or reload user role relation
        $csUser->load('role.permissions');

        $this->assertTrue($csUser->hasPermission('view_customers'));
        $this->assertFalse($csUser->hasPermission('create_payments'));
    }

    public function test_has_permission_returns_false_if_no_role(): void
    {
        $user = User::factory()->create([
            'role_id' => null,
        ]);

        $this->assertFalse($user->hasPermission('view_customers'));
    }

    public function test_role_permission_seeder_maps_permissions_correctly(): void
    {
        // Seed Roles, Permissions, and their relationships
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        // Find CS role and verify it has specific permissions
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $csPermissions = $csRole->permissions->pluck('name')->toArray();

        $this->assertContains('view_customers', $csPermissions);
        $this->assertContains('create_customers', $csPermissions);
        $this->assertNotContains('create_payments', $csPermissions);
        $this->assertNotContains('view_reports_all', $csPermissions);

        // Find Teknisi role and verify permissions
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisiPermissions = $teknisiRole->permissions->pluck('name')->toArray();

        $this->assertContains('fill_survey', $teknisiPermissions);
        $this->assertContains('fill_installation', $teknisiPermissions);
        $this->assertNotContains('create_payments', $teknisiPermissions);
        $this->assertNotContains('manage_pop', $teknisiPermissions);

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $adminPermissions = $adminRole->permissions->pluck('name')->toArray();

        $this->assertContains('manage_pop', $adminPermissions);
        $this->assertContains('manage_users', $adminPermissions);
        $this->assertContains('create_payments', $adminPermissions);
    }

    public function test_role_semantics_cover_owner_admin_and_teknisi(): void
    {
        $this->seed(RoleSeeder::class);

        $owner = Role::where('name', 'Owner')->firstOrFail();
        $admin = Role::where('name', 'Admin')->firstOrFail();
        $teknisi = Role::where('name', 'Teknisi')->firstOrFail();

        $this->assertTrue($owner->isFullAccessRole());
        $this->assertTrue($admin->isFullAccessRole());
        $this->assertTrue($teknisi->isTechnicianRole());
        $this->assertFalse($teknisi->isFullAccessRole());
    }
}
