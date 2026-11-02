<?php

namespace Tests\Feature;

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
    public function parameter_sort_tampilan_lama_tidak_mengubah_urutan_papan(): void
    {
        $itemIds = fn ($response) => $response->viewData('groups')->flatMap(fn (array $group) => $group['categories']
            ->flatMap(fn (array $category) => $category['rows']->map(fn (array $row) => $row['item']->id)))->all();

        $normal = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'papan']))->assertOk();
        $legacySort = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'papan', 'sort' => 'qty', 'dir' => 'desc']))->assertOk();
        $invalidSort = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'papan', 'sort' => 'DROP TABLE']))->assertOk();

        $this->assertSame($itemIds($normal), $itemIds($legacySort));
        $this->assertSame($itemIds($normal), $itemIds($invalidSort));
    }

    #[Test]
    public function chip_filter_aktif_tampil_dan_bisa_dihapus(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'papan', 'search' => 'Kabel Sort', 'low_stock_only' => 1]));

        $response->assertOk()
            ->assertSee('Filter aktif')
            ->assertSee('Hanya menipis')
            ->assertSee('Hapus semua');

        // Link hapus chip "Hanya menipis" harus membawa search yang masih aktif.
        $this->assertStringContainsString('href="'.e(route('warehouse.stock.index', ['view' => 'papan', 'search' => 'Kabel Sort'])).'"', $response->getContent());
    }

    #[Test]
    public function tanpa_filter_chip_tidak_ditampilkan(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'papan']));

        $response->assertOk()->assertDontSee('Filter aktif');
    }
}
