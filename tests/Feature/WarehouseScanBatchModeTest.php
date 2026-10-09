<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
 * Mode Batch di Scan Barang (2026-09-08, laporan user: "bisa gak Receive/
 * Transfer/Issue mode batch full-scan DI Scan Barang") — 3 sub-mode yang
 * masing-masing POST langsung ke rute store() ASLI (`warehouse.receive.store`
 * dkk, sudah ditest sendiri di `WarehouseReceiveTest`/`WarehouseTransferTest`/
 * `WarehouseIssueTest`). Fokus test DI SINI cuma bahan yang BARU: data yang
 * dikirim `WarehouseScanController::index()` ke view (kategori/barang/pop
 * buat form batch) dan gating tampilan tab per permission.
 */
class WarehouseScanBatchModeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Item $modem;

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

        $this->pusat = Pop::create(['code' => 'SCNB-PUSAT', 'pop_code' => 'SCBP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Batch Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'SCNB-A', 'pop_code' => 'SCBA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Batch A', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'SCNB-MODEM', 'name' => 'Modem Batch Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
    }

    #[Test]
    public function halaman_scan_muat_data_lengkap_buat_mode_batch(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.scan.index'));

        $response->assertOk()
            ->assertSee('Mode Batch', false)
            ->assertSee('Terima Barang', false)
            ->assertSee('Transfer ke Cabang', false)
            ->assertSee('Serah ke Teknisi', false)
            ->assertViewHas('pusatPops', fn ($pops) => $pops->contains('id', $this->pusat->id))
            ->assertViewHas('scanCategories', fn ($cats) => $cats->isNotEmpty())
            ->assertViewHas('scanItems', fn ($items) => $items->contains('id', $this->modem->id));
    }

    /**
     * Form batch Receive POST ke `warehouse.receive.store` ASLI (bukan
     * endpoint baru) — pastikan payload bentuk `lines[0][...]` yang
     * dikonstruksi hidden input di Blade itu BENERAN diterima controller
     * aslinya, bukan cuma asumsi di Blade doang.
     */
    #[Test]
    public function submit_batch_receive_ke_endpoint_asli_berhasil(): void
    {
        $response = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                [
                    'item_id' => $this->modem->id,
                    'unit_price' => 350000,
                    'serial_numbers' => "SCNB-SN-001\nSCNB-SN-002",
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_serials', ['serial_number' => 'SCNB-SN-001', 'item_id' => $this->modem->id]);
        $this->assertDatabaseHas('inventory_serials', ['serial_number' => 'SCNB-SN-002', 'item_id' => $this->modem->id]);
    }
}
