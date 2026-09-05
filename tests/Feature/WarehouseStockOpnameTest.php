<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryReceiveService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fase 2 Gudang, Prioritas 1, gap #3 — Stock Opname (kontrol-anti-manipulasi.md
 * §5, fase-2-adaptasi-wms.md P1). Beda dari Penyesuaian Stok biasa: opname
 * input JUMLAH FISIK (bukan delta), boleh hasilnya PAS (selisih nol) — tetap
 * WAJIB tercatat, bukan cuma di-skip kalau gak ada selisih.
 */
class WarehouseStockOpnameTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $this->pusat = Pop::create(['code' => 'OPN-PUSAT', 'pop_code' => 'OPNP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Opname Test', 'type' => 'pusat', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'OPN-KABEL', 'name' => 'Kabel Opname Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);
    }

    #[Test]
    public function opname_hasil_pas_selisih_nol_tetap_tercatat(): void
    {
        $txn = app(InventoryAdjustmentService::class)->recordStockOpname($this->pusat, $this->kabel->id, 100, $this->owner);

        $this->assertEquals(0, (float) $txn->qty, 'hasil PAS tetap punya baris ledger, qty 0 BUKAN dilewati');
        $this->assertSame(InventoryTransactionType::STOCK_OPNAME, $txn->type);

        $balance = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $this->assertEquals(100, (float) $balance->qty, 'balance gak berubah kalau opname hasilnya sama persis');
    }

    #[Test]
    public function opname_hasil_kurang_mengoreksi_balance_sesuai_hitungan_fisik(): void
    {
        $txn = app(InventoryAdjustmentService::class)->recordStockOpname($this->pusat, $this->kabel->id, 85, $this->owner, null, 'kurang 15m dari sistem');

        $this->assertEquals(-15, (float) $txn->qty);

        $balance = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $this->assertEquals(85, (float) $balance->qty);
    }

    #[Test]
    public function opname_hasil_lebih_mengoreksi_balance_ke_atas(): void
    {
        $txn = app(InventoryAdjustmentService::class)->recordStockOpname($this->pusat, $this->kabel->id, 120, $this->owner);

        $this->assertEquals(20, (float) $txn->qty);

        $balance = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $this->assertEquals(120, (float) $balance->qty);
    }

    #[Test]
    public function opname_qty_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(InventoryAdjustmentService::class)->recordStockOpname($this->pusat, $this->kabel->id, -5, $this->owner);
    }

    #[Test]
    public function opname_full_flow_via_http_lalu_tampil_di_management_stock(): void
    {
        $store = $this->actingAs($this->owner)->post(route('warehouse.adjustments.opname.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->kabel->id,
            'counted_qty' => 90,
            'notes' => 'Opname rutin awal bulan',
        ]);

        $store->assertRedirect(route('warehouse.stock.index'));
        $store->assertSessionHas('success');

        $txn = InventoryTransaction::where('type', InventoryTransactionType::STOCK_OPNAME->value)->firstOrFail();
        $this->assertEquals(-10, (float) $txn->qty);

        $stockPage = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));
        $stockPage->assertOk()->assertSee('Opname:');
        $stockPage->assertDontSee('Belum pernah');
    }

    #[Test]
    public function item_yang_belum_pernah_diopname_tampil_beda(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()->assertSee('Belum pernah');
    }

    #[Test]
    public function tanpa_permission_adjustment_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.adjustments.opname.create'))->assertForbidden();
        $this->actingAs($teknisi)->post(route('warehouse.adjustments.opname.store'), [])->assertForbidden();
    }
}
