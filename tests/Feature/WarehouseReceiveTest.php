<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
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
    public function receive_batch_campuran_serialized_dan_quantity_tercatat_benar(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $modem = Item::create(['code' => 'WR-MODEM', 'name' => 'Modem WR', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        $kabel = Item::create(['code' => 'WR-KABEL', 'name' => 'Kabel WR', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'notes' => 'Faktur WR-001',
            'lines' => [
                ['item_id' => $modem->id, 'serial_numbers' => "WR-SN-001\nWR-SN-002", 'unit_price' => 250000],
                ['item_id' => $kabel->id, 'qty' => 150, 'unit_price' => 5000],
            ],
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));
        $store->assertSessionHas('success');

        $this->assertEquals(2, InventorySerial::where('item_id', $modem->id)->where('status', SerialStatus::AVAILABLE->value)->count());
        $this->assertEquals(['WR-SN-001', 'WR-SN-002'], InventorySerial::where('item_id', $modem->id)->orderBy('serial_number')->pluck('serial_number')->all());

        $this->assertEquals(150, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $kabel->id)->where('lot_no', '')->value('qty'));

        $this->assertEquals(3, InventoryTransaction::where('reference_number', $reference)->where('type', 'receive')->count(), '2 SN + 1 baris qty = 3 baris ledger');

        $this->actingAs($this->owner)->get(route('warehouse.receive.show', $reference))
            ->assertOk()
            ->assertSee('WR-SN-001');
    }

    /**
     * ADHOC-75 (2026-09-16) — barang QUANTITY yang diterima 2x dengan harga
     * beda otomatis pecah jadi 2 lot (lot pertama tetap sentinel '', lot
     * kedua digenerate sistem), BUKAN numpuk ke satu saldo yang bikin harga
     * lama-baru gak kepisah. Lihat docs/plan/warehouse/analisa-2-slot-harga-quantity.md.
     */
    #[Test]
    public function receive_quantity_harga_beda_otomatis_pecah_jadi_2_lot(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $dropcore = Item::create(['code' => 'WR-DC4', 'name' => 'Dropcore 4 Core WR', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [['item_id' => $dropcore->id, 'qty' => 12, 'unit_price' => 250000]],
        ])->assertSessionHas('success');

        $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [['item_id' => $dropcore->id, 'qty' => 10, 'unit_price' => 260000]],
        ])->assertSessionHas('success');

        $balances = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $dropcore->id)->orderBy('id')->get();
        $this->assertCount(2, $balances, 'harga beda wajib bikin 2 baris balance, bukan numpuk ke 1');
        $this->assertEquals('', $balances[0]->lot_no);
        $this->assertEquals(12, $balances[0]->qty);
        $this->assertStringStartsWith('WR-DC4-', $balances[1]->lot_no);
        $this->assertEquals(10, $balances[1]->qty);

        // Harga ke-3 sebelum salah satu lot habis — DITOLAK (guard, belum
        // pernah terjadi di data real, keputusan user 2026-09-16).
        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [['item_id' => $dropcore->id, 'qty' => 5, 'unit_price' => 270000]],
        ]);
        $store->assertSessionHas('error');
        $this->assertCount(2, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $dropcore->id)->get(), 'ditolak — tetap 2 lot, gak nambah lot ke-3');
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

    /**
     * 2026-09-07 — laporan user "input SN pertama oke, SN kedua gak bisa".
     * Akar masalahnya: seluruh baris (termasuk baris VALID) ilang begitu
     * batch di-rollback gara-gara 1 baris lain gagal (SN dobel/udah
     * kedaftar) — `back()->withInput()` udah bener ngirim data lama balik
     * ke session, tapi `<x-inventory-line-rows>` gak pernah bacanya. Test
     * ini nge-lock kontrak level HTTP (`withInput()` beneran ngebawa SEMUA
     * baris) — bagian Blade/Alpine baca `old()`-nya sendiri gak bisa dites
     * PHPUnit (gak ada JS test runner di repo ini), tapi kontrak backend-nya
     * WAJIB tetap bener biar rehydrate di sisi Blade ada bahan buat dibaca.
     */
    #[Test]
    public function gagal_submit_gara2_1_baris_gak_ngilangin_baris_lain_yang_valid(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modemBaru = Item::create(['code' => 'WR-KEEP', 'name' => 'Modem WR Keep', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        $modemDup = Item::create(['code' => 'WR-DUP-3', 'name' => 'Modem WR Dup 3', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        InventorySerial::create([
            'item_id' => $modemDup->id,
            'serial_number' => 'WR-EXISTING-002',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->pusat->id,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $modemBaru->id, 'serial_numbers' => 'WR-KEEP-001', 'unit_price' => 250000],
                ['item_id' => $modemDup->id, 'serial_numbers' => 'WR-EXISTING-002', 'unit_price' => 300000],
            ],
        ]);

        $store->assertSessionHas('error');
        $store->assertSessionHasInput('lines.0.item_id', (string) $modemBaru->id);
        $store->assertSessionHasInput('lines.0.serial_numbers', 'WR-KEEP-001');
        $store->assertSessionHasInput('lines.1.item_id', (string) $modemDup->id);
        // Batch di-rollback total — SN valid di baris 0 gak boleh ke-simpan.
        $this->assertEquals(0, InventorySerial::where('serial_number', 'WR-KEEP-001')->count());
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
    public function receive_roll_kabel_generate_n_roll_dengan_id_unik(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create([
            'code' => 'WR-ROLL-FO',
            'name' => 'Kabel FO WR',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'notes' => 'Faktur WR-ROLL-001',
            'lines' => [
                ['item_id' => $kabel->id, 'roll_count' => 3, 'vendor' => 'PT Fiber Nusantara', 'unit_price' => 2500000],
            ],
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));

        $rolls = InventoryRoll::where('item_id', $kabel->id)->orderBy('id')->get();
        $this->assertCount(3, $rolls);
        $this->assertEquals(3, $rolls->pluck('roll_code')->unique()->count(), 'roll_code wajib unik antar roll');

        $today = date('Ymd');
        foreach ($rolls as $roll) {
            $this->assertStringStartsWith("WR-ROLL-FO-{$today}-", $roll->roll_code);
            $this->assertEquals(1000, $roll->length_total);
            $this->assertEquals(1000, $roll->length_remaining);
            $this->assertEquals('PT Fiber Nusantara', $roll->vendor);
            // Input form "Harga Beli per Roll" 2.500.000 @ meter_per_roll=1000
            // — disimpan PER METER (2.500), bukan mentah per-roll (koreksi
            // 2026-09-18: qty di ledger SELALU meter, harga-per-roll yang
            // gak dikonversi bikin nilai kekali 1000x di semua kalkulasi
            // hilir, lihat InventoryReceiveService::receiveRoll()).
            $this->assertEquals(2500, $roll->unit_price_snapshot);
            $this->assertEquals(RollStatus::AVAILABLE, $roll->status);
            $this->assertEquals($this->pusat->id, $roll->current_pop_id);
        }

        $this->assertEquals(3, InventoryTransaction::where('reference_number', $reference)->where('type', 'receive')->whereNotNull('roll_id')->count());
    }

    #[Test]
    public function receive_roll_tanpa_meter_per_roll_di_master_ditolak_ramah(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WR-ROLL-NOLEN', 'name' => 'Kabel FO Tanpa Konversi', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll']);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $kabel->id, 'roll_count' => 2, 'unit_price' => 2000000],
            ],
        ]);

        $store->assertSessionHas('error');
        $this->assertEquals(0, InventoryRoll::count());
    }

    #[Test]
    public function receive_roll_kabel_menerima_format_rupiah_bertitik_dari_form(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create([
            'code' => 'WR-ROLL-DOT',
            'name' => 'Kabel FO Bertitik',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'notes' => 'Faktur WR-ROLL-DOT-001',
            'lines' => [
                ['item_id' => $kabel->id, 'roll_count' => 2, 'vendor' => 'PT Fiber Nusantara', 'unit_price' => '120.000'],
            ],
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));

        $rolls = InventoryRoll::where('item_id', $kabel->id)->get();
        $this->assertCount(2, $rolls);
        foreach ($rolls as $roll) {
            // 120.000/roll @ meter_per_roll=1000 → 120/meter tersimpan.
            $this->assertEquals(120.0, (float) $roll->unit_price_snapshot);
        }
    }

    /**
     * ADHOC (2026-09-17) — item SERIALIZED `auto_generate_serial=true` (ODP,
     * Splitter — gak punya SN vendor): staf isi jumlah unit doang, sistem
     * generate SN + baris ledger per unit sendiri. Lihat
     * docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
     */
    #[Test]
    public function receive_serialized_auto_generate_serial_dari_jumlah_unit(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $odp = Item::create([
            'code' => 'WR-ODP',
            'name' => 'ODP WR',
            'item_category_id' => $catAktif->id,
            'unit' => 'pcs',
            'tracking_type' => 'serialized',
            'auto_generate_serial' => true,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'notes' => 'Faktur WR-ODP-001',
            'lines' => [
                ['item_id' => $odp->id, 'serial_count' => 5, 'unit_price' => 75000],
            ],
        ]);

        $reference = InventoryTransaction::where('type', 'receive')->value('reference_number');
        $store->assertRedirect(route('warehouse.receive.show', $reference));

        $serials = InventorySerial::where('item_id', $odp->id)->orderBy('id')->get();
        $this->assertCount(5, $serials);
        $this->assertEquals(5, $serials->pluck('serial_number')->unique()->count(), 'serial_number wajib unik antar unit');

        $today = date('Ymd');
        foreach ($serials as $serial) {
            $this->assertStringStartsWith("WR-ODP-{$today}-", $serial->serial_number);
            $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
            $this->assertEquals($this->pusat->id, $serial->current_pop_id);
        }

        $this->assertEquals(5, InventoryTransaction::where('reference_number', $reference)->where('type', 'receive')->whereNotNull('serial_id')->count());
    }

    #[Test]
    public function receive_serialized_auto_generate_tanpa_jumlah_unit_ditolak_ramah(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $odp = Item::create([
            'code' => 'WR-ODP-2',
            'name' => 'ODP WR 2',
            'item_category_id' => $catAktif->id,
            'unit' => 'pcs',
            'tracking_type' => 'serialized',
            'auto_generate_serial' => true,
        ]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [
                ['item_id' => $odp->id, 'unit_price' => 75000],
            ],
        ]);

        $store->assertSessionHas('error');
        $this->assertEquals(0, InventorySerial::where('item_id', $odp->id)->count());
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
