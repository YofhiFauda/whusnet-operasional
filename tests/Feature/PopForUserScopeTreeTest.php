<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.5 — Pop::forUser() harus lewat EffectiveAccessService (paham pop_tree),
 * bukan pivot user_pods yang buta hierarki.
 *
 * Bug lama: user ber-scope SELECTED_POP/POP_TREE ke sebuah cabang HANYA melihat
 * cabang itu di dropdown (pivot user_pops), Mini POP di bawahnya hilang —
 * sementara data pelanggannya (via applyUserScope/getAllowedPopIds) mencakup
 * sub-POP. Dropdown & data jadi tidak sinkron. Test ini mengunci: forUser()
 * mengembalikan cabang + seluruh sub-POP-nya.
 */
class PopForUserScopeTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function pop(string $code, ?int $parentId = null): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'RQ',
            'cid_prefix' => $code,
            'name' => "POP {$code}",
            'type' => $parentId ? 'mini' : 'cabang',
            'status' => 'active',
            'parent_id' => $parentId,
        ]);
    }

    private function scopedUser(array $targetPopIds): User
    {
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        foreach ($targetPopIds as $pid) {
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pid]);
        }
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    public function test_foruser_mengembalikan_cabang_beserta_sub_pop_nya(): void
    {
        $cabang = $this->pop('CBG');
        $mini = $this->pop('CBG1', $cabang->id);
        $luar = $this->pop('OTHER');

        $user = $this->scopedUser([$cabang->id]);

        $visible = Pop::forUser($user)->pluck('id')->all();

        $this->assertContains($cabang->id, $visible, 'Cabang yang di-scope harus terlihat.');
        $this->assertContains($mini->id, $visible, 'Mini POP di bawah cabang harus ikut (pop_tree).');
        $this->assertNotContains($luar->id, $visible, 'POP di luar scope tidak boleh terlihat.');
    }

    public function test_foruser_deny_by_default_untuk_user_tanpa_scope(): void
    {
        $this->pop('NOSCOPE');
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        // Tanpa roleScopes → tidak boleh melihat POP apa pun (deny-by-default),
        // bukan malah semua POP seperti bug cek role-name lama.
        $this->assertSame([], Pop::forUser($user)->pluck('id')->all());
    }

    public function test_owner_melihat_semua_pop(): void
    {
        $owner = $this->loginAsAdmin(); // Owner → hasAllPopAccess
        $this->pop('OWN1');

        $this->assertGreaterThan(0, Pop::forUser($owner)->count());
    }
}
