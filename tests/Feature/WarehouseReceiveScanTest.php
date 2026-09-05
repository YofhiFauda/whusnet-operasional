<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
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
 * Scan Barang Masuk (Lacak Barang/SN — tab Single/Batch Assign, 2026-09-04)
 * — endpoint `WarehouseReceiveController::storeScanned()` yang dipakai DUA
 * tab sekaligus (data-nya identik: satu Gudang Pusat + satu item + daftar
 * SN + satu harga). Ditest terpisah dari `WarehouseReceiveTest` (form
 * manual multi-baris) karena bentuk request-nya beda (flat, bukan
 * `lines[]`) dan ada aturan tambahan yang gak ada di form manual: gudang
 * WAJIB Pusat (`assertPusat()`), SN gak boleh dobel sama yang udah ada di
 * DB.
 */
class WarehouseReceiveScanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

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

        $this->pusat = Pop::create(['code' => 'WRS-PUSAT', 'pop_code' => 'WRSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat WRS', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'WRS-CABANG', 'pop_code' => 'WRSC', 'registration_prefix' => 'E', 'cid_prefix' => 'F', 'name' => 'Cabang WRS', 'type' => 'cabang', 'status' => 'active']);

        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'WRS-MODEM', 'name' => 'Modem WRS', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
    }

    #[Test]
    public function single_assign_scan_tercatat_dan_redirect_ke_bon_penerimaan(): void
    {
        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store-scanned'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->modem->id,
            'unit_price' => 350000,
            'serial_numbers' => ['WRS-SN-001'],
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));
        $store->assertSessionHas('success');

        $serial = InventorySerial::where('serial_number', 'WRS-SN-001')->firstOrFail();
        $this->assertSame(SerialStatus::AVAILABLE, $serial->status);
        $this->assertSame($this->pusat->id, $serial->current_pop_id);
        $this->assertEquals(350000, InventoryTransaction::where('serial_id', $serial->id)->value('unit_price_snapshot'));
    }

    #[Test]
    public function batch_assign_banyak_sn_satu_item_tercatat_semua(): void
    {
        $serials = ['WRS-BATCH-001', 'WRS-BATCH-002', 'WRS-BATCH-003'];

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store-scanned'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->modem->id,
            'unit_price' => 300000,
            'serial_numbers' => $serials,
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));

        $this->assertEquals(3, InventorySerial::whereIn('serial_number', $serials)->where('status', SerialStatus::AVAILABLE->value)->count());
        $this->assertEquals(3, InventoryTransaction::where('reference_number', $reference)->count());
    }

    #[Test]
    public function gudang_cabang_ditolak_karena_receive_cuma_boleh_pusat(): void
    {
        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store-scanned'), [
            'pop_id' => $this->cabang->id,
            'item_id' => $this->modem->id,
            'unit_price' => 300000,
            'serial_numbers' => ['WRS-CABANG-001'],
        ]);

        $store->assertRedirect();
        $store->assertSessionHas('error');
        $this->assertEquals(0, InventorySerial::where('serial_number', 'WRS-CABANG-001')->count());
    }

    #[Test]
    public function sn_yang_udah_ada_di_db_ditolak_validasi(): void
    {
        InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'WRS-DUP-001',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->pusat->id,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store-scanned'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->modem->id,
            'unit_price' => 300000,
            'serial_numbers' => ['WRS-DUP-001'],
        ]);

        $store->assertSessionHasErrors('serial_numbers.0');
    }

    #[Test]
    public function sn_dobel_dalam_satu_submit_ditolak_validasi(): void
    {
        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store-scanned'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->modem->id,
            'unit_price' => 300000,
            'serial_numbers' => ['WRS-SAME-001', 'wrs-same-001'],
        ]);

        $store->assertSessionHasErrors();
        $this->assertEquals(0, InventorySerial::where('serial_number', 'WRS-SAME-001')->count());
    }

    #[Test]
    public function unit_price_kosong_ditolak_validasi(): void
    {
        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store-scanned'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $this->modem->id,
            'serial_numbers' => ['WRS-NOPRICE-001'],
        ]);

        $store->assertSessionHasErrors('unit_price');
        $this->assertEquals(0, InventoryTransaction::count());
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->post(route('warehouse.receive.store-scanned'), [])->assertForbidden();
    }
}
