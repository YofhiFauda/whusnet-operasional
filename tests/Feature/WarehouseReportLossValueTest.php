<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryIssueService;
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
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

/**
 * ADHOC-79 — Nilai Rugi RUSAK/HILANG di Laporan Gudang.
 *
 * Dua hal yang diuji di sini:
 * 1. Klasifikasi kategori kerugian HARUS pakai `resulting_status` (bukan
 *    `reason` mentah) buat adjustment SERIALIZED — sebelum fix, `reason`
 *    teks bebas staf (mis. "jatuh_kena_air") bikin baris damaged nyasar ke
 *    grup sendiri, nilai rugi gak pernah kehitung. Lihat docblock
 *    `WarehouseReportController::buildAdjustmentSummary()`.
 * 2. Nilai Rugi (qty x last-cost RECEIVE terakhir) cuma dihitung buat
 *    kategori RUSAK+HILANG, kategori lain (quarantine/scrapped) null.
 */
class WarehouseReportLossValueTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'RLV-PUSAT', 'pop_code' => 'RLVP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Report Loss Value Test', 'type' => 'pusat', 'status' => 'active']);
    }

    #[Test]
    public function serial_damaged_dengan_reason_teks_bebas_tetap_masuk_kategori_rusak_dan_kehitung_nilai_ruginya(): void
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $modem = Item::create(['code' => 'RLV-MODEM', 'name' => 'Modem Loss Value Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['RLV-SN-001'], 250000, $this->owner);

        // `reason` SENGAJA bukan literal "damaged" — ini kasus nyata staf
        // ngisi catatan kondisi, bukan kategori.
        app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::DAMAGED, 'jatuh kena air pas hujan', $this->owner, null, 'warehouse/evidence/damaged/x.jpg'
        );

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));
        $response->assertOk();

        $rows = collect($response->viewData('adjustmentRows'));
        $row = $rows->firstWhere('reason', 'damaged');

        $this->assertNotNull($row, 'Baris damaged harus kegrup lewat resulting_status, bukan ilang di reason teks bebas.');
        $this->assertEquals('Rusak', $row['reason_label']);
        $this->assertEquals(1.0, $row['total_qty']);
        $this->assertEquals(250000.0, $row['unit_cost']);
        $this->assertEquals(250000.0, $row['loss_value']);

        // Gak boleh ada baris nyasar terpisah dengan key reason teks bebasnya.
        $this->assertNull($rows->firstWhere('reason', 'jatuh kena air pas hujan'));
    }

    #[Test]
    public function custody_lost_kehitung_nilai_ruginya_dari_last_cost_receive(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RLV-KABEL', 'name' => 'Kabel Loss Value Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 100, 5000, $this->owner);

        $technician = User::factory()->create();
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => $kabel->id,
            'lot_no' => null,
            'qty_remaining' => 50,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        app(InventoryAdjustmentService::class)->adjustCustody($custody, -10, 'lost', $this->owner, null, 'warehouse/evidence/lost/x.jpg');

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));

        $rows = collect($response->viewData('adjustmentRows'));
        $row = $rows->firstWhere('reason', 'lost');

        $this->assertNotNull($row);
        $this->assertEquals(10.0, $row['total_qty']);
        $this->assertEquals(5000.0, $row['unit_cost']);
        $this->assertEquals(50000.0, $row['loss_value']);
    }

    #[Test]
    public function kategori_quarantine_tidak_dihitung_sebagai_nilai_rugi(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RLV-KABEL2', 'name' => 'Kabel Loss Value Test 2', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 100, 5000, $this->owner);

        $technician = User::factory()->create();
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => $kabel->id,
            'lot_no' => null,
            'qty_remaining' => 50,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        app(InventoryAdjustmentService::class)->adjustCustody($custody, -5, 'quarantine', $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));

        $rows = collect($response->viewData('adjustmentRows'));
        $row = $rows->firstWhere('reason', 'quarantine');

        $this->assertNotNull($row);
        $this->assertNull($row['unit_cost']);
        $this->assertNull($row['loss_value']);
    }

    #[Test]
    public function kpi_total_loss_value_menjumlah_seluruh_baris_rusak_dan_hilang(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RLV-KABEL3', 'name' => 'Kabel Loss Value Test 3', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 100, 5000, $this->owner);

        app(InventoryAdjustmentService::class)->adjustPopBalance($this->pusat, $kabel->id, -8, 'damaged', $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.index'));

        $kpi = $response->viewData('kpi');
        $this->assertEquals(40000.0, $kpi['total_loss_value']);
    }

    #[Test]
    public function export_excel_berhasil_didownload_dengan_permission(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function export_excel_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.reports.export'))->assertForbidden();
    }

    #[Test]
    public function export_excel_diplot_per_pop_satu_sheet_per_gudang_plus_sheet_custody_terpisah(): void
    {
        $pusatB = Pop::create(['code' => 'RLV-PUSATB', 'pop_code' => 'RLVPB', 'registration_prefix' => 'E', 'cid_prefix' => 'F', 'name' => 'Pusat B Loss Value Test', 'type' => 'pusat', 'status' => 'active']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelA = Item::create(['code' => 'RLV-EXP-A', 'name' => 'Kabel Export A', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        $kabelB = Item::create(['code' => 'RLV-EXP-B', 'name' => 'Kabel Export B', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        // POP A: pergerakan biasa (RECEIVE) + RUSAK lewat adjustPopBalance().
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabelA, 100, 5000, $this->owner);
        app(InventoryAdjustmentService::class)->adjustPopBalance($this->pusat, $kabelA->id, -8, 'damaged', $this->owner);

        // POP B: item lain, cuma pergerakan, gak ada kerugian.
        app(InventoryReceiveService::class)->receiveQuantity($pusatB, $kabelB, 50, 6000, $this->owner);

        // Custody teknisi (tanpa atribusi POP) — HILANG.
        $technician = User::factory()->create();
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => $kabelA->id,
            'lot_no' => null,
            'qty_remaining' => 20,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        app(InventoryAdjustmentService::class)->adjustCustody($custody, -3, 'lost', $this->owner, null, 'warehouse/evidence/lost/x.jpg');

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.export'));
        $response->assertOk();

        // response()->download() = BinaryFileResponse — kontennya dibaca
        // langsung dari file saat dikirim (bukan disimpan di $content),
        // jadi getContent() kosong. Ambil path file aslinya sebelum
        // deleteFileAfterSend() beres-beres.
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-export-'.uniqid().'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $tempPath);

        $sheetNames = SimpleExcelReader::create($tempPath, 'xlsx')->getSheetNames();

        $this->assertContains($this->pusat->name, $sheetNames);
        $this->assertContains($pusatB->name, $sheetNames);
        $this->assertContains('Custody Teknisi (Tanpa POP)', $sheetNames);

        // Sheet per-POP header-nya 2 BARIS (grup warna + sub-label,
        // ADHOC-79 ronde ke-5) — baca posisional (`readPopSheetRow()`),
        // bukan asosiatif by header text kayak sheet lain yang cuma 1 baris.
        $itemRow = $this->readPopSheetRow($tempPath, $this->pusat->name, 'Kabel Export A');
        $this->assertNotNull($itemRow, 'Barang yang RECEIVE+RUSAK di POP ini harus nongol satu baris gabungan.');
        // Satu-satunya lot (belum pernah ganti harga) → masuk slot "Awal".
        $this->assertEquals(100, (float) $itemRow[self::COL_BARANG_MASUK_QTY_AWAL]);
        $this->assertEquals(5000, (float) $itemRow[self::COL_BARANG_MASUK_HARGA_AWAL]);
        $this->assertEquals(8, (float) $itemRow[self::COL_QTY_RUSAK]);
        $this->assertEquals(40000, (float) $itemRow[self::COL_NILAI_RUSAK]);

        $itemRowB = $this->readPopSheetRow($tempPath, $pusatB->name, 'Kabel Export B');
        $this->assertNotNull($itemRowB);
        $this->assertEquals(50, (float) $itemRowB[self::COL_BARANG_MASUK_QTY_AWAL]);

        $custodyRows = SimpleExcelReader::create($tempPath, 'xlsx')->fromSheetName('Custody Teknisi (Tanpa POP)')->getRows()->toArray();
        $this->assertCount(1, $custodyRows);
        $this->assertEquals('Hilang', $custodyRows[0]['Kategori']);

        @unlink($tempPath);
    }

    /**
     * Koreksi 2026-09-18 (laporan user, kasus nyata) — barang ROLL tampil
     * satuan ROLL di Laporan Bulanan, BUKAN meter mentah kayak tracking
     * internal. Nilai (Rp) sudah otomatis benar dari fix
     * `InventoryReceiveService::receiveRoll()` (harga tersimpan per-meter) —
     * ini nguji lapisan PRESENTASI-nya: qty & harga balik ke roll.
     */
    #[Test]
    public function barang_roll_di_export_tampil_satuan_roll_bukan_meter(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create([
            'code' => 'RLV-ROLL-EXP',
            'name' => 'Dropcore Export Roll Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        // Studi kasus persis laporan user: 12 roll @1.000m/roll, Rp 777.000/roll.
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 12, 'Vendor Roll Export', 777000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.export'));
        $response->assertOk();

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-export-roll-'.uniqid().'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $tempPath);

        $row = $this->readPopSheetRow($tempPath, $this->pusat->name, 'Dropcore Export Roll Test');
        $this->assertNotNull($row);

        $this->assertEquals('roll', $row[2], 'Kolom Satuan harus "roll", bukan "meter".');
        // Batch pertama Roll masuk ke slot "Awal/Lama" — 12 ROLL, BUKAN 12.000 meter.
        $this->assertEquals(12, (float) $row[self::COL_BARANG_MASUK_QTY_AWAL]);
        $this->assertEquals(777000, (float) $row[self::COL_BARANG_MASUK_HARGA_AWAL]);
        // Nilai (Rp) TIDAK boleh berubah — 12 x 777.000, bukan 12.000 x 777.000.
        $this->assertEquals(9324000, (float) $row[self::COL_BARANG_MASUK_NILAI]);
        $this->assertEquals(12, (float) $row[self::COL_STOK_AKHIR_QTY_LAMA]);
        $this->assertEquals(777000, (float) $row[self::COL_STOK_AKHIR_HARGA_LAMA]);

        @unlink($tempPath);
    }

    /**
     * Ini "Laporan Akhir Bulan" (ronde ke-3 klarifikasi user 2026-09-17) —
     * Stok Awal/Stok Akhir WAJIB kebaca, bukan cuma pergerakan dalam
     * periode. QUANTITY: replay 2-slot Lama/Baru dari ledger, bukan baca
     * `InventoryBalance.qty` (itu cuma saldo TERKINI).
     */
    #[Test]
    public function stok_awal_dan_stok_akhir_quantity_direplay_dari_ledger_2_slot_lama_baru(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RLV-2SLOT', 'name' => 'Kabel 2 Slot Stok Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        // Bulan LALU — RECEIVE 100 @5000, bakal jadi lot "Lama" buat periode berjalan.
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 100, 5000, $this->owner);
        InventoryTransaction::query()->where('item_id', $kabel->id)->update(['created_at' => now()->subMonth()]);

        // Bulan INI — RECEIVE 40 @6000 (harga beda), bikin lot ke-2 "Baru".
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 40, 6000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.export'));
        $response->assertOk();

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-export-'.uniqid().'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $tempPath);

        $row = $this->readPopSheetRow($tempPath, $this->pusat->name, 'Kabel 2 Slot Stok Test');

        $this->assertNotNull($row);
        // Barang Masuk PERIODE INI = 40 @6000 doang (RECEIVE bulan lalu
        // gak ikut kehitung, itu udah masuk Stok Awal) — lot Lama ('')
        // gak nerima apa-apa bulan ini.
        $this->assertEquals(0, (float) $row[self::COL_BARANG_MASUK_QTY_AWAL]);
        $this->assertEquals(40, (float) $row[self::COL_BARANG_MASUK_QTY_BARU]);
        $this->assertEquals(6000, (float) $row[self::COL_BARANG_MASUK_HARGA_BARU]);
        $this->assertEquals(240000, (float) $row[self::COL_BARANG_MASUK_NILAI]);

        // Stok Awal = saldo SEBELUM bulan ini — cuma lot Lama yang ada.
        $this->assertEquals(100, (float) $row[self::COL_STOK_AWAL_QTY_LAMA]);
        $this->assertEquals(5000, (float) $row[self::COL_STOK_AWAL_HARGA_LAMA]);
        $this->assertEquals(0, (float) $row[self::COL_STOK_AWAL_QTY_BARU]);

        // Stok Akhir = saldo SAMPAI akhir bulan ini — dua-duanya ada.
        $this->assertEquals(100, (float) $row[self::COL_STOK_AKHIR_QTY_LAMA]);
        $this->assertEquals(40, (float) $row[self::COL_STOK_AKHIR_QTY_BARU]);
        $this->assertEquals(6000, (float) $row[self::COL_STOK_AKHIR_HARGA_BARU]);

        @unlink($tempPath);
    }

    /**
     * SERIALIZED — Stok Awal/Akhir ngikutin LOKASI unit di ledger (baris
     * `to_pop_id` TERAKHIR sebelum tanggal cutoff), bukan `inventory_serials.status`
     * terkini. Modem yang sudah di-ISSUE ke teknisi bulan ini harus hilang
     * dari Stok Akhir Cabang, meski masih tetap ada Stok Awal-nya.
     */
    #[Test]
    public function stok_awal_dan_stok_akhir_serialized_ngikutin_lokasi_unit_di_ledger(): void
    {
        $cabang = Pop::create(['code' => 'RLV-CABANG', 'pop_code' => 'RLVC', 'registration_prefix' => 'G', 'cid_prefix' => 'H', 'name' => 'Cabang StokAsOf Test', 'type' => 'cabang', 'status' => 'active']);

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $modem = Item::create(['code' => 'RLV-SN-2SLOT', 'name' => 'Modem StokAsOf Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        // Bulan LALU — RECEIVE di Pusat lalu transfer tuntas ke Cabang.
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['STOKASOF-001'], 250000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $cabang, [
            ['item_id' => $modem->id, 'serial_numbers' => ['STOKASOF-001']],
        ], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, ['STOKASOF-001'], [], $this->owner);
        InventoryTransaction::query()->where('item_id', $modem->id)->update(['created_at' => now()->subMonth()]);

        // Bulan INI — teknisi ambil modem itu dari Cabang.
        $teknisi = User::factory()->create();
        app(InventoryIssueService::class)->issue($cabang, $teknisi, [
            ['item_id' => $modem->id, 'serial_numbers' => ['STOKASOF-001']],
        ], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.export'));
        $response->assertOk();

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-export-'.uniqid().'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $tempPath);

        $row = $this->readPopSheetRow($tempPath, $cabang->name, 'Modem StokAsOf Test');

        $this->assertNotNull($row);
        // SERIALIZED pada Stok Awal masuk ke slot "Lama" (Harga Awal Satuan).
        $this->assertEquals(1, (float) $row[self::COL_STOK_AWAL_QTY_LAMA]);
        $this->assertEquals(250000, (float) $row[self::COL_STOK_AWAL_HARGA_LAMA]);
        $this->assertEquals(0, (float) $row[self::COL_STOK_AKHIR_QTY_LAMA]);
        $this->assertEquals(1, (float) $row[self::COL_STOK_TERPAKAI]);

        @unlink($tempPath);
    }

    /**
     * SERIALIZED 2-Slot — membuktikan jika ada 2 batch harga beda untuk Modem,
     * keduanya terbagi ke slot Lama dan Baru tanpa tercampur/dirata-rata.
     */
    #[Test]
    public function serialized_dua_batch_harga_terbagi_ke_slot_lama_dan_baru(): void
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $modem = Item::create(['code' => 'RLV-SN-MULTI', 'name' => 'Modem Multi Harga Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        // Bulan LALU — Batch 1: 5 unit @ Rp 300.000
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SN-B1-01', 'SN-B1-02', 'SN-B1-03', 'SN-B1-04', 'SN-B1-05'], 300000, $this->owner);
        InventoryTransaction::query()->where('item_id', $modem->id)->update(['created_at' => now()->subMonth()]);

        // Bulan INI — Batch 2: 5 unit @ Rp 310.000 (Harga Baru)
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SN-B2-01', 'SN-B2-02', 'SN-B2-03', 'SN-B2-04', 'SN-B2-05'], 310000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.reports.export'));
        $response->assertOk();

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-export-sn-2slot-'.uniqid().'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $tempPath);

        $row = $this->readPopSheetRow($tempPath, $this->pusat->name, 'Modem Multi Harga Test');
        $this->assertNotNull($row);

        // Stok Awal: 5 @ 300.000 (Slot Lama)
        $this->assertEquals(5, (float) $row[self::COL_STOK_AWAL_QTY_LAMA]);
        $this->assertEquals(300000, (float) $row[self::COL_STOK_AWAL_HARGA_LAMA]);
        $this->assertEquals(0, (float) $row[self::COL_STOK_AWAL_QTY_BARU]);

        // Barang Masuk: 5 @ 310.000 (Slot Baru)
        $this->assertEquals(0, (float) $row[self::COL_BARANG_MASUK_QTY_AWAL]);
        $this->assertEquals(5, (float) $row[self::COL_BARANG_MASUK_QTY_BARU]);
        $this->assertEquals(310000, (float) $row[self::COL_BARANG_MASUK_HARGA_BARU]);
        $this->assertEquals(1550000, (float) $row[self::COL_BARANG_MASUK_NILAI]);

        // Stok Akhir: 5 @ 300.000 (Lama) + 5 @ 310.000 (Baru) = Nilai 3.050.000
        $this->assertEquals(5, (float) $row[self::COL_STOK_AKHIR_QTY_LAMA]);
        $this->assertEquals(300000, (float) $row[self::COL_STOK_AKHIR_HARGA_LAMA]);
        $this->assertEquals(5, (float) $row[self::COL_STOK_AKHIR_QTY_BARU]);
        $this->assertEquals(310000, (float) $row[self::COL_STOK_AKHIR_HARGA_BARU]);
        $this->assertEquals(3050000, (float) $row[self::COL_STOK_AKHIR_NILAI]);

        @unlink($tempPath);
    }

    // Indeks kolom (0-based) sheet per-POP — header-nya 2 baris gabungan
    // warna (ADHOC-79 ronde ke-6, layout persis test1.html), gak bisa
    // dibaca asosiatif by nama header biasa (baris 1 sisa "" buat sel yang
    // di-merge). Urutan HARUS PERSIS sama dengan
    // `WarehouseReportController::writeStockReportHeader()`.
    private const COL_BARANG_MASUK_QTY_AWAL = 3;

    private const COL_BARANG_MASUK_HARGA_AWAL = 4;

    private const COL_BARANG_MASUK_QTY_BARU = 5;

    private const COL_BARANG_MASUK_HARGA_BARU = 6;

    private const COL_BARANG_MASUK_NILAI = 7;

    private const COL_STOK_AWAL_QTY_LAMA = 8;

    private const COL_STOK_AWAL_HARGA_LAMA = 9;

    private const COL_STOK_AWAL_QTY_BARU = 10;

    private const COL_STOK_AWAL_HARGA_BARU = 11;

    private const COL_STOK_TERPAKAI = 13;

    private const COL_STOK_AKHIR_QTY_LAMA = 14;

    private const COL_STOK_AKHIR_HARGA_LAMA = 15;

    private const COL_STOK_AKHIR_QTY_BARU = 16;

    private const COL_STOK_AKHIR_HARGA_BARU = 17;

    private const COL_STOK_AKHIR_NILAI = 18;

    private const COL_QTY_RUSAK = 19;

    private const COL_NILAI_RUSAK = 20;

    /**
     * Baca satu baris data (by "Nama Barang" di kolom 1) dari sheet per-POP
     * yang header-nya 2 baris — `noHeaderRow()` biar `getRows()` balikin
     * array posisional (bukan asosiatif by teks header), `skip(2)` buang 2
     * baris header, cocokin manual ke `$itemName`.
     *
     * @return array<int, mixed>|null
     */
    private function readPopSheetRow(string $path, string $sheetName, string $itemName): ?array
    {
        $rows = SimpleExcelReader::create($path, 'xlsx')
            ->fromSheetName($sheetName)
            ->noHeaderRow()
            ->getRows()
            ->skip(2)
            ->map(fn ($row) => array_values($row))
            ->toArray();

        foreach ($rows as $row) {
            if (($row[1] ?? null) === $itemName) {
                return $row;
            }
        }

        return null;
    }
}
