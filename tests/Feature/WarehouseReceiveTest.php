<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
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
 * Koreksi Warehouse (masalah 1, "pencatatan barang masuk masih berantakan")
 * — `WarehouseReceiveController`/`InventoryReceiveService` udah lengkap
 * (Fase 8), tapi baru sekarang punya test level-HTTP. Sebelum ini flow
 * paling kritis (satu-satunya titik barang baru masuk sistem) jalan tanpa
 * jaring pengaman, beda dari Transfer/Issue yang udah dicover
 * `WarehouseTransferAndIssueTest`. Pola sama: Owner (wildcard `*`) sebagai
 * actor.
 */
class WarehouseReceiveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

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

        $this->pusat = Pop::create(['code' => 'WR-PUSAT', 'pop_code' => 'WRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat WR', 'type' => 'pusat', 'status' => 'active']);
    }

    #[Test]
    public function halaman_create_bisa_dibuka_tanpa_error(): void
    {
        // Render-level guard (2026-09-04) — form ini py PHP mentah ke-embed
        // langsung di attribute `x-data="..."` (scan-picker Kategori→Barang,
        // toast). Kesalahan sintaks di situ LOLOS `pint`/`view:cache`
        // (`@js()` cuma dicompile jadi PHP, gak divalidasi sintaksnya saat
        // itu) — cuma ketauan pas view BENERAN di-render (di-`require`).
        // Test render biasa kayak ini satu-satunya jaring yang nangkep
        // kelas bug itu.
        $this->actingAs($this->owner)->get(route('warehouse.receive.create'))
            ->assertOk()
            ->assertSee('Scan Kamera');
    }

    #[Test]
    public function receive_batch_campuran_serialized_quantity_batch_tercatat_benar(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $modem = Item::create(['code' => 'WR-MODEM', 'name' => 'Modem WR', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        $kabel = Item::create(['code' => 'WR-KABEL', 'name' => 'Kabel WR', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        $drum = Item::create(['code' => 'WR-DRUM', 'name' => 'Drum WR', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'batch']);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'notes' => 'Faktur WR-001',
            'lines' => [
                ['item_id' => $modem->id, 'serial_numbers' => "WR-SN-001\nWR-SN-002", 'unit_price' => 250000],
                ['item_id' => $kabel->id, 'qty' => 150, 'unit_price' => 5000],
                ['item_id' => $drum->id, 'qty' => 300, 'lot_no' => 'LOT-WR-01', 'unit_price' => 4500],
            ],
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));
        $store->assertSessionHas('success');

        $this->assertEquals(2, InventorySerial::where('item_id', $modem->id)->where('status', SerialStatus::AVAILABLE->value)->count());
        $this->assertEquals(['WR-SN-001', 'WR-SN-002'], InventorySerial::where('item_id', $modem->id)->orderBy('serial_number')->pluck('serial_number')->all());

        $this->assertEquals(150, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $kabel->id)->where('lot_no', '')->value('qty'));
        $this->assertEquals(300, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $drum->id)->where('lot_no', 'LOT-WR-01')->value('qty'));

        $this->assertEquals(4, InventoryTransaction::where('reference_number', $reference)->where('type', 'receive')->count(), '2 SN + 1 baris qty + 1 baris batch = 4 baris ledger');

        $this->actingAs($this->owner)->get(route('warehouse.receive.show', $reference))
            ->assertOk()
            ->assertSee('WR-SN-001');
    }

    #[Test]
    public function sn_dobel_dalam_satu_submit_manual_ditolak_ramah_bukan_500(): void
    {
        // Regresi (2026-09-04) — laporan user: form manual (textarea SN,
        // beda dari endpoint scan yang udah divalidasi Rule::unique) gak
        // py guard sama sekali sebelum ini, SN dobel LOLOS sampai ke
        // `InventorySerial::create()` dan ngelempar
        // `UniqueConstraintViolationException` MENTAH → 500 blank ke user.
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'WR-DUP-1', 'name' => 'Modem WR Dup', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $modem->id, 'serial_numbers' => "WR-DUP-SN-001\nWR-DUP-SN-001", 'unit_price' => 300000],
            ],
        ]);

        $store->assertSessionHas('error');
        $this->assertEquals(0, InventoryTransaction::count());
    }

    #[Test]
    public function sn_yang_sudah_ada_di_db_via_form_manual_ditolak_ramah_bukan_500(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'WR-DUP-2', 'name' => 'Modem WR Dup 2', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'WR-EXISTING-001',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->pusat->id,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $modem->id, 'serial_numbers' => 'WR-EXISTING-001', 'unit_price' => 300000],
            ],
        ]);

        $store->assertSessionHas('error');
        // Cuma 1 SN yang udah ada dari sebelumnya — gak nambah baris baru.
        $this->assertEquals(1, InventorySerial::where('serial_number', 'WR-EXISTING-001')->count());
    }

    #[Test]
    public function unit_price_kosong_ditolak_validasi(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WR-KABEL-2', 'name' => 'Kabel WR 2', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $kabel->id, 'qty' => 100],
            ],
        ]);

        $store->assertSessionHasErrors('lines.0.unit_price');
        $this->assertEquals(0, InventoryTransaction::count());
    }

    #[Test]
    public function unit_price_nol_ditolak_service_dan_tidak_menyisakan_saldo(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WR-KABEL-3', 'name' => 'Kabel WR 3', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        // Lolos rule Laravel 'min:1' (0 ditolak di situ juga), tapi cek
        // redundan InventoryReceiveService::assertPositivePrice() tetap
        // ditest langsung biar dua lapis guard-nya kebukti — bukan cuma satu.
        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $kabel->id, 'qty' => 100, 'unit_price' => 0],
            ],
        ]);

        $store->assertSessionHasErrors('lines.0.unit_price');
        $this->assertEquals(0, InventoryBalance::where('item_id', $kabel->id)->count());
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.receive.create'))->assertForbidden();
        $this->actingAs($teknisi)->post(route('warehouse.receive.store'), [])->assertForbidden();
    }
}
