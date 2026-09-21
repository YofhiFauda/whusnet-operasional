<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
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
 * Koreksi IA Gudang (2026-09-03) — "Management Stock" hub baru
 * (`WarehouseStockController`) yang gantiin tabel "Stok Saat Ini" mentah di
 * Dashboard (gak ada filter/pagination sebelumnya). Pola test sama
 * `WarehouseCustodyAndTraceabilityTest` — fokus POP scope, plus filter
 * search/low-stock yang jadi alasan utama halaman ini dibikin.
 */
class WarehouseStockPageTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'WS-PUSAT', 'pop_code' => 'WSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat WS', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'WS-A', 'pop_code' => 'WSA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang WS A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'WS-B', 'pop_code' => 'WSB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang WS B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'WS-KABEL', 'name' => 'Kabel WS', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, $this->owner);

        $t1 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t1, [], [$this->kabel->id => 100], $this->owner);

        $t2 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->kabel->id, 'qty' => 50]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t2, [], [$this->kabel->id => 50], $this->owner);
    }

    #[Test]
    public function owner_lihat_stok_semua_gudang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee('Cabang WS A')
            ->assertSee('Cabang WS B');
    }

    #[Test]
    public function filter_pop_id_cuma_nampilin_gudang_terpilih(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['pop_id' => $this->cabangA->id]));

        $response->assertOk()
            ->assertSee('Cabang WS A');

        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->pop_id === $this->cabangA->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->pop_id === $this->cabangB->id));
    }

    #[Test]
    public function filter_search_cuma_nampilin_barang_yang_cocok(): void
    {
        $lain = Item::create(['code' => 'WS-LAIN', 'name' => 'Barang Lain WS', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $lain, 10, 1000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'Kabel WS']));

        $response->assertOk()
            ->assertSee('Kabel WS')
            ->assertDontSee('Barang Lain WS');
    }

    #[Test]
    public function filter_low_stock_only_cuma_nampilin_yang_di_bawah_minimum(): void
    {
        $balanceA = InventoryBalance::where('pop_id', $this->cabangA->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $balanceA->update(['minimum_stock' => 500]); // 100 < 500 → low stock
        $balanceB = InventoryBalance::where('pop_id', $this->cabangB->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $balanceB->update(['minimum_stock' => 10]); // 50 > 10 → aman

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['low_stock_only' => 1]));

        $response->assertOk()
            ->assertSee('Cabang WS A');

        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->id === $balanceA->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->id === $balanceB->id));
    }

    /**
     * ADHOC-75 (2026-09-16) — Kelola Stok sebelumnya cuma nunjuk qty polos
     * buat barang QUANTITY, jadi 2 baris lot (Harga Lama/Baru) gak bisa
     * dibedain staf sama sekali. Regresi laporan user: "pathcore berhasil
     * kepisah 2 baris, tapi gimana bedain harga lama-baru, Kelola Stok gak
     * nampilin harga".
     */
    #[Test]
    public function kelola_stok_nampilin_label_dan_harga_per_lot_barang_quantity(): void
    {
        $item = Item::create(['code' => 'WS-2LOT', 'name' => 'Barang 2 Lot WS', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        $receiveSvc = app(InventoryReceiveService::class);

        $receiveSvc->receiveQuantity($this->pusat, $item, 12, 250000, $this->owner);
        $receiveSvc->receiveQuantity($this->pusat, $item, 10, 260000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'Barang 2 Lot WS']));

        $response->assertOk()
            ->assertSee('Harga Lama')
            ->assertSee('Harga Baru')
            ->assertSee('Rp 250.000')
            ->assertSee('Rp 260.000');
    }

    /**
     * Barang QUANTITY yang cuma py 1 lot (belum pernah ganti harga) TETAP
     * nampilin harga polos, TAPI TANPA label "Harga Lama" — gak ada "Baru"
     * buat dibandingin, jadi label itu nyesatkan kalau dipaksa muncul.
     */
    #[Test]
    public function kelola_stok_barang_quantity_satu_lot_tampil_harga_tanpa_label_lama(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'Kabel WS']));

        $response->assertOk()
            ->assertSee('Rp 5.000')
            ->assertDontSee('Harga Lama')
            ->assertDontSee('Harga Baru');
    }

    #[Test]
    public function pop_admin_cabang_a_tidak_bisa_lihat_stok_cabang_b(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);

        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee('Cabang WS A')
            ->assertDontSee('Cabang WS B');
    }

    #[Test]
    public function pop_admin_gak_bisa_intip_gudang_lain_lewat_filter_pop_id(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);

        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        // Filter pop_id dipaksa ke Cabang B (di luar scope) — query dasarnya
        // (whereIn popIds scoped) udah gak nyertain Cabang B sama sekali,
        // jadi filter tambahan ini otomatis gak match apa-apa, BUKAN 403.
        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock.index', ['pop_id' => $this->cabangB->id]));

        $response->assertOk()->assertDontSee('Cabang WS B');
    }

    /**
     * 2026-09-07 — laporan user "modem input by SN gak masuk Kelola Stok".
     * Akar masalah: `receiveSerialized()` cuma nulis `inventory_serials`,
     * gak pernah nyentuh `inventory_balances` yang jadi SATU-SATUNYA sumber
     * query halaman ini sebelumnya. Sekarang digabung — count(*) AVAILABLE
     * per gudang+item dibungkus jadi baris InventoryBalance sintetis.
     */
    #[Test]
    public function modem_serialized_yang_baru_diterima_muncul_di_kelola_stok(): void
    {
        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'WS-MODEM', 'name' => 'Modem WS Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WS-SN-001', 'WS-SN-002', 'WS-SN-003'], 250000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()->assertSee('Modem WS Test');

        $balances = $response->viewData('balances');
        $modemRow = $balances->first(fn ($b) => $b->item_id === $modem->id && $b->pop_id === $this->pusat->id);

        $this->assertNotNull($modemRow, 'Baris modem serialized wajib muncul di Kelola Stok');
        $this->assertEquals(3.0, (float) $modemRow->qty, 'Qty = jumlah SN berstatus AVAILABLE');
    }

    #[Test]
    public function threshold_serialized_tetap_kepake_buat_badge_stok_rendah(): void
    {
        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'WS-MODEM-2', 'name' => 'Modem WS Rendah', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WS-SN-010'], 250000, $this->owner);

        // Threshold disimpan lewat storeThreshold() — reuse endpoint aslinya,
        // bukan bikin InventoryBalance manual, biar test ini beneran nguji
        // jalur yang staf pakai.
        $this->actingAs($this->owner)->post(route('warehouse.stock.threshold.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $modem->id,
            'minimum_stock' => 5, // 1 SN < 5 → harus kebaca low stock
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['low_stock_only' => 1]));

        $response->assertOk()->assertSee('Modem WS Rendah');
    }

    #[Test]
    public function pop_admin_gak_lihat_modem_serialized_gudang_lain(): void
    {
        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modemA = Item::create(['code' => 'WS-MODEM-A', 'name' => 'Modem WS Cabang A', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        InventorySerial::create([
            'item_id' => $modemA->id,
            'serial_number' => 'WS-SN-A-001',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->cabangB->id,
        ]);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock.index'));

        $response->assertOk()->assertDontSee('Modem WS Cabang A');
    }

    #[Test]
    public function filter_category_id_cuma_nampilin_barang_kategori_tersebut(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $catModem = ItemCategory::where('code', 'media_converter')->firstOrFail();

        $modem = Item::create(['code' => 'WS-MODEM-CAT', 'name' => 'Modem Kategori Test', 'item_category_id' => $catModem->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WS-CAT-001'], 300000, $this->owner);

        // Filter kategori kabel: kabel muncul, modem tidak
        $responseKabel = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['category_id' => $catKabel->id]));
        $responseKabel->assertOk();
        $balancesKabel = $responseKabel->viewData('balances');
        $this->assertTrue($balancesKabel->contains(fn ($b) => $b->item_id === $this->kabel->id));
        $this->assertFalse($balancesKabel->contains(fn ($b) => $b->item_id === $modem->id));

        // Filter kategori modem: modem muncul, kabel tidak
        $responseModem = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['category_id' => $catModem->id]));
        $responseModem->assertOk();
        $balancesModem = $responseModem->viewData('balances');
        $this->assertTrue($balancesModem->contains(fn ($b) => $b->item_id === $modem->id));
        $this->assertFalse($balancesModem->contains(fn ($b) => $b->item_id === $this->kabel->id));
    }

    #[Test]
    public function filter_item_id_cuma_nampilin_barang_spesifik(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelLain = Item::create(['code' => 'WS-KABEL-2', 'name' => 'Kabel 2 Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabelLain, 50, 4000, $this->owner);

        // Filter spesifik kabel 1
        $response1 = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['item_id' => $this->kabel->id]));
        $response1->assertOk();
        $balances1 = $response1->viewData('balances');
        $this->assertTrue($balances1->contains(fn ($b) => $b->item_id === $this->kabel->id));
        $this->assertFalse($balances1->contains(fn ($b) => $b->item_id === $kabelLain->id));

        // Filter spesifik kabel 2
        $response2 = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['item_id' => $kabelLain->id]));
        $response2->assertOk();
        $balances2 = $response2->viewData('balances');
        $this->assertTrue($balances2->contains(fn ($b) => $b->item_id === $kabelLain->id));
        $this->assertFalse($balances2->contains(fn ($b) => $b->item_id === $this->kabel->id));
    }

    #[Test]
    public function filter_kombinasi_kategori_dan_pop_id(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $catModem = ItemCategory::where('code', 'media_converter')->firstOrFail();

        $modem = Item::create(['code' => 'WS-MODEM-KOMBI', 'name' => 'Modem Kombi Test', 'item_category_id' => $catModem->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WS-KM-001'], 300000, $this->owner);

        $t = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $modem->id, 'serial_numbers' => ['WS-KM-001']]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t, ['WS-KM-001'], [], $this->owner);

        // Filter Cabang A + Kategori Modem
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', [
            'pop_id' => $this->cabangA->id,
            'category_id' => $catModem->id,
        ]));

        $response->assertOk();
        $balances = $response->viewData('balances');
        $this->assertTrue($balances->contains(fn ($b) => $b->item_id === $modem->id && $b->pop_id === $this->cabangA->id));
        $this->assertFalse($balances->contains(fn ($b) => $b->item_id === $this->kabel->id));
    }
}
