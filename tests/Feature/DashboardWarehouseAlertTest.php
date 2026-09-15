<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Owner Fase 3 (docs/plan/analisa-dashboard-owner-statistik.md
 * §6 Fase 3): Alert Stok Kritis POP (Pilar 6).
 */
class DashboardWarehouseAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $this->pusat = Pop::create([
            'code' => 'WH3-PUSAT', 'pop_code' => 'WH3P', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'Gudang Pusat Fase3', 'type' => 'pusat', 'status' => 'active',
        ]);
        $this->cabang = Pop::create([
            'code' => 'WH3-CABANG', 'pop_code' => 'WH3C', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'Gudang Cabang Fase3', 'type' => 'cabang', 'status' => 'active',
        ]);

        $category = ItemCategory::firstOrCreate(['code' => 'WH3'], ['name' => 'Kategori Fase3']);

        $this->item = Item::create([
            'item_category_id' => $category->id,
            'code' => 'WH3-ONT-01',
            'name' => 'ONT Fase3',
            'unit' => 'Unit',
            'tracking_type' => 'serialized',
            'is_active' => true,
        ]);
    }

    public function test_low_stock_dihitung_dan_muncul_di_tabel_alert(): void
    {
        InventoryBalance::create([
            'pop_id' => $this->pusat->id,
            'item_id' => $this->item->id,
            'qty' => 3,
            'minimum_stock' => 10,
        ]);

        // Stok aman — TIDAK boleh ikut kehitung.
        InventoryBalance::create([
            'pop_id' => $this->cabang->id,
            'item_id' => $this->item->id,
            'qty' => 100,
            'minimum_stock' => 10,
        ]);

        $response = $this->actingAs($this->owner)->get('/');

        $this->assertSame(1, $response->viewData('stats')['low_stock_count']);
        $this->assertCount(1, $response->viewData('lowStockItems'));
        $response->assertSee('Alert Stok Kritis POP', false);
        $response->assertSee('ONT Fase3');
        $response->assertSee('Gudang Pusat Fase3');
    }

    /**
     * Item TANPA `minimum_stock` (belum diisi admin) tidak punya patokan
     * pembanding — persis docblock `InventoryBalance::isLowStock()`, jangan
     * dianggap "rendah" cuma karena qty kecil.
     */
    public function test_item_tanpa_minimum_stock_tidak_dianggap_kritis(): void
    {
        InventoryBalance::create([
            'pop_id' => $this->pusat->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'minimum_stock' => null,
        ]);

        $stats = $this->actingAs($this->owner)->get('/')->viewData('stats');

        $this->assertSame(0, $stats['low_stock_count']);
    }

    /**
     * `pop_admin` yang scope-nya cuma cabang TIDAK BOLEH melihat stok kritis
     * gudang pusat — pola scoping sama persis `WarehouseController::index()`
     * (`EffectiveAccessService`, bukan query mentah).
     */
    public function test_pop_admin_scope_cabang_tidak_melihat_stok_kritis_pusat(): void
    {
        InventoryBalance::create([
            'pop_id' => $this->pusat->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'minimum_stock' => 10,
        ]);

        $popAdminRole = Role::where('name', 'POP Admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->cabang->id]);

        $stats = $this->actingAs($popAdmin)->get('/')->viewData('stats');

        $this->assertSame(0, $stats['low_stock_count']);
    }

    /**
     * `teknisi` punya `dashboard.view` TAPI TIDAK punya `warehouse.view` —
     * blok Alert Stok Kritis harus gak nongol sama sekali buat dia.
     */
    public function test_user_tanpa_warehouse_view_tidak_melihat_blok_gudang(): void
    {
        InventoryBalance::create([
            'pop_id' => $this->pusat->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'minimum_stock' => 10,
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $response = $this->actingAs($teknisi)->get('/');

        $response->assertOk();
        $this->assertArrayNotHasKey('low_stock_count', $response->viewData('stats'));
        $response->assertDontSee('Alert Stok Kritis POP', false);
    }
}
