<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
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
 * Fase 2 Gudang, Prioritas 2 — Laporan Gudang, tab Kerugian
 * (fase-2-adaptasi-wms.md P2). Rekap ADJUSTMENT per kategori per periode.
 * Atribusi POP TIDAK selalu akurat — klaim custody teknisi (`adjustCustody()`)
 * gak nyimpen pop_id sama sekali di ledger-nya, jadi tampil "— (Custody
 * Teknisi)". Ini keterbatasan data jujur, bukan bug — lihat docblock
 * `WarehouseReportController::buildAdjustmentSummary()`.
 */
class WarehouseReportAdjustmentSummaryTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'RPTA-PUSAT', 'pop_code' => 'RPTAP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Report Adjustment Test', 'type' => 'pusat', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'RPTA-KABEL', 'name' => 'Kabel Report Adjustment Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
    }

    #[Test]
    public function rekap_kategori_hilang_dari_custody_tampil_tanpa_atribusi_pop(): void
    {
        $technician = User::factory()->create();
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => $this->kabel->id,
            'lot_no' => null,
            'qty_remaining' => 50,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        app(InventoryAdjustmentService::class)->adjustCustody($custody, -10, 'lost', $this->owner, 'kabel ilang di lapangan', 'warehouse/evidence/lost/x.jpg');

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));
        $response->assertOk();

        $rows = collect($response->viewData('adjustmentRows'));
        $lostRow = $rows->firstWhere('reason', 'lost');

        $this->assertNotNull($lostRow);
        $this->assertEquals('Hilang', $lostRow['reason_label']);
        $this->assertEquals('— (Custody Teknisi)', $lostRow['pop_label']);
        $this->assertEquals(1, $lostRow['count']);
        $this->assertEquals(10.0, $lostRow['total_qty']);
    }

    #[Test]
    public function rekap_penyesuaian_pop_balance_teratribusi_ke_gudang_dengan_benar(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);

        app(InventoryAdjustmentService::class)->adjustPopBalance($this->pusat, $this->kabel->id, -5, 'rusak_gudang', $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));

        $rows = collect($response->viewData('adjustmentRows'));
        $row = $rows->firstWhere('reason', 'rusak_gudang');

        $this->assertNotNull($row);
        $this->assertEquals($this->pusat->name, $row['pop_label']);
        $this->assertEquals(5.0, $row['total_qty']);
    }

    #[Test]
    public function transaksi_di_luar_periode_tidak_ikut_teragregasi(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, null, $this->owner);
        app(InventoryAdjustmentService::class)->adjustPopBalance($this->pusat, $this->kabel->id, -5, 'rusak_gudang', $this->owner);

        InventoryTransaction::query()->where('type', 'adjustment')->update(['created_at' => now()->subMonth()]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['period' => now()->format('Y-m')]));

        $rows = collect($response->viewData('adjustmentRows'));
        $this->assertTrue($rows->isEmpty());
    }

    #[Test]
    public function filter_pop_id_eksplisit_gak_ikutin_baris_custody_tanpa_atribusi(): void
    {
        $technician = User::factory()->create();
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => $this->kabel->id,
            'lot_no' => null,
            'qty_remaining' => 50,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        app(InventoryAdjustmentService::class)->adjustCustody($custody, -10, 'lost', $this->owner, null, 'warehouse/evidence/lost/x.jpg');

        // Filter POP eksplisit ke Pusat — baris custody (gak py pop_id sama
        // sekali) sengaja GAK ikut nongol, karena gak bisa dipastikan itu
        // dari cabang mana (lihat docblock buildAdjustmentSummary()).
        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['pop_id' => $this->pusat->id]));

        $rows = collect($response->viewData('adjustmentRows'));
        $this->assertTrue($rows->isEmpty());
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.reports.index'))->assertForbidden();
    }
}
