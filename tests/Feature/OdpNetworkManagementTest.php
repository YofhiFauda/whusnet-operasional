<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\FopUnifiedDashboardService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\OdpFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdpNetworkManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(OdpFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_odp_memiliki_port_child_dan_red_zone_dihitung_dari_status_port(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-A', 'name' => 'POP ODP A', 'type' => 'cabang', 'status' => 'active']);

        $this->actingAs($owner)->post(route('network.odps.store'), [
            'pop_id' => $pop->id,
            'code' => 'odp-test-01',
            'name' => 'ODP Test 01',
            'capacity' => 8,
        ])->assertRedirect(route('network.odps.index'));

        $odp = Odp::where('code', 'ODP-TEST-01')->firstOrFail();
        $this->assertSame(8, $odp->ports()->count());
        $this->assertSame(0, $odp->ports()->where('is_used', true)->count());

        foreach ($odp->ports()->orderBy('number')->take(7)->get() as $port) {
            $this->actingAs($owner)->put(route('network.odp-ports.update', $port), [
                'is_used' => 1,
            ])->assertRedirect(route('network.odps.index'));
        }

        $summary = app(FopUnifiedDashboardService::class)->getOdpSummary(true, []);
        $this->assertSame(1, $summary['red_zone_count']);
        $this->assertSame(7, $summary['red_zone'][0]['used']);
        $this->assertSame(8, $summary['red_zone'][0]['capacity']);
        $this->actingAs($owner)->get(route('network.odps.index'))->assertSee('Red Zone');
    }

    public function test_fop_hanya_bisa_mengelola_odp_di_pop_scope_nya(): void
    {
        $allowedPop = Pop::create(['code' => 'ODP-B', 'name' => 'POP ODP B', 'type' => 'cabang', 'status' => 'active']);
        $foreignPop = Pop::create(['code' => 'ODP-C', 'name' => 'POP ODP C', 'type' => 'cabang', 'status' => 'active']);
        $role = Role::where('code', 'fop')->firstOrFail();
        $fop = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $fop->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $allowedPop->id]);

        $localOdp = Odp::create(['pop_id' => $allowedPop->id, 'code' => 'ODP-B-1', 'name' => 'Lokal']);
        $foreignOdp = Odp::create(['pop_id' => $foreignPop->id, 'code' => 'ODP-C-1', 'name' => 'Asing']);
        $foreignPort = $foreignOdp->ports()->create(['number' => 1, 'is_used' => true]);

        $this->actingAs($fop)->get(route('network.odps.index'))
            ->assertOk()
            ->assertSee('ODP-B-1')
            ->assertDontSee('ODP-C-1');

        $this->actingAs($fop)->post(route('network.odps.store'), [
            'pop_id' => $foreignPop->id,
            'code' => 'ODP-C-2',
            'name' => 'Asing Baru',
            'capacity' => 8,
        ])->assertSessionHasErrors('pop_id');

        $this->actingAs($fop)->post(route('network.odps.ports.add', $foreignOdp), [
            'additional_capacity' => 2,
        ])->assertForbidden();
        $this->actingAs($fop)->put(route('network.odp-ports.update', $foreignPort), [
            'is_used' => 0,
        ])->assertForbidden();

        $this->assertTrue($foreignPort->fresh()->is_used);
        $this->assertSame(1, $foreignOdp->ports()->count());
        $this->assertSame(0, app(FopUnifiedDashboardService::class)->getOdpSummary(false, [$allowedPop->id])['red_zone_count']);
    }

    public function test_sales_tidak_dapat_mengakses_master_odp(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);
        $this->actingAs($sales)->get(route('network.odps.index'))->assertForbidden();
    }

    public function test_kapasitas_odp_bisa_ditambah_tanpa_menghapus_port_lama(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-D', 'name' => 'POP ODP D', 'type' => 'cabang', 'status' => 'active']);
        $odp = Odp::create(['pop_id' => $pop->id, 'code' => 'ODP-D-1', 'name' => 'Perlu Ekspansi']);
        $usedPort = $odp->ports()->create(['number' => 1, 'is_used' => true]);

        $this->actingAs($owner)->post(route('network.odps.ports.add', $odp), [
            'additional_capacity' => 3,
        ])->assertRedirect(route('network.odps.index'));

        $this->assertSame([1, 2, 3, 4], $odp->ports()->orderBy('number')->pluck('number')->all());
        $this->assertTrue($usedPort->fresh()->is_used);
    }

    public function test_atasan_dapat_melihat_odp_tetapi_tidak_dapat_mengubah_port(): void
    {
        $atasan = User::factory()->create(['role_id' => Role::where('code', 'atasan')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-E', 'name' => 'POP ODP E', 'type' => 'cabang', 'status' => 'active']);
        $odp = Odp::create(['pop_id' => $pop->id, 'code' => 'ODP-E-1', 'name' => 'ODP Pantau']);
        $port = $odp->ports()->create(['number' => 1]);

        $this->actingAs($atasan)->get(route('network.odps.index'))
            ->assertOk()
            ->assertSee('Master ODP &amp; Port', false)
            ->assertSee('ODP-E-1');

        $this->actingAs($atasan)->put(route('network.odp-ports.update', $port), [
            'is_used' => 1,
        ])->assertForbidden();
        $this->assertFalse($port->fresh()->is_used);
    }
}
