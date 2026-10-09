<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reproduksi: akun teknisi yang juga punya kolektor.view (worklist kolektor) —
 * halaman worklist harus bisa dibuka tanpa error, dan teknisi tidak boleh
 * diperlakukan sebagai kolektor (tanpa assign admin).
 */
class CollectorWorklistTeknisiTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'POP-WLT', 'pop_code' => 'WLT', 'registration_prefix' => 'C', 'cid_prefix' => 'W',
            'name' => 'POP Worklist Teknisi', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function teknisiDenganScope(bool $punyaKolektorView): User
    {
        $role = Role::where('code', 'teknisi')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        if ($punyaKolektorView) {
            $role->permissions()->syncWithoutDetaching([Permission::where('code', 'kolektor.view')->firstOrFail()->id]);
            app(EffectiveAccessService::class)->clearCache($user);
        }

        return $user;
    }

    /**
     * Teknisi tidak punya worklist kolektor, walau kebetulan punya kolektor.view.
     * Worklist itu berbasis assignment admin; teknisi mencari pelanggan sendiri
     * di Catat Pembayaran.
     */
    #[Test]
    public function teknisi_dengan_kolektor_view_diarahkan_ke_catat_pembayaran(): void
    {
        $teknisi = $this->teknisiDenganScope(punyaKolektorView: true);

        $this->actingAs($teknisi)->get(route('collector-worklist.index'))
            ->assertRedirect(route('technician-payments.index'));
        $this->actingAs($teknisi)->get(route('collector-worklist.index', ['tab' => 'bayar']))
            ->assertRedirect(route('technician-payments.index'));
    }

    #[Test]
    public function kolektor_tetap_bisa_membuka_worklist(): void
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $kolektor->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $this->actingAs($kolektor)->get(route('collector-worklist.index'))->assertOk();
    }
}
