<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fase 2 Gudang, Prioritas 2 — Laporan Gudang, tab Pergerakan Barang
 * (fase-2-adaptasi-wms.md P2). Agregat RECEIVE/TRANSFER/ISSUE per gudang
 * per periode — data mentahnya dari `inventory_transactions`, atribusi POP
 * penuh akurat (beda dari tab Kerugian, lihat `WarehouseReportAdjustmentSummaryTest`).
 */
class WarehouseReportMovementSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Item $kabel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $this->pusat = Pop::create(['code' => 'RPT-PUSAT', 'pop_code' => 'RPTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Report Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'RPT-CABANG', 'pop_code' => 'RPTC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Report Test', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'RPT-KABEL', 'name' => 'Kabel Report Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
    }

    #[Test]
    public function agregat_movement_benar_per_gudang_di_bulan_berjalan(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $this->kabel->id, 'qty' => 40]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => 40], $this->owner);

        $technician = User::factory()->create();
        app(InventoryIssueService::class)->issue($this->cabang, $technician, [['item_id' => $this->kabel->id, 'qty' => 10]], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));
        $response->assertOk();

        $movementRows = collect($response->viewData('movementRows'));
        $pusatRow = $movementRows->firstWhere(fn ($r) => $r['pop']->id === $this->pusat->id);
        $cabangRow = $movementRows->firstWhere(fn ($r) => $r['pop']->id === $this->cabang->id);

        $this->assertEquals(100.0, $pusatRow['receive']);
        $this->assertEquals(40.0, $pusatRow['transfer_out']);
        $this->assertEquals(0.0, $pusatRow['transfer_in']);

        $this->assertEquals(40.0, $cabangRow['transfer_in']);
        $this->assertEquals(10.0, $cabangRow['issue']);
        $this->assertEquals(0.0, $cabangRow['receive'], 'Cabang gak pernah RECEIVE langsung dari supplier');
    }

    #[Test]
    public function transaksi_di_luar_periode_tidak_ikut_teragregasi(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);

        // Backdate ke bulan lalu — pola query-builder update() sengaja gak
        // ke-tangkep InventoryTransactionObserver (dokumentasi limitation-nya
        // sendiri), dipakai di sini murni buat setup fixture test, bukan
        // simulasi bug produksi.
        InventoryTransaction::query()->update(['created_at' => now()->subMonth()]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['period' => now()->format('Y-m')]));

        $movementRows = collect($response->viewData('movementRows'));
        $this->assertTrue($movementRows->isEmpty(), 'transaksi bulan lalu gak boleh nongol di laporan bulan ini');
    }

    #[Test]
    public function gudang_tanpa_pergerakan_tidak_muncul_sebagai_baris_nol(): void
    {
        $sepi = Pop::create(['code' => 'RPT-SEPI', 'pop_code' => 'RPTS', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Sepi Report Test', 'type' => 'cabang', 'status' => 'active']);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));

        $movementRows = collect($response->viewData('movementRows'));
        $this->assertFalse($movementRows->contains(fn ($r) => $r['pop']->id === $sepi->id));
    }

    #[Test]
    public function filter_pop_id_cuma_nampilin_gudang_terpilih(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $this->kabel->id, 'qty' => 40]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => 40], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['pop_id' => $this->cabang->id]));

        $movementRows = collect($response->viewData('movementRows'));
        $this->assertTrue($movementRows->contains(fn ($r) => $r['pop']->id === $this->cabang->id));
        $this->assertFalse($movementRows->contains(fn ($r) => $r['pop']->id === $this->pusat->id));
    }

    #[Test]
    public function pop_admin_cuma_lihat_gudang_dalam_scope(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $this->kabel->id, 'qty' => 40]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => 40], $this->owner);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdmin->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabang->id]);

        $response = $this->actingAs($popAdmin)->get(route('warehouse.reports.index'));
        $response->assertOk();

        $movementRows = collect($response->viewData('movementRows'));
        $this->assertTrue($movementRows->contains(fn ($r) => $r['pop']->id === $this->cabang->id));
        $this->assertFalse($movementRows->contains(fn ($r) => $r['pop']->id === $this->pusat->id));
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.reports.index'))->assertForbidden();
    }
}
