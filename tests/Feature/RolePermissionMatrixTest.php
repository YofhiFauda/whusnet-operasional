<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
        $feature = Feature::firstOrCreate(['code' => 'roles'], ['name' => 'Roles', 'type' => 'root', 'is_active' => true]);
        $action = Action::firstOrCreate(['code' => 'update'], ['name' => 'Manage']);
        $perm = Permission::firstOrCreate(['code' => 'manage_roles'], [
            'feature_id' => $feature->id,
            'action_id' => $action->id,
            'name' => 'Manage Roles',
            'module' => 'System',
        ]);

        $ownerRole = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner', 'is_system' => true]);
        $ownerRole->permissions()->syncWithoutDetaching([$perm->id]);

        $this->user = User::factory()->create([
            'role_id' => $ownerRole->id,
        ]);

        // Target role to edit
        $this->targetRole = Role::create(['name' => 'Test Role', 'code' => 'test']);

        $action2 = Action::firstOrCreate(['code' => 'view'], ['name' => 'View']);
        $this->testPerm = Permission::firstOrCreate(['code' => 'dummy_permission'], [
            'feature_id' => $feature->id,
            'action_id' => $action2->id,
            'name' => 'Dummy',
            'module' => 'Test',
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

    /**
     * Checkbox permission harus menjelaskan fungsi NYATA modulnya, bukan arti
     * generik kata kerja action-nya. Permission digenerate tanpa kolom
     * `description`, jadi kalau map per-kode di matrix.blade.php hilang, semua
     * checkbox balik jadi teks generik ("Dapat menonaktifkan / mengisolir
     * layanan") dan admin salah kira soal apa yang lagi dia centang.
     */
    public function test_matrix_menampilkan_deskripsi_sesuai_fungsi_modul()
    {
        $feature = Feature::firstOrCreate(['code' => 'customers'], ['name' => 'Pelanggan', 'type' => 'root', 'is_active' => true]);
        $action = Action::firstOrCreate(['code' => 'deactivate'], ['name' => 'Deactivate']);
        Permission::firstOrCreate(['code' => 'customers.deactivate'], [
            'feature_id' => $feature->id,
            'action_id' => $action->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('roles.matrix', $this->targetRole));

        $response->assertStatus(200);
        $response->assertSee('Putus langganan pelanggan aktif (terminasi).', false);
        $response->assertDontSee('Dapat menonaktifkan / mengisolir layanan.', false);
    }

    public function test_can_update_permissions()
    {
        $this->assertCount(0, $this->targetRole->permissions);

        $response = $this->actingAs($this->user)->put(route('roles.update', $this->targetRole), [
            'permissions' => [$this->testPerm->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertCount(1, $this->targetRole->fresh()->permissions);
        $this->assertEquals($this->testPerm->id, $this->targetRole->fresh()->permissions->first()->id);
    }

    public function test_cannot_update_owner_permissions()
    {
        $ownerRole = Role::where('name', 'Owner')->first();

        $response = $this->actingAs($this->user)->put(route('roles.update', $ownerRole), [
            'permissions' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
