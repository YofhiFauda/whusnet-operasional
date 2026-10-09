<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
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
 * Asset Traceability — lookup roll kabel (`?roll=`), sejalan lookup SN
 * (`?sn=`) yang sudah ada. Fokus: ledger urut benar, scoping POP (out-of-
 * scope → not-found, bukan 403, konsisten pola SN — CLAUDE.md §3 POP scope).
 */
class WarehouseTraceabilityRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangLain;

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

        $this->pusat = Pop::create(['code' => 'TR-PUSAT', 'pop_code' => 'TRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Trace Roll Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangLain = Pop::create(['code' => 'TR-CABANG', 'pop_code' => 'TRC', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Cabang Lain Trace Roll', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function lacak_roll_menampilkan_ledger_receive(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'TR-ROLL', 'name' => 'Kabel FO Trace Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 700]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 1, 'PT Vendor Trace', 2100000, $this->owner)[0];

        $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['roll' => $roll->roll_code]))
            ->assertOk()
            ->assertSee($roll->roll_code)
            ->assertSee('Barang Masuk (Pengadaan)');
    }

    #[Test]
    public function roll_di_luar_scope_pop_admin_dianggap_tidak_ditemukan(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'TR-ROLL-2', 'name' => 'Kabel FO Trace Test 2', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 400]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 1, null, 1200000, $this->owner)[0];

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdmin->pops()->attach($this->cabangLain->id);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->cabangLain->id]);

        $this->actingAs($popAdmin)->get(route('warehouse.traceability.index', ['roll' => $roll->roll_code]))
            ->assertOk()
            ->assertSee('tidak ditemukan')
            ->assertDontSee('Kabel FO Trace Test 2');
    }

    #[Test]
    public function pencarian_sn_tetap_jalan_regresi(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['sn' => 'TIDAK-ADA-SN']))
            ->assertOk()
            ->assertSee('tidak ditemukan');
    }
}
