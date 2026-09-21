<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\InventoryRoll;
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
 * Cetak label roll kabel (single + batch) — permission REUSE
 * `warehouse_transfer.view`, sama pola `warehouse.receive.show`. Lihat
 * docs/TASKS.md ADHOC kabel-per-roll.
 */
class WarehouseRollPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $pusatLain;

    private InventoryRoll $roll;

    private string $reference;

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

        $this->pusat = Pop::create(['code' => 'RP-PUSAT', 'pop_code' => 'RPP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat RP', 'type' => 'pusat', 'status' => 'active']);
        $this->pusatLain = Pop::create(['code' => 'RP-PUSAT-2', 'pop_code' => 'RPP2', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Gudang Pusat RP 2', 'type' => 'pusat', 'status' => 'active']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RP-ROLL', 'name' => 'Kabel FO RP', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);

        $this->reference = 'RCV-'.date('Ymd').'-000001';
        $rolls = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 2, 'PT Vendor RP', 1800000, $this->owner, null, $this->reference);
        $this->roll = $rolls[0];
    }

    #[Test]
    public function cetak_label_satu_roll_render_barcode_dan_kode(): void
    {
        // Label cetak SENGAJA cuma barcode + roll_code + nama barang
        // (koreksi user 2026-09-16) — meter/vendor dicabut dari sticker.
        $this->actingAs($this->owner)->get(route('warehouse.rolls.print', $this->roll))
            ->assertOk()
            ->assertSee($this->roll->roll_code)
            ->assertSee($this->roll->item->name);
    }

    #[Test]
    public function cetak_label_batch_render_semua_roll_dari_satu_referensi(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.receive.rolls.print', $this->reference))
            ->assertOk();

        $rolls = InventoryRoll::where('item_id', $this->roll->item_id)->get();
        $this->assertCount(2, $rolls);

        foreach ($rolls as $roll) {
            $response->assertSee($roll->roll_code);
        }
    }

    #[Test]
    public function roll_di_luar_scope_pop_admin_ditolak(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdmin->pops()->attach($this->pusatLain->id);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);

        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $this->pusatLain->id,
        ]);

        $this->actingAs($popAdmin)->get(route('warehouse.rolls.print', $this->roll))
            ->assertForbidden();
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.rolls.print', $this->roll))->assertForbidden();
    }
}
