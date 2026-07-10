<?php

namespace Tests\Feature\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLegacyAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_works_with_string_and_array()
    {
        $role = Role::create(['name' => 'Admin', 'code' => 'admin_pop']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($user->hasRole('admin_pop'));
        $this->assertFalse($user->hasRole('owner'));
        $this->assertTrue($user->hasRole(['owner', 'admin_pop']));
    }

    public function test_has_permission_uses_effective_access_service()
    {
        $role = Role::create(['name' => 'Role1', 'code' => 'role_1']);
        $permission = Permission::create(['code' => 'invoices.view', 'name' => 'View Invoices']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($user->hasPermission('invoices.view'));
        $this->assertFalse($user->hasPermission('invoices.create'));
    }

    public function test_has_full_access_checks_wildcard_permission()
    {
        $role = Role::create(['name' => 'Super Admin', 'code' => 'super_admin']);
        $permission = Permission::create(['code' => '*', 'name' => 'All Access']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($user->hasFullAccess());
    }

    public function test_is_technician_checks_teknisi_role()
    {
        $role1 = Role::create(['name' => 'Teknisi', 'code' => 'teknisi']);
        $user1 = User::factory()->create(['role_id' => $role1->id]);

        $role2 = Role::create(['name' => 'Sales', 'code' => 'sales']);
        $user2 = User::factory()->create(['role_id' => $role2->id]);

        $this->assertTrue($user1->isTechnician());
        $this->assertFalse($user2->isTechnician());
    }
}
