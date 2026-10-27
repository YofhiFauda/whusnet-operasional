<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
 * Terima semua tetap tersedia untuk kiriman lengkap. Terima parsial mencatat
 * tiap SN/roll/qty yang benar-benar tiba dan membiarkan sisa tetap terbuka.
 */
class WarehouseTransferReceiveSimplifiedTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

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

        $this->pusat = Pop::create(['code' => 'RCV-PUSAT', 'pop_code' => 'RVP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat RCV', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'RCV-CABANG', 'pop_code' => 'RVC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang RCV', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function konfirmasi_terima_tanpa_body_apa_pun_tetap_menerima_semua_barang(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RCV-KABEL', 'name' => 'Dropcore RCV', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 300, 5000, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $kabel->id, 'qty' => 100],
        ], $this->owner);

        // TIDAK ada body sama sekali — dulu ini artinya "gak ada yang
        // dicentang" (partial 0). Sekarang harus FULL received tanpa perlu
        // kirim confirmed_quantities/confirmed_serial_numbers/confirmed_roll_codes.
        $receive = $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer));

        $transfer->refresh();
        $receive->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::RECEIVED, $transfer->status);
        $this->assertEquals(100, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $kabel->id)->value('qty'));
    }

    #[Test]
    public function konfirmasi_terima_serialized_tanpa_body_tetap_pindah_semua_sn(): void
    {
        $category = ItemCategory::where('code', 'modem_ont')->firstOrFail();
        $modem = Item::create(['code' => 'RCV-MODEM', 'name' => 'Modem RCV', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SN-RCV-1', 'SN-RCV-2'], 250000, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $modem->id, 'serial_numbers' => ['SN-RCV-1', 'SN-RCV-2']],
        ], $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer))
            ->assertRedirect(route('warehouse.transfers.show', $transfer));

        $transfer->refresh();
        $this->assertSame(TransferStatus::RECEIVED, $transfer->status);
        $this->assertEquals(2, InventorySerial::where('current_pop_id', $this->cabang->id)->where('item_id', $modem->id)->count());
    }

    #[Test]
    public function halaman_show_menyediakan_terima_semua_dan_terima_parsial(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RCV-KABEL2', 'name' => 'Dropcore RCV 2', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 300, 5000, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $kabel->id, 'qty' => 100],
        ], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.transfers.show', $transfer));

        $response->assertOk()
            ->assertSee('Konfirmasi & Terima Semua Barang', false)
            ->assertSee('Ya, Konfirmasi Terima Semua')
            ->assertSee('Terima Sisa / Parsial')
            ->assertSee('name="confirmed_quantities', false);
    }

    #[Test]
    public function issue_available_stock_ngasih_sinyal_transfer_belum_dikonfirmasi(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RCV-KABEL3', 'name' => 'Dropcore RCV 3', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 300, 5000, $this->owner);

        app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $kabel->id, 'qty' => 100],
        ], $this->owner);
        // SENGAJA belum dikonfirmasi — transfer masih in_transit.

        $response = $this->actingAs($this->owner)
            ->getJson(route('warehouse.issues.available-stock', ['pop_id' => $this->cabang->id]));

        $response->assertOk()
            ->assertJson(['items' => [], 'pending_transfer_count' => 1]);
    }

    #[Test]
    public function issue_available_stock_nol_transfer_pending_kalau_sudah_dikonfirmasi(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RCV-KABEL4', 'name' => 'Dropcore RCV 4', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 300, 5000, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $kabel->id, 'qty' => 100],
        ], $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer));

        $response = $this->actingAs($this->owner)
            ->getJson(route('warehouse.issues.available-stock', ['pop_id' => $this->cabang->id]));

        $response->assertOk()->assertJsonPath('pending_transfer_count', 0);
        $this->assertNotEmpty($response->json('items'));
    }

    #[Test]
    public function penerimaan_sebagian_dapat_dilanjutkan_tanpa_menggandakan_stok(): void
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $item = Item::create(['code' => 'RCV-PARTIAL', 'name' => 'Kabel Partial', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 770, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $item->id, 'qty' => 100]], $this->owner);
        $dispatch = $transfer->transactions()->whereNotNull('from_pop_id')->firstOrFail();

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer), [
            'receive_mode' => 'partial',
            'confirmed_quantities' => [$dispatch->id => 80],
        ])->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::RECEIVED_PARTIAL, $transfer->fresh()->status);
        $this->assertEquals(80, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $item->id)->value('qty'));
        $this->actingAs($this->owner)->get(route('warehouse.transfers.show', $transfer))
            ->assertOk()->assertSee('20 meter')->assertSee('Terima Sisa');

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer), [
            'receive_mode' => 'partial',
            'confirmed_quantities' => [$dispatch->id => 30],
        ])->assertSessionHas('error');
        $this->assertEquals(80, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $item->id)->value('qty'));

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer), [
            'receive_mode' => 'partial',
            'confirmed_quantities' => [$dispatch->id => 20],
        ])->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::RECEIVED, $transfer->fresh()->status);
        $this->assertEquals(100, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $item->id)->value('qty'));
        $this->assertEquals(2, $transfer->transactions()->whereNotNull('to_pop_id')->count());
    }

    #[Test]
    public function penerimaan_sebagian_sn_tidak_boleh_dikonfirmasi_dua_kali(): void
    {
        $category = ItemCategory::where('code', 'modem_ont')->firstOrFail();
        $item = Item::create(['code' => 'RCV-PARTIAL-SN', 'name' => 'Modem Partial', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $item, ['SN-PART-1', 'SN-PART-2'], 200000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $item->id, 'serial_numbers' => ['SN-PART-1', 'SN-PART-2']]], $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer), [
            'receive_mode' => 'partial',
            'confirmed_serial_numbers' => ['SN-PART-1'],
        ])->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::RECEIVED_PARTIAL, $transfer->fresh()->status);
        $this->assertEquals(1, InventorySerial::where('current_pop_id', $this->cabang->id)->where('item_id', $item->id)->count());

        $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer), [
            'receive_mode' => 'partial',
            'confirmed_serial_numbers' => ['SN-PART-2'],
        ])->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::RECEIVED, $transfer->fresh()->status);
        $this->assertEquals(2, InventorySerial::where('current_pop_id', $this->cabang->id)->where('item_id', $item->id)->count());
    }
}
