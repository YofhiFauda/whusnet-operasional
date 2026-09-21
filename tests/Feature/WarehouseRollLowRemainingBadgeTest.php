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
 * Badge "Sisa Kecil" muncul di 3 titik: Custody (tab Roll Kabel),
 * Traceability (detail roll), dan pesan Scan Barang — sejalan
 * `InventoryRoll::isLowRemaining()`. Lihat
 * docs/plan/warehouse/analisa-gap-roll-kabel.md §8.
 */
class WarehouseRollLowRemainingBadgeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryRoll $lowRoll;

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

        $cabang = Pop::create(['code' => 'RLB-CABANG', 'pop_code' => 'RLBC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Roll Badge Test', 'type' => 'cabang', 'status' => 'active']);
        $teknisi = User::factory()->create();

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RLB-ROLL', 'name' => 'Kabel FO Badge Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500, 'minimum_length' => 40]);

        $this->lowRoll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'RLB-ROLL-'.date('Ymd').'-000001',
            'length_total' => 500,
            'length_remaining' => 25,
            'status' => RollStatus::IN_USE,
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
        ]);
    }

    #[Test]
    public function badge_muncul_di_halaman_custody(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.custody.index'))
            ->assertOk()
            ->assertSee('Sisa Kecil');
    }

    #[Test]
    public function badge_muncul_di_halaman_traceability(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['roll' => $this->lowRoll->roll_code]))
            ->assertOk()
            ->assertSee('Sisa Kecil');
    }

    #[Test]
    public function pesan_scan_menyertakan_peringatan_sisa_kecil(): void
    {
        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => $this->lowRoll->roll_code]));

        $response->assertOk();
        $this->assertStringContainsString('Sisa kecil', $response->json('message'));
    }
}
