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
 * Ambang Stok Rendah (2026-09-03) — sebelum ini `minimum_stock` gak punya
 * form pengisian sama sekali (kolomnya ADA, cuma dibaca `isLowStock()`,
 * gak pernah ditulis) — badge "Stok Rendah" praktis mati di produksi.
 */
class WarehouseStockThresholdTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

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

        $this->pusat = Pop::create(['code' => 'THR-PUSAT', 'pop_code' => 'THRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Threshold Test', 'type' => 'pusat', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'THR-KABEL', 'name' => 'Kabel Threshold Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 30, 5000, null, $this->owner);
    }

    #[Test]
    public function set_minimum_stock_bikin_badge_stok_rendah_nyala(): void
    {
        $balance = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $this->assertFalse($balance->isLowStock(), 'sebelum diisi, minimum_stock null → gak pernah low-stock');

        $response = $this->actingAs($this->owner)->post(route('warehouse.stock.threshold.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->kabel->id,
            'minimum_stock' => 50, // qty 30 < 50
        ]);

        $response->assertRedirect(route('warehouse.stock.index'));
        $response->assertSessionHas('success');

        $balance->refresh();
        $this->assertEquals(50, (float) $balance->minimum_stock);
        $this->assertTrue($balance->isLowStock());
    }

    #[Test]
    public function minimum_stock_di_atas_qty_tampil_di_filter_low_stock_only(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.stock.threshold.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->kabel->id,
            'minimum_stock' => 50,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['low_stock_only' => 1]));
        $response->assertOk()->assertSee('THR-KABEL');
    }

    #[Test]
    public function pop_admin_gak_bisa_atur_ambang_gudang_di_luar_scope(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);

        $cabangLain = Pop::create(['code' => 'THR-CABANG', 'pop_code' => 'THRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Lain', 'type' => 'cabang', 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $popAdmin->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $cabangLain->id]);

        $response = $this->actingAs($popAdmin)->post(route('warehouse.stock.threshold.store'), [
            'pop_id' => $this->pusat->id, // di luar scope popAdmin
            'item_id' => $this->kabel->id,
            'minimum_stock' => 10,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.stock.threshold.create'))->assertForbidden();
        $this->actingAs($teknisi)->post(route('warehouse.stock.threshold.store'), [])->assertForbidden();
    }
}
