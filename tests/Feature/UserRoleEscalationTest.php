<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * NOC punya users.create/update, tapi tidak boleh menjadikan siapa pun Owner
 * atau mengedit user yang role-nya melebihi izinnya sendiri. Dulu validator
 * hanya memeriksa kombinasi role↔scope, jadi aksi "ganti role" lolos.
 */
class UserRoleEscalationTest extends TestCase
{
    use RefreshDatabase;

    private User $noc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $nocRole = Role::where('code', 'noc')->firstOrFail();
        $this->noc = User::factory()->create(['role_id' => $nocRole->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $this->noc->id,
            'role_id' => $nocRole->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $this->assertTrue($this->noc->fresh()->hasPermission('users.update'), 'Prasyarat: NOC harus punya users.update.');
    }

    private function ownerUser(): User
    {
        $ownerRole = Role::where('code', 'owner')->firstOrFail();

        return User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(int $roleId, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Target Baru',
            'email' => 'target-'.uniqid().'@example.test',
            'status' => 'active',
            'role_id' => $roleId,
            'scope_type' => 'all_pop',
            'password' => 'Rahasia!123',
            'password_confirmation' => 'Rahasia!123',
        ], $overrides);
    }

    #[Test]
    public function noc_cannot_create_user_with_owner_role(): void
    {
        $ownerRole = Role::where('code', 'owner')->firstOrFail();

        $this->actingAs($this->noc)
            ->post(route('users.store'), $this->userPayload($ownerRole->id, ['email' => 'target-noc@example.test']))
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'target-noc@example.test']);
    }

    #[Test]
    public function noc_cannot_promote_itself_to_owner(): void
    {
        $ownerRole = Role::where('code', 'owner')->firstOrFail();

        $this->actingAs($this->noc)
            ->put(route('users.update', $this->noc), [
                'name' => $this->noc->name,
                'email' => $this->noc->email,
                'status' => 'active',
                'role_id' => $ownerRole->id,
                'scope_type' => 'all_pop',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertSame('noc', $this->noc->fresh()->role->code);
    }

    #[Test]
    public function noc_cannot_edit_owner_user_even_if_role_kept(): void
    {
        $owner = $this->ownerUser();
        $ownerRole = $owner->role;

        $this->actingAs($this->noc)
            ->put(route('users.update', $owner), [
                'name' => 'Owner Diubah',
                'email' => $owner->email,
                'status' => 'active',
                'role_id' => $ownerRole->id,
                'scope_type' => 'all_pop',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertSame('owner', $owner->fresh()->role->code);
        $this->assertNotSame('Owner Diubah', $owner->fresh()->name);
    }

    /**
     * Admin boleh mengelola role `helpdesk` (config rbac.role_management_scope),
     * tapi tidak boleh memberi `helpdesk` izin yang admin sendiri tidak punya.
     */
    #[Test]
    public function admin_cannot_grant_helpdesk_a_permission_it_does_not_hold(): void
    {
        $this->grantAdminRoleManagement();
        $admin = $this->makeUserWithRole('admin');
        $helpdesk = Role::where('code', 'helpdesk')->firstOrFail();
        $this->assertTrue($admin->hasPermission('roles.update'), 'Prasyarat: admin harus punya roles.update.');

        $notHeld = Permission::query()->whereNotNull('code')->get()
            ->first(fn (Permission $p) => ! $admin->hasPermission($p->code));
        $this->assertNotNull($notHeld, 'Prasyarat: harus ada izin yang tidak dipegang admin.');

        $before = $helpdesk->permissions()->pluck('permissions.id')->sort()->values()->all();
        $requested = array_values(array_unique(array_merge($before, [$notHeld->id])));

        $this->actingAs($admin)
            ->put(route('roles.update', $helpdesk), ['permissions' => $requested])
            ->assertSessionHas('error');

        $this->assertSame($before, $helpdesk->permissions()->pluck('permissions.id')->sort()->values()->all());
    }

    #[Test]
    public function admin_can_grant_helpdesk_a_permission_it_holds(): void
    {
        $this->grantAdminRoleManagement();
        $admin = $this->makeUserWithRole('admin');
        $helpdesk = Role::where('code', 'helpdesk')->firstOrFail();

        $this->assertTrue($admin->hasPermission('roles.update'), 'Prasyarat: admin harus punya roles.update.');

        $held = Permission::query()->whereNotNull('code')->get()->first(
            fn (Permission $p) => $admin->hasPermission($p->code) && ! $helpdesk->permissions()->where('permissions.id', $p->id)->exists()
        );
        $this->assertNotNull($held, 'Prasyarat: harus ada izin yang dipegang admin tapi belum ada di helpdesk.');

        $requested = array_merge($helpdesk->permissions()->pluck('permissions.id')->all(), [$held->id]);

        $this->actingAs($admin)
            ->put(route('roles.update', $helpdesk), ['permissions' => $requested])
            ->assertSessionDoesntHaveErrors()
            ->assertSessionMissing('error');

        $this->assertTrue($helpdesk->permissions()->where('permissions.id', $held->id)->exists());
    }

    /**
     * Admin di seed TIDAK punya roles.update, jadi matriks role tak terjangkau.
     * Izin itu diberikan ke ROLE admin (bukan langsung ke user) supaya jalur
     * yang diuji sama dengan produksi.
     */
    private function grantAdminRoleManagement(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $adminRole->permissions()->syncWithoutDetaching(
            Permission::where('code', 'roles.update')->pluck('id')->all()
        );
        $this->assertNotEmpty(Permission::where('code', 'roles.update')->get(), 'Prasyarat: izin roles.update harus ada.');
    }

    /**
     * Izin `X.aksi` yang admin pegang, tapi `X.view`-nya tidak. Menyimpan `X.aksi`
     * otomatis ikut menyimpan `X.view` — jadi harus ditolak juga.
     */
    #[Test]
    public function admin_cannot_grant_permission_whose_auto_view_it_does_not_hold(): void
    {
        $this->grantAdminRoleManagement();
        $admin = $this->makeUserWithRole('admin');
        $helpdesk = Role::where('code', 'helpdesk')->firstOrFail();

        $candidate = Permission::with('feature')->whereNotNull('code')->get()->first(function (Permission $p) use ($admin) {
            if (! $p->feature || ! $admin->hasPermission($p->code)) {
                return false;
            }

            $view = $p->feature->code.'.view';

            return $view !== $p->code && ! $admin->hasPermission($view) && Permission::where('code', $view)->exists();
        });
        $this->assertNotNull($candidate, 'Prasyarat: admin harus punya izin yang view induknya tidak dia pegang.');

        $before = $helpdesk->permissions()->pluck('permissions.id')->sort()->values()->all();

        $this->actingAs($admin)
            ->put(route('roles.update', $helpdesk), ['permissions' => array_merge($before, [$candidate->id])])
            ->assertSessionHas('error');

        $this->assertSame($before, $helpdesk->permissions()->pluck('permissions.id')->sort()->values()->all());
    }

    private function makeUserWithRole(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        return $user->fresh();
    }

    #[Test]
    public function owner_can_still_assign_owner_role(): void
    {
        $owner = $this->ownerUser();
        $ownerRole = $owner->role;

        $this->actingAs($owner)
            ->post(route('users.store'), $this->userPayload($ownerRole->id, ['email' => 'owner-baru@example.test']))
            ->assertSessionDoesntHaveErrors('role_id');

        $this->assertDatabaseHas('users', ['email' => 'owner-baru@example.test', 'role_id' => $ownerRole->id]);
    }
}
