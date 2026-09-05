<?php

namespace Tests\Feature;

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
 * ADHOC-54 Fase 8 lanjutan — Custody (§2.6) + Asset Traceability (§2.8).
 * Fokus pada POP scope: `pop_admin` cabang A TIDAK BOLEH lihat custody/SN
 * cabang B — ini query paling sensitif kebocoran data lintas cabang
 * (CLAUDE.md § larangan keras #3).
 */
class WarehouseCustodyAndTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Item $kabel;

    private User $teknisiA;

    private User $teknisiB;

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

        $this->pusat = Pop::create(['code' => 'CTT-PUSAT', 'pop_code' => 'CTTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat CTT', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'CTT-A', 'pop_code' => 'CTTA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang CTT A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'CTT-B', 'pop_code' => 'CTTB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang CTT B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'CTT-KABEL', 'name' => 'Kabel CTT', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->teknisiA = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi CTT A']);
        $this->teknisiB = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi CTT B']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, null, $this->owner);

        $t1 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t1, [], [$this->kabel->id => 100], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabangA, $this->teknisiA, [['item_id' => $this->kabel->id, 'qty' => 40]], $this->owner);

        $t2 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t2, [], [$this->kabel->id => 100], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabangB, $this->teknisiB, [['item_id' => $this->kabel->id, 'qty' => 30]], $this->owner);
    }

    #[Test]
    public function owner_lihat_custody_semua_cabang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index'));

        $response->assertOk()
            ->assertSee('Teknisi CTT A')
            ->assertSee('Teknisi CTT B');
    }

    #[Test]
    public function pop_admin_cabang_a_tidak_bisa_lihat_custody_cabang_b(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);

        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.custody.index'));

        $response->assertOk()
            ->assertSee('Teknisi CTT A')
            ->assertDontSee('Teknisi CTT B');
    }

    #[Test]
    public function pop_admin_tidak_bisa_trace_sn_di_luar_scope(): void
    {
        $item = Item::create(['code' => 'CTT-MODEM', 'name' => 'Modem CTT', 'item_category_id' => ItemCategory::where('code', 'media_converter')->firstOrFail()->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $item, ['CTT-SN-B'], 300000, $this->owner);
        $t3 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $item->id, 'serial_numbers' => ['CTT-SN-B']]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t3, ['CTT-SN-B'], [], $this->owner);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.traceability.index', ['sn' => 'CTT-SN-B']));

        $response->assertOk()->assertSee('tidak ditemukan');
    }
}
