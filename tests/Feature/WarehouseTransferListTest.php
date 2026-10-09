<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
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
 * Analisa UI/UX gudang Fase 8 (§S3/M3) — halaman Daftar Transfer tunggal:
 * semua transfer (bukan cuma in-transit) + surat jalan punya tempat tinggal.
 */
class WarehouseTransferListTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

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

        $this->pusat = Pop::create(['code' => 'TFL-PUSAT', 'pop_code' => 'TFLP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat TFL', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'TFL-A', 'pop_code' => 'TFLA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang TFL A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'TFL-B', 'pop_code' => 'TFLB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang TFL B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'TFL-KABEL', 'name' => 'Kabel TFL', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 500, 5000, $this->owner);

        // Transfer ke A → diterima (RECEIVED). Transfer ke B → tetap in-transit.
        $tA = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($tA, [], [$this->kabel->id => 100], $this->owner);

        app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->kabel->id, 'qty' => 50]], $this->owner);
    }

    #[Test]
    public function daftar_menampilkan_transfer_diterima_dan_in_transit(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.transfers.index'));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('transfers'));
        $response->assertSee('Diterima Penuh')->assertSee('Dalam Perjalanan');
    }

    #[Test]
    public function filter_status_hanya_menampilkan_yang_cocok(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.transfers.index', ['status' => TransferStatus::IN_TRANSIT->value]));

        $response->assertOk();
        $transfers = $response->viewData('transfers');
        $this->assertCount(1, $transfers);
        $this->assertSame($this->cabangB->id, $transfers->first()->to_pop_id);
    }

    #[Test]
    public function filter_status_tak_dikenal_diabaikan(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.transfers.index', ['status' => 'bukan_status']));

        $response->assertOk();
        $this->assertNull($response->viewData('statusFilter'));
        $this->assertCount(2, $response->viewData('transfers'));
    }

    #[Test]
    public function setiap_baris_punya_link_surat_jalan(): void
    {
        $tB = InventoryTransfer::where('to_pop_id', $this->cabangB->id)->firstOrFail();

        $response = $this->actingAs($this->owner)->get(route('warehouse.transfers.index'));

        $response->assertOk()->assertSee(route('warehouse.transfers.surat-jalan', $tB), false);
    }

    #[Test]
    public function pop_admin_hanya_lihat_transfer_cabangnya(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.transfers.index'));

        $response->assertOk();
        $transfers = $response->viewData('transfers');
        $this->assertCount(1, $transfers);
        $this->assertSame($this->cabangA->id, $transfers->first()->to_pop_id);
    }
}
