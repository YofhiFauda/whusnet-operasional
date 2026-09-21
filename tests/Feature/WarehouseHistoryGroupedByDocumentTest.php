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
 * Riwayat Mutasi (2026-09-16, permintaan user) — satu dokumen Input/Serah
 * Terima yang mencakup banyak jenis barang (mis. 30 modem + 20 roll kabel)
 * sebelumnya nampil sebagai puluhan baris terpisah. Sekarang dikelompokkan
 * jadi SATU kartu per `reference_number`, kliknya masuk ke halaman detail
 * yang sudah ada (`warehouse.receive.show`/`warehouse.issues.show`) yang
 * sudah menampilkan rincian tiap barang. Lihat
 * `WarehouseHistoryController::groupByDocument()`.
 */
class WarehouseHistoryGroupedByDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): User
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();

        return User::factory()->create(['role_id' => $ownerRole->id]);
    }

    #[Test]
    public function receive_batch_banyak_jenis_barang_tampil_satu_kartu_di_riwayat(): void
    {
        $owner = $this->seedBase();

        $pusat = Pop::create(['code' => 'HGD-PUSAT', 'pop_code' => 'HGDP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Grouping Test', 'type' => 'pusat', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'HGD-MODEM', 'name' => 'Modem Grouping Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        $kabel = Item::create(['code' => 'HGD-KABEL', 'name' => 'Kabel Grouping Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $modemSerials = collect(range(1, 3))->map(fn ($i) => "HGD-MODEM-SN-{$i}")->all();

        $reference = app(InventoryReceiveService::class)->receiveBatch($pusat, [
            ['item_id' => $modem->id, 'serial_numbers' => $modemSerials, 'unit_price' => 250000],
            ['item_id' => $kabel->id, 'qty' => 500, 'unit_price' => 5000],
        ], $owner);

        // 3 baris ledger serial + 1 baris lot = 4 baris mentah, tapi 1
        // reference_number yang sama — harus jadi SATU kartu di Riwayat.
        $this->assertDatabaseCount('inventory_transactions', 4);

        $response = $this->actingAs($owner)->get(route('warehouse.history.index'));

        $response->assertOk()
            ->assertSee('2 Jenis Barang')
            ->assertSee('4', false) // "4 baris" di kolom Jumlah
            ->assertSee(route('warehouse.receive.show', $reference), false);

        // Cuma SATU baris tabel (satu onclick + satu <a> nama barang di
        // dalamnya, 2 kemunculan URL) yang nge-link ke dokumen ini — bukan
        // 4 baris ledger mentah terpisah kayak sebelum grouping.
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, route('warehouse.receive.show', $reference)));
    }

    #[Test]
    public function receive_satu_jenis_barang_tetap_tampil_seperti_biasa(): void
    {
        $owner = $this->seedBase();

        $pusat = Pop::create(['code' => 'HGS-PUSAT', 'pop_code' => 'HGSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Single Test', 'type' => 'pusat', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $router = Item::create(['code' => 'HGS-ROUTER', 'name' => 'Router Single Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($pusat, $router, 10, 100000, $owner);

        $response = $this->actingAs($owner)->get(route('warehouse.history.index'));

        $response->assertOk()
            ->assertSee('Router Single Test')
            ->assertDontSee('Jenis Barang');
    }
}
