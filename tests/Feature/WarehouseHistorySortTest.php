<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
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
 * Analisa UI/UX gudang §A5 — Ledger (Riwayat Mutasi) dapat sort kolom: Waktu
 * (default desc) dan Tipe dokumen. Whitelist; parameter asing diabaikan.
 */
class WarehouseHistorySortTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

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

        $this->pusat = Pop::create(['code' => 'HSO-PUSAT', 'pop_code' => 'HSOP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat HSO', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'HSO-A', 'pop_code' => 'HSOA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang HSO', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'HSO-KABEL', 'name' => 'Kabel HSO', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        // Dokumen 1: RECEIVE (lebih lama). Dokumen 2: TRANSFER (lebih baru).
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 500, 5000, $this->owner);
        app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $kabel->id, 'qty' => 100]], $this->owner);

        // Pastikan RECEIVE lebih lama dari TRANSFER secara waktu.
        InventoryTransaction::where('type', 'receive')->update(['created_at' => now()->subDays(2)]);
        InventoryTransaction::where('type', 'transfer')->update(['created_at' => now()->subDay()]);
    }

    #[Test]
    public function default_sort_waktu_desc_dokumen_terbaru_di_atas(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.history.index'));

        $response->assertOk();
        $this->assertSame('date', $response->viewData('sort'));
        $this->assertSame('desc', $response->viewData('sortDirection'));
        $first = $response->viewData('ledger')->first();
        $this->assertSame('transfer', $first->type->value); // terbaru
    }

    #[Test]
    public function sort_waktu_asc_dokumen_terlama_di_atas(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.history.index', ['sort' => 'date', 'dir' => 'asc']));

        $response->assertOk();
        $first = $response->viewData('ledger')->first();
        $this->assertSame('receive', $first->type->value); // terlama
    }

    #[Test]
    public function sort_tipe_mengurutkan_berdasarkan_label_tipe(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.history.index', ['sort' => 'type', 'dir' => 'asc']));

        $response->assertOk()->assertSee('Tipe Dokumen', false);
        $this->assertSame('type', $response->viewData('sort'));
    }

    #[Test]
    public function parameter_sort_asing_jatuh_ke_default_date(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.history.index', ['sort' => 'DROP', 'dir' => 'xyz']));

        $response->assertOk();
        $this->assertSame('date', $response->viewData('sort'));
        $this->assertSame('desc', $response->viewData('sortDirection'));
    }
}
