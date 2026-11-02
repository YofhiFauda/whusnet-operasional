<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use App\Services\MarketingMerchandiseService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingMerchandiseRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Item $mug;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, WarehouseFeatureSeeder::class, RolePermissionSeeder::class, ItemCategorySeeder::class] as $seeder) {
            $this->seed($seeder);
        }

        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->pusat = Pop::create(['code' => 'MKT-P', 'pop_code' => 'MKTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Marketing', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'MKT-A', 'pop_code' => 'MKTA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang A', 'type' => 'cabang', 'status' => 'active']);
        $this->mug = Item::create(['code' => 'MKT-MUG', 'name' => 'Mug Branding', 'item_category_id' => ItemCategory::where('code', 'merchandise_marketing')->firstOrFail()->id, 'unit' => 'pcs', 'tracking_type' => 'quantity'])->refresh();
        app(MarketingMerchandiseService::class)->receive($this->pusat, $this->mug, 100, $this->owner);
    }

    #[Test]
    public function receive_biasa_menolak_barang_marketing_di_semua_jalur_service(): void
    {
        $receive = app(InventoryReceiveService::class);

        // Guard ada di service, jadi import/command/tinker yang memanggil langsung
        // tidak bisa menembusnya — bukan hanya form Receive.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Barang nonoperasional');

        $receive->receiveQuantity($this->pusat, $this->mug, 5, 1000, $this->owner);
    }

    #[Test]
    public function form_receive_menolak_barang_marketing_dan_stok_tidak_berubah(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [['item_id' => $this->mug->id, 'qty' => 5, 'unit_price' => '1000']],
        ])->assertSessionHas('error');

        $this->assertEquals(100, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
    }

    #[Test]
    public function kategori_nonoperasional_baru_memakai_alur_pusat_ke_cabang(): void
    {
        $category = ItemCategory::create([
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);
        $pin = Item::create([
            'code' => 'HADIAH-PIN',
            'name' => 'Pin Event',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
            'ownership_mode' => 'not_applicable',
        ]);

        $this->actingAs($this->owner)->post(route('warehouse.marketing.receive.store'), [
            'pusat_pop_id' => $this->pusat->id,
            'item_id' => $pin->id,
            'qty' => 100,
        ])->assertRedirect(route('warehouse.marketing.index'));

        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $pin->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertEquals(70, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $pin->id)->sum('qty'));
        $this->assertEquals(30, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $pin->id)->sum('qty'));
        $this->assertDatabaseHas('stock_requests', ['request_kind' => 'marketing', 'cabang_pop_id' => $this->cabang->id]);
    }

    #[Test]
    public function laporan_nonoperasional_dapat_difilter_per_kategori(): void
    {
        $category = ItemCategory::create([
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);
        $pin = Item::create([
            'code' => 'HADIAH-PIN',
            'name' => 'Pin Event',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
            'ownership_mode' => 'not_applicable',
        ]);
        $this->actingAs($this->owner)->post(route('warehouse.marketing.receive.store'), [
            'pusat_pop_id' => $this->pusat->id,
            'item_id' => $pin->id,
            'qty' => 10,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.marketing.index', [
            'item_category_id' => $category->id,
        ]));
        $response->assertOk()->assertSee('Pin Event')->assertDontSee('Mug Branding');

        $csv = $this->actingAs($this->owner)->get(route('warehouse.marketing.export', [
            'item_category_id' => $category->id,
        ]))->streamedContent();
        $this->assertStringContainsString('Pin Event', $csv);
        $this->assertStringNotContainsString('Mug Branding', $csv);
    }

    #[Test]
    public function permintaan_marketing_memindah_stok_pusat_ke_cabang_seketika(): void
    {
        $response = $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $this->assertEquals(70, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
        $this->assertEquals(30, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->mug->id)->sum('qty'));
        $this->assertSame(TransferStatus::RECEIVED, InventoryTransfer::firstOrFail()->status);
        $this->actingAs($this->owner)->get(route('warehouse.marketing.index'))->assertOk()->assertSee('Mug Branding')->assertSee('Cabang A');
    }

    #[Test]
    public function stok_tidak_cukup_tidak_memindahkan_barang(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id, 'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id, 'qty' => 101, 'request_key' => (string) Str::uuid(),
        ])->assertSessionHas('error');

        $this->assertEquals(100, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
        $this->assertDatabaseCount('inventory_transfers', 0);
    }

    #[Test]
    public function barang_operasional_ditolak_dan_cabang_lain_tidak_bisa_dipilih(): void
    {
        $other = Item::create(['code' => 'MKT-NO', 'name' => 'Bukan Merchandise', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id, 'cabang_pop_id' => $this->cabang->id,
            'item_id' => $other->id, 'qty' => 1, 'request_key' => (string) Str::uuid(),
        ])->assertSessionHas('error');

        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabang->id]);
        $cabangB = Pop::create(['code' => 'MKT-B', 'pop_code' => 'MKTB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang B', 'type' => 'cabang', 'status' => 'active']);

        $this->actingAs($admin)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id, 'cabang_pop_id' => $cabangB->id,
            'item_id' => $this->mug->id, 'qty' => 1, 'request_key' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    #[Test]
    public function permintaan_stok_operasional_tidak_menerima_merchandise(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.stock-requests.store'), [
            'cabang_pop_id' => $this->cabang->id,
            'lines' => [['item_id' => $this->mug->id, 'qty_requested' => 1]],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('stock_requests', 0);
    }

    #[Test]
    public function transfer_biasa_tidak_boleh_mengambil_merchandise(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [['item_id' => $this->mug->id, 'qty' => 1]],
            $this->owner,
        );
    }

    #[Test]
    public function laporan_cabang_hanya_menampilkan_lokasi_dalam_scope(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id, 'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id, 'qty' => 30, 'request_key' => (string) Str::uuid(),
        ]);

        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabang->id]);

        $this->actingAs($admin)->get(route('warehouse.marketing.index'))
            ->assertOk()
            ->assertSee('Cabang A')
            ->assertSee('Mutasi stok')
            ->assertDontSee('Pusat Marketing');
    }

    #[Test]
    public function pengguna_berscope_pusat_dapat_melihat_penerimaan_pusat(): void
    {
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->pusat->id]);

        $this->actingAs($admin)->get(route('warehouse.marketing.index'))
            ->assertOk()
            ->assertViewHas('receipts', fn ($receipts) => $receipts->total() === 1)
            ->assertSee('Pusat Marketing')
            ->assertDontSee('Cabang A</td>', false);
    }

    #[Test]
    public function kirim_ulang_dengan_kunci_sama_tidak_memotong_stok_dua_kali(): void
    {
        $payload = [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ];

        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), $payload)->assertRedirect();
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('inventory_transfers', 1);
        $this->assertEquals(70, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
        $this->assertEquals(30, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->mug->id)->sum('qty'));
    }

    #[Test]
    public function kunci_permintaan_yang_dipakai_untuk_payload_berbeda_ditolak(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => $key,
            'notes' => 'Acara A',
        ];

        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), $payload)->assertRedirect();
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            ...$payload,
            'qty' => 20,
        ])->assertSessionHas('error');
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            ...$payload,
            'notes' => 'Acara B',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('stock_requests', 1);
        $this->assertDatabaseCount('inventory_transfers', 1);
        $this->assertEquals(70, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
    }

    #[Test]
    public function transfer_marketing_tidak_menyalin_harga_dari_penerimaan_lama(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_transfer_id' => InventoryTransfer::firstOrFail()->id,
            'from_pop_id' => $this->pusat->id,
            'unit_price_snapshot' => null,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_transfer_id' => InventoryTransfer::firstOrFail()->id,
            'to_pop_id' => $this->cabang->id,
            'unit_price_snapshot' => null,
        ]);
    }

    #[Test]
    public function laporan_mutasi_menghitung_saldo_awal_dan_akhir_per_lokasi(): void
    {
        $this->travel(1)->days();
        $fromDate = today()->toDateString();
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $response = $this->actingAs($this->owner)->get(route('warehouse.marketing.index', [
            'from_date' => $fromDate,
            'to_date' => $fromDate,
            'item_id' => $this->mug->id,
        ]));

        $response->assertOk()->assertViewHas('stockSummary', function ($summary): bool {
            $pusat = $summary->first(fn ($row) => $row['pop']->id === $this->pusat->id);
            $cabang = $summary->first(fn ($row) => $row['pop']->id === $this->cabang->id);

            return $pusat['opening'] === 100.0 && $pusat['incoming'] === 0.0
                && $pusat['outgoing'] === 30.0 && $pusat['closing'] === 70.0
                && $cabang['opening'] === 0.0 && $cabang['incoming'] === 30.0
                && $cabang['outgoing'] === 0.0 && $cabang['closing'] === 30.0;
        });
        $this->travelBack();
    }

    #[Test]
    public function laporan_barang_masuk_pusat_dapat_difilter_dan_menampilkan_pencatat(): void
    {
        $this->travel(1)->days();
        $fromDate = today()->toDateString();
        for ($number = 0; $number < 31; $number++) {
            $this->actingAs($this->owner)->post(route('warehouse.marketing.receive.store'), [
                'pusat_pop_id' => $this->pusat->id,
                'item_id' => $this->mug->id,
                'qty' => 1,
            ])->assertRedirect();
        }

        $response = $this->actingAs($this->owner)->get(route('warehouse.marketing.index', [
            'from_date' => $fromDate,
            'to_date' => $fromDate,
        ]));

        $response->assertOk()
            ->assertViewHas('receipts', fn ($receipts) => $receipts->total() === 31 && $receipts->hasPages())
            ->assertSee($this->owner->name);
        $this->travelBack();
    }

    #[Test]
    public function permintaan_dapat_mengambil_stok_dari_dua_lot_harga(): void
    {
        // Lot kedua berharga = sisa stok lama dari sebelum form Marketing tanpa harga ada.
        // Dibuat langsung di saldo karena Receive biasa kini menolak barang Marketing.
        InventoryBalance::create(['pop_id' => $this->pusat->id, 'item_id' => $this->mug->id, 'lot_no' => 'LOT-LAMA', 'qty' => 50]);

        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 120,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertEquals(30, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
        $this->assertEquals(120, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->mug->id)->sum('qty'));
    }

    #[Test]
    public function stok_marketing_tidak_masuk_penyesuaian_selisih(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(InventoryAdjustmentService::class)->adjustPopBalance(
            $this->pusat,
            $this->mug->id,
            -1,
            'stock_opname_diff',
            $this->owner,
        );
    }

    #[Test]
    public function barang_marketing_masuk_pusat_tanpa_input_harga(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.receive.store'), [
            'pusat_pop_id' => $this->pusat->id,
            'item_id' => $this->mug->id,
            'qty' => 20,
        ])->assertRedirect(route('warehouse.marketing.index'));

        $this->assertEquals(120, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $this->mug->id,
            'qty' => 20,
            'unit_price_snapshot' => null,
            'to_pop_id' => $this->pusat->id,
        ]);
    }

    #[Test]
    public function form_barang_masuk_operasional_tidak_menerima_merchandise(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.receive.store'), [
            'pop_id' => $this->pusat->id,
            'lines' => [['item_id' => $this->mug->id, 'qty' => 10, 'unit_price' => 1000]],
        ])->assertSessionHas('error');

        $this->assertEquals(100, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->mug->id)->sum('qty'));
    }

    #[Test]
    public function laporan_dapat_disaring_ke_satu_cabang(): void
    {
        $cabangB = Pop::create(['code' => 'MKT-FB', 'pop_code' => 'MKTFB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang B Filter', 'type' => 'cabang', 'status' => 'active']);
        $payload = [
            'from_pop_id' => $this->pusat->id,
            'item_id' => $this->mug->id,
            'qty' => 5,
            'request_key' => (string) Str::uuid(),
        ];
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), $payload + ['cabang_pop_id' => $cabangB->id])->assertRedirect();

        $this->actingAs($this->owner)->get(route('warehouse.marketing.index', ['cabang_pop_id' => $this->cabang->id]))
            ->assertOk()
            ->assertDontSee('Cabang B Filter</td>', false);
    }

    #[Test]
    public function laporan_mutasi_dapat_diekspor_tanpa_harga(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $response = $this->actingAs($this->owner)->get(route('warehouse.marketing.export'));
        $response->assertOk();
        $this->assertStringContainsString('Cabang A', $response->streamedContent());
        $this->assertStringNotContainsString('Harga', $response->streamedContent());
    }

    #[Test]
    public function papan_stok_marketing_hanya_menawarkan_permintaan_ke_cabang(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $html = $this->actingAs($this->owner)->get(route('warehouse.stock.index'))->assertOk()->getContent();
        preg_match_all('/data-stock-detail="([^"]*)"/', $html, $matches);
        $details = collect($matches[1])->map(fn ($encoded) => json_decode(html_entity_decode($encoded, ENT_QUOTES), true));
        $detail = $details->first(fn ($row) => $row['popId'] === $this->cabang->id && $row['itemId'] === $this->mug->id);

        $this->assertNotNull($detail);
        $this->assertNotNull($detail['actions']['requestStockUrl']);
        $this->assertNull($detail['actions']['issueUrl']);
        $this->assertNull($detail['actions']['adjustUrl']);
        $this->assertSame([], $detail['priceTiers']);
    }

    #[Test]
    public function merchandise_di_cabang_tidak_bisa_diissue_ke_teknisi(): void
    {
        $this->actingAs($this->owner)->post(route('warehouse.marketing.store'), [
            'from_pop_id' => $this->pusat->id,
            'cabang_pop_id' => $this->cabang->id,
            'item_id' => $this->mug->id,
            'qty' => 30,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $teknisi = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id]);

        try {
            app(InventoryIssueService::class)->issue(
                $this->cabang,
                $teknisi,
                [['item_id' => $this->mug->id, 'qty' => 1]],
                $this->owner,
            );
            $this->fail('Merchandise Marketing tidak boleh masuk custody teknisi.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('nonoperasional', $exception->getMessage());
        }

        $this->assertEquals(30, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->mug->id)->sum('qty'));
    }

    #[Test]
    public function formulir_marketing_dapat_dibuka(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.marketing.index'))
            ->assertOk()
            ->assertSee('Posisi Stok')
            ->assertSee('Aktivitas')
            ->assertSee('Laporan Mutasi')
            ->assertSee('Pindahkan ke Cabang')
            ->assertSee('Permintaan Stok Operasional');

        $this->actingAs($this->owner)->get(route('warehouse.marketing.create'))
            ->assertOk()
            ->assertSee('Mug Branding')
            ->assertSee('Cabang A')
            ->assertSee('Yang terjadi setelah disimpan')
            ->assertSee('Stok Pusat')
            ->assertSee('Stok Cabang');

        $this->actingAs($this->owner)->get(route('warehouse.marketing.receive.create'))
            ->assertOk()
            ->assertSee('Mug Branding')
            ->assertSee('Pusat Marketing')
            ->assertSee('Stok Cabang belum berubah');
    }

    #[Test]
    public function export_dan_filter_cabang_menolak_pop_di_luar_scope(): void
    {
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabang->id]);
        $cabangB = Pop::create(['code' => 'MKT-SB', 'pop_code' => 'MKTSB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang B Rahasia', 'type' => 'cabang', 'status' => 'active']);

        $this->actingAs($admin)->get(route('warehouse.marketing.index', ['cabang_pop_id' => $cabangB->id]))->assertForbidden();
        $this->actingAs($admin)->get(route('warehouse.marketing.export', ['cabang_pop_id' => $cabangB->id]))->assertForbidden();
    }

    #[Test]
    public function master_marketing_harus_quantity_dan_kategori_terkunci_setelah_ada_stok(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MKT-SERIAL',
            'name' => 'Merchandise Salah Tipe',
            'item_category_id' => $this->mug->item_category_id,
            'unit' => 'pcs',
            'is_active' => 1,
            'tracking_type' => 'serialized',
            'auto_generate_serial' => 0,
        ])->assertSessionHasErrors('tracking_type');

        $this->actingAs($this->owner)->put(route('master.items.update', $this->mug), [
            'code' => $this->mug->code,
            'name' => $this->mug->name,
            'item_category_id' => ItemCategory::where('code', 'lainnya')->firstOrFail()->id,
            'unit' => 'pcs',
            'is_active' => 1,
        ])->assertSessionHasErrors('item_category_id');

        $this->assertSame('merchandise_marketing', $this->mug->fresh()->category->code);
    }
}
