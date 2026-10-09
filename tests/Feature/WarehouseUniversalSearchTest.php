<?php

namespace Tests\Feature;

use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
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
 * Analisa UI/UX gudang §S2 — pencarian universal satu kotak: SN, roll,
 * nomor transfer, dan nomor surat jalan sekaligus dari header, terjangkau
 * di semua halaman gudang. Scope POP memakai aturan yang SAMA dengan
 * Traceability (`ChecksAssetScope`) — tidak boleh bocor lintas cabang.
 */
class WarehouseUniversalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Item $modem;

    private User $teknisiA;

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

        $this->pusat = Pop::create(['code' => 'USR-PUSAT', 'pop_code' => 'USRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Cari Universal', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'USR-A', 'pop_code' => 'USRA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Cari A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'USR-B', 'pop_code' => 'USRB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Cari B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'USR-MODEM', 'name' => 'Modem Cari Universal', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->teknisiA = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Rudi Cari Universal']);

        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['USR-SN-A'], 300000, $this->owner);
        $t = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->modem->id, 'serial_numbers' => ['USR-SN-A']]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t, ['USR-SN-A'], [], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabangA, $this->teknisiA, [['item_id' => $this->modem->id, 'serial_numbers' => ['USR-SN-A']]], $this->owner);
    }

    #[Test]
    public function cari_by_serial_number_ketemu(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.search', ['q' => 'USR-SN-A']));

        $response->assertOk()->assertSee('USR-SN-A');
        $this->assertTrue($response->viewData('results')->contains(fn ($r) => $r['type'] === 'serial' && $r['label'] === 'USR-SN-A'));
    }

    #[Test]
    public function cari_by_nama_teknisi_ketemu_sn_yang_dipegang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.search', ['q' => 'Rudi Cari Universal']));

        $response->assertOk();
        $this->assertTrue($response->viewData('results')->contains(fn ($r) => $r['type'] === 'serial' && $r['label'] === 'USR-SN-A'));
    }

    #[Test]
    public function cari_by_nomor_roll_ketemu(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $fo = Item::create(['code' => 'USR-ROLLFO', 'name' => 'Kabel FO Cari Universal', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 1, null, 1500000, $this->owner)[0];

        $response = $this->actingAs($this->owner)->get(route('warehouse.search', ['q' => $roll->roll_code]));

        $response->assertOk();
        $this->assertTrue($response->viewData('results')->contains(fn ($r) => $r['type'] === 'roll' && $r['label'] === $roll->roll_code));
    }

    #[Test]
    public function cari_by_nomor_referensi_transfer_ketemu(): void
    {
        $transfer = InventoryTransfer::first();

        $response = $this->actingAs($this->owner)->get(route('warehouse.search', ['q' => $transfer->reference_number]));

        $response->assertOk();
        $this->assertTrue($response->viewData('results')->contains(fn ($r) => $r['type'] === 'transfer' && $r['label'] === $transfer->reference_number));
    }

    #[Test]
    public function cari_by_nomor_surat_jalan_ketemu_transfer_yang_sama(): void
    {
        $transfer = InventoryTransfer::first();
        $suratJalanNumber = sprintf('SJ/WHUS/%s/%s/%03d', $transfer->created_at->format('Y'), $transfer->created_at->format('m'), $transfer->id);

        $response = $this->actingAs($this->owner)->get(route('warehouse.search', ['q' => $suratJalanNumber]));

        $response->assertOk();
        $this->assertTrue($response->viewData('results')->contains(fn ($r) => $r['type'] === 'transfer' && $r['label'] === $transfer->reference_number));
    }

    #[Test]
    public function pop_admin_tidak_menemukan_apa_pun_di_luar_scope(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminB = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminB->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangB->id]);

        $response = $this->actingAs($popAdminB)->get(route('warehouse.search', ['q' => 'USR-SN-A']));

        $response->assertOk();
        $this->assertFalse($response->viewData('results')->contains(fn ($r) => $r['label'] === 'USR-SN-A'));

        $transfer = InventoryTransfer::first();
        $response2 = $this->actingAs($popAdminB)->get(route('warehouse.search', ['q' => $transfer->reference_number]));
        $this->assertFalse($response2->viewData('results')->contains(fn ($r) => $r['label'] === $transfer->reference_number));
    }

    #[Test]
    public function tanpa_kata_kunci_hasil_kosong_dan_tidak_error(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.search'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('results'));
    }

    #[Test]
    public function kotak_cari_tampil_di_header_halaman_gudang_lain(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.index'));

        $response->assertOk()
            ->assertSee(route('warehouse.search'), false)
            ->assertSee('Cari SN, roll, transfer, surat jalan', false);
    }

    #[Test]
    public function user_tanpa_permission_warehouse_view_ditolak(): void
    {
        // sales TIDAK punya permission warehouse apa pun (RolePermissionSeeder)
        // — gerbang route ini sengaja reuse warehouse.view (sama Dashboard/Stok).
        $salesRole = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['role_id' => $salesRole->id]);
        UserRoleScope::create(['user_id' => $sales->id, 'role_id' => $salesRole->id, 'scope_type' => 'all_pop']);

        $this->actingAs($sales)->get(route('warehouse.search', ['q' => 'x']))->assertForbidden();
    }
}
