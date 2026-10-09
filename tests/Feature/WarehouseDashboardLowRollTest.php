<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
 * Dashboard Gudang — kartu "Roll Kabel Sisa Kecil"
 * (docs/plan/warehouse/analisa-gap-roll-kabel.md §8). Jawaban langsung buat
 * "2 roll sisa 30m numpuk nganggur, gimana sistem nanganin" — tetap
 * tercatat stok, cuma di-flag, POP-scoped sama seperti Peringatan Stok
 * Rendah.
 */
class WarehouseDashboardLowRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

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

        $this->pusat = Pop::create(['code' => 'WDR-PUSAT', 'pop_code' => 'WDRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Dashboard Roll Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'WDR-CABANG', 'pop_code' => 'WDRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Dashboard Roll Test', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function dashboard_menampilkan_roll_sisa_kecil_di_gudang(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WDR-ROLL', 'name' => 'Kabel FO Dashboard Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000, 'minimum_length' => 50]);

        InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'WDR-ROLL-'.date('Ymd').'-000001',
            'length_total' => 1000,
            'length_remaining' => 30,
            'status' => RollStatus::AVAILABLE,
            'current_pop_id' => $this->pusat->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.index'));

        $response->assertOk()
            ->assertSee('Roll Kabel Sisa Kecil')
            ->assertSee('WDR-ROLL-'.date('Ymd').'-000001');
    }

    #[Test]
    public function roll_sisa_besar_tidak_muncul_di_kartu(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WDR-ROLL-2', 'name' => 'Kabel FO Dashboard Roll Aman', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000, 'minimum_length' => 50]);

        InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'WDR-ROLL-2-'.date('Ymd').'-000001',
            'length_total' => 1000,
            'length_remaining' => 800,
            'status' => RollStatus::AVAILABLE,
            'current_pop_id' => $this->pusat->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.index'));

        $response->assertOk()->assertDontSee('Roll Kabel Sisa Kecil');
    }

    #[Test]
    public function roll_sisa_kecil_di_custody_teknisi_ikut_kescope(): void
    {
        $teknisi = User::factory()->create();
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WDR-ROLL-3', 'name' => 'Kabel FO Dashboard Roll Custody', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000, 'minimum_length' => 50]);

        InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'WDR-ROLL-3-'.date('Ymd').'-000001',
            'length_total' => 1000,
            'length_remaining' => 20,
            'status' => RollStatus::IN_USE,
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.index'));

        $response->assertOk()->assertSee('Roll Kabel Sisa Kecil');
    }
}
