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
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhusnetTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    public function peta_merangkai_backbone_pop_agregasi_dan_mini_pop_hasil_seeder(): void
    {
        Pop::factory()->create(['name' => 'WHUSNET', 'type' => 'pusat', 'parent_id' => null]);
        $jetis = Pop::factory()->create(['name' => 'Jetis', 'type' => 'cabang', 'parent_id' => null]);
        $mini = Pop::factory()->create(['code' => 'C1', 'name' => 'C1', 'type' => 'mini_pop', 'parent_id' => $jetis->id]);
        Pop::factory()->create(['name' => 'Sandya', 'type' => 'cabang', 'parent_id' => null]);
        $this->seed(WhusnetTopologySeeder::class);

        $role = Role::where('code', 'owner')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $topology = app(InfrastructureMapService::class)->build($user, null, 'name')['topologyDetails'];
        $backbone = NetworkNode::where('code', 'BB-PUSAT-JETIS-01')->firstOrFail();
        $aggregation = NetworkNode::where('code', 'AGG-JETIS-01')->firstOrFail();

        $this->assertSame($backbone->id, $topology[-$jetis->id]['parent_id']);
        $this->assertSame(-$jetis->id, $topology[$aggregation->id]['parent_id']);
        $this->assertSame($aggregation->id, $topology[-$mini->id]['parent_id']);
        $this->assertSame($mini->id, NetworkNode::where('code', 'OLT-JETIS-01')->firstOrFail()->anchor_pop_id);

        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $scopedUser = User::factory()->create(['role_id' => $fopRole->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $scopedUser->id,
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $jetis->id]);
        $scopedTopology = app(InfrastructureMapService::class)->build($scopedUser, $jetis->id, 'name')['topologyDetails'];
        $this->assertArrayNotHasKey($backbone->id, $scopedTopology);
        $this->assertNull($scopedTopology[-$jetis->id]['parent_id']);
        $this->assertSame($aggregation->id, $scopedTopology[-$mini->id]['parent_id']);
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
        $this->assertSame(1, $xpath->query('//template[@x-for="id in layout.order" and parent::div]/svg')->length);
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
        $payload = app(InfrastructureMapService::class)->build(auth()->user(), null, 'path');
        $virtualOntId = -1000000000 - $customer->id;
        $this->assertSame('customer_ont', $payload['topologyDetails'][$virtualOntId]['type']);
        $this->assertSame($node->id, $payload['topologyDetails'][$virtualOntId]['parent_id']);
        $this->assertSame(1, $payload['summary']['nodes']);
        $this->assertSame(0, NetworkNode::whereHas('nodeType', fn ($query) => $query->where('code', 'ont'))->count());
        $this->assertArrayNotHasKey(-1000000000 - $withoutModem->id, $payload['topologyDetails']);
        $this->get(route('network.nodes.show', $node))
            ->assertOk()->assertSee('ONT-SN-A')->assertSee('ONT-SN-B')
            ->assertSee('ONT Huawei')->assertSee('DEVICE-SN')->assertSee('ZTE F660')
            ->assertSee('LEGACY-ONT-SN')->assertSee('ONT Legacy ZTE')
            ->assertSee('Modem belum tercatat')
            ->assertDontSee('ROUTER-SN')->assertDontSee('OLD-ONT-SN');

        $this->get(route('network.map.index'))
            ->assertOk()->assertSee('ONT-SN-A')->assertSee('ONT-SN-B')
            ->assertSee('DEVICE-SN')->assertSee('LEGACY-ONT-SN')->assertSee('customer_modems')->assertDontSee('ROUTER-SN')
            ->assertDontSee('OLD-ONT-SN');

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
        $limitedPayload = app(InfrastructureMapService::class)->build($limitedUser, null, 'path');
        $this->assertSame([], $limitedPayload['topologyDetails'][$virtualOntId]['customer_modems']);
        $this->assertTrue($limitedPayload['topologyDetails'][$virtualOntId]['customer_modems_hidden']);
        $this->actingAs($limitedUser)->get(route('network.map.index'))
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
            ->assertSee('Sorot tipe node')
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
        $this->get(route('network.nodes.index'))->assertOk()->assertSee('Pusat / Server Utama')->assertSee('Mini POP');
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
}
