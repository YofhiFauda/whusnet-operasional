<?php

namespace Tests\Feature;

use App\Enums\MaterialKind;
use App\Enums\ScopeType;
use App\Models\FopTask;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TaskMaterial;
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

    #[Test]
    public function lacak_roll_menampilkan_setiap_pemakaian_dan_total_meternya(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create(['code' => 'TR-ROLL-USED', 'name' => 'Kabel Jejak Pemakaian', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $item, 1, null, 770000, $this->owner)[0];
        $firstTask = FopTask::create(['task_number' => 'TRACE-USED-1', 'category' => 'MTN', 'tugas' => 'Pasang ODP A', 'pop_id' => $this->pusat->id]);
        $secondTask = FopTask::create(['task_number' => 'TRACE-USED-2', 'category' => 'MTN', 'tugas' => 'Pasang ODP B', 'pop_id' => $this->pusat->id]);

        foreach ([[$firstTask, 600], [$secondTask, 380]] as [$task, $qty]) {
            TaskMaterial::create([
                'fop_task_id' => $task->id,
                'kind' => MaterialKind::TERPAKAI,
                'item_id' => $item->id,
                'item_type' => $category->code,
                'item_name' => $item->name,
                'lot_no' => $roll->roll_code,
                'qty' => $qty,
                'unit' => 'meter',
                'unit_price_snapshot' => 770,
                'recorded_by' => $this->owner->id,
            ]);
        }

        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['roll' => $roll->roll_code]));

        $response->assertOk()->assertSee('TRACE-USED-1')->assertSee('TRACE-USED-2')->assertSeeText('Total terpakai: 980 meter')->assertSeeText('Tujuan pemakaian: Pusat Trace Roll Test');
        $this->assertEquals(980, $response->viewData('rollUsage')->sum('qty'));
    }

    #[Test]
    public function jejak_roll_juga_menampilkan_meter_yang_dipakai_di_infrastruktur(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create(['code' => 'TR-INFRA-USED', 'name' => 'Kabel Infra Trace', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $item, 1, null, 770000, $this->owner)[0];

        InventoryTransaction::create([
            'type' => 'deploy',
            'reference_number' => 'DPL-TRACE-1',
            'item_id' => $item->id,
            'roll_id' => $roll->id,
            'qty' => 25,
            'unit_price_snapshot' => 770,
            'to_pop_id' => $this->pusat->id,
            'reason' => 'infra_cable_consumption',
            'notes' => '25 meter dipakai di ODP Selatan',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['roll' => $roll->roll_code]));

        $response->assertOk()
            ->assertSeeText('Total terpakai: 25 meter')
            ->assertSeeText('Lokasi pemakaian: 25 meter dipakai di ODP Selatan')
            ->assertSee('Pemakaian Infrastruktur');
    }
}
