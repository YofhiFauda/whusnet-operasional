<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
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
 * Scan Barang — fallback lookup roll kabel (App\Enums\TrackingType::ROLL)
 * begitu lookup SN gagal. Namespace roll_code vs serial_number gak pernah
 * collide, jadi fallback aman dicoba di endpoint yang sama tanpa mode
 * terpisah. Lihat docs/TASKS.md ADHOC kabel-per-roll.
 */
class WarehouseScanRollLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

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

        $this->pusat = Pop::create(['code' => 'SCR-PUSAT', 'pop_code' => 'SCRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Scan Roll Test', 'type' => 'pusat', 'status' => 'active']);
    }

    #[Test]
    public function scan_roll_code_ketemu_dan_kasih_info_sisa_meter(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'SCR-ROLL', 'name' => 'Kabel FO Scan Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 800]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 1, 'PT Vendor Scan', 2000000, $this->owner)[0];

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => $roll->roll_code]));

        $response->assertOk()
            ->assertJson(['found' => true, 'in_scope' => true, 'item_name' => 'Kabel FO Scan Test']);
    }

    #[Test]
    public function scan_sn_modem_tetap_jalan_regresi(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'SCR-MODEM', 'name' => 'Modem Scan Roll Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SCR-SN-001',
            'status' => 'available',
            'current_pop_id' => $this->pusat->id,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCR-SN-001']));

        $response->assertOk()->assertJson(['found' => true, 'item_name' => 'Modem Scan Roll Test']);
    }

    #[Test]
    public function scan_kode_tak_dikenal_dapat_pesan_belum_tercatat(): void
    {
        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'KODE-TIDAK-ADA-999']));

        $response->assertOk()->assertJson(['found' => false]);
    }

    #[Test]
    public function roll_di_luar_scope_pop_admin_gak_bocor_detail(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'SCR-ROLL-2', 'name' => 'Kabel FO Scan Test 2', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 1, null, 1500000, $this->owner)[0];

        $cabangLain = Pop::create(['code' => 'SCR-CABANG', 'pop_code' => 'SCRC', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Cabang Lain Scan Roll', 'type' => 'cabang', 'status' => 'active']);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdmin->pops()->attach($cabangLain->id);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangLain->id]);

        $response = $this->actingAs($popAdmin)->getJson(route('warehouse.scan.lookup', ['sn' => $roll->roll_code]));

        $response->assertOk()->assertJson(['found' => true, 'in_scope' => false]);
        $response->assertJsonMissing(['item_name' => 'Kabel FO Scan Test 2']);
    }
}
