<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
 * Analisa UI/UX gudang §A4 & §A5 — chip filter aktif dan sort kolom di
 * Kelola Stok. Sort memakai whitelist; parameter asing diabaikan, bukan error.
 */
class WarehouseStockSortAndChipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Item $kabel;

    private Item $aksesoris;

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

        $this->pusat = Pop::create(['code' => 'WSC-PUSAT', 'pop_code' => 'WSCP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Sort WS', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'WSC-A', 'pop_code' => 'WSCA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Sort WS', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'WSC-KABEL', 'name' => 'Kabel Sort', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        $this->aksesoris = Item::create(['code' => 'WSC-AKS', 'name' => 'Aksesoris Sort', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);

        // Kabel: pusat 200, cabang 100 (qty terkecil). Aksesoris: pusat 500 (qty terbesar).
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $this->kabel->id, 'qty' => 100]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => 100], $this->owner);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->aksesoris, 500, 1000, $this->owner);
    }

    #[Test]
    public function sort_qty_desc_menaruh_stok_terbesar_di_atas(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'qty', 'dir' => 'desc']));

        $response->assertOk()->assertSee('aria-sort="descending"', false);
        $balances = $response->viewData('balances');
        $this->assertSame($this->aksesoris->id, $balances->first()->item_id);
        $this->assertSame($this->cabang->id, $balances->last()->pop_id);
    }

    #[Test]
    public function sort_qty_asc_menaruh_stok_terkecil_di_atas(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'qty', 'dir' => 'asc']));

        $response->assertOk()->assertSee('aria-sort="ascending"', false);
        $balances = $response->viewData('balances');
        $this->assertSame($this->cabang->id, $balances->first()->pop_id);
    }

    #[Test]
    public function sort_berdasarkan_nama_barang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'item', 'dir' => 'asc']));

        $response->assertOk();
        $balances = $response->viewData('balances');
        $this->assertSame($this->aksesoris->id, $balances->first()->item_id);
    }

    #[Test]
    public function parameter_sort_tidak_dikenal_diabaikan(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'DROP TABLE', 'dir' => 'sembarang']));

        $response->assertOk()->assertSee('aria-sort="none"', false);
        $this->assertNull($response->viewData('sort'));
        $this->assertSame('asc', $response->viewData('sortDirection'));
    }

    #[Test]
    public function sort_kesehatan_desc_menaruh_stok_kritis_di_atas(): void
    {
        // Kabel di cabang (qty 100) dibuat kritis; aksesoris pusat aman.
        $balanceCabang = InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->kabel->id)->firstOrFail();
        $balanceCabang->update(['minimum_stock' => 500]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'kesehatan', 'dir' => 'desc']));

        $response->assertOk()->assertSee('aria-sort="descending"', false);
        $balances = $response->viewData('balances');
        $this->assertTrue($balances->first()->isLowStock(), 'baris kritis harus di atas saat kesehatan desc');
    }

    #[Test]
    public function sort_jenis_mengurutkan_berdasarkan_tracking_type(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'jenis', 'dir' => 'asc']));

        $response->assertOk();
        $this->assertSame('jenis', $response->viewData('sort'));
    }

    #[Test]
    public function parameter_sort_kolom_baru_tetap_whitelist(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['sort' => 'kesehatan']));
        $response->assertOk();
        $this->assertSame('kesehatan', $response->viewData('sort'));
    }

    #[Test]
    public function chip_filter_aktif_tampil_dan_bisa_dihapus(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'Kabel Sort', 'low_stock_only' => 1]));

        $response->assertOk()
            ->assertSee('Filter aktif')
            ->assertSee('Stok Menipis')
            ->assertSee('Hapus semua');

        // Link hapus chip "Stok Menipis" harus membawa search yang masih aktif.
        $this->assertStringContainsString('href="'.route('warehouse.stock.index', ['search' => 'Kabel Sort']).'"', $response->getContent());
    }

    #[Test]
    public function tanpa_filter_chip_tidak_ditampilkan(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()->assertDontSee('Filter aktif');
    }

    /**
     * Analisa §A6 — dropdown "Nama Barang" diganti combobox yang bisa diketik.
     * Opsi barang dimuat sebagai array JS (comboBox), bukan <select> biasa.
     */
    /**
     * Analisa Fase 5 (§U4/V6) — badge kondisi di modal SN (Alpine) memakai
     * design token `.badge-*`, bukan kelas warna hardcode sendiri.
     */
    #[Test]
    public function modal_sn_badge_kondisi_pakai_design_token(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee("'badge-success'", false)
            ->assertSee("'badge-warning'", false)
            ->assertSee("'badge-info'", false)
            ->assertSee("'badge-error'", false);
    }

    #[Test]
    public function dropdown_barang_pakai_combobox_yang_bisa_dicari(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee('comboBox(', false)
            ->assertSee('Ketik / pilih nama barang', false)
            ->assertSee('Kabel Sort'); // nama barang ada di opsi combobox
    }
}
