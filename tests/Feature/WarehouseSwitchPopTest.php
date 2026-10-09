<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
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
 * Analisa UI/UX gudang §S1 — switcher cabang global: satu pilihan di header,
 * disimpan session, dibaca balik di Dasbor/Kelola Stok/Riwayat/Custody/
 * Traceability sebagai DEFAULT filter saat halaman itu dibuka TANPA pop_id
 * eksplisit. `pop_id` eksplisit di query string tetap menang telak — ini
 * yang menjamin SEMUA test lama (ratusan, kirim pop_id eksplisit atau tanpa
 * sama sekali tanpa pernah menyentuh switcher) hasilnya identik.
 */
class WarehouseSwitchPopTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

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

        $this->pusat = Pop::create(['code' => 'WSW-PUSAT', 'pop_code' => 'WSWP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Switch', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'WSW-A', 'pop_code' => 'WSWA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Switch A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'WSW-B', 'pop_code' => 'WSWB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Switch B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'WSW-KABEL', 'name' => 'Kabel Switch', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 500, 5000, $this->owner);
        $tA = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($tA, [], [$this->kabel->id => 100], $this->owner);
        $tB = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->kabel->id, 'qty' => 50]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($tB, [], [$this->kabel->id => 50], $this->owner);
    }

    #[Test]
    public function switch_ke_cabang_dalam_scope_tersimpan_di_session(): void
    {
        $this->actingAs($this->owner)
            ->post(route('warehouse.switch-pop'), ['pop_id' => $this->cabangA->id])
            ->assertRedirect();

        $this->assertSame($this->cabangA->id, session('warehouse.pop_id'));
    }

    #[Test]
    public function switch_ke_cabang_di_luar_scope_ditolak_403(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $this->actingAs($popAdminA)
            ->post(route('warehouse.switch-pop'), ['pop_id' => $this->cabangB->id])
            ->assertForbidden();

        $this->assertNull(session('warehouse.pop_id'));
    }

    #[Test]
    public function switch_ke_semua_cabang_menghapus_session(): void
    {
        session(['warehouse.pop_id' => $this->cabangA->id]);

        $this->actingAs($this->owner)
            ->post(route('warehouse.switch-pop'), [])
            ->assertRedirect();

        $this->assertNull(session('warehouse.pop_id'));
    }

    #[Test]
    public function halaman_stok_pakai_session_sebagai_default_saat_pop_id_absen(): void
    {
        session(['warehouse.pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk();
        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangA->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
    }

    #[Test]
    public function pop_id_eksplisit_menang_telak_atas_session(): void
    {
        session(['warehouse.pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['pop_id' => $this->cabangB->id]));

        $response->assertOk();
        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->pop_id === $this->cabangA->id));
        // pop_id eksplisit TIDAK menulis balik ke session.
        $this->assertSame($this->cabangA->id, session('warehouse.pop_id'));
    }

    #[Test]
    public function pop_id_kosong_eksplisit_artinya_semua_bukan_fallback_session(): void
    {
        session(['warehouse.pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['pop_id' => '']));

        $response->assertOk();
        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangA->id));
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
    }

    #[Test]
    public function tanpa_session_dan_tanpa_pop_id_tetap_semua_gudang_seperti_dulu(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk();
        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangA->id));
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
    }

    #[Test]
    public function header_tampilkan_cabang_terpilih_di_semua_halaman_gudang(): void
    {
        session(['warehouse.pop_id' => $this->cabangA->id]);

        // Dashboard DAN Riwayat Mutasi — dua controller beda, header sama.
        foreach ([route('warehouse.index'), route('warehouse.history.index')] as $url) {
            $response = $this->actingAs($this->owner)->get($url);
            $response->assertOk()->assertSee('Cabang Switch A', false);
        }
    }

    #[Test]
    public function session_pop_di_luar_scope_baru_divalidasi_ulang_bukan_dipercaya_buta(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        // Session berisi cabang B (seandainya scope-nya berubah belakangan).
        session(['warehouse.pop_id' => $this->cabangB->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('balances')->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
    }

    #[Test]
    public function switcher_tersembunyi_kalau_cuma_punya_1_cabang(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.index'));

        $response->assertOk()->assertDontSee('Menampilkan');
    }
}
