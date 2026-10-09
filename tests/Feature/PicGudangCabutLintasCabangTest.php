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
 * Mencabut penunjukan PIC Gudang hanya boleh untuk cabang yang masuk scope actor.
 * Dulu `destroy()` menerima id pivot mana pun — actor cabang A bisa mencabut PIC cabang B.
 */
class PicGudangCabutLintasCabangTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    private User $actor;

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

        $this->assertTrue($this->actor->fresh()->hasPermission('users.update'), 'Prasyarat: actor harus punya users.update.');
    }

    private function makePop(string $suffix): Pop
    {
        return Pop::create([
            'code' => 'POP-PIC'.$suffix,
            'pop_code' => 'PIC'.$suffix,
            'registration_prefix' => 'R'.$suffix,
            'cid_prefix' => 'C'.$suffix,
            'name' => 'Cabang '.$suffix,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makePicAt(Pop $pop): WarehousePopPic
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        return WarehousePopPic::create(['pop_id' => $pop->id, 'user_id' => $teknisi->id]);
    }

    #[Test]
    public function actor_cannot_revoke_pic_of_a_branch_outside_its_scope(): void
    {
        $picB = $this->makePicAt($this->popB);

        $this->actingAs($this->actor)
            ->delete(route('warehouse.pic-gudang.destroy', $picB))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('warehouse_pop_pics', ['id' => $picB->id]);
    }

    #[Test]
    public function actor_can_revoke_pic_of_a_branch_inside_its_scope(): void
    {
        $picA = $this->makePicAt($this->popA);

        $this->actingAs($this->actor)
            ->delete(route('warehouse.pic-gudang.destroy', $picA))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('warehouse_pop_pics', ['id' => $picA->id]);
    }
}
