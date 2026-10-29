<?php

namespace Tests\Feature\Network;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\FopTask;
use App\Models\InfrastructureBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InfrastructureMapService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function peta_merangkai_server_pop_dan_mini_pop_hasil_seeder_struktur_baru(): void
    {
        $pusat = Pop::factory()->create(['name' => 'WHUSNET', 'type' => 'pusat', 'parent_id' => null]);
        $jetis = Pop::factory()->create(['name' => 'Jetis', 'type' => 'cabang', 'parent_id' => $pusat->id]);
        $mini = Pop::factory()->create(['code' => 'C1', 'name' => 'C1', 'type' => 'mini_pop', 'parent_id' => $jetis->id]);
        $sandya = Pop::factory()->create(['name' => 'Sandya', 'type' => 'cabang', 'parent_id' => null]);
        Pop::factory()->create(['code' => 'D1', 'name' => 'D1', 'type' => 'mini_pop', 'parent_id' => $sandya->id]);
        $role = Role::where('code', 'owner')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $type = fn (string $code) => NetworkNodeType::where('code', $code)->firstOrFail();
        $server = NetworkNode::factory()->create(['node_type_id' => $type('server')->id, 'anchor_pop_id' => $pusat->id, 'code' => 'CORE-WHUSNET-PUSAT']);
        $olt = NetworkNode::factory()->create(['node_type_id' => $type('olt_chassis')->id, 'anchor_pop_id' => $mini->id, 'code' => 'OLT-JETIS-01']);
        NetworkNode::factory()->create(['node_type_id' => $type('switch_mini_pop')->id, 'anchor_pop_id' => $mini->id, 'code' => 'SW-JETIS-01']);
        $positions = app(InfrastructurePositionService::class);
        $slot = $positions->createSlot($olt, 1);
        $pon = $positions->createPort($slot, 1, ['capacity' => 8]);
        $odp = NetworkNode::factory()->create(['node_type_id' => $type('odp')->id, 'parent_id' => $pon->id, 'anchor_pop_id' => null, 'code' => 'ODP-JETIS-01']);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $topology = app(InfrastructureMapService::class)->build($user, null, 'name')['topologyDetails'];

        // Rancangan hierarki §1/§16.2: Server diproyeksikan di atas Pusat,
        // Cabang → Mini POP → OLT/Switch; tanpa backbone/agregasi lama.
        $this->assertSame($server->id, $topology[-$pusat->id]['parent_id']);
        $this->assertNull($topology[$server->id]['parent_id']);
        $this->assertSame(-$pusat->id, $topology[-$jetis->id]['parent_id']);
        $this->assertSame(-$jetis->id, $topology[-$mini->id]['parent_id']);
        $this->assertSame(-$mini->id, $topology[$olt->id]['parent_id']);
        $this->assertSame(-$mini->id, $topology[NetworkNode::where('code', 'SW-JETIS-01')->value('id')]['parent_id']);

        // Task 11: Slot/PON/Port Switch baris `network_nodes` sungguhan —
        // rantai lengkap OLT→Slot→PON→ODP dari `parent_id`, bukan lagi
        // kanvas negatif-offset dari tabel `infrastructure_slots/ports`.
        $this->assertSame($olt->id, $topology[$slot->id]['parent_id']);
        $this->assertSame($slot->id, $topology[$pon->id]['parent_id']);
        $this->assertSame($pon->id, $topology[$odp->id]['parent_id']);

        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $scopedUser = User::factory()->create(['role_id' => $fopRole->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $scopedUser->id,
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $jetis->id]);
        $scopedTopology = app(InfrastructureMapService::class)->build($scopedUser, $jetis->id, 'name')['topologyDetails'];
        $this->assertArrayNotHasKey($server->id, $scopedTopology);
        $this->assertNull($scopedTopology[-$jetis->id]['parent_id']);
        $this->assertSame(-$jetis->id, $scopedTopology[-$mini->id]['parent_id']);
    }

    #[Test]
    public function slot_olt_kosong_artinya_slot_tanpa_pon_dan_mengikuti_scope_pop(): void
    {
        $jetis = Pop::factory()->create(['name' => 'Jetis', 'type' => 'cabang', 'parent_id' => null]);
        $miniJetis = Pop::factory()->create(['code' => 'CJ', 'name' => 'CJ', 'type' => 'mini_pop', 'parent_id' => $jetis->id]);
        $sandya = Pop::factory()->create(['name' => 'Sandya', 'type' => 'cabang', 'parent_id' => null]);
        $miniSandya = Pop::factory()->create(['code' => 'DS', 'name' => 'DS', 'type' => 'mini_pop', 'parent_id' => $sandya->id]);
        $oltType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();

        $oltJetis = NetworkNode::factory()->create(['node_type_id' => $oltType->id, 'anchor_pop_id' => $miniJetis->id, 'code' => 'OLT-JETIS-SLOT']);
        NetworkNode::factory()->slot($oltJetis, 1)->create();
        $slotTerisi = NetworkNode::factory()->slot($oltJetis, 2)->create();
        NetworkNode::factory()->pon($slotTerisi, 1)->create();

        // Dua Slot kosong di cabang lain: tidak boleh ikut terhitung saat scope Jetis.
        $oltSandya = NetworkNode::factory()->create(['node_type_id' => $oltType->id, 'anchor_pop_id' => $miniSandya->id, 'code' => 'OLT-SANDYA-SLOT']);
        NetworkNode::factory()->slot($oltSandya, 1)->create();
        NetworkNode::factory()->slot($oltSandya, 2)->create();

        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $owner->id, 'role_id' => $owner->role_id, 'scope_type' => ScopeType::ALL_POP]);

        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $scoped = User::factory()->create(['role_id' => $fopRole->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $scoped->id, 'role_id' => $fopRole->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $jetis->id]);

        $service = app(InfrastructureMapService::class);

        $this->assertSame(1, $service->build($scoped, $jetis->id, 'name')['summary']['free_olt_slots']);
        $this->assertSame(3, $service->build($owner, null, 'name')['summary']['free_olt_slots']);
    }

    #[Test]
    public function peta_menampilkan_topologi_inventaris_port_dan_bukti_aset_terpasang(): void
    {
        $cabang = Pop::factory()->create(['name' => 'Cabang Peta A', 'type' => 'cabang']);
        $type = NetworkNodeType::factory()->create([
            'code' => 'odp_peta',
            'name' => 'ODP Peta',
            'has_ports' => true,
            'monitors_capacity' => true,
            'red_zone_threshold' => 80,
        ]);
        $node = NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'node_type_id' => $type->id,
            'code' => 'ODP-PETA-01',
            'name' => 'ODP Peta Satu',
        ]);
        NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'code' => 'NODE-TIDAK-DICARI']);
        $node->ports()->create(['number' => 1, 'is_used' => true]);
        $node->ports()->create(['number' => 2, 'is_used' => false]);

        $category = ItemCategory::create([
            'code' => 'peta_test',
            'name' => 'Kategori Peta',
            'default_unit' => 'pcs',
            'equipment_class' => 'pasif',
            'is_system' => false,
            'is_active' => true,
        ]);
        $serialItem = Item::create([
            'code' => 'PETA-SPLITTER',
            'name' => 'Splitter Peta',
            'unit' => 'pcs',
            'item_category_id' => $category->id,
            'tracking_type' => 'serialized',
            'ownership_mode' => 'infrastructure_asset',
        ]);
        $serial = InventorySerial::create([
            'item_id' => $serialItem->id,
            'serial_number' => 'PETA-SN-001',
            'status' => SerialStatus::DEPLOYED->value,
            'current_network_node_id' => $node->id,
        ]);
        $technician = User::factory()->create(['name' => 'Teknisi Peta']);
        $task = Task::create([
            'task_number' => 'TASK-PETA-001',
            'task_type' => TaskType::INFR,
            'title' => 'Pasang splitter peta',
            'pop_id' => $cabang->id,
            'status' => TaskStatus::DRAFT,
        ]);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-PETA-001',
            'task_id' => $task->id,
            'task_date' => now(),
            'category' => TaskType::INFR,
            'tugas' => 'Pasang splitter peta',
            'pop_id' => $cabang->id,
            'status' => TaskStatus::DRAFT,
            'priority' => FopTaskPriority::MEDIUM,
        ]);
        InventoryTransaction::create([
            'type' => 'deploy',
            'reference_number' => 'PETA-DEPLOY-001',
            'item_id' => $serialItem->id,
            'serial_id' => $serial->id,
            'qty' => 1,
            'to_network_node_id' => $node->id,
            'to_pop_id' => $cabang->id,
            'from_technician_id' => $technician->id,
            'fop_task_id' => $fopTask->id,
            'created_by' => $technician->id,
        ]);

        $qtyItem = Item::create([
            'code' => 'PETA-CLOSURE',
            'name' => 'Closure Peta',
            'unit' => 'pcs',
            'item_category_id' => $category->id,
            'tracking_type' => 'quantity',
            'ownership_mode' => 'infrastructure_asset',
        ]);
        InfrastructureBalance::create([
            'item_id' => $qtyItem->id,
            'lot_no' => '',
            'network_node_id' => $node->id,
            'qty' => 3,
        ]);

        $originalNode = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'code' => 'NODE-ASAL-KOREKSI']);
        $originalDeployment = InventoryTransaction::create([
            'type' => 'deploy', 'reference_number' => 'PETA-DEPLOY-OLD',
            'item_id' => $qtyItem->id, 'qty' => 3,
            'to_network_node_id' => $originalNode->id, 'to_pop_id' => $cabang->id,
            'fop_task_id' => $fopTask->id, 'created_by' => $technician->id,
        ]);
        InventoryTransaction::create([
            'type' => 'adjustment', 'reference_number' => 'PETA-CORRECTION-001',
            'item_id' => $qtyItem->id, 'qty' => 3, 'reason' => 'infra_record_correction',
            'source_inventory_transaction_id' => $originalDeployment->id,
            'to_network_node_id' => $node->id, 'to_pop_id' => $cabang->id,
            'fop_task_id' => $fopTask->id, 'created_by' => $technician->id,
        ]);

        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $owner->roleScopes()->create(['role_id' => $owner->role_id, 'scope_type' => ScopeType::ALL_POP]);
        $asset = collect(app(InfrastructureMapService::class)->build($owner, null, 'name')['assetsByNode'][$node->id])
            ->firstWhere('name', 'Closure Peta');
        $this->assertSame('TASK-PETA-001', $asset['task']);

        $this->loginAsAdmin();
        $response = $this->get(route('network.map.index'));

        $response->assertOk()
            ->assertSee('Topologi')
            ->assertSee('Inventaris')
            ->assertSee('ODP-PETA-01')
            ->assertSee('ODP Peta Satu')
            ->assertSee('PETA-SN-001')
            ->assertSee('Splitter Peta')
            ->assertSee('Closure Peta')
            ->assertSee('Teknisi Peta')
            ->assertSee('TASK-PETA-001')
            ->assertDontSee('TFOP-PETA-001')
            ->assertSee('1/2');

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//template[@x-for="id in renderIds" and parent::div]/svg')->length);
        $this->assertStringContainsString('edgePaths(layout)', $xpath->query('//div[contains(@class, "isp-infra")]')->item(0)->getAttribute('x-data'));

        $export = $this->get(route('network.map.export', ['q' => 'PETA-SN-001', 'type' => $type->code]));
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('PETA-SN-001', $export->streamedContent());
        $this->assertStringContainsString('Teknisi Peta', $export->streamedContent());
        $this->assertStringNotContainsString('NODE-TIDAK-DICARI', $export->streamedContent());
    }

    #[Test]
    public function peta_tidak_membocorkan_node_dan_aset_di_luar_scope_cabang(): void
    {
        $cabangA = Pop::factory()->create(['name' => 'Cabang Aman A', 'type' => 'cabang']);
        $cabangB = Pop::factory()->create(['name' => 'Cabang Rahasia B', 'type' => 'cabang']);
        $visibleParent = NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'code' => 'NODE-AMAN']);
        NetworkNode::factory()->create(['anchor_pop_id' => $cabangB->id, 'code' => 'NODE-RAHASIA']);
        NetworkNode::factory()->create([
            'anchor_pop_id' => $cabangB->id,
            'parent_id' => $visibleParent->id,
            'code' => 'NODE-TURUNAN-RAHASIA',
        ]);

        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangA->id]);

        $this->actingAs($user)->get(route('network.map.index', ['pop_id' => $cabangB->id]))
            ->assertOk()
            ->assertDontSee('NODE-RAHASIA')
            ->assertDontSee('NODE-TURUNAN-RAHASIA')
            ->assertDontSee('Cabang Rahasia B');

        $export = $this->actingAs($user)->get(route('network.map.export'));
        $export->assertOk();
        $this->assertStringContainsString('NODE-AMAN', $export->streamedContent());
        $this->assertStringNotContainsString('NODE-TURUNAN-RAHASIA', $export->streamedContent());
    }

    #[Test]
    public function node_anak_tetap_tampil_saat_induk_di_luar_scope(): void
    {
        $cabang = Pop::factory()->create(['name' => 'Cabang Induk', 'type' => 'cabang']);
        $miniPop = Pop::factory()->create([
            'name' => 'Mini POP Terbatas',
            'type' => 'mini_pop',
            'parent_id' => $cabang->id,
        ]);
        $parent = NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'code' => 'NODE-INDUK-SCOPE',
        ]);
        NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id,
            'parent_id' => $parent->id,
            'code' => 'NODE-ANAK-SCOPE',
        ]);

        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $miniPop->id]);

        $this->actingAs($user)->get(route('network.map.index'))
            ->assertOk()
            ->assertSee('NODE-ANAK-SCOPE')
            ->assertDontSee('NODE-INDUK-SCOPE');
    }

    #[Test]
    public function node_anak_tanpa_anchor_mewarisi_scope_pop_induk(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $miniPop = Pop::factory()->create([
            'type' => 'mini_pop',
            'parent_id' => $cabang->id,
        ]);
        $parent = NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id,
            'code' => 'ODC-POP-TERBATAS',
        ]);
        NetworkNode::factory()->create([
            'anchor_pop_id' => null,
            'parent_id' => $parent->id,
            'code' => 'ODP-WARIS-POP',
        ]);

        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $miniPop->id]);

        $this->actingAs($user)->get(route('network.map.index'))
            ->assertOk()
            ->assertSee('ODC-POP-TERBATAS')
            ->assertSee('ODP-WARIS-POP');
    }

    #[Test]
    public function master_node_memiliki_tautan_ke_peta_infrastruktur(): void
    {
        $this->loginAsAdmin();

        $this->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('Peta Infrastruktur ISP')
            ->assertSee(route('network.map.index'), false);
    }

    #[Test]
    public function peta_default_tidak_memuat_port_dan_kartu_ont_pelanggan_demi_memori(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::factory()->create(['code' => 'odp_lazy', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $type->id]);
        $port = $node->ports()->create(['number' => 1, 'is_used' => true]);
        $customer = Customer::factory()->create(['pop_id' => $cabang->id, 'full_name' => 'Pelanggan Lazy']);
        NetworkPortAssignment::create([
            'network_node_port_id' => $port->id, 'customer_id' => $customer->id,
            'active_key' => 1, 'assigned_at' => now(),
        ]);

        $this->loginAsAdmin();
        $payload = app(InfrastructureMapService::class)->build(auth()->user(), null, 'topology');

        $this->assertArrayNotHasKey(-1000000000 - $customer->id, $payload['topologyDetails']);
        $this->assertSame([], $payload['topologyDetails'][$node->id]['ports']);
        $this->assertFalse($payload['topologyDetails'][$node->id]['ports_loaded']);
        $this->assertSame(route('network.map.ports', $node), $payload['topologyDetails'][$node->id]['ports_url']);
        $this->assertSame(1, $payload['topologyDetails'][$node->id]['used_ports_count']);
        $this->assertFalse($node->fresh()->relationLoaded('ports'));

        $this->get(route('network.map.index'))->assertOk()->assertDontSee('Pelanggan Lazy');
    }

    #[Test]
    public function tab_pohon_dan_inventaris_hanya_dirender_saat_dibuka(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'code' => 'NODE-TAB-LAZY', 'name' => 'Node Tab Lazy']);
        $this->loginAsAdmin();

        // Topologi (default): payload kanvas memuat node, tapi baris tabel
        // inventaris & pohon tidak dirender.
        $default = $this->get(route('network.map.index'))->assertOk();
        $default->assertDontSee('data-inventory-row', false);
        // URL tab dirender server di atribut Alpine (query lain ikut terbawa).
        $default->assertSee('tab=inventaris', false);
        $default->assertSee('tab=pohon', false);

        $this->get(route('network.map.index', ['tab' => 'inventaris']))
            ->assertOk()->assertSee('data-inventory-row', false)->assertSee('NODE-TAB-LAZY');
    }

    #[Test]
    public function mode_ont_mengirim_kartu_ont_lewat_endpoint_per_odp_dan_per_slot_bukan_di_payload_awal(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $slotType = NetworkNodeType::firstOrCreate(['code' => 'olt_slot'], NetworkNodeType::factory()->make(['code' => 'olt_slot', 'has_ports' => false])->toArray());
        $odpType = NetworkNodeType::firstOrCreate(['code' => 'odp'], NetworkNodeType::factory()->make(['code' => 'odp', 'has_ports' => true])->toArray());
        $slot = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $slotType->id, 'code' => 'SLOT-ONT-1']);
        $odp = NetworkNode::factory()->create(['parent_id' => $slot->id, 'anchor_pop_id' => null, 'node_type_id' => $odpType->id, 'code' => 'ODP-ONT-1']);
        $ontIds = [];
        foreach ([1, 2] as $number) {
            $port = $odp->ports()->create(['number' => $number, 'is_used' => true]);
            $customer = Customer::factory()->create(['pop_id' => $cabang->id, 'full_name' => 'Pelanggan Lazy '.$number]);
            NetworkPortAssignment::create([
                'network_node_port_id' => $port->id, 'customer_id' => $customer->id,
                'active_key' => 1, 'assigned_at' => now(),
            ]);
            $ontIds[] = -1000000000 - $customer->id;
        }

        $this->loginAsAdmin();
        $html = $this->get(route('network.map.index', ['ont' => 1]))->assertOk()->getContent();

        // Payload awal: struktur sampai ODP + URL, kartu ONT belum ada...
        $this->assertStringContainsString('ODP-ONT-1', $html);
        $this->assertStringContainsString('"ont_batch_url"', $html);
        $this->assertStringNotContainsString('"typeName":"ONT pelanggan"', $html);
        // ...tetapi pelanggan tetap bisa dicari lewat indeks.
        $this->assertStringContainsString('pelanggan lazy 1', $html);

        $perOdp = $this->getJson(route('network.map.children', ['node' => $odp, 'ont' => 1]))->assertOk()->json('nodes');
        $this->assertEqualsCanonicalizing($ontIds, array_map('intval', array_keys($perOdp)));

        $perSlot = $this->getJson(route('network.map.children-batch', ['node' => $slot, 'ont' => 1]))->assertOk()->json('nodes');
        $this->assertEqualsCanonicalizing($ontIds, array_map('intval', array_keys($perSlot)));
        $this->assertSame('customer_ont', $perSlot[$ontIds[0]]['type']);

        // Tab Pohon/Inventaris tidak membangun kartu ONT (berat), tetapi mengingat pilihan ONT
        // untuk tombol kembali ke Topologi.
        foreach (['pohon', 'inventaris'] as $tab) {
            $this->get(route('network.map.index', ['ont' => 1, 'tab' => $tab]))->assertOk()
                ->assertSee('withOnt: false', false)->assertSee('ontRequested: true', false)
                ->assertDontSee('"ont_batch_url"', false);
        }
        $this->get(route('network.map.index', ['ont' => 1]))->assertOk()
            ->assertSee('withOnt: true', false)->assertSee('ontRequested: true', false);

        // Batch hanya untuk mode ONT, dan hanya untuk Slot yang punya ONT.
        $this->getJson(route('network.map.children-batch', ['node' => $slot]))->assertNotFound();
        $this->getJson(route('network.map.children-batch', ['node' => $odp, 'ont' => 1]))->assertNotFound();
    }

    #[Test]
    public function cari_pelanggan_menemukan_nama_cid_dan_sn_sesuai_izin_dan_scope(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $lain = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::factory()->create(['code' => 'odp_cari', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $type->id, 'code' => 'ODP-CARI-1']);
        $nodeLain = NetworkNode::factory()->create(['anchor_pop_id' => $lain->id, 'node_type_id' => $type->id]);

        $customer = Customer::factory()->create(['pop_id' => $cabang->id, 'full_name' => 'Budi Pencarian', 'ont_sn' => 'ZTEGCARI001']);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'router_or_ont_serial' => 'HWTCCARI777']);
        $asing = Customer::factory()->create(['pop_id' => $lain->id, 'full_name' => 'Budi Asing']);
        foreach ([[$node, $customer], [$nodeLain, $asing]] as $index => [$target, $pelanggan]) {
            $port = $target->ports()->create(['number' => 1, 'is_used' => true]);
            NetworkPortAssignment::create([
                'network_node_port_id' => $port->id, 'customer_id' => $pelanggan->id,
                'active_key' => 1, 'assigned_at' => now(),
            ]);
        }

        $this->loginAsAdmin();
        $byName = $this->getJson(route('network.map.search', ['q' => 'pencarian']))->assertOk()->json('results');
        $this->assertCount(1, $byName);
        $this->assertSame(-1000000000 - $customer->id, $byName[0]['ont_id']);
        $this->assertSame('ODP-CARI-1', $byName[0]['node_code']);
        $this->assertSame('Nama', $byName[0]['match']);
        $this->assertSame(route('network.map.ont', $customer->id), $byName[0]['detail_url']);

        // Kartu tunggal hasil cari: detail ONT + induknya, tanpa membangun seluruh peta.
        $single = $this->getJson($byName[0]['detail_url'])->assertOk()->json();
        $this->assertSame($node->id, $single['parent_id']);
        $this->assertSame('customer_ont', $single['node']['type']);
        $this->assertSame($byName[0]['ont_id'], $single['node']['id']);
        $this->assertStringContainsString('ODP-CARI-1', $single['node']['location_description']);
        $this->getJson(route('network.map.ont', 999999))->assertNotFound();

        $bySn = $this->getJson(route('network.map.search', ['q' => 'HWTCCARI']))->assertOk()->json('results');
        $this->assertSame('SN', $bySn[0]['match']);
        $this->assertCount(2, $this->getJson(route('network.map.search', ['q' => 'budi']))->json('results'));
        $this->getJson(route('network.map.search', ['q' => 'bu']))->assertUnprocessable();

        // Tanpa izin SN perangkat, pencarian SN tidak menemukan apa pun.
        $role = Role::where('code', 'fop')->firstOrFail();
        $role->permissions()->detach(Permission::where('code', 'customers.detail.devices.view_sensitive')->value('id'));
        $limited = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $limited->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        $this->actingAs($limited)->getJson(route('network.map.search', ['q' => 'HWTCCARI']))->assertOk()->assertJsonCount(0, 'results');
        // ...dan scope POP menyaring pelanggan cabang lain.
        $this->actingAs($limited)->getJson(route('network.map.search', ['q' => 'budi']))->assertOk()->assertJsonCount(1, 'results');
        $this->actingAs($limited)->getJson(route('network.map.ont', $asing->id))->assertNotFound();
    }

    #[Test]
    public function jaringan_besar_mengirim_odp_per_pon_bukan_di_payload_awal(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $ponType = NetworkNodeType::firstOrCreate(['code' => 'olt_pon'], NetworkNodeType::factory()->make(['code' => 'olt_pon', 'has_ports' => false])->toArray());
        $odpType = NetworkNodeType::firstOrCreate(['code' => 'odp'], NetworkNodeType::factory()->make(['code' => 'odp', 'has_ports' => true])->toArray());
        $pon = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $ponType->id, 'code' => 'PON-LAZY-1']);
        NetworkNode::factory()->count(405)->sequence(fn ($seq) => [
            'code' => 'ODP-LAZY-'.$seq->index,
            'parent_id' => $pon->id,
            'anchor_pop_id' => null,
            'node_type_id' => $odpType->id,
        ])->create();

        $this->loginAsAdmin();
        $html = $this->get(route('network.map.index'))->assertOk()->getContent();

        $this->assertStringContainsString('PON-LAZY-1', $html);
        $this->assertStringContainsString('lazy_children_count', $html);
        // Kartu ODP tidak ada di payload kanvas...
        $this->assertStringNotContainsString('"code":"ODP-LAZY-7"', $html);
        // ...tetapi tetap bisa dicari lewat indeks.
        $this->assertStringContainsString('odp-lazy-7', $html);

        $nodes = $this->getJson(route('network.map.children', $pon))->assertOk()->json('nodes');
        $this->assertCount(405, $nodes);
        $this->assertContains('ODP-LAZY-7', array_column($nodes, 'code'));

        // Tab Inventaris butuh data penuh: tidak ada pemisahan.
        $this->get(route('network.map.index', ['tab' => 'inventaris']))->assertOk()->assertSee('ODP-LAZY-7');
    }

    #[Test]
    public function tab_topologi_jaringan_besar_dilayani_dari_cache_dan_fresh_membangun_ulang(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $ponType = NetworkNodeType::firstOrCreate(['code' => 'olt_pon'], NetworkNodeType::factory()->make(['code' => 'olt_pon', 'has_ports' => false])->toArray());
        $odpType = NetworkNodeType::firstOrCreate(['code' => 'odp'], NetworkNodeType::factory()->make(['code' => 'odp', 'has_ports' => true])->toArray());
        $pon = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $ponType->id, 'code' => 'PON-CACHE-1']);
        NetworkNode::factory()->count(405)->sequence(fn ($seq) => [
            'code' => 'ODP-CACHE-'.$seq->index, 'parent_id' => $pon->id, 'anchor_pop_id' => null, 'node_type_id' => $odpType->id,
        ])->create();

        $this->loginAsAdmin();
        $this->get(route('network.map.index'))->assertOk()->assertSee('PON-CACHE-1');

        // Hit cache: tidak ada query ke network_nodes sama sekali.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('network.map.index'))->assertOk()->assertSee('PON-CACHE-1')->assertSee('406 node dalam cakupan');
        $nodeQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'network_nodes'))->count();
        $this->assertSame(0, $nodeQueries, 'tab Topologi dari cache tidak boleh menyentuh network_nodes');

        // ?fresh=1 membuang cache lalu membangun ulang.
        NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'code' => 'NODE-BARU-FRESH']);
        $this->get(route('network.map.index'))->assertDontSee('NODE-BARU-FRESH');
        $this->get(route('network.map.index', ['fresh' => 1]))->assertRedirect(route('network.map.index'));
        $this->get(route('network.map.index'))->assertOk()->assertSee('NODE-BARU-FRESH');
    }

    #[Test]
    public function tombol_buka_dan_tutup_semua_tersedia_dengan_url_mode_ont_dari_server(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id]);
        $this->loginAsAdmin();

        $html = $this->get(route('network.map.index'))->assertOk()
            ->assertSee('Buka Semua')->assertSee('Tutup Semua')
            ->assertSee('expandAll()', false)->assertSee('collapseToSlots()', false)
            ->getContent();
        // URL mode ONT dirender server (dalam JSON x-data, garis miring ter-escape).
        $this->assertStringContainsString('ont=1', $html);
        $this->assertStringContainsString('expand=all', $html);

        $this->get(route('network.map.index', ['ont' => 1, 'expand' => 'all']))
            ->assertOk()->assertSee('startExpanded: true', false);

        // Toggle "ONT pelanggan: tampil" (tanpa expand=all) tidak boleh macet di Slot:
        // kartu ONT baru terlihat kalau lipatan PON/ODP ikut terbuka.
        $this->get(route('network.map.index', ['ont' => 1]))
            ->assertOk()->assertSee('startExpanded: true', false);
        $this->get(route('network.map.index'))
            ->assertOk()->assertSee('startExpanded: false', false);
    }

    #[Test]
    public function children_node_di_luar_scope_pop_mendapat_404(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $ponType = NetworkNodeType::firstOrCreate(['code' => 'olt_pon'], NetworkNodeType::factory()->make(['code' => 'olt_pon', 'has_ports' => false])->toArray());
        $odpType = NetworkNodeType::firstOrCreate(['code' => 'odp'], NetworkNodeType::factory()->make(['code' => 'odp', 'has_ports' => true])->toArray());
        $ponAsing = NetworkNode::factory()->create(['anchor_pop_id' => $cabangB->id, 'node_type_id' => $ponType->id]);
        NetworkNode::factory()->create(['parent_id' => $ponAsing->id, 'anchor_pop_id' => null, 'node_type_id' => $odpType->id]);

        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangA->id]);

        $this->actingAs($user)->getJson(route('network.map.children', $ponAsing))->assertNotFound();
    }

    #[Test]
    public function endpoint_port_node_mengembalikan_pelanggan_dan_modem_sesuai_izin(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::factory()->create(['code' => 'odp_ports', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $type->id]);
        $port = $node->ports()->create(['number' => 1, 'is_used' => true]);
        $node->ports()->create(['number' => 2, 'is_used' => false]);
        $customer = Customer::factory()->create(['pop_id' => $cabang->id, 'full_name' => 'Budi Port']);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'router_or_ont_serial' => 'ONT-LAZY-1']);
        NetworkPortAssignment::create([
            'network_node_port_id' => $port->id, 'customer_id' => $customer->id,
            'active_key' => 1, 'assigned_at' => now(),
        ]);

        $this->loginAsAdmin();
        $json = $this->getJson(route('network.map.ports', $node))->assertOk()->json('ports');
        $this->assertCount(2, $json);
        $this->assertSame('Budi Port', $json[0]['customer_name']);
        $this->assertSame('ONT-LAZY-1', $json[0]['customer_modems'][0]['serial_number']);
        $this->assertSame('empty', $json[1]['kind']);

        $role = Role::where('code', 'fop')->firstOrFail();
        $role->permissions()->detach(Permission::where('code', 'customers.detail.devices.view_sensitive')->value('id'));
        $limited = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $limited->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $hidden = $this->actingAs($limited)->getJson(route('network.map.ports', $node))->assertOk()->json('ports');
        $this->assertTrue($hidden[0]['customer_modems_hidden']);
        $this->assertSame([], $hidden[0]['customer_modems']);
    }

    #[Test]
    public function endpoint_port_node_di_luar_scope_pop_mendapat_404(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $asing = NetworkNode::factory()->create(['anchor_pop_id' => $cabangB->id]);

        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangA->id]);

        $this->actingAs($user)->getJson(route('network.map.ports', $asing))->assertNotFound();
    }

    #[Test]
    public function modem_pelanggan_di_port_odp_tampil_lengkap_di_detail_dan_peta(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::factory()->create(['code' => 'odp_modem', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'node_type_id' => $type->id]);
        $port = $node->ports()->create(['number' => 1, 'is_used' => true]);
        $emptyModemPort = $node->ports()->create(['number' => 2, 'is_used' => true]);
        $customer = Customer::factory()->create(['pop_id' => $cabang->id, 'ont_sn' => 'LEGACY-ONT-SN']);
        $withoutModem = Customer::factory()->create(['pop_id' => $cabang->id]);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'router_or_ont_serial' => 'ONT-SN-A']);
        CustomerDevice::create([
            'customer_id' => $customer->id, 'device_type' => 'ont',
            'brand' => 'ZTE', 'model' => 'F660', 'serial_number' => 'DEVICE-SN',
        ]);
        NetworkPortAssignment::create([
            'network_node_port_id' => $port->id, 'customer_id' => $customer->id,
            'active_key' => 1, 'assigned_at' => now(),
        ]);
        NetworkPortAssignment::create([
            'network_node_port_id' => $emptyModemPort->id, 'customer_id' => $withoutModem->id,
            'active_key' => 1, 'assigned_at' => now(),
        ]);

        $modemCategory = ItemCategory::create([
            'code' => 'modem_ont', 'name' => 'Modem/ONT', 'default_unit' => 'pcs',
            'equipment_class' => 'aktif', 'is_active' => true,
        ]);
        $otherCategory = ItemCategory::create([
            'code' => 'router_gateway', 'name' => 'Router Gateway', 'default_unit' => 'pcs',
            'equipment_class' => 'aktif', 'is_active' => true,
        ]);
        $modem = Item::create([
            'code' => 'ONT-TEST', 'name' => 'ONT Huawei', 'unit' => 'pcs',
            'item_category_id' => $modemCategory->id, 'tracking_type' => 'serialized',
            'ownership_mode' => 'installable',
        ]);
        $router = Item::create([
            'code' => 'ROUTER-TEST', 'name' => 'Router Internal', 'unit' => 'pcs',
            'item_category_id' => $otherCategory->id, 'tracking_type' => 'serialized',
            'ownership_mode' => 'installable',
        ]);
        $legacyCategory = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $legacyModem = Item::create([
            'code' => 'ONT-LEGACY-TEST', 'name' => 'ONT Legacy ZTE', 'unit' => 'pcs',
            'item_category_id' => $legacyCategory->id, 'tracking_type' => 'serialized',
            'ownership_mode' => 'installable',
        ]);
        foreach (['ONT-SN-A', 'ONT-SN-B'] as $serialNumber) {
            InventorySerial::create([
                'item_id' => $modem->id, 'customer_id' => $customer->id,
                'serial_number' => $serialNumber, 'status' => SerialStatus::INSTALLED->value,
            ]);
        }
        InventorySerial::create([
            'item_id' => $router->id, 'customer_id' => $customer->id,
            'serial_number' => 'ROUTER-SN', 'status' => SerialStatus::INSTALLED->value,
        ]);
        InventorySerial::create([
            'item_id' => $legacyModem->id, 'customer_id' => $customer->id,
            'serial_number' => 'LEGACY-ONT-SN', 'status' => SerialStatus::INSTALLED->value,
        ]);
        InventorySerial::create([
            'item_id' => $modem->id, 'customer_id' => $customer->id,
            'serial_number' => 'OLD-ONT-SN', 'status' => SerialStatus::RETURNED->value,
        ]);

        $this->loginAsAdmin();
        $payload = app(InfrastructureMapService::class)->build(auth()->user(), null, 'path', true);
        $virtualOntId = -1000000000 - $customer->id;
        $this->assertSame('customer_ont', $payload['topologyDetails'][$virtualOntId]['type']);
        $this->assertSame($node->id, $payload['topologyDetails'][$virtualOntId]['parent_id']);
        $this->assertSame(1, $payload['summary']['nodes']);
        $this->assertSame(0, NetworkNode::whereHas('nodeType', fn ($query) => $query->where('code', 'ont'))->count());
        $this->assertArrayHasKey(-1000000000 - $withoutModem->id, $payload['topologyDetails']);
        // Kartu ONT hanya membawa identitas; SN modem diambil lazy dari daftar port node induk.
        $this->assertSame('ONT pelanggan', $payload['topologyDetails'][-1000000000 - $withoutModem->id]['typeName']);
        $this->assertSame([], $payload['topologyDetails'][$virtualOntId]['customer_modems']);
        $this->assertSame(route('network.map.ports', $node), $payload['topologyDetails'][$virtualOntId]['modems_url']);
        $this->get(route('network.nodes.show', $node))
            ->assertOk()->assertSee('ONT-SN-A')->assertSee('ONT-SN-B')
            ->assertSee('ONT Huawei')->assertSee('DEVICE-SN')->assertSee('ZTE F660')
            ->assertSee('LEGACY-ONT-SN')->assertSee('ONT Legacy ZTE')
            ->assertSee('Modem belum tercatat')
            ->assertDontSee('ROUTER-SN')->assertDontSee('OLD-ONT-SN');

        $this->get(route('network.map.index', ['ont' => 1]))
            ->assertOk()->assertSee('customer_modems')->assertDontSee('ONT-SN-A')->assertDontSee('ROUTER-SN');

        $ports = collect($this->getJson(route('network.map.ports', $node))->assertOk()->json('ports'));
        $modemSerials = $ports->firstWhere('customer_cid', $payload['topologyDetails'][$virtualOntId]['code'])['customer_modems'];
        $serials = array_column($modemSerials, 'serial_number');
        foreach (['ONT-SN-A', 'ONT-SN-B', 'DEVICE-SN', 'LEGACY-ONT-SN'] as $expected) {
            $this->assertContains($expected, $serials);
        }
        $this->assertNotContains('ROUTER-SN', $serials);
        $this->assertNotContains('OLD-ONT-SN', $serials);

        $role = Role::where('code', 'fop')->firstOrFail();
        $sensitivePermissionId = Permission::where('code', 'customers.detail.devices.view_sensitive')->value('id');
        $role->permissions()->detach($sensitivePermissionId);
        $limitedUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $limitedUser->id, 'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $this->actingAs($limitedUser)->get(route('network.nodes.show', $node))
            ->assertOk()->assertSee('SN modem dibatasi')->assertDontSee('ONT-SN-A')
            ->assertDontSee('DEVICE-SN');
        $limitedPayload = app(InfrastructureMapService::class)->build($limitedUser, null, 'path', true);
        $this->assertSame([], $limitedPayload['topologyDetails'][$virtualOntId]['customer_modems']);
        $this->assertTrue($limitedPayload['topologyDetails'][$virtualOntId]['customer_modems_hidden']);
        $this->actingAs($limitedUser)->get(route('network.map.index', ['ont' => 1]))
            ->assertOk()->assertSee('customer_modems_hidden')->assertDontSee('ONT-SN-A')
            ->assertDontSee('DEVICE-SN');
    }

    #[Test]
    public function master_node_menampilkan_inspector_untuk_semua_node_dan_mengelompokkan_mini_pop(): void
    {
        $cabang = Pop::factory()->create(['name' => 'Cabang Presentasi', 'type' => 'cabang']);
        $mini = Pop::factory()->create(['name' => 'Mini Presentasi', 'type' => 'mini_pop', 'parent_id' => $cabang->id]);
        for ($i = 1; $i <= 11; $i++) {
            $node = NetworkNode::factory()->create([
                'anchor_pop_id' => $i === 11 ? $mini->id : $cabang->id,
                'code' => sprintf('NODE-PRES-%02d', $i),
            ]);
        }

        $this->loginAsAdmin();
        $response = $this->get(route('network.nodes.index'));
        $response->assertOk()
            ->assertSee('NODE-PRES-11')
            ->assertSee('inspector-node-'.$node->id, false)
            ->assertSee('Cabang Presentasi')
            ->assertSee('Mini Presentasi')
            ->assertDontSee('All Cards View');

        $otherCabang = Pop::factory()->create(['name' => 'Cabang Rahasia Presentasi', 'type' => 'cabang']);
        NetworkNode::factory()->create(['anchor_pop_id' => $otherCabang->id, 'code' => 'NODE-RAHASIA-PRES']);
        $role = Role::where('code', 'fop')->firstOrFail();
        $limitedUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $limitedUser->id, 'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $this->actingAs($limitedUser)->get(route('network.nodes.index'))
            ->assertOk()->assertSee('NODE-PRES-11')->assertDontSee('NODE-RAHASIA-PRES')
            ->assertDontSee('Cabang Rahasia Presentasi');
    }

    #[Test]
    public function pengguna_tanpa_izin_network_nodes_view_ditolak(): void
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('network.map.index'))->assertForbidden();
    }

    #[Test]
    public function peta_menampilkan_pusat_cabang_dan_mini_pop_tanpa_node_perangkat(): void
    {
        $pusat = Pop::factory()->create(['name' => 'WHUSNET', 'type' => 'pusat', 'parent_id' => null]);
        $cabang = Pop::factory()->create(['name' => 'Jetis', 'type' => 'cabang', 'parent_id' => $pusat->id]);
        $mini = Pop::factory()->create(['name' => 'C1', 'type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $this->loginAsAdmin();

        $payload = app(InfrastructureMapService::class)->build(auth()->user(), null, 'path');

        $this->assertSame(-$pusat->id, $payload['topologyDetails'][-$cabang->id]['parent_id']);
        $this->assertSame(-$cabang->id, $payload['topologyDetails'][-$mini->id]['parent_id']);
        $this->assertSame('pop_pusat', $payload['topologyDetails'][-$pusat->id]['type']);
        $this->assertSame(0, $payload['summary']['nodes']);
        $this->get(route('network.map.index'))->assertOk()->assertSee('WHUSNET')->assertSee('Jetis')->assertSee('C1');
        // Label "Pusat / Server Utama" milik partial `_pop-tree` lama yang sudah
        // diganti proyeksi hierarki tunggal (ADHOC-202 Task 10): pohon Master
        // Node kini menulis label tipe POP dari `type_label` proyeksi, dan
        // urutannya tetap Pusat → Cabang → Mini POP tanpa node perangkat.
        $this->get(route('network.nodes.index'))->assertOk()
            ->assertSeeInOrder(['WHUSNET', 'POP Pusat', 'Jetis', 'POP Cabang', 'C1', 'Mini POP']);
    }

    #[Test]
    public function peta_menempatkan_perangkat_di_bawah_pop_dan_menjaga_scope_pop(): void
    {
        $pusat = Pop::factory()->create(['name' => 'Pusat Topologi', 'type' => 'pusat']);
        $cabang = Pop::factory()->create(['name' => 'Cabang Terlihat', 'type' => 'cabang', 'parent_id' => $pusat->id]);
        $mini = Pop::factory()->create(['name' => 'Mini Terlihat', 'type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $rahasia = Pop::factory()->create(['name' => 'Cabang Rahasia Topologi', 'type' => 'cabang', 'parent_id' => $pusat->id]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $mini->id, 'code' => 'ODP-VISIBLE-01']);
        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $payload = app(InfrastructureMapService::class)->build($user, null, 'path');

        $this->assertSame(-$mini->id, $payload['topologyDetails'][$node->id]['parent_id']);
        $this->assertArrayHasKey(-$cabang->id, $payload['topologyDetails']);
        $this->assertArrayNotHasKey(-$rahasia->id, $payload['topologyDetails']);
        $this->actingAs($user)->get(route('network.map.index'))->assertOk()->assertDontSee('Cabang Rahasia Topologi');
    }

    /**
     * Deep link "Lihat detail di Peta Infrastruktur" dari Master Node
     * (rancangan-pembagian-detail-master-node-vs-peta-infrastruktur.md §4).
     * Node valid & dalam scope → tab Inventaris + node itu terpilih duluan.
     */
    #[Test]
    public function node_id_valid_dalam_scope_membuka_tab_inventaris_dengan_node_terpilih(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $response = $this->actingAs($user)->get(route('network.map.index', ['node_id' => $node->id]));

        $response->assertOk();
        $response->assertSee("tab: 'inventaris'", false);
        $response->assertSee("selectedNodeId: {$node->id},", false);
    }

    #[Test]
    public function node_id_di_luar_scope_pop_mendapat_404(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $nodeAsing = NetworkNode::factory()->create(['anchor_pop_id' => $cabangB->id, 'parent_id' => null]);
        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangA->id]);

        $this->actingAs($user)->get(route('network.map.index', ['node_id' => $nodeAsing->id]))->assertNotFound();
    }

    #[Test]
    public function node_id_yang_tidak_ada_mendapat_404_sama_seperti_di_luar_scope(): void
    {
        $this->loginAsAdmin();

        $this->get(route('network.map.index', ['node_id' => 999999]))->assertNotFound();
    }

    #[Test]
    public function node_id_tersaring_pop_id_yang_berbeda_mendapat_404(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'parent_id' => null]);
        $this->loginAsAdmin();

        $this->get(route('network.map.index', ['node_id' => $node->id, 'pop_id' => $cabangB->id]))->assertNotFound();
    }

    /**
     * Rancangan §5: tautan "Riwayat Gudang" (SN/roll → warehouse.traceability.index)
     * cuma boleh tampil buat aktor yang pegang warehouse_traceability.view —
     * network_nodes.view tidak otomatis ikut permission itu (role matrix dinamis).
     */
    #[Test]
    public function tautan_riwayat_gudang_tersembunyi_tanpa_permission_warehouse_traceability(): void
    {
        // setUp() di file ini gak nyeeding fitur warehouse_traceability (itu
        // modul Gudang, bukan Network) — tanpa WarehouseFeatureSeeder,
        // permission warehouse_traceability.view gak pernah tergenerate, jadi
        // detach(null) di bawah bakal nendang SEMUA permission role (bug test,
        // bukan bug produksi). Seed dulu lalu generate ulang permission.
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $modem = Item::create([
            'code' => 'MAP-TRACE-MODEM',
            'name' => 'Modem Uji Trace',
            'item_category_id' => ItemCategory::create([
                'code' => 'map_trace_cat', 'name' => 'Kategori Uji Trace', 'default_unit' => 'pcs',
                'equipment_class' => 'pasif', 'is_system' => false, 'is_active' => true,
            ])->id,
            'unit' => 'pcs',
        ]);
        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'MAP-TRACE-SN-0001',
            'status' => SerialStatus::DEPLOYED,
            'current_network_node_id' => $node->id,
        ]);

        $role = Role::where('code', 'fop')->firstOrFail();
        $traceabilityPermissionId = Permission::where('code', 'warehouse_traceability.view')->value('id');
        $role->permissions()->detach($traceabilityPermissionId);
        $limitedUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $limitedUser->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $limitedResponse = $this->actingAs($limitedUser)->get(route('network.map.index'));
        $limitedResponse->assertOk();
        $limitedResponse->assertSee($serial->serial_number);
        // "nodes" (topologyDetails) di-embed sebagai JS object literal di dalam atribut
        // HTML x-data="..." — Blade meng-escape "/" dan `"` berlapis buat itu, jadi
        // assertSee(URL mentah) gak bisa dipakai. Cek substring tanpa slash/quote yang
        // pasti lolos semua layer escaping itu.
        $this->assertStringNotContainsString(
            'traceability?sn=MAP-TRACE-SN-0001',
            $limitedResponse->getContent(),
        );

        $role->permissions()->attach($traceabilityPermissionId);
        $fullUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $fullScope = UserRoleScope::create(['user_id' => $fullUser->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $fullScope->id, 'pop_id' => $cabang->id]);

        $fullResponse = $this->actingAs($fullUser)->get(route('network.map.index'));
        $fullResponse->assertOk();
        $this->assertStringContainsString(
            'traceability?sn=MAP-TRACE-SN-0001',
            $fullResponse->getContent(),
        );
    }

    /**
     * Tab "Barang Terpasang" (docs/plan/infrastruktur/rancangan-tab-barang-terpasang.md):
     * pilih satu titik di hierarki (POP/Mini POP/OLT/Slot/PON/ODP) → barang
     * gudang di SELURUH subtree-nya tampil, bukan cuma device itu sendiri
     * (beda dari tab Inventaris yang device-centric).
     */
    #[Test]
    public function tab_barang_terpasang_menyaring_barang_sesuai_titik_subtree_terpilih(): void
    {
        $cabang = Pop::factory()->create(['name' => 'Cabang Terpasang', 'type' => 'cabang']);
        $mini = Pop::factory()->create(['name' => 'Mini Terpasang', 'type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $miniLain = Pop::factory()->create(['name' => 'Mini Lain Terpasang', 'type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $type = fn (string $code) => NetworkNodeType::where('code', $code)->firstOrFail();

        $olt = NetworkNode::factory()->create(['node_type_id' => $type('olt_chassis')->id, 'anchor_pop_id' => $mini->id, 'code' => 'OLT-TERPASANG-01']);
        $positions = app(InfrastructurePositionService::class);
        $slot = $positions->createSlot($olt, 1);
        $pon = $positions->createPort($slot, 1, ['capacity' => 8]);
        $odp = NetworkNode::factory()->create(['node_type_id' => $type('odp')->id, 'parent_id' => $pon->id, 'anchor_pop_id' => null, 'code' => 'ODP-TERPASANG-01']);
        NetworkNode::factory()->create(['anchor_pop_id' => $miniLain->id, 'code' => 'SW-TERPASANG-LAIN']);

        $category = ItemCategory::create([
            'code' => 'terpasang_test', 'name' => 'Kategori Terpasang', 'default_unit' => 'pcs',
            'equipment_class' => 'pasif', 'is_system' => false, 'is_active' => true,
        ]);
        $closureItem = Item::create([
            'code' => 'TERPASANG-CLOSURE', 'name' => 'Closure Terpasang', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'quantity', 'ownership_mode' => 'infrastructure_asset',
        ]);
        InfrastructureBalance::create(['item_id' => $closureItem->id, 'lot_no' => '', 'network_node_id' => $olt->id, 'qty' => 2]);

        $serialItem = Item::create([
            'code' => 'TERPASANG-SPLITTER', 'name' => 'Splitter Terpasang', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset',
        ]);
        InventorySerial::create([
            'item_id' => $serialItem->id, 'serial_number' => 'TERPASANG-SN-001',
            'status' => SerialStatus::DEPLOYED->value, 'current_network_node_id' => $odp->id,
        ]);

        $this->loginAsAdmin();

        // Belum pilih apapun: "Semua" default -> seluruh barang dalam scope tampil.
        $defaultHtml = $this->get(route('network.map.index', ['tab' => 'terpasang']))
            ->assertOk()
            ->assertSee('Cabang Terpasang')
            ->assertSee('Closure Terpasang')
            ->assertSee('Splitter Terpasang')
            ->getContent();
        $this->assertSame(2, substr_count($defaultHtml, 'data-asset-row'));

        // Pilih POP (Cabang): semua barang di bawahnya (lewat Mini POP) tampil,
        // barang milik Mini POP cabang lain tidak ikut.
        $this->get(route('network.map.index', ['tab' => 'terpasang', 'asset_pop_id' => $cabang->id]))
            ->assertOk()
            ->assertSee('Closure Terpasang')
            ->assertSee('Splitter Terpasang')
            ->assertSee('Mini Terpasang'); // opsi dropdown Mini POP ikut cabang terpilih

        // Pilih Mini POP spesifik: OLT dropdown kebuka (cuma OLT anchor ke Mini POP ini).
        $miniResponse = $this->get(route('network.map.index', [
            'tab' => 'terpasang', 'asset_pop_id' => $cabang->id, 'asset_mini_id' => $mini->id,
        ]))->assertOk();
        $miniResponse->assertSee('Closure Terpasang')->assertSee('Splitter Terpasang')->assertSee('OLT-TERPASANG-01');

        // Turun sampai ODP spesifik: hanya barang di ODP itu (SN), QTY di OLT tidak ikut.
        // ("Closure Terpasang" tetap ada di JSON kanvas topologi yang selalu dikirim,
        // jadi hitung baris tabel barang, bukan cuma cek teks nama barang muncul.)
        $odpOnlyHtml = $this->get(route('network.map.index', [
            'tab' => 'terpasang', 'asset_pop_id' => $cabang->id, 'asset_mini_id' => $mini->id,
            'asset_olt_id' => $olt->id, 'asset_slot_id' => $slot->id, 'asset_pon_id' => $pon->id, 'asset_odp_id' => $odp->id,
        ]))->assertOk()->assertSee('TERPASANG-SN-001')->getContent();
        $this->assertSame(1, substr_count($odpOnlyHtml, 'data-asset-row'));

        // Service: assertion presisi tanpa noise HTML.
        $this->loginAsAdmin();
        $data = app(InfrastructureMapService::class)->build(auth()->user(), null, 'topology');
        $subtreeOdp = app(InfrastructureMapService::class)->subtreeNodeIds($data['childrenByNode'], [$odp->id]);
        $rowsOdp = app(InfrastructureMapService::class)->assetRowsForNodes($data['assetsByNode'], $data['pathsByNode'], $subtreeOdp);
        $this->assertCount(1, $rowsOdp);
        $this->assertSame('Splitter Terpasang', $rowsOdp[0]['name']);
        $this->assertNotEmpty($rowsOdp[0]['path']);

        $subtreeOlt = app(InfrastructureMapService::class)->subtreeNodeIds($data['childrenByNode'], [$olt->id]);
        $rowsOlt = app(InfrastructureMapService::class)->assetRowsForNodes($data['assetsByNode'], $data['pathsByNode'], $subtreeOlt);
        $names = array_column($rowsOlt, 'name');
        $this->assertContains('Closure Terpasang', $names);
        $this->assertContains('Splitter Terpasang', $names); // ODP turunan OLT ikut ter-BFS
    }

    #[Test]
    public function tab_barang_terpasang_mengabaikan_pop_di_luar_scope_pengguna(): void
    {
        $cabangAman = Pop::factory()->create(['name' => 'Cabang Aman Terpasang', 'type' => 'cabang']);
        $cabangRahasia = Pop::factory()->create(['name' => 'Cabang Rahasia Terpasang', 'type' => 'cabang']);
        $miniRahasia = Pop::factory()->create(['name' => 'Mini Rahasia Terpasang', 'type' => 'mini_pop', 'parent_id' => $cabangRahasia->id]);
        NetworkNode::factory()->create(['anchor_pop_id' => $cabangRahasia->id, 'code' => 'NODE-RAHASIA-TERPASANG']);

        $role = Role::where('code', 'fop')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangAman->id]);

        $response = $this->actingAs($user)->get(route('network.map.index', [
            'tab' => 'terpasang', 'asset_pop_id' => $cabangRahasia->id,
        ]));

        $response->assertOk()
            ->assertDontSee('Cabang Rahasia Terpasang')
            ->assertDontSee('Mini Rahasia Terpasang')
            ->assertDontSee('NODE-RAHASIA-TERPASANG')
            // Filter di luar scope diabaikan -> balik ke "Semua" dalam scope sendiri (kosong di sini), bukan error/bocor.
            ->assertDontSee('data-asset-row', false);
    }

    /**
     * Payload kanvas (`#peta-data`: index/nodes/hierarchy) bisa ±4MB di scope besar
     * (lihat investigasi Lighthouse Performance ADHOC tab "Barang Terpasang") — tab ini
     * TIDAK PERNAH baca `window.__petaData` (tabelnya 100% server-rendered), jadi payload
     * itu harus dikosongkan SAAT tab ini aktif, bukan selalu dikirim seperti Topologi/
     * Pohon/Inventaris (yang masih butuh `this.nodes` buat drawer `selectNode()`).
     */
    #[Test]
    public function tab_barang_terpasang_mengosongkan_payload_kanvas_topologi(): void
    {
        $cabang = Pop::factory()->create(['name' => 'Cabang Payload Kanvas', 'type' => 'cabang']);
        NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'code' => 'NODE-PAYLOAD-KANVAS']);

        $this->loginAsAdmin();

        $terpasangHtml = $this->get(route('network.map.index', ['tab' => 'terpasang']))->assertOk()->getContent();
        $this->assertStringNotContainsString('NODE-PAYLOAD-KANVAS', $terpasangHtml);

        $topologiHtml = $this->get(route('network.map.index', ['tab' => 'topologi']))->assertOk()->getContent();
        $this->assertStringContainsString('NODE-PAYLOAD-KANVAS', $topologiHtml);

        $inventarisHtml = $this->get(route('network.map.index', ['tab' => 'inventaris']))->assertOk()->getContent();
        $this->assertStringContainsString('NODE-PAYLOAD-KANVAS', $inventarisHtml);
    }
}
