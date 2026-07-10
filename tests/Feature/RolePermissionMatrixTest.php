<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Feature;
use App\Models\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $targetRole;
    protected Permission $testPerm;

    protected function setUp(): void
    {
        parent::setUp();

        // Create manage_roles permission
        $feature = Feature::create(['code' => 'roles', 'name' => 'Roles', 'type' => 'root', 'is_active' => true]);
        $action = Action::create(['code' => 'update', 'name' => 'Manage']);
        $perm = Permission::create([
            'feature_id' => $feature->id,
            'action_id' => $action->id,
            'code' => 'manage_roles',
            'name' => 'Manage Roles',
            'module' => 'System'
        ]);

        $ownerRole = Role::create(['name' => 'Owner', 'code' => 'owner', 'is_system' => true]);
        $ownerRole->permissions()->attach($perm);

        $this->user = User::factory()->create([
            'role_id' => $ownerRole->id
        ]);
        
        // Target role to edit
        $this->targetRole = Role::create(['name' => 'Test Role', 'code' => 'test']);
        
        $action2 = Action::create(['code' => 'view', 'name' => 'View']);
        $this->testPerm = Permission::create([
            'feature_id' => $feature->id,
            'action_id' => $action2->id,
            'code' => 'dummy_permission',
            'name' => 'Dummy',
            'module' => 'Test'
        ]);
    }

    public function test_can_view_role_list()
    {
        $response = $this->actingAs($this->user)->get(route('roles.index'));
        $response->assertStatus(200);
        $response->assertSee('Test Role');
    }

    public function test_can_view_matrix()
    {
        $response = $this->actingAs($this->user)->get(route('roles.matrix', $this->targetRole));
        $response->assertStatus(200);
        $response->assertSee('Feature Tree Permission Matrix');
    }

    public function test_can_update_permissions()
    {
        $this->assertCount(0, $this->targetRole->permissions);

        $response = $this->actingAs($this->user)->put(route('roles.update', $this->targetRole), [
            'permissions' => [$this->testPerm->id]
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertCount(1, $this->targetRole->fresh()->permissions);
        $this->assertEquals($this->testPerm->id, $this->targetRole->fresh()->permissions->first()->id);
    }

    public function test_cannot_update_owner_permissions()
    {
        $ownerRole = Role::where('name', 'Owner')->first();
        
        $response = $this->actingAs($this->user)->put(route('roles.update', $ownerRole), [
            'permissions' => []
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
