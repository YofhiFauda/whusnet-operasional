<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `meter_per_roll`/`minimum_length` di Master Barang — gap nyata ketemu
 * 2026-09-15: kolom + `InventoryReceiveService::receiveRoll()` udah ada
 * sejak fitur ROLL dibangun, tapi form Master Barang gak pernah kasih jalan
 * buat ngisinya (admin gak bisa Receive roll sama sekali tanpa lewat
 * tinker). Lihat docs/plan/warehouse/analisa-gap-roll-kabel.md §8.
 */
class MasterItemRollFieldsTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
    }

    #[Test]
    public function tambah_barang_roll_wajib_isi_meter_per_roll(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIF-ROLL-1',
            'name' => 'Kabel FO Master Item Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'is_active' => 1,
            'tracking_type' => 'roll',
        ]);

        $response->assertSessionHasErrors('meter_per_roll');
        $this->assertEquals(0, Item::where('code', 'MIF-ROLL-1')->count());
    }

    #[Test]
    public function tambah_barang_roll_dengan_meter_per_roll_dan_minimum_length_sukses(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIF-ROLL-2',
            'name' => 'Kabel FO Master Item Test 2',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'is_active' => 1,
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
            'minimum_length' => 50,
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item = Item::where('code', 'MIF-ROLL-2')->firstOrFail();
        $this->assertEquals(1000, $item->meter_per_roll);
        $this->assertEquals(50, $item->minimum_length);
    }

    #[Test]
    public function unit_dipaksa_meter_server_side_walau_klien_kirim_nilai_lain(): void
    {
        // Bug nyata 2026-09-16: placeholder lama ("meter / pcs / roll")
        // nyaranin "roll" sebagai satuan, ketauan 1 item produksi (DC-4C)
        // kesimpen unit="Roll". Field disabled di klien pas roll — tapi
        // server WAJIB tetep maksa "meter" walau ada yang ngirim value lain
        // langsung ke endpoint (skip form, devtools, dst).
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIF-ROLL-UNIT',
            'name' => 'Kabel FO Unit Lock Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'Roll', // dikirim sengaja, HARUS diabaikan server
            'is_active' => 1,
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        $item = Item::where('code', 'MIF-ROLL-UNIT')->firstOrFail();
        $this->assertEquals('meter', $item->unit);
    }

    #[Test]
    public function minimum_length_wajib_lebih_kecil_dari_meter_per_roll(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIF-ROLL-3',
            'name' => 'Kabel FO Master Item Test 3',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'is_active' => 1,
            'tracking_type' => 'roll',
            'meter_per_roll' => 500,
            'minimum_length' => 500,
        ]);

        $response->assertSessionHasErrors('minimum_length');
    }

    #[Test]
    public function barang_non_roll_gak_boleh_isi_meter_per_roll(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIF-QTY-1',
            'name' => 'Kabel Qty Master Item Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'is_active' => 1,
            'tracking_type' => 'quantity',
            'meter_per_roll' => 1000,
        ]);

        $response->assertSessionHasErrors('meter_per_roll');
    }

    #[Test]
    public function daftar_barang_menampilkan_badge_roll_kabel_bukan_quantity(): void
    {
        // Bug nyata 2026-09-16 (laporan user): item tracking_type=roll
        // ke-render sebagai "QUANTITY" di daftar — badge switch di
        // master/items/index.blade.php cuma py cabang serialized/batch,
        // roll ketiban default else.
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        Item::create(['code' => 'MIF-ROLL-5', 'name' => 'Kabel FO Badge List Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        Item::create(['code' => 'MIF-QTY-2', 'name' => 'RJ45 Badge List Test', 'item_category_id' => $catKabel->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);

        $response = $this->actingAs($this->owner)->get(route('master.items.index'));

        $response->assertOk()->assertSee('ROLL KABEL');

        // Regresi: barang quantity biasa TETAP kebaca QUANTITY, bukan ikut
        // salah gara-gara fix ini.
        $response->assertSee('QUANTITY');
    }

    #[Test]
    public function edit_barang_roll_yang_sudah_locked_tetap_bisa_ubah_meter_per_roll(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create([
            'code' => 'MIF-ROLL-4', 'name' => 'Kabel FO Locked Test', 'item_category_id' => $catKabel->id,
            'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000,
        ]);

        // Kunci tracking_type — simulasikan barang udah py pergerakan ledger
        // via InventoryTransaction langsung (tanpa mesti generate roll asli).
        InventoryTransaction::create([
            'type' => 'receive', 'item_id' => $item->id, 'qty' => 1,
        ]);

        $response = $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => $item->code,
            'name' => $item->name,
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'is_active' => 1,
            'meter_per_roll' => 1200,
            'minimum_length' => 60,
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item->refresh();
        $this->assertEquals(1200, $item->meter_per_roll);
        $this->assertEquals(60, $item->minimum_length);
    }

    #[Test]
    public function ubah_tipe_dari_roll_ke_quantity_mereset_roll_fields(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create([
            'code' => 'MIF-ROLL-UNLOCKED',
            'name' => 'Kabel FO Unlocked Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
            'minimum_length' => 50,
        ]);

        $response = $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => $item->code,
            'name' => $item->name,
            'item_category_id' => $catKabel->id,
            'unit' => 'pcs',
            'is_active' => 1,
            'tracking_type' => 'quantity',
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item->refresh();
        $this->assertEquals('quantity', $item->tracking_type->value);
        $this->assertNull($item->meter_per_roll);
        $this->assertNull($item->minimum_length);
    }
}
