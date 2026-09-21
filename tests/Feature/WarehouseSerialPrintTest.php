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
 * Cetak label SN barang SERIALIZED `auto_generate_serial=true` (ODP,
 * Splitter — gak punya SN vendor). Pola PERSIS `WarehouseRollPrintTest`.
 * Lihat docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
 */
class WarehouseSerialPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $pusatLain;

    private InventorySerial $serial;

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

        $this->pusat = Pop::create(['code' => 'SP-PUSAT', 'pop_code' => 'SPP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat SP', 'type' => 'pusat', 'status' => 'active']);
        $this->pusatLain = Pop::create(['code' => 'SP-PUSAT-2', 'pop_code' => 'SPP2', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Gudang Pusat SP 2', 'type' => 'pusat', 'status' => 'active']);

        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $odp = Item::create(['code' => 'SP-ODP', 'name' => 'ODP SP', 'item_category_id' => $catAktif->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'auto_generate_serial' => true]);

        $this->reference = 'RCV-'.date('Ymd').'-000001';
        $serials = app(InventoryReceiveService::class)->receiveSerializedAuto($this->pusat, $odp, 2, 75000, $this->owner, null, $this->reference);
        $this->serial = $serials[0];
    }

    #[Test]
    public function cetak_label_satu_sn_render_barcode_dan_kode(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.serials.print', $this->serial))
            ->assertOk()
            ->assertSee($this->serial->serial_number)
            ->assertSee($this->serial->item->name);
    }

    #[Test]
    public function cetak_label_batch_render_semua_sn_dari_satu_referensi(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.receive.serials.print', $this->reference))
            ->assertOk();

        $serials = InventorySerial::where('item_id', $this->serial->item_id)->get();
        $this->assertCount(2, $serials);

        foreach ($serials as $serial) {
            $response->assertSee($serial->serial_number);
        }
    }

    #[Test]
    public function sn_di_luar_scope_pop_admin_ditolak(): void
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

        $this->actingAs($popAdmin)->get(route('warehouse.serials.print', $this->serial))
            ->assertForbidden();
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.serials.print', $this->serial))->assertForbidden();
    }
}
