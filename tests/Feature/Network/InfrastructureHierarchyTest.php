<?php

namespace Tests\Feature\Network;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureHierarchyService;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proyeksi hierarki tunggal (Task 10/11, rancangan "Slot & PON sebagai
 * node" §7): Server → POP → Mini POP → OLT → Slot → PON → ODP dan
 * Mini POP → Switch → Slot → Port. Sejak Task 11 Slot/PON/Port Switch
 * adalah baris `network_nodes` sungguhan (bukan lagi `kind = slot`/`port`
 * terpisah) dan TIDAK dimuat di proyeksi default — lihat
 * `NodeChildrenLazyLoadTest` untuk perilaku lazy load & endpoint anak.
 * File ini menguji bentuk proyeksi `build()` secara umum.
 */
class InfrastructureHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Pop $miniA;

    private Pop $miniB;

    private NetworkNode $server;

    private NetworkNode $olt1;

    private NetworkNode $switch;

    private NetworkNode $slot;

    private NetworkNode $pon;

    private NetworkNode $odpVerified;

    private NetworkNode $odpLegacy;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        $this->pusat = Pop::factory()->create(['type' => 'pusat', 'name' => 'WHUSNET']);
        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Jetis', 'parent_id' => $this->pusat->id]);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Sandya', 'parent_id' => $this->pusat->id]);
        $this->miniA = Pop::factory()->create(['type' => 'mini_pop', 'name' => 'C1', 'parent_id' => $this->cabangA->id]);
        $this->miniB = Pop::factory()->create(['type' => 'mini_pop', 'name' => 'D1', 'parent_id' => $this->cabangB->id]);

        $positions = app(InfrastructurePositionService::class);
        $this->server = $this->node('server', $this->pusat, 'SRV-UTAMA');
        $this->olt1 = $this->node('olt_chassis', $this->miniA, 'OLT-1');
        $this->switch = $this->node('switch_mini_pop', $this->miniA, 'SW-1');
        $this->slot = $positions->createSlot($this->olt1, 1);
        $this->pon = $positions->createPort($this->slot, 1, ['capacity' => 10, 'red_zone_threshold' => 80]);
        $this->odpVerified = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $this->pon->id, 'anchor_pop_id' => null, 'code' => 'ODP-1', 'name' => 'ODP-1',
        ]);
        // ODP lama: masih ditambatkan LANGSUNG ke POP (anchor_pop_id), bukan
        // lewat PON — peninggalan sebelum keputusan "ODP wajib punya PON".
        $this->odpLegacy = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => null, 'anchor_pop_id' => $this->miniB->id, 'code' => 'ODP-LEGACY', 'name' => 'ODP-LEGACY',
        ]);
    }

    private function node(string $typeCode, Pop $anchor, string $code): NetworkNode
    {
        $typeId = NetworkNodeType::where('code', $typeCode)->value('id');

        return NetworkNode::factory()->create([
            'node_type_id' => $typeId,
            'anchor_pop_id' => $anchor->id, 'code' => $code, 'name' => $code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projection(?User $user = null, bool $withPositions = false): array
    {
        return app(InfrastructureHierarchyService::class)->build($user ?? $this->owner, withPositions: $withPositions);
    }

    private function cabangUser(Pop $cabang, string $role = 'sales'): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function server_tampil_di_atas_pop_pusat_tanpa_siklus(): void
    {
        $p = $this->projection();

        $this->assertContains('node:'.$this->server->id, $p['roots']);
        $this->assertSame('node:'.$this->server->id, $p['items']['pop:'.$this->pusat->id]['parent']);
        $this->assertNull($p['items']['node:'.$this->server->id]['parent']);
    }

    #[Test]
    public function perangkat_olt_dan_switch_tampil_langsung_di_bawah_mini_pop(): void
    {
        $p = $this->projection();

        $this->assertSame('pop:'.$this->cabangA->id, $p['items']['pop:'.$this->miniA->id]['parent']);
        $this->assertEqualsCanonicalizing(
            ['node:'.$this->olt1->id, 'node:'.$this->switch->id],
            array_values(array_diff($p['children']['pop:'.$this->miniA->id], ['node:'.$this->server->id])),
        );
        $this->assertTrue($p['items']['node:'.$this->olt1->id]['has_children']);
    }

    #[Test]
    public function odp_lama_anchor_pop_tetap_muncul_di_bawah_pop(): void
    {
        $p = $this->projection();

        $this->assertArrayHasKey('node:'.$this->odpLegacy->id, $p['items']);
        $this->assertSame('pop:'.$this->miniB->id, $p['items']['node:'.$this->odpLegacy->id]['parent']);
        $this->assertContains('node:'.$this->odpLegacy->id, $p['children']['pop:'.$this->miniB->id]);
    }

    #[Test]
    public function item_tidak_lagi_berkunci_slot_atau_port(): void
    {
        // $withPositions = true supaya Slot/PON ikut termuat — barulah bisa
        // dipastikan betul TIDAK ADA lagi item ber-kind `slot`/`port`.
        $p = $this->projection(withPositions: true);

        $kinds = collect($p['items'])->pluck('kind')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['pop', 'mini_pop', 'node'], $kinds);
        $this->assertSame('node', $p['items']['node:'.$this->slot->id]['kind']);
        $this->assertSame('node', $p['items']['node:'.$this->pon->id]['kind']);
        $this->assertSame('olt_slot', $p['items']['node:'.$this->slot->id]['type']);
        $this->assertSame('olt_pon', $p['items']['node:'.$this->pon->id]['type']);
    }

    #[Test]
    public function with_positions_memuat_rantai_lengkap_sampai_odp(): void
    {
        $p = $this->projection(withPositions: true);

        $this->assertSame('node:'.$this->olt1->id, $p['items']['node:'.$this->slot->id]['parent']);
        $this->assertSame('node:'.$this->slot->id, $p['items']['node:'.$this->pon->id]['parent']);
        $this->assertSame('node:'.$this->pon->id, $p['items']['node:'.$this->odpVerified->id]['parent']);
        $this->assertSame('Jetis → C1 → OLT-1 → Slot 1 → PON 1 → ODP-1', $p['items']['node:'.$this->odpVerified->id]['breadcrumb']);
    }

    #[Test]
    public function kapasitas_pon_dihitung_dari_pelanggan_aktif_odp_anak(): void
    {
        $port = NetworkNodePort::create(['network_node_id' => $this->odpVerified->id, 'number' => 1]);
        $customer = Customer::factory()->create(['pop_id' => $this->cabangA->id, 'full_name' => 'Pelanggan Satu']);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);

        $p = $this->projection(withPositions: true);
        $capacity = $p['items']['node:'.$this->pon->id]['capacity'];

        $this->assertSame(1, $capacity['used']);
        $this->assertSame(10, $capacity['capacity']);
        $this->assertSame('pelanggan aktif', $capacity['unit_label']);
    }

    #[Test]
    public function aktor_satu_cabang_tidak_melihat_cabang_lain(): void
    {
        $user = $this->cabangUser($this->cabangA);
        $p = $this->projection($user);

        $this->assertArrayNotHasKey('pop:'.$this->cabangB->id, $p['items']);
        $this->assertArrayNotHasKey('node:'.$this->odpLegacy->id, $p['items']);
        $this->assertArrayHasKey('node:'.$this->olt1->id, $p['items']);
    }

    #[Test]
    public function metrik_rekonsiliasi_per_cabang_menghitung_odp_di_bawah_pon(): void
    {
        $port = NetworkNodePort::create(['network_node_id' => $this->odpVerified->id, 'number' => 1]);
        $customer = Customer::factory()->create(['pop_id' => $this->cabangA->id, 'full_name' => 'Pelanggan Satu']);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);

        $metrics = app(InfrastructureHierarchyService::class)->metricsByCabang($this->owner);

        // ODP di bawah PON (lazy) + ODP lama anchor-POP (tidak lazy) harus
        // sama-sama terhitung karena metricsByCabang selalu withPositions.
        $this->assertSame(1, $metrics[$this->cabangA->id]['odp']);
        // 'customers' selalu 0: item ber-kind `customer` tidak pernah
        // terbentuk (attachCustomers() sudah dinonaktifkan sebelum Task 11
        // juga — bukan regresi, perilaku warisan yang dipertahankan).
        $this->assertSame(0, $metrics[$this->cabangA->id]['customers']);
        $this->assertSame(1, $metrics[$this->cabangA->id]['used_ports']);
        $this->assertSame(1, $metrics[$this->cabangB->id]['odp']);
    }

    #[Test]
    public function master_node_merender_pohon_tanpa_memuat_pon(): void
    {
        $this->withoutVite();

        $response = $this->actingAs($this->owner)->get(route('network.nodes.index'));

        $response->assertOk()
            ->assertSee('data-hierarchy-key="node:'.$this->olt1->id.'"', false)
            ->assertSee('data-hierarchy-key="node:'.$this->odpLegacy->id.'"', false)
            ->assertDontSee('data-hierarchy-key="node:'.$this->pon->id.'"', false);
    }

    #[Test]
    public function master_node_cabang_lain_tidak_bocor(): void
    {
        $this->withoutVite();

        $this->actingAs($this->cabangUser($this->cabangA, 'fop'))->get(route('network.nodes.index'))
            ->assertOk()
            ->assertDontSee('ODP-LEGACY')
            ->assertDontSee('data-hierarchy-key="pop:'.$this->cabangB->id.'"', false);
    }
}
