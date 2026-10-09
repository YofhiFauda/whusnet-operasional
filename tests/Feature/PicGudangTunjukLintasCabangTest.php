<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\WarehousePopPic;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menunjuk PIC Gudang hanya boleh untuk cabang yang masuk scope actor.
 * Dulu store() hanya menilai scope user yang ditunjuk, bukan actor — admin
 * cabang A bisa menunjuk PIC untuk cabang B lewat pop_id. Sama dengan destroy().
 */
class PicGudangTunjukLintasCabangTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    private User $actor;

    private User $calon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->popA = $this->makePop('A');
        $this->popB = $this->makePop('B');

        $role = Role::where('code', 'noc')->firstOrFail();
        $this->actor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $this->actor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->popA->id]);

        // Calon PIC juga harus punya scope ke cabang A — aturan existing: PIC yang
        // scope-nya tidak mencakup cabang itu akan macet (store() menolak).
        $this->calon = User::factory()->create([
            'role_id' => Role::where('code', 'pic_gudang')->firstOrFail()->id,
            'status' => 'active',
        ]);
        $calonScope = UserRoleScope::create([
            'user_id' => $this->calon->id,
            'role_id' => $this->calon->role_id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $calonScope->id, 'pop_id' => $this->popA->id]);
    }

    private function makePop(string $suffix): Pop
    {
        return Pop::create([
            'code' => 'POP-PICT'.$suffix,
            'pop_code' => 'PT'.$suffix,
            'registration_prefix' => 'R'.$suffix,
            'cid_prefix' => 'C'.$suffix,
            'name' => 'Cabang '.$suffix,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function actor_tidak_bisa_menunjuk_pic_untuk_cabang_di_luar_scope(): void
    {
        $this->actingAs($this->actor)
            ->post(route('warehouse.pic-gudang.store'), ['pop_id' => $this->popB->id, 'user_id' => $this->calon->id])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('warehouse_pop_pics', ['pop_id' => $this->popB->id, 'user_id' => $this->calon->id]);
    }

    #[Test]
    public function actor_bisa_menunjuk_pic_untuk_cabang_di_dalam_scope(): void
    {
        $this->actingAs($this->actor)
            ->post(route('warehouse.pic-gudang.store'), ['pop_id' => $this->popA->id, 'user_id' => $this->calon->id])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue(WarehousePopPic::where('pop_id', $this->popA->id)->where('user_id', $this->calon->id)->exists());
    }
}
