<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
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
 * Koreksi IA Gudang (2026-09-03) — "Management Stock" hub baru
 * (`WarehouseStockController`) yang gantiin tabel "Stok Saat Ini" mentah di
 * Dashboard (gak ada filter/pagination sebelumnya). Pola test sama
 * `WarehouseCustodyAndTraceabilityTest` — fokus POP scope, plus filter
 * search/low-stock yang jadi alasan utama halaman ini dibikin.
 */
class WarehouseStockPageTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'WS-PUSAT', 'pop_code' => 'WSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat WS', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'WS-A', 'pop_code' => 'WSA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang WS A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'WS-B', 'pop_code' => 'WSB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang WS B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'WS-KABEL', 'name' => 'Kabel WS', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, null, $this->owner);

        $t1 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t1, [], [$this->kabel->id => 100], $this->owner);

        $t2 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->kabel->id, 'qty' => 50]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t2, [], [$this->kabel->id => 50], $this->owner);
    }

    #[Test]
    public function owner_lihat_stok_semua_gudang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee('Cabang WS A')
            ->assertSee('Cabang WS B');
    }

    #[Test]
    public function filter_pop_id_cuma_nampilin_gudang_terpilih(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['pop_id' => $this->cabangA->id]));

        $response->assertOk()
            ->assertSee('Cabang WS A');

        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangA->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
    }

    #[Test]
    public function filter_search_cuma_nampilin_barang_yang_cocok(): void
    {
        $lain = Item::create(['code' => 'WS-LAIN', 'name' => 'Barang Lain WS', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $lain, 10, 1000, null, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'Kabel WS']));

        $response->assertOk()
            ->assertSee('Kabel WS')
            ->assertDontSee('Barang Lain WS');
    }

    #[Test]
    public function filter_low_stock_only_cuma_nampilin_yang_di_bawah_minimum(): void
    {
        $balanceA = InventoryBalance::where('pop_id', $this->cabangA->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $balanceA->update(['minimum_stock' => 500]); // 100 < 500 → low stock
        $balanceB = InventoryBalance::where('pop_id', $this->cabangB->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $balanceB->update(['minimum_stock' => 10]); // 50 > 10 → aman

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['low_stock_only' => 1]));

        $response->assertOk()
            ->assertSee('Cabang WS A');

        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->id === $balanceA->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->id === $balanceB->id));
    }

    #[Test]
    public function pop_admin_cabang_a_tidak_bisa_lihat_stok_cabang_b(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);

        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee('Cabang WS A')
            ->assertDontSee('Cabang WS B');
    }

    #[Test]
    public function pop_admin_gak_bisa_intip_gudang_lain_lewat_filter_pop_id(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);

        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        // Filter pop_id dipaksa ke Cabang B (di luar scope) — query dasarnya
        // (whereIn popIds scoped) udah gak nyertain Cabang B sama sekali,
        // jadi filter tambahan ini otomatis gak match apa-apa, BUKAN 403.
        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock.index', ['pop_id' => $this->cabangB->id]));

        $response->assertOk()->assertDontSee('Cabang WS B');
    }
}
