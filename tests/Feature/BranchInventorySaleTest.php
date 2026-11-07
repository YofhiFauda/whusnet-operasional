<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Enums\TransferStatus;
use App\Models\BranchInventorySale;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\BranchInventorySaleService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use App\Services\WarehouseStockAsOfService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchInventorySaleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, WarehouseFeatureSeeder::class, RolePermissionSeeder::class, ItemCategorySeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->pusat = Pop::create(['code' => 'BSA-PUSAT', 'pop_code' => 'BSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Sale Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'BSA-A', 'pop_code' => 'BSA', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Cabang A Sale Test', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'BSA-B', 'pop_code' => 'BSB', 'registration_prefix' => 'C', 'cid_prefix' => 'F', 'name' => 'Cabang B Sale Test', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function sn_vendor_dijual_antar_cabang_dan_stok_pembeli_memakai_harga_jual(): void
    {
        $this->assertTrue(class_exists(BranchInventorySaleService::class));
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
            $this->owner
        );

        $this->assertSame(SerialStatus::TRANSFERRED, $serial->fresh()->status);
        $this->assertEquals(200000, $sale->transfer->transactions()->whereNotNull('from_pop_id')->firstOrFail()->unit_price_snapshot);
        app(BranchInventorySaleService::class)->receive($sale, [$serial->id], $this->owner);

        $this->assertSame(SerialStatus::AVAILABLE, $serial->fresh()->status);
        $this->assertSame($this->cabangB->id, $serial->fresh()->current_pop_id);
        $this->assertEquals(230000, app(WarehouseStockAsOfService::class)->balanceAsOf($this->cabangB, $serial->item, now()->addDay())['nilai']);
        $this->assertEquals(230000, $sale->fresh()->posted_total);
        $this->assertSame('not_tracked', $sale->fresh()->payment_status);
    }

    #[Test]
    public function laporan_menjadi_acuan_cross_check_tanpa_menyimpulkan_status_pembayaran(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
            $this->owner
        );
        app(BranchInventorySaleService::class)->receive($sale, [$serial->id], $this->owner);

        $this->actingAs($this->owner)->get(route('warehouse.branch-sales.index'))
            ->assertOk()
            ->assertSee('Cross-check pembayaran internal')
            ->assertSee('230.000,00')
            ->assertSee('Sistem Gudang tidak mencatat status atau transaksi pembayaran.');
        $this->actingAs($this->owner)->get(route('warehouse.branch-sales.show', $sale))
            ->assertOk()
            ->assertSee('Acuan cross-check pembayaran')
            ->assertSee('Gudang tidak mencatat status pembayaran.');
        $this->assertSame('not_tracked', $sale->fresh()->payment_status);
    }

    #[Test]
    public function sn_buatan_sistem_memakai_alur_jual_beli_yang_sama(): void
    {
        $this->assertTrue(class_exists(BranchInventorySaleService::class));
        $serial = $this->serialAtCabangA(true);
        $this->assertNotEmpty($serial->serial_number);

        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '125000.00']],
            $this->owner
        );
        app(BranchInventorySaleService::class)->receive($sale, [$serial->id], $this->owner);

        $this->assertSame($this->cabangB->id, $serial->fresh()->current_pop_id);
        $this->assertEquals(125000, $sale->fresh()->posted_total);
    }

    #[Test]
    public function penerimaan_bertahap_menghitung_hanya_sn_diterima_dan_dokumen_final_setelah_semua_tiba(): void
    {
        $first = $this->serialAtCabangA(false);
        [$second] = app(InventoryReceiveService::class)->receiveSerialized(
            $this->pusat, $first->item, ['BSA-SN-2'], 250000, $this->owner
        );
        $forward = app(InventoryTransferService::class)->createTransfer(
            $this->pusat, $this->cabangA,
            [['item_id' => $first->item_id, 'serial_numbers' => [$second->serial_number]]],
            $this->owner
        );
        app(InventoryTransferService::class)->receiveTransfer($forward, [$second->serial_number], [], $this->owner);

        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [
                ['serial_id' => $first->id, 'sale_price' => '230000.00'],
                ['serial_id' => $second->id, 'sale_price' => '270000.00'],
            ],
            $this->owner
        );
        app(BranchInventorySaleService::class)->receive($sale, [$first->id], $this->owner);
        $this->assertSame(TransferStatus::RECEIVED_PARTIAL, $sale->transfer->fresh()->status);
        $this->assertSame('not_tracked', $sale->fresh()->payment_status);

        // Belum final selama masih ada SN di jalan: tidak ada total dan nilai stok B baru SN pertama.
        $this->assertNull($sale->fresh()->posted_at);
        $this->assertSame(SerialStatus::TRANSFERRED, $second->fresh()->status);
        $this->assertEquals(230000, app(WarehouseStockAsOfService::class)->balanceAsOf($this->cabangB, $first->item, now()->addDay())['nilai']);

        app(BranchInventorySaleService::class)->receive($sale, [$second->id], $this->owner);
        $this->assertSame(TransferStatus::RECEIVED, $sale->transfer->fresh()->status);
        $this->assertNotNull($sale->fresh()->posted_at);
        $this->assertEquals(500000, $sale->fresh()->posted_total);
        $this->assertSame(BranchInventorySale::PAYMENT_NOT_TRACKED, $sale->fresh()->payment_status);
        $this->assertEquals(500000, app(WarehouseStockAsOfService::class)->balanceAsOf($this->cabangB, $first->item, now()->addDay())['nilai']);
    }

    #[Test]
    public function tidak_ada_lagi_jalur_tutup_sisa_hilang_untuk_dokumen_jual_beli(): void
    {
        $this->assertFalse(Route::has('warehouse.branch-sales.close-missing'));
        $this->assertFalse(method_exists(BranchInventorySaleService::class, 'closeMissing'));
    }

    #[Test]
    public function endpoint_transfer_biasa_tidak_boleh_menerima_dokumen_jual_beli(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
            $this->owner
        );

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $sale->transfer))
            ->assertForbidden();
        $this->actingAs($this->owner)->get(route('warehouse.transfers.invoice', $sale->transfer))
            ->assertForbidden();
        $this->actingAs($this->owner)->get(route('warehouse.transfers.show', $sale->transfer))
            ->assertRedirect(route('warehouse.branch-sales.show', $sale));
        $this->assertSame(SerialStatus::TRANSFERRED, $serial->fresh()->status);
    }

    #[Test]
    public function endpoint_jual_beli_membuat_dokumen_dan_harga_hanya_terlihat_oleh_peran_berizin(): void
    {
        $serial = $this->serialAtCabangA(false);
        $this->actingAs($this->owner)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
        ])->assertRedirect();

        $sale = BranchInventorySale::firstOrFail();
        $this->actingAs($this->owner)->get(route('warehouse.branch-sales.show', $sale))
            ->assertOk()->assertSee('230.000')->assertSee($serial->serial_number);

        $viewer = $this->userAtPopWithoutPriceAccess($this->cabangA);
        $this->actingAs($viewer)->get(route('warehouse.branch-sales.show', $sale))
            ->assertOk()->assertDontSee('230.000');
    }

    #[Test]
    public function scope_penjual_dan_pembeli_diperiksa_pada_aksi_http(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
            $this->owner
        );
        $seller = $this->userAtPop('admin_gudang', $this->cabangA);
        $buyer = $this->userAtPop('admin_gudang', $this->cabangB);
        $outsider = $this->userAtPop('admin_gudang', $this->pusat);

        $this->actingAs($seller)->post(route('warehouse.branch-sales.receive', $sale))->assertForbidden();
        $this->actingAs($buyer)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
        ])->assertForbidden();
        $this->actingAs($outsider)->get(route('warehouse.branch-sales.show', $sale))->assertForbidden();
        $this->actingAs($buyer)->post(route('warehouse.branch-sales.receive', $sale))
            ->assertRedirect(route('warehouse.branch-sales.show', $sale));
        $this->assertSame($this->cabangB->id, $serial->fresh()->current_pop_id);
    }

    #[Test]
    public function penjualan_ditolak_untuk_sn_yang_sedang_dikirim_atau_sudah_terjual(): void
    {
        $serial = $this->serialAtCabangA(false);
        $service = app(BranchInventorySaleService::class);
        $service->create($this->cabangA, $this->cabangB, [
            ['serial_id' => $serial->id, 'sale_price' => '230000.00'],
        ], $this->owner);

        try {
            $service->create($this->cabangA, $this->cabangB, [
                ['serial_id' => $serial->id, 'sale_price' => '230000.00'],
            ], $this->owner);
            $this->fail('SN yang masih dalam perjalanan seharusnya ditolak.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak layak', $exception->getMessage());
        }
        $this->assertDatabaseCount('branch_inventory_sales', 1);
    }

    #[Test]
    public function halaman_form_dan_laporan_jual_beli_bisa_dibuka(): void
    {
        $serial = $this->serialAtCabangA(false);
        $this->actingAs($this->owner)->get(route('warehouse.branch-sales.create', [
            'from_pop_id' => $this->cabangA->id,
        ]))->assertOk()->assertSee($serial->serial_number);

        $this->actingAs($this->owner)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [
                ['serial_id' => $serial->id, 'sale_price' => '230000.00'],
                ['sale_price' => '999999.00'],
            ],
        ])->assertRedirect();
        $this->actingAs($this->owner)->get(route('warehouse.branch-sales.index'))
            ->assertOk()->assertSee('Cabang A Sale Test')->assertSee('Cabang B Sale Test');
    }

    #[Test]
    public function barang_yang_bukan_tracking_serialized_ditolak_walau_ada_nomor_unit(): void
    {
        $serial = $this->serialAtCabangA(false);
        $serial->item->update(['tracking_type' => 'roll']);

        try {
            app(BranchInventorySaleService::class)->create(
                $this->cabangA, $this->cabangB,
                [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
                $this->owner
            );
            $this->fail('Barang ROLL tidak boleh dijual antar Cabang.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak layak', $exception->getMessage());
        }
        $this->assertDatabaseCount('branch_inventory_sales', 0);
    }

    #[Test]
    public function hpp_penjualan_berikutnya_mengikuti_harga_perolehan_unit_di_cabang_pembeli(): void
    {
        $serial = $this->serialAtCabangA(false);
        $service = app(BranchInventorySaleService::class);
        $firstSale = $service->create($this->cabangA, $this->cabangB, [
            ['serial_id' => $serial->id, 'sale_price' => '230000.00'],
        ], $this->owner);
        $service->receive($firstSale, [$serial->id], $this->owner);
        $cabangC = Pop::factory()->create(['code' => 'BSA-C', 'name' => 'Cabang C Sale Test']);

        $secondSale = $service->create($this->cabangB, $cabangC, [
            ['serial_id' => $serial->id, 'sale_price' => '260000.00'],
        ], $this->owner);
        $this->assertEquals(230000, $secondSale->transfer->transactions()
            ->where('from_pop_id', $this->cabangB->id)->firstOrFail()->unit_price_snapshot);
        $service->receive($secondSale, [$serial->id], $this->owner);
        $this->assertEquals(260000, app(WarehouseStockAsOfService::class)
            ->balanceAsOf($cabangC, $serial->item, now()->addDay())['nilai']);
    }

    #[Test]
    public function form_asli_dengan_baris_tak_tercentang_tetap_membuat_dokumen(): void
    {
        $dipilih = $this->serialAtCabangA(false);
        $tidakDipilih = $this->serialAtCabangA(true);

        // Bentuk persis yang dikirim browser: kunci per SN, baris tak tercentang
        // hanya membawa kolom harga kosong.
        $this->actingAs($this->owner)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [
                $dipilih->id => ['serial_id' => (string) $dipilih->id, 'sale_price' => '230000'],
                $tidakDipilih->id => ['sale_price' => ''],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $sale = BranchInventorySale::firstOrFail();
        $this->assertCount(1, $sale->lines);
        $this->assertSame(SerialStatus::AVAILABLE, $tidakDipilih->fresh()->status);
    }

    #[Test]
    #[DataProvider('hargaYangDiketikPengguna')]
    public function harga_gaya_indonesia_diterima_atau_ditolak_dengan_pesan_jelas(string $input, ?string $tersimpan): void
    {
        $serial = $this->serialAtCabangA(false);

        $response = $this->actingAs($this->owner)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [$serial->id => ['serial_id' => (string) $serial->id, 'sale_price' => $input]],
        ]);

        if ($tersimpan === null) {
            $response->assertSessionHasErrors();
            $this->assertSame(0, BranchInventorySale::count());

            return;
        }
        $response->assertSessionHasNoErrors();
        $this->assertEquals($tersimpan, BranchInventorySale::firstOrFail()->lines->first()->sale_price);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function hargaYangDiketikPengguna(): array
    {
        return [
            'angka bulat' => ['230000', '230000.00'],
            'dua desimal' => ['230000.50', '230000.50'],
            'titik ribuan' => ['230.000', '230000.00'],
            'koma desimal' => ['230000,5', '230000.50'],
            'titik ribuan dan koma desimal' => ['1.230.000,50', '1230000.50'],
            'nol' => ['0', null],
            'kosong' => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('kondisiBarang')]
    public function kondisi_barang_menentukan_layak_tidaknya_dijual(?string $kondisi, bool $sudahDiperiksa, bool $layak): void
    {
        $serial = $this->serialAtCabangA(false);
        $serial->update([
            'condition' => $kondisi,
            'condition_checked_at' => $sudahDiperiksa ? now() : null,
        ]);

        // Form dan service harus sepakat: yang tampil di form = yang lolos service.
        $page = $this->actingAs($this->owner)
            ->get(route('warehouse.branch-sales.create', ['from_pop_id' => $this->cabangA->id]))
            ->assertOk();
        $layak ? $page->assertSee($serial->serial_number) : $page->assertDontSee($serial->serial_number);

        $service = fn () => app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000']],
            $this->owner
        );
        if ($layak) {
            $this->assertNotNull($service());

            return;
        }
        $this->expectException(InvalidArgumentException::class);
        $service();
    }

    #[Test]
    public function picker_sn_dipaginasi_saat_lebih_dari_seratus_sn_tersedia(): void
    {
        $item = Item::create([
            'code' => 'BSA-PAGING',
            'name' => 'Modem Paging',
            'item_category_id' => ItemCategory::where('code', 'modem_ont')->firstOrFail()->id,
            'unit' => 'unit',
            'tracking_type' => 'serialized',
            'ownership_mode' => 'installable',
        ]);
        for ($i = 1; $i <= 105; $i++) {
            InventorySerial::create([
                'item_id' => $item->id,
                'serial_number' => sprintf('BSA-PAGE-%03d', $i),
                'status' => SerialStatus::AVAILABLE,
                'current_pop_id' => $this->cabangA->id,
            ]);
        }

        $pageOne = $this->actingAs($this->owner)
            ->get(route('warehouse.branch-sales.create', ['from_pop_id' => $this->cabangA->id]))
            ->assertOk();
        $pageOne->assertSee('BSA-PAGE-001')->assertDontSee('BSA-PAGE-101');

        $pageTwo = $this->actingAs($this->owner)
            ->get(route('warehouse.branch-sales.create', ['from_pop_id' => $this->cabangA->id, 'page' => 2]))
            ->assertOk();
        $pageTwo->assertSee('BSA-PAGE-101')->assertDontSee('BSA-PAGE-001');
    }

    #[Test]
    public function halaman_create_memiliki_sticky_action_bar_dan_tombol_aksi_atas(): void
    {
        $serial = $this->serialAtCabangA(false);

        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.branch-sales.create', ['from_pop_id' => $this->cabangA->id]))
            ->assertOk();

        // Verifikasi Alpine data bindings
        $response->assertSee('branchSaleCreate()');
        // Verifikasi sticky bottom bar agar user tidak perlu scroll ke bawah
        $response->assertSee('sticky bottom-4');
        // Verifikasi tombol submit di header card atas
        $response->assertSee('form="branch-sales-form"', false);
        // Verifikasi select-all checkbox
        $response->assertSee('aria-label="Pilih semua SN di halaman ini"', false);
        // Verifikasi fitur bulk price
        $response->assertSee('Set harga seragam');
        $response->assertSee('applyBulkPrice()');
        // Verifikasi quick local filter
        $response->assertSee('Filter cepat di halaman ini');
    }

    /**
     * @return array<string, array{?string, bool, bool}>
     */
    public static function kondisiBarang(): array
    {
        return [
            'baru' => ['new', false, true],
            'tanpa kondisi dianggap baru' => [null, false, true],
            'bekas baik sudah diperiksa' => ['used_good', true, true],
            'bekas baik belum diperiksa' => ['used_good', false, false],
            'bekas rusak sudah diperiksa boleh dijual' => ['used_damaged', true, true],
            'bekas rusak belum diperiksa' => ['used_damaged', false, false],
        ];
    }

    #[Test]
    public function validasi_gagal_mempertahankan_centang_dan_harga_yang_diketik(): void
    {
        $serial = $this->serialAtCabangA(false);

        $this->actingAs($this->owner)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [$serial->id => ['serial_id' => (string) $serial->id, 'sale_price' => '12.34.56']],
        ])->assertSessionHasErrors()
            ->assertSessionHasInput("lines.{$serial->id}.serial_id", (string) $serial->id)
            ->assertSessionHasInput("lines.{$serial->id}.sale_price", '12.34.56');
        $this->assertSame(0, BranchInventorySale::count());
    }

    #[Test]
    public function traceability_sn_menandai_asal_dibeli_dan_tidak_melabeli_kiriman_cabang_sebagai_pusat(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000']],
            $this->owner
        );
        $url = route('warehouse.traceability.index', ['sn' => $serial->serial_number]);

        // Dalam perjalanan: belum menjadi stok pembeli, jadi belum ada label "Dibeli dari".
        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertDontSee('Dibeli dari')
            ->assertSee('Jual Beli Dikirim (Cabang Penjual)');

        app(BranchInventorySaleService::class)->receive($sale, [$serial->id], $this->owner);

        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertSee('Dibeli dari '.$this->cabangA->name)
            ->assertSee($sale->reference_number)
            ->assertSee('Dibeli — Diterima (Cabang Pembeli)');
    }

    #[Test]
    public function form_buat_menandai_cabang_pembeli_wajib_dengan_label_terkait(): void
    {
        $this->serialAtCabangA(false);

        // Toast validasi klien membaca `el.labels[0]` untuk menyebut kolom yang kosong;
        // tanpa label for= yang cocok, pesannya jatuh ke teks generik yang tak menunjuk kolom.
        $this->actingAs($this->owner)
            ->get(route('warehouse.branch-sales.create', ['from_pop_id' => $this->cabangA->id]))
            ->assertOk()
            ->assertSee('for="to_pop_id"', false)
            ->assertSee('id="to_pop_id" name="to_pop_id" required', false)
            ->assertSee('Cabang pembeli');
    }

    #[Test]
    public function halaman_detail_menjelaskan_konfirmasi_hanya_selama_dokumen_terbuka_tanpa_tutup_sisa(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000']],
            $this->owner
        );
        $show = route('warehouse.branch-sales.show', $sale);

        $this->actingAs($this->owner)->get($show)->assertOk()
            ->assertSee('Terima semua SN')
            ->assertSee('Apa yang terjadi bila diterima?')
            ->assertSee('aria-controls="bantuan-konfirmasi-sn"', false)
            ->assertDontSee('Tutup sisa')
            // Tanpa pilih per SN: tidak ada checkbox dan tidak ada kolom Terima.
            ->assertDontSee('type="checkbox"', false)
            ->assertDontSee('serial_ids', false);

        // Setelah semua SN diterima, tombol terima (dan penjelasannya) hilang.
        app(BranchInventorySaleService::class)->receive($sale, [$serial->id], $this->owner);
        $this->actingAs($this->owner)->get($show)->assertOk()
            ->assertDontSee('Terima semua SN')
            ->assertDontSee('Apa yang terjadi bila diterima?');
    }

    #[Test]
    public function terima_semua_menerima_seluruh_sn_sisa_sekali_klik_dan_tidak_bisa_diulang(): void
    {
        $first = $this->serialAtCabangA(false);
        $second = $this->serialAtCabangA(true);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [
                ['serial_id' => $first->id, 'sale_price' => '230000'],
                ['serial_id' => $second->id, 'sale_price' => '310000'],
            ],
            $this->owner
        );
        $receive = route('warehouse.branch-sales.receive', $sale);

        $this->actingAs($this->owner)->post($receive)->assertRedirect(route('warehouse.branch-sales.show', $sale));

        $this->assertSame(SerialStatus::AVAILABLE, $first->fresh()->status);
        $this->assertSame(SerialStatus::AVAILABLE, $second->fresh()->status);
        $this->assertSame($this->cabangB->id, $second->fresh()->current_pop_id);
        $this->assertSame(TransferStatus::RECEIVED, $sale->transfer->fresh()->status);
        $this->assertEquals(540000, $sale->fresh()->posted_total);

        // Klik ganda / kirim ulang: ditolak dengan pesan, stok dan total tidak berubah.
        $this->actingAs($this->owner)->post($receive)->assertSessionHas('error');
        $this->assertEquals(540000, $sale->fresh()->posted_total);
    }

    #[Test]
    public function terima_semua_hanya_mengambil_sn_yang_belum_diterima(): void
    {
        $first = $this->serialAtCabangA(false);
        $second = $this->serialAtCabangA(true);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [
                ['serial_id' => $first->id, 'sale_price' => '230000'],
                ['serial_id' => $second->id, 'sale_price' => '310000'],
            ],
            $this->owner
        );
        // Dokumen lama bisa sudah diterima sebagian lewat service.
        app(BranchInventorySaleService::class)->receive($sale, [$first->id], $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.branch-sales.receive', $sale))->assertSessionHasNoErrors();

        $this->assertSame(SerialStatus::AVAILABLE, $second->fresh()->status);
        $this->assertEquals(540000, $sale->fresh()->posted_total);
    }

    #[Test]
    public function hanya_owner_dan_admin_gudang_yang_memegang_izin_jual_beli(): void
    {
        $serial = $this->serialAtCabangA(false);
        $payload = [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [$serial->id => ['serial_id' => (string) $serial->id, 'sale_price' => '230000']],
        ];

        foreach (['pic_gudang', 'pop_admin'] as $roleCode) {
            $user = $this->userAtPop($roleCode, $this->cabangA);
            $this->actingAs($user)->get(route('warehouse.branch-sales.index'))->assertForbidden();
            $this->actingAs($user)->post(route('warehouse.branch-sales.store'), $payload)->assertForbidden();
        }
        $this->assertSame(0, BranchInventorySale::count());

        $this->actingAs($this->userAtPop('admin_gudang', $this->cabangA))
            ->post(route('warehouse.branch-sales.store'), $payload)->assertRedirect();
        $this->assertSame(1, BranchInventorySale::count());
    }

    #[Test]
    public function laporan_per_sn_menurunkan_status_dan_hanya_menjumlah_sn_diterima(): void
    {
        $diterima = $this->serialAtCabangA(false);
        $diJalan = $this->serialAtCabangA(true);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [
                ['serial_id' => $diterima->id, 'sale_price' => '230000'],
                ['serial_id' => $diJalan->id, 'sale_price' => '310000'],
            ],
            $this->owner
        );
        $url = route('warehouse.branch-sales.report');

        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertSee($diterima->serial_number)->assertSee($diJalan->serial_number)
            ->assertSee('Dalam perjalanan')
            ->assertViewHas('summary', fn (array $summary) => $summary['Diterima pembeli']['units'] === 0);

        app(BranchInventorySaleService::class)->receive($sale, [$diterima->id], $this->owner);

        // 310.000 milik SN yang masih di jalan tidak boleh ikut nominal acuan; HPP penjual (200.000) tetap histori.
        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertViewHas('summary', fn (array $summary) => $summary['Diterima pembeli']['units'] === 1
                && $summary['Diterima pembeli']['sale'] === 230000.0
                && $summary['Diterima pembeli']['cost'] === 200000.0)
            ->assertSee('200.000,00');

        $this->actingAs($this->owner)->get($url.'?status=dalam_perjalanan')->assertOk()
            ->assertSee($diJalan->serial_number)->assertDontSee($diterima->serial_number);
        $this->actingAs($this->owner)->get($url.'?status=hilang')->assertSessionHasErrors('status');
    }

    #[Test]
    public function laporan_per_sn_menyembunyikan_harga_dan_mengikuti_scope_pop(): void
    {
        $serial = $this->serialAtCabangA(false);
        app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000']],
            $this->owner
        );

        $tanpaHarga = $this->userAtPopWithoutPriceAccess($this->cabangA);
        $this->actingAs($tanpaHarga)->get(route('warehouse.branch-sales.report'))
            ->assertOk()->assertSee($serial->serial_number)
            ->assertDontSee('230.000')->assertDontSee('200.000');
        $this->actingAs($tanpaHarga)->get(route('warehouse.branch-sales.report.export'))
            ->assertOk()->assertDontSee('Harga Jual')->assertDontSee('230000.00');

        $diLuarScope = $this->userAtPop('admin_gudang', $this->pusat);
        $this->actingAs($diLuarScope)->get(route('warehouse.branch-sales.report'))
            ->assertOk()->assertDontSee($serial->serial_number);
    }

    #[Test]
    public function ekspor_csv_laporan_per_sn_memuat_hpp_dan_harga_jual_untuk_peran_berizin(): void
    {
        $serial = $this->serialAtCabangA(false);
        app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000']],
            $this->owner
        );

        $csv = $this->actingAs($this->owner)->get(route('warehouse.branch-sales.report.export'))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Dokumen,Tanggal,Penjual,Pembeli,"Nomor SN",Barang,Kondisi,Status,"HPP Penjual","Harga Jual"', $csv);
        $this->assertStringContainsString($serial->serial_number, $csv);
        $this->assertStringContainsString('200000.00,230000.00', $csv);
    }

    #[Test]
    public function total_cross_check_dipisah_penjualan_dan_pembelian_untuk_user_ber_scope(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000']],
            $this->owner
        );
        app(BranchInventorySaleService::class)->receive($sale, [$serial->id], $this->owner);

        $this->actingAs($this->userAtPop('admin_gudang', $this->cabangA))
            ->get(route('warehouse.branch-sales.index'))
            ->assertOk()->assertSee('Penjualan (Cabang Anda sebagai penjual)')
            ->assertSee('Pembelian (Cabang Anda sebagai pembeli)');
    }

    /**
     * Role dengan izin lihat dokumen tetapi tanpa izin harga: admin_gudang yang izin
     * `warehouse_branch_sale_price.view`-nya dicabut lewat matrix (seperti yang
     * dilakukan admin lewat Role Matrix).
     */
    private function userAtPopWithoutPriceAccess(Pop $pop): User
    {
        Role::where('code', 'admin_gudang')->firstOrFail()->permissions()
            ->detach(Permission::where('code', 'warehouse_branch_sale_price.view')->value('id'));
        Cache::flush();

        return $this->userAtPop('admin_gudang', $pop);
    }

    private function userAtPop(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'selected_pop',
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function serialAtCabangA(bool $generated): InventorySerial
    {
        $item = Item::create([
            'code' => $generated ? 'BSA-AUTO' : 'BSA-VENDOR',
            'name' => $generated ? 'Switch SN Sistem' : 'Modem SN Vendor',
            'item_category_id' => ItemCategory::where('code', $generated ? 'switch_jaringan' : 'modem_ont')->firstOrFail()->id,
            'unit' => 'unit',
            'tracking_type' => 'serialized',
            'auto_generate_serial' => $generated,
            'ownership_mode' => 'installable',
        ]);
        $receive = app(InventoryReceiveService::class);
        [$serial] = $generated
            ? $receive->receiveSerializedAuto($this->pusat, $item, 1, 200000, $this->owner)
            : $receive->receiveSerialized($this->pusat, $item, ['BSA-SN-1'], 200000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer(
            $this->pusat, $this->cabangA,
            [['item_id' => $item->id, 'serial_numbers' => [$serial->serial_number]]],
            $this->owner
        );
        app(InventoryTransferService::class)->receiveTransfer($transfer, [$serial->serial_number], [], $this->owner);

        return $serial->fresh();
    }
}
