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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Task 11, rancangan "Slot & PON sebagai node" §7: tingkat Slot ke bawah
 * (Slot, PON, ODP anak PON) TIDAK dimuat di proyeksi default Master Node —
 * dimuat JIT lewat `GET network.nodes.children`. Lihat
 * `InfrastructureHierarchyTest` untuk bentuk proyeksi secara umum.
 */
class NodeChildrenLazyLoadTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Pop $miniA;

    private NetworkNode $olt1;

    private NetworkNode $slot1;

    private NetworkNode $slot2;

    private NetworkNode $pon;

    private NetworkNode $odpSatu;

    private NetworkNode $odpDua;

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

        $this->olt1 = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $this->miniA->id, 'code' => 'OLT-1', 'name' => 'OLT-1',
        ]);
        $positions = app(InfrastructurePositionService::class);
        // Nomor sengaja dibuat 2 dulu lalu 1, supaya tes ordering (urut
        // nomor, bukan urut insert) benar-benar menguji sesuatu.
        $this->slot2 = $positions->createSlot($this->olt1, 2);
        $this->slot1 = $positions->createSlot($this->olt1, 1);
        $this->pon = $positions->createPort($this->slot1, 1, ['capacity' => 10, 'red_zone_threshold' => 80]);
        $this->odpSatu = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $this->pon->id, 'anchor_pop_id' => null, 'code' => 'ODP-SATU', 'name' => 'ODP-SATU',
        ]);
        $this->odpDua = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $this->pon->id, 'anchor_pop_id' => null, 'code' => 'ODP-DUA', 'name' => 'ODP-DUA',
        ]);
    }

    private function cabangUser(Pop $cabang, string $role = 'fop'): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function pohon_tidak_memuat_slot_ke_bawah_sampai_dibuka(): void
    {
        $p = app(InfrastructureHierarchyService::class)->build($this->owner);

        $this->assertArrayHasKey('node:'.$this->olt1->id, $p['items']);
        $this->assertArrayNotHasKey('node:'.$this->slot1->id, $p['items']);
        $this->assertArrayNotHasKey('node:'.$this->pon->id, $p['items']);
        $this->assertArrayNotHasKey('node:'.$this->odpSatu->id, $p['items']);
        $this->assertTrue($p['items']['node:'.$this->olt1->id]['has_children']);
        $this->assertSame([], $p['children']['node:'.$this->olt1->id] ?? []);
    }

    #[Test]
    public function children_olt_mengembalikan_slot_urut_nomor(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('network.nodes.children', $this->olt1));

        $response->assertOk();
        $children = $response->json('children');

        $this->assertCount(2, $children);
        $this->assertSame('Slot 1', $children[0]['label']);
        $this->assertSame('Slot 2', $children[1]['label']);
        $this->assertSame('olt_slot', $children[0]['type']);
        $this->assertTrue($children[0]['has_children']);
        $this->assertFalse($children[1]['has_children']);
        $this->assertSame('node:'.$this->slot1->id, $children[0]['key']);
    }

    #[Test]
    public function children_pon_mengembalikan_odp_dengan_pemakaian(): void
    {
        $port = NetworkNodePort::create(['network_node_id' => $this->odpSatu->id, 'number' => 1]);
        NetworkNodePort::create(['network_node_id' => $this->odpSatu->id, 'number' => 2]);
        $customer = Customer::factory()->create(['pop_id' => $this->cabangA->id, 'full_name' => 'Pelanggan Satu']);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);

        $response = $this->actingAs($this->owner)
            ->getJson(route('network.nodes.children', $this->pon));

        $response->assertOk();
        $children = collect($response->json('children'))->keyBy('id');

        $this->assertCount(2, $children);
        $this->assertSame(1, $children[$this->odpSatu->id]['used']);
        $this->assertSame(2, $children[$this->odpSatu->id]['capacity']);
        $this->assertSame(0, $children[$this->odpDua->id]['used']);
        $this->assertSame('odp', $children[$this->odpSatu->id]['type']);
    }

    #[Test]
    public function children_di_luar_scope_403(): void
    {
        $user = $this->cabangUser($this->cabangB);

        $this->actingAs($user)->getJson(route('network.nodes.children', $this->olt1))
            ->assertForbidden();
    }

    #[Test]
    public function children_node_tidak_ada_404(): void
    {
        $this->actingAs($this->owner)->getJson('/network/nodes/999999/children')->assertNotFound();
    }

    #[Test]
    public function children_tidak_n_plus_satu_saat_dimuat_berjenjang(): void
    {
        // Bobot query (Task 11 Step 3): satu panggilan children() HARUS
        // tetap sejumlah kecil query TETAP walau jumlah anaknya banyak —
        // withCount('children'/'ports') dieksekusi sebagai subquery dalam
        // SATU SELECT, bukan N query per anak (N+1).
        for ($number = 3; $number <= 12; $number++) {
            app(InfrastructurePositionService::class)->createSlot($this->olt1, $number);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($this->owner)->getJson(route('network.nodes.children', $this->olt1));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertCount(12, $response->json('children'));
        // Scope check (authorizeNodeScope) + query anak itu sendiri + cek
        // route model binding — longgar tapi tetap jadi penjaga regresi N+1
        // nyata (yang akan linear terhadap 12 anak kalau lolos).
        $this->assertLessThanOrEqual(6, $queryCount, 'Query log: '.json_encode(DB::getQueryLog()));
    }
}
