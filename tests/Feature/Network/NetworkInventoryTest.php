<?php

namespace Tests\Feature\Network;

use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman Inventaris Infrastruktur (`network.inventaris.index`): bukti barang
 * gudang yang terpasang netap di node jaringan, dibaca dari ledger DEPLOY.
 *
 * Deploy dijalankan lewat `InventoryService` sungguhan supaya ledger yang
 * dibaca halaman ini identik dengan jalur produksi.
 */
class NetworkInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private User $technician;

    private User $actor;

    private FopTask $fopTaskA;

    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->cabangA = Pop::factory()->create(['name' => 'Cabang Inv A', 'type' => 'cabang']);
        $this->cabangB = Pop::factory()->create(['name' => 'Cabang Inv B', 'type' => 'cabang']);

        $this->technician = User::factory()->create(['name' => 'Teknisi Inventaris Satu']);
        $this->actor = User::factory()->create();

        $this->fopTaskA = FopTask::create([
            'task_number' => 'TFOP-INV-0001',
            'task_date' => now(),
            'category' => 'INFR REQ',
            'tugas' => 'Uji Inventaris',
            'pop_id' => $this->cabangA->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);

        $this->category = ItemCategory::create([
            'code' => 'inv_test_cat',
            'name' => 'Kategori Uji Inventaris',
            'default_unit' => 'pcs',
            'equipment_class' => 'pasif',
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    private function makeItem(string $code, string $name, string $trackingType, string $unit = 'pcs'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => $name,
            'unit' => $unit,
            'item_category_id' => $this->category->id,
            'tracking_type' => $trackingType,
            'ownership_mode' => 'infrastructure_asset',
        ]);
    }

    private function issuedSerial(Item $item, string $serialNumber, Pop $issuedFrom): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $issuedFrom->id,
        ]);
    }

    /**
     * Actor ber-role $roleCode, scope `selected_pop` cuma ke $pop — pola sama
     * dengan `NetworkNodeAssetViewTest`.
     */
    private function makeScopedUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    #[Test]
    public function sn_qty_dan_roll_terpasang_tampil_dengan_lokasi_teknisi_dan_task(): void
    {
        $service = app(InventoryService::class);
        $odc = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangA->id, 'name' => 'ODC Inventaris Uji']);

        $splitter = $this->makeItem('INV-SPLITTER', 'Splitter Inventaris', 'serialized');
        $serial = $this->issuedSerial($splitter, 'INV-SN-0001', $this->cabangA);
        $service->deploySerial($serial, $odc, $this->fopTaskA, [$this->technician], $this->actor);

        $closure = $this->makeItem('INV-CLOSURE', 'Closure Inventaris', 'quantity');
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'item_id' => $closure->id,
            'qty_remaining' => 10,
            'unit_price_snapshot' => 10000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        $service->deployQuantity([$this->technician], $closure, 4, $odc, $this->fopTaskA, $this->actor);

        $kabel = $this->makeItem('INV-FEEDER', 'Kabel Feeder Inventaris', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-INV-001',
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);
        $service->deployRoll($roll, 40, $odc, [$this->technician], $this->fopTaskA, $this->actor);

        $this->loginAsAdmin();
        $response = $this->get(route('network.inventaris.index'));

        $response->assertOk();
        $response->assertSee('INV-SN-0001');
        $response->assertSee('Splitter Inventaris');
        $response->assertSee('Closure Inventaris');
        $response->assertSee('Kabel Feeder Inventaris');
        $response->assertSee('ROLL-INV-001');
        $response->assertSee('ODC Inventaris Uji');
        $response->assertSee('Teknisi Inventaris Satu');
        $response->assertSee('TFOP-INV-0001');
        $response->assertViewHas('summary', ['sn' => 1, 'qty' => 2, 'nodes' => 1]);
    }

    #[Test]
    public function sn_yang_sudah_dicabut_tidak_tampil(): void
    {
        $service = app(InventoryService::class);
        $odc = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangA->id, 'name' => 'ODC Dicabut']);

        $splitter = $this->makeItem('INV-SPLIT-CABUT', 'Splitter Dicabut', 'serialized');
        $serial = $this->issuedSerial($splitter, 'INV-SN-CABUT', $this->cabangA);
        $service->deploySerial($serial, $odc, $this->fopTaskA, [$this->technician], $this->actor);
        $service->retrieveSerialFromLocation($serial->refresh(), $this->fopTaskA, $this->actor);

        $this->loginAsAdmin();
        $response = $this->get(route('network.inventaris.index'));

        $response->assertOk();
        $response->assertDontSee('INV-SN-CABUT');
        $response->assertViewHas('summary', ['sn' => 0, 'qty' => 0, 'nodes' => 0]);
    }

    #[Test]
    public function sn_yang_dipasang_ulang_hanya_tampil_sekali_dengan_lokasi_terakhir(): void
    {
        $service = app(InventoryService::class);
        $odcAwal = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangA->id, 'name' => 'ODC Pindah Awal']);
        $odcAkhir = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangA->id, 'name' => 'ODC Pindah Akhir']);

        $splitter = $this->makeItem('INV-SPLIT-ULANG', 'Splitter Ulang', 'serialized');
        $serial = $this->issuedSerial($splitter, 'INV-SN-ULANG', $this->cabangA);
        $service->deploySerial($serial, $odcAwal, $this->fopTaskA, [$this->technician], $this->actor);
        $service->retrieveSerialFromLocation($serial->refresh(), $this->fopTaskA, $this->actor);
        // Retrieve menaruh SN di custody $actor; dikembalikan ke teknisi tim agar bisa dipasang lagi.
        $serial->refresh()->update(['current_technician_id' => $this->technician->id]);
        $service->deploySerial($serial->refresh(), $odcAkhir, $this->fopTaskA, [$this->technician], $this->actor);

        $this->loginAsAdmin();
        $response = $this->get(route('network.inventaris.index'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'INV-SN-ULANG'));
        $response->assertSee('ODC Pindah Akhir');
        $response->assertDontSee('ODC Pindah Awal');
    }

    #[Test]
    public function barang_di_node_cabang_lain_tidak_tampil_untuk_user_di_luar_scope(): void
    {
        $service = app(InventoryService::class);
        $odcB = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangB->id, 'name' => 'ODC Cabang B']);

        $splitter = $this->makeItem('INV-SPLIT-ASING', 'Splitter Asing', 'serialized');
        $serial = $this->issuedSerial($splitter, 'INV-SN-ASING', $this->cabangB);
        $fopTaskB = FopTask::create([
            'task_number' => 'TFOP-INV-0002',
            'task_date' => now(),
            'category' => 'INFR REQ',
            'tugas' => 'Uji Inventaris B',
            'pop_id' => $this->cabangB->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);
        $service->deploySerial($serial, $odcB, $fopTaskB, [$this->technician], $this->actor);

        $user = $this->makeScopedUser('fop', $this->cabangA);
        $response = $this->actingAs($user)->get(route('network.inventaris.index'));

        $response->assertOk();
        $response->assertDontSee('INV-SN-ASING');
        $response->assertViewHas('summary', ['sn' => 0, 'qty' => 0, 'nodes' => 0]);
    }

    #[Test]
    public function filter_cabang_dan_pencarian_serial_mempersempit_daftar(): void
    {
        $service = app(InventoryService::class);
        $odcA = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangA->id, 'name' => 'ODC Filter A']);
        $odcB = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangB->id, 'name' => 'ODC Filter B']);

        $splitter = $this->makeItem('INV-SPLIT-FILTER', 'Splitter Filter', 'serialized');
        $serialA = $this->issuedSerial($splitter, 'INV-SN-FILTER-A', $this->cabangA);
        $service->deploySerial($serialA, $odcA, $this->fopTaskA, [$this->technician], $this->actor);

        $serialB = $this->issuedSerial($splitter, 'INV-SN-FILTER-B', $this->cabangB);
        $fopTaskB = FopTask::create([
            'task_number' => 'TFOP-INV-0003',
            'task_date' => now(),
            'category' => 'INFR REQ',
            'tugas' => 'Uji Filter B',
            'pop_id' => $this->cabangB->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);
        $service->deploySerial($serialB, $odcB, $fopTaskB, [$this->technician], $this->actor);

        $this->loginAsAdmin();

        $this->get(route('network.inventaris.index', ['pop_id' => $this->cabangB->id]))
            ->assertOk()
            ->assertSee('INV-SN-FILTER-B')
            ->assertDontSee('INV-SN-FILTER-A');

        $this->get(route('network.inventaris.index', ['q' => 'INV-SN-FILTER-A']))
            ->assertOk()
            ->assertSee('INV-SN-FILTER-A')
            ->assertDontSee('INV-SN-FILTER-B');

        $this->get(route('network.inventaris.index', ['tipe' => 'qty']))
            ->assertOk()
            ->assertDontSee('INV-SN-FILTER-A')
            ->assertDontSee('INV-SN-FILTER-B');
    }

    #[Test]
    public function pop_di_luar_scope_user_diabaikan_walau_dipilih_di_filter(): void
    {
        $service = app(InventoryService::class);
        $odcB = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangB->id, 'name' => 'ODC Luar Scope']);

        $splitter = $this->makeItem('INV-SPLIT-LUAR', 'Splitter Luar', 'serialized');
        $serial = $this->issuedSerial($splitter, 'INV-SN-LUAR', $this->cabangB);
        $fopTaskB = FopTask::create([
            'task_number' => 'TFOP-INV-0004',
            'task_date' => now(),
            'category' => 'INFR REQ',
            'tugas' => 'Uji Luar',
            'pop_id' => $this->cabangB->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);
        $service->deploySerial($serial, $odcB, $fopTaskB, [$this->technician], $this->actor);

        $user = $this->makeScopedUser('fop', $this->cabangA);
        $response = $this->actingAs($user)->get(route('network.inventaris.index', ['pop_id' => $this->cabangB->id]));

        $response->assertOk();
        $response->assertDontSee('INV-SN-LUAR');
    }

    #[Test]
    public function owner_melihat_menu_sidebar_inventaris_infrastruktur(): void
    {
        $this->loginAsAdmin();

        $this->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee(route('network.inventaris.index'), false)
            ->assertSee('Inventaris Infrastruktur');
    }

    #[Test]
    public function user_tanpa_permission_network_nodes_view_ditolak(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('network.inventaris.index'))->assertForbidden();
    }
}
