<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman "Barang Rusak" diperluas (4 tab) — selain SN (sudah dicover
 * `WarehouseDamagedStockPageTest`), tab `roll`/`balance`/`custody` nampung
 * jenis kerugian yang sebelumnya cuma kelihatan di `/warehouse/history`.
 */
class WarehouseDamagedStockTabsTest extends TestCase
{
    use RefreshDatabase;

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

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    private function ownerWithAllPopAccess(): User
    {
        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id]);
        $owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        return $owner;
    }

    #[Test]
    public function tab_serial_default_ikut_memuat_sn_lost(): void
    {
        $owner = $this->ownerWithAllPopAccess();
        $pusat = Pop::create(['code' => 'DMG2-PUSAT', 'pop_code' => 'DM2', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Pusat Lost Test', 'type' => 'pusat', 'status' => 'active']);
        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $modem = Item::create(['code' => 'DMG2-MODEM', 'name' => 'Modem Lost Test', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        [$hilang] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $modem, ['DMG2-SN-LOST'], 250000, $owner);
        $hilang->update(['status' => SerialStatus::LOST]);

        $this->actingAs($owner)
            ->get(route('warehouse.damaged.index'))
            ->assertOk()
            ->assertSee('DMG2-SN-LOST');
    }

    #[Test]
    public function tab_roll_menampilkan_roll_rusak_dan_menyembunyikan_roll_sehat(): void
    {
        $owner = $this->ownerWithAllPopAccess();
        $pusat = Pop::create(['code' => 'DMG3-PUSAT', 'pop_code' => 'DM3', 'registration_prefix' => 'C', 'cid_prefix' => 'F', 'name' => 'Pusat Roll Test', 'type' => 'pusat', 'status' => 'active']);
        $cat = ItemCategory::where('equipment_class', 'material')->first() ?? ItemCategory::first();
        $kabel = Item::create(['code' => 'DMG3-KABEL', 'name' => 'Kabel Roll Test', 'item_category_id' => $cat->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'ownership_mode' => 'installable', 'meter_per_roll' => 1000]);

        $rollSehat = InventoryRoll::create(['item_id' => $kabel->id, 'roll_code' => 'ROLL-SEHAT', 'length_total' => 1000, 'length_remaining' => 1000, 'unit_price_snapshot' => 1000, 'status' => RollStatus::AVAILABLE, 'current_pop_id' => $pusat->id, 'received_at' => now()]);
        $rollRusak = InventoryRoll::create(['item_id' => $kabel->id, 'roll_code' => 'ROLL-RUSAK', 'length_total' => 1000, 'length_remaining' => 400, 'unit_price_snapshot' => 1000, 'status' => RollStatus::AVAILABLE, 'current_pop_id' => $pusat->id, 'received_at' => now()]);

        app(InventoryAdjustmentService::class)->adjustRollStatus($rollRusak, RollStatus::DAMAGED, 'rusak_tertindih', $owner, null, 'evidence/dummy.jpg');

        $this->actingAs($owner)
            ->get(route('warehouse.damaged.index', ['tab' => 'roll']))
            ->assertOk()
            ->assertSee('ROLL-RUSAK')
            ->assertDontSee('ROLL-SEHAT');

        $this->assertEquals(RollStatus::AVAILABLE, $rollSehat->refresh()->status);
    }

    #[Test]
    public function tab_balance_menampilkan_koreksi_saldo_rugi(): void
    {
        $owner = $this->ownerWithAllPopAccess();
        $pusat = Pop::create(['code' => 'DMG4-PUSAT', 'pop_code' => 'DM4', 'registration_prefix' => 'C', 'cid_prefix' => 'G', 'name' => 'Pusat Balance Test', 'type' => 'pusat', 'status' => 'active']);
        $cat = ItemCategory::where('equipment_class', 'material')->first() ?? ItemCategory::first();
        $rj45 = Item::create(['code' => 'DMG4-RJ45', 'name' => 'RJ45 Balance Test', 'item_category_id' => $cat->id, 'unit' => 'pcs', 'tracking_type' => 'quantity', 'ownership_mode' => 'installable']);
        \App\Models\InventoryBalance::create(['pop_id' => $pusat->id, 'item_id' => $rj45->id, 'lot_no' => '', 'qty' => 20]);

        app(InventoryAdjustmentService::class)->adjustPopBalance($pusat, $rj45->id, -5.0, 'rusak_kelembaban', $owner);

        $this->actingAs($owner)
            ->get(route('warehouse.damaged.index', ['tab' => 'balance']))
            ->assertOk()
            ->assertSee('RJ45 Balance Test')
            ->assertSee('rusak_kelembaban');
    }

    #[Test]
    public function tab_custody_menampilkan_klaim_rusak_dengan_bukti_dan_menyembunyikan_klaim_lain_pop(): void
    {
        $owner = $this->ownerWithAllPopAccess();
        $popA = Pop::create(['code' => 'DMG5-A', 'pop_code' => 'DM5', 'registration_prefix' => 'C', 'cid_prefix' => 'H', 'name' => 'Cabang A Custody Test', 'type' => 'cabang', 'status' => 'active']);
        $popB = Pop::create(['code' => 'DMG5-B', 'pop_code' => 'DM6', 'registration_prefix' => 'C', 'cid_prefix' => 'I', 'name' => 'Cabang B Custody Test', 'type' => 'cabang', 'status' => 'active']);
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisiA = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi Custody A']);
        $teknisiB = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi Custody B']);
        $cat = ItemCategory::where('equipment_class', 'material')->first() ?? ItemCategory::first();
        $kabel = Item::create(['code' => 'DMG5-KABEL', 'name' => 'Kabel Custody Test', 'item_category_id' => $cat->id, 'unit' => 'meter', 'tracking_type' => 'quantity', 'ownership_mode' => 'installable']);

        $custodyA = TechnicianCustody::create(['technician_id' => $teknisiA->id, 'issued_from_pop_id' => $popA->id, 'item_id' => $kabel->id, 'lot_no' => '', 'qty_remaining' => 50, 'unit_price_snapshot' => 1000, 'status' => 'partially_used', 'issued_at' => now()]);
        $custodyB = TechnicianCustody::create(['technician_id' => $teknisiB->id, 'issued_from_pop_id' => $popB->id, 'item_id' => $kabel->id, 'lot_no' => '', 'qty_remaining' => 30, 'unit_price_snapshot' => 1000, 'status' => 'partially_used', 'issued_at' => now()]);

        $evidence = UploadedFile::fake()->image('bukti.jpg');
        $service = app(InventoryAdjustmentService::class);
        $service->adjustCustody($custodyA, -10.0, 'damaged', $owner, 'kabel putus', 'warehouse/evidence/damaged/dummy-a.jpg');
        $service->adjustCustody($custodyB, -5.0, 'damaged', $owner, 'kabel putus juga', 'warehouse/evidence/damaged/dummy-b.jpg');

        // pop_admin A cuma scope ke popA — klaim custody B (pop B) gak boleh kelihatan.
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdminA->roleScopes()->create(['role_id' => $popAdminRole->id, 'scope_type' => ScopeType::SELECTED_POP->value])
            ->targets()->create(['pop_id' => $popA->id]);

        $response = $this->actingAs($popAdminA)
            ->get(route('warehouse.damaged.index', ['tab' => 'custody']));

        $response->assertOk()
            ->assertSee('Teknisi Custody A')
            ->assertDontSee('Teknisi Custody B');
    }
}
