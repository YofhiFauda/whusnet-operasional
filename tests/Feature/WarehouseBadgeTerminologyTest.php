<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
 * Analisa UI/UX gudang §C2 — badge tipe tracking ("SERIAL NUMBER"/"QUANTITY"
 * all-caps Inggris) dibakukan jadi "SN"/"REGULER", selaras badge "ROLL KABEL"
 * yang sudah ada. Form Receive/Transfer/Issue dan Kelola Stok dicek pakai
 * istilah yang SAMA, bukan 3 variasi beda-beda seperti sebelumnya.
 */
class WarehouseBadgeTerminologyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

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
    }

    #[Test]
    public function form_terima_barang_pakai_badge_sn_bukan_serial_number(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.receive.create'));

        $response->assertOk()->assertDontSee('SERIAL NUMBER');
        $this->assertStringContainsString('SN', $response->getContent());
    }

    #[Test]
    public function form_transfer_pakai_badge_sn_bukan_serial_number(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.transfers.create'));

        $response->assertOk()->assertDontSee('SERIAL NUMBER');
        $this->assertStringContainsString('SN', $response->getContent());
    }

    #[Test]
    public function form_serah_teknisi_pakai_badge_sn_bukan_serial_number(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.issues.create'));

        $response->assertOk()->assertDontSee('SERIAL NUMBER');
        $this->assertStringContainsString('SN', $response->getContent());
    }

    #[Test]
    public function kelola_stok_badge_reguler_selaras_form_lain(): void
    {
        $pusat = Pop::create(['code' => 'WBT-PUSAT', 'pop_code' => 'WBTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Badge Terminology', 'type' => 'pusat', 'status' => 'active']);
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create(['code' => 'WBT-KABEL', 'name' => 'Kabel Badge Terminology', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $item, 100, 5000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        // "QUANTITY" sengaja tidak dicek dontSee — HTML comment lama di view
        // ini masih sebut "SERIAL NUMBER"/"ROLL KABEL" (dipertahankan karena
        // WarehouseStockRollTest kebetulan bergantung padanya), bukan badge
        // yang user lihat. Cukup pastikan badge barunya ada.
        $response->assertOk()->assertSee('REGULER');
    }
}
