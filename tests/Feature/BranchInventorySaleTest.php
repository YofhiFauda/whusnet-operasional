<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Enums\TransferStatus;
use App\Models\BranchInventorySale;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
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
    public function hanya_sn_yang_diterima_menjadi_kewajiban_dan_sisa_hilang_tidak_memotong_stok_lagi(): void
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

        app(BranchInventorySaleService::class)->closeMissing(
            $sale, 'SN kedua hilang dalam perjalanan', 'evidence/sale-loss.jpg', $this->owner
        );
        $this->assertSame(SerialStatus::LOST, $second->fresh()->status);
        $this->assertSame(TransferStatus::CLOSED_LOST, $sale->transfer->fresh()->status);
        $this->assertEquals(230000, $sale->fresh()->posted_total);
        $this->assertSame('not_tracked', $sale->fresh()->payment_status);
        $this->assertEquals(230000, app(WarehouseStockAsOfService::class)->balanceAsOf($this->cabangB, $first->item, now()->addDay())['nilai']);
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
        $this->actingAs($this->owner)->post(route('warehouse.transfers.close-missing', $sale->transfer))
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

        $viewer = $this->userAtPop('pic_gudang', $this->cabangA);
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
        $seller = $this->userAtPop('pop_admin', $this->cabangA);
        $buyer = $this->userAtPop('pop_admin', $this->cabangB);
        $outsider = $this->userAtPop('pop_admin', $this->pusat);

        $this->actingAs($seller)->post(route('warehouse.branch-sales.receive', $sale), [
            'serial_ids' => [$serial->id],
        ])->assertForbidden();
        $this->actingAs($buyer)->post(route('warehouse.branch-sales.store'), [
            'from_pop_id' => $this->cabangA->id,
            'to_pop_id' => $this->cabangB->id,
            'lines' => [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
        ])->assertForbidden();
        $this->actingAs($outsider)->get(route('warehouse.branch-sales.show', $sale))->assertForbidden();
        $this->actingAs($buyer)->post(route('warehouse.branch-sales.receive', $sale), [
            'serial_ids' => [$serial->id],
        ])->assertRedirect(route('warehouse.branch-sales.show', $sale));
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
        } catch (\InvalidArgumentException $exception) {
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
        } catch (\InvalidArgumentException $exception) {
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
    public function seluruh_sn_hilang_tidak_membuat_tagihan_internal_bernilai_nol_sebagai_belum_dibayar(): void
    {
        $serial = $this->serialAtCabangA(false);
        $sale = app(BranchInventorySaleService::class)->create(
            $this->cabangA, $this->cabangB,
            [['serial_id' => $serial->id, 'sale_price' => '230000.00']],
            $this->owner
        );
        app(BranchInventorySaleService::class)->closeMissing(
            $sale, 'Unit hilang dalam perjalanan', 'evidence/all-lost.jpg', $this->owner
        );

        $this->assertEquals(0, $sale->fresh()->posted_total);
        $this->assertSame('not_due', $sale->fresh()->payment_status);
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
