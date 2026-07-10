<?php

namespace Tests\Feature\Services;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EffectiveAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EffectiveAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EffectiveAccessService();
    }

    public function test_it_caches_and_returns_permissions()
    {
        $role = Role::create(['name' => 'Test Role', 'code' => 'test_role']);
        $permission1 = Permission::create(['code' => 'customers.view', 'name' => 'View Customers']);
        $permission2 = Permission::create(['code' => 'customers.create', 'name' => 'Create Customers']);
        $role->permissions()->sync([$permission1->id, $permission2->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        // First call should query DB and cache
        $permissions = $this->service->getPermissions($user);
        $this->assertCount(2, $permissions);
        $this->assertTrue(in_array('customers.view', $permissions));

        // Delete permission from DB but cache should still return 2
        $role->permissions()->detach();
        $cachedPermissions = $this->service->getPermissions($user);
        $this->assertCount(2, $cachedPermissions);

        // Clear cache and try again
        $this->service->clearCache($user);
        $freshPermissions = $this->service->getPermissions($user);
        $this->assertCount(0, $freshPermissions);
    }

    public function test_user_can_checks_permission_code()
    {
        $role = Role::create(['name' => 'Role2', 'code' => 'role_2']);
        $permission = Permission::create(['code' => 'invoices.view', 'name' => 'View Invoices']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($this->service->userCan($user, 'invoices.view'));
        $this->assertTrue($this->service->userCan($user, 'invoices', 'view'));
        $this->assertFalse($this->service->userCan($user, 'invoices.create'));
    }

    public function test_user_can_resolves_wildcard()
    {
        $role = Role::create(['name' => 'Role3', 'code' => 'role_3']);
        
        // Setup role with a global wildcard and a feature wildcard
        $perm1 = Permission::create(['code' => '*']);
        $perm2 = Permission::create(['code' => 'customers.*']);
        $role->permissions()->sync([$perm1->id, $perm2->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($this->service->userCan($user, 'customers.view'));
        $this->assertTrue($this->service->userCan($user, 'any_feature.delete')); // caught by global *
    }

    public function test_it_returns_all_pop_scope()
    {
        $role = Role::create(['name' => 'NOC', 'code' => 'noc']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        $this->assertEquals(ScopeType::ALL_POP, $this->service->getScopeType($user));
        $this->assertEquals([], $this->service->getAllowedPopIds($user)); // all_pop should return empty meaning no filter
    }

    public function test_it_returns_selected_pop_scope()
    {
        $role = Role::create(['name' => 'Admin', 'code' => 'admin_pop']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $pop1 = Pop::factory()->create();
        $pop2 = Pop::factory()->create();

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::SELECTED_POP->value,
        ]);

        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop1->id]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop2->id]);

        $this->assertEquals(ScopeType::SELECTED_POP, $this->service->getScopeType($user));
        $allowed = $this->service->getAllowedPopIds($user);
        
        $this->assertCount(2, $allowed);
        $this->assertTrue(in_array($pop1->id, $allowed));
        $this->assertTrue(in_array($pop2->id, $allowed));
    }

    public function test_it_resolves_pop_tree_scope()
    {
        $role = Role::create(['name' => 'Atasan', 'code' => 'atasan']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $mainPop = Pop::factory()->create();
        $childPop1 = Pop::factory()->create(['parent_id' => $mainPop->id]);
        $childPop2 = Pop::factory()->create(['parent_id' => $mainPop->id]);
        $grandchildPop = Pop::factory()->create(['parent_id' => $childPop1->id]);

        // Unrelated pop
        $otherPop = Pop::factory()->create();

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::POP_TREE->value,
        ]);

        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $mainPop->id]);

        $this->assertEquals(ScopeType::POP_TREE, $this->service->getScopeType($user));
        $allowed = $this->service->getAllowedPopIds($user);

        $this->assertCount(4, $allowed); // main, child1, child2, grandchild
        $this->assertTrue(in_array($mainPop->id, $allowed));
        $this->assertTrue(in_array($childPop1->id, $allowed));
        $this->assertTrue(in_array($childPop2->id, $allowed));
        $this->assertTrue(in_array($grandchildPop->id, $allowed));
        
        $this->assertFalse(in_array($otherPop->id, $allowed));
    }

    public function test_pop_tree_with_no_targets_returns_empty_array()
    {
        $role = Role::create(['name' => 'Teknisi', 'code' => 'teknisi']);
        $user = User::factory()->create(['role_id' => $role->id]);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::POP_TREE->value,
        ]);

        $this->assertEquals(ScopeType::POP_TREE, $this->service->getScopeType($user));
        $this->assertEquals([], $this->service->getAllowedPopIds($user));
    }
}
