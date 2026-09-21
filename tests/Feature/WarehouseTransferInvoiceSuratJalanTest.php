<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\InventoryRoll;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
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
 * docs/plan/warehouse/rancangan-invoice-surat-jalan-transfer.md — Invoice
 * (berharga, root permission `warehouse_transfer_invoice.view`, Gudang Pusat
 * doang) & Surat Jalan (tanpa harga, reuse `warehouse_transfer.view`, admin
 * cabang ikut boleh) dicetak dari Transfer Pusat→Cabang yang sama.
 */
class WarehouseTransferInvoiceSuratJalanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $popAdminCabang;

    private Pop $pusat;

    private Pop $cabang;

    private InventoryTransfer $transfer;

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

        $this->pusat = Pop::create(['code' => 'INV-PUSAT', 'pop_code' => 'IVP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat INV', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'INV-CABANG', 'pop_code' => 'IVC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang INV', 'type' => 'cabang', 'status' => 'active', 'pic_name' => 'Budi Santoso']);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $this->popAdminCabang = User::factory()->create(['role_id' => $popAdminRole->id]);
        $this->popAdminCabang->pops()->attach($this->cabang->id);

        $scope = UserRoleScope::create([
            'user_id' => $this->popAdminCabang->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->cabang->id]);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create(['code' => 'INV-KABEL', 'name' => 'Dropcore INV', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 300, 5000, $this->owner);

        $this->transfer = app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [['item_id' => $item->id, 'qty' => 100]],
            $this->owner,
        );
    }

    #[Test]
    public function owner_bisa_cetak_invoice_dan_surat_jalan(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.transfers.invoice', $this->transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->owner)->get(route('warehouse.transfers.surat-jalan', $this->transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function admin_pusat_bisa_cetak_invoice_pop_admin_cabang_tidak_boleh(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $adminPusat = User::factory()->create(['role_id' => $adminRole->id]);
        UserRoleScope::create([
            'user_id' => $adminPusat->id,
            'role_id' => $adminRole->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $this->actingAs($adminPusat)->get(route('warehouse.transfers.invoice', $this->transfer))
            ->assertOk();

        // pop_admin cabang cuma dapat warehouse_transfer.view (Surat Jalan),
        // TIDAK dapat warehouse_transfer_invoice.view — lihat RolePermissionSeeder.
        $this->actingAs($this->popAdminCabang)->get(route('warehouse.transfers.invoice', $this->transfer))
            ->assertForbidden();
    }

    #[Test]
    public function pop_admin_cabang_bisa_cetak_surat_jalan_transfer_ke_cabangnya(): void
    {
        $this->actingAs($this->popAdminCabang)->get(route('warehouse.transfers.surat-jalan', $this->transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function surat_jalan_tidak_pernah_diquery_dengan_kolom_harga(): void
    {
        $lines = $this->transfer->transactions()->whereNotNull('from_pop_id')
            ->with(['item'])
            ->get(['id', 'item_id', 'lot_no', 'serial_id', 'roll_id', 'qty', 'inventory_transfer_id'])
            ->load(['serial', 'roll']);

        // `unit_price_snapshot` SENGAJA gak ikut di-select (lihat
        // WarehouseTransferController::suratJalan()) — assert langsung ke
        // atribut model, bukan cuma nebak dari HTML, biar gak lolos kalau
        // suatu saat Blade-nya diubah tapi query-nya lupa disamain.
        $this->assertNull($lines->first()->unit_price_snapshot);

        $html = view('warehouse.transfers.surat-jalan', [
            'transfer' => $this->transfer->fresh(['fromPop', 'toPop', 'createdBy']),
            'lines' => $lines,
            'suratJalanNumber' => 'SJ/WHUS/2026/09/001',
            'fromPopAddress' => '-',
            'toPopAddress' => '-',
        ])->render();

        $this->assertStringNotContainsString('Harga Satuan', $html);
        $this->assertStringNotContainsString('Rp ', $html);
        $this->assertStringContainsString('PT CONNEXA DIGITAL NETWORK', $html);
        $this->assertStringContainsString('Pucangombo, Tegalombo, Pacitan, Jawa Timur', $html);
        $this->assertStringContainsString('info@connexa.net.id', $html);
        $this->assertStringContainsString('082240003434', $html);
        $this->assertStringContainsString('SURAT JALAN TRANSFER PERALATAN GUDANG', $html);
    }

    #[Test]
    public function invoice_menampilkan_harga_dan_nama_kepala_gudang(): void
    {
        $lines = $this->transfer->transactions()->whereNotNull('from_pop_id')->with(['item', 'serial', 'roll'])->get();
        $total = $lines->sum(fn ($line) => (float) $line->qty * (float) $line->unit_price_snapshot);

        $html = view('warehouse.transfers.invoice', [
            'transfer' => $this->transfer->fresh(['fromPop', 'toPop', 'createdBy']),
            'lines' => $lines,
            'total' => $total,
            'kepalaGudang' => config('warehouse.kepala_gudang'),
            'fromPopAddress' => '-',
            'suratJalanNumber' => 'SJ/WHUS/2026/09/001',
        ])->render();

        $this->assertStringContainsString('PT CONNEXA DIGITAL NETWORK', $html);
        $this->assertStringContainsString('Pucangombo, Tegalombo, Pacitan, Jawa Timur', $html);
        $this->assertStringContainsString('info@connexa.net.id', $html);
        $this->assertStringContainsString('082240003434', $html);
        $this->assertStringContainsString('Harga Satuan', $html);
        $this->assertStringContainsString('Nama Barang', $html);
        $this->assertStringContainsString('Nadya Naralita Setiadi', $html);
        $this->assertStringContainsString('Rp', $html);
        // Daftar SN per-unit SENGAJA gak ada di Invoice (redundan sama
        // Surat Jalan, keputusan user 2026-09-17) — assert label-nya gak
        // pernah dicetak, walau fixture ini pakai item non-SN.
        $this->assertStringNotContainsString('SN/Kode', $html);
    }

    #[Test]
    public function invoice_menggabung_dua_sn_item_yang_sama_jadi_satu_baris(): void
    {
        $category = ItemCategory::where('code', 'modem_ont')->firstOrFail();
        $modem = Item::create([
            'code' => 'INV-MODEM',
            'name' => 'ONT Modem INV',
            'item_category_id' => $category->id,
            'unit' => 'unit',
            'tracking_type' => 'serialized',
        ]);

        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SN-INV-1', 'SN-INV-2'], 250000, $this->owner);

        $transferModem = app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [['item_id' => $modem->id, 'serial_numbers' => ['SN-INV-1', 'SN-INV-2']]],
            $this->owner,
        );

        // Dua SN = dua baris `inventory_transactions` (satu per SN), TAPI
        // sama-sama item+harga yang sama — Invoice harus gabung jadi SATU
        // baris qty=2, BUKAN dua baris kayak Surat Jalan (§ keputusan
        // 2026-09-17: daftar per-SN itu domain Surat Jalan doang).
        $rawLines = $transferModem->transactions()->whereNotNull('from_pop_id')->with(['item'])->get();
        $this->assertCount(2, $rawLines);

        $summaryLines = $rawLines
            ->groupBy(fn ($line) => $line->item_id.'|'.$line->unit_price_snapshot)
            ->map(fn ($group) => (object) [
                'item' => $group->first()->item,
                'qty' => $group->sum('qty'),
                'unit_price_snapshot' => $group->first()->unit_price_snapshot,
            ])
            ->values();

        $this->assertCount(1, $summaryLines, 'Invoice wajib gabung dua SN item yang sama jadi satu baris.');
        $this->assertEquals(2, (float) $summaryLines->first()->qty);

        $this->actingAs($this->owner)->get(route('warehouse.transfers.invoice', $transferModem))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function invoice_dan_surat_jalan_bisa_merender_transfer_dengan_barang_serialized_dan_roll(): void
    {
        $modemCategory = ItemCategory::where('code', 'modem_ont')->firstOrFail();
        $modemItem = Item::create([
            'code' => 'MOD-TEST-01',
            'name' => 'Modem Test SN',
            'item_category_id' => $modemCategory->id,
            'unit' => 'unit',
            'tracking_type' => 'serialized',
        ]);

        $rollCategory = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $rollItem = Item::create([
            'code' => 'KBL-ROLL-01',
            'name' => 'Kabel Roll Test',
            'item_category_id' => $rollCategory->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modemItem, ['SN-TEST-12345'], 250000, $this->owner);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $rollItem, 1, 'Vendor Test', 1500000, $this->owner);

        $roll = InventoryRoll::where('item_id', $rollItem->id)->firstOrFail();

        $transfer = app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [
                ['item_id' => $modemItem->id, 'serial_numbers' => ['SN-TEST-12345']],
                ['item_id' => $rollItem->id, 'roll_codes' => [$roll->roll_code]],
            ],
            $this->owner,
        );

        // Akses invoice dan surat jalan melalui HTTP request dengan lazy loading prevention aktif
        $this->actingAs($this->owner)->get(route('warehouse.transfers.invoice', $transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->owner)->get(route('warehouse.transfers.surat-jalan', $transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function invoice_barang_roll_tampil_satuan_roll_bukan_meter(): void
    {
        // Koreksi 2026-09-18 (laporan user): Kelola Stok/tracking internal
        // barang ROLL tetap satuan METER, tapi Invoice WAJIB balik ke
        // satuan ROLL (staf beli/nilai per roll) — sebelumnya kepencet
        // meter mentah (contoh nyata: 12 roll @1.000m/roll @Rp777.000/roll
        // kena hitung sbg 12.000 meter × harga-per-roll, 1000x lipat).
        $rollCategory = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $rollItem = Item::create([
            'code' => 'KBL-ROLL-INV',
            'name' => 'Dropcore 4Core Invoice Test',
            'item_category_id' => $rollCategory->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        // "Harga Beli per Roll" = 777.000, persis studi kasus user.
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $rollItem, 12, 'Vendor Roll', 777000, $this->owner);

        $rolls = InventoryRoll::where('item_id', $rollItem->id)->get();
        $this->assertCount(12, $rolls);
        // Tersimpan PER METER (777.000/1.000), bukan mentah per-roll — ini
        // yang tadinya kepencet 1000x di seluruh kalkulasi nilai hilir.
        $this->assertEquals(777.0, (float) $rolls->first()->unit_price_snapshot);

        $transfer = app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [['item_id' => $rollItem->id, 'roll_codes' => $rolls->pluck('roll_code')->all()]],
            $this->owner,
        );

        $lines = $transfer->transactions()->whereNotNull('from_pop_id')->with(['item'])->get();
        $total = $lines->sum(fn ($line) => (float) $line->qty * (float) $line->unit_price_snapshot);

        // Nilai TOTAL harus balik ke 12 × 777.000 = 9.324.000 — BUKAN
        // 12.000 meter × 777.000 (yang tadinya salah).
        $this->assertEquals(9324000.0, $total);

        $summaryLines = $lines
            ->groupBy(fn ($line) => $line->item_id.'|'.$line->unit_price_snapshot)
            ->map(function ($group) {
                $item = $group->first()->item;
                $meterPerRoll = (float) $item->meter_per_roll;

                return (object) [
                    'item' => $item,
                    'qty' => $group->count(),
                    'unit' => 'roll',
                    'unit_price_snapshot' => (float) $group->first()->unit_price_snapshot * $meterPerRoll,
                ];
            })
            ->values();

        $this->assertCount(1, $summaryLines);
        $this->assertEquals(12, $summaryLines->first()->qty, 'Qty Invoice harus 12 ROLL, bukan 12000 meter.');
        $this->assertEquals(777000.0, $summaryLines->first()->unit_price_snapshot, 'Harga Satuan Invoice harus balik ke per-roll.');
        $this->assertEquals('roll', $summaryLines->first()->unit);

        $html = view('warehouse.transfers.invoice', [
            'transfer' => $transfer->fresh(['fromPop', 'toPop', 'createdBy']),
            'lines' => $summaryLines,
            'total' => $total,
            'kepalaGudang' => config('warehouse.kepala_gudang'),
            'fromPopAddress' => '-',
            'suratJalanNumber' => 'SJ/WHUS/2026/09/001',
        ])->render();

        $this->assertStringContainsString('12 roll', $html);
        $this->assertStringContainsString('Rp 777.000', $html);
        $this->assertStringContainsString('Rp 9.324.000', $html);
    }

    #[Test]
    public function endpoint_invoice_asli_ikut_konversi_roll_ke_satuan_roll(): void
    {
        // Sama seperti test di atas, tapi lewat endpoint HTTP beneran
        // (bukan rakit manual) — buktiin controller yang jalan produksi
        // gak error dan lolos gerbang permission utuh.
        $rollCategory = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $rollItem = Item::create([
            'code' => 'KBL-ROLL-INV2',
            'name' => 'Dropcore Endpoint Test',
            'item_category_id' => $rollCategory->id,
            'unit' => 'meter',
            'tracking_type' => 'roll',
            'meter_per_roll' => 1000,
        ]);

        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $rollItem, 12, 'Vendor Roll', 777000, $this->owner);
        $rolls = InventoryRoll::where('item_id', $rollItem->id)->get();

        $transfer = app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [['item_id' => $rollItem->id, 'roll_codes' => $rolls->pluck('roll_code')->all()]],
            $this->owner,
        );

        $this->actingAs($this->owner)->get(route('warehouse.transfers.invoice', $transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
