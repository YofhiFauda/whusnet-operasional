<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Analisa UI/UX gudang §A1 — Custody dipaginasi. Daftar per tab dibatasi per
 * halaman, TAPI KPI & dropdown teknisi tetap total penuh (dari agregat DB),
 * bukan cuma isi halaman yang dibuka.
 */
class WarehouseCustodyPaginationTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'CPG-PUSAT', 'pop_code' => 'CPGP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat CPG', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'CPG-A', 'pop_code' => 'CPGA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang CPG', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'CPG-MODEM', 'name' => 'Modem CPG', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        // 30 SN di tangan 1 teknisi → lebih dari 25 (per halaman) → ada pagination.
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi CPG']);

        $serialNumbers = collect(range(1, 30))->map(fn ($n) => sprintf('CPG-SN-%03d', $n))->all();
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, $serialNumbers, 300000, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $this->modem->id, 'serial_numbers' => $serialNumbers]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, $serialNumbers, [], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [['item_id' => $this->modem->id, 'serial_numbers' => $serialNumbers]], $this->owner);
    }

    #[Test]
    public function tab_serial_dipaginasi_25_per_halaman(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index'));

        $response->assertOk();
        $serials = $response->viewData('serials');
        $this->assertInstanceOf(LengthAwarePaginator::class, $serials);
        $this->assertCount(25, $serials); // isi halaman 1
        $this->assertSame(30, $serials->total()); // total penuh
        $this->assertTrue($serials->hasPages());
    }

    #[Test]
    public function kpi_serial_count_tetap_total_penuh_bukan_satu_halaman(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index'));

        $response->assertOk();
        $this->assertSame(30, $response->viewData('kpi')['serial_count']);
    }

    #[Test]
    public function halaman_kedua_menampilkan_sisa(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index', ['serial_page' => 2, 'tab' => 'serials']));

        $response->assertOk();
        $serials = $response->viewData('serials');
        $this->assertCount(5, $serials); // 30 - 25
        $this->assertSame(2, $serials->currentPage());
    }

    /**
     * Analisa §U3 — kolom "Diinput oleh & sejak": SN di custody menunjukkan
     * siapa yang menyerahkan (dari transaksi ISSUE) dan kapan.
     */
    #[Test]
    public function kolom_diinput_oleh_menyebut_operator_issue(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index'));

        $response->assertOk()->assertSee('Diinput Oleh', false);
        $issuers = $response->viewData('serialIssuers');
        $firstSerialId = $response->viewData('serials')->first()->id;
        $this->assertNotNull($issuers->get($firstSerialId));
        $this->assertSame($this->owner->id, $issuers->get($firstSerialId)->created_by);
    }

    /**
     * Analisa §U3 — "diinput oleh" juga untuk roll & custody quantity, bukan
     * cuma SN. Roll dicocokkan via roll_id; quantity via tech+item+lot+waktu.
     */
    #[Test]
    public function diinput_oleh_tersedia_untuk_roll_dan_material_quantity(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $rollItem = Item::create(['code' => 'CPG-ROLL', 'name' => 'Kabel Roll CPG', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        $qtyItem = Item::create(['code' => 'CPG-QTY', 'name' => 'Connector CPG', 'item_category_id' => $catKabel->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi Roll CPG']);

        // Roll: receive → transfer → issue. Quantity: receive → transfer → issue.
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $rollItem, 1, null, 1500000, $this->owner)[0];
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $qtyItem, 100, 2000, $this->owner);

        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $rollItem->id, 'roll_codes' => [$roll->roll_code]],
            ['item_id' => $qtyItem->id, 'qty' => 100],
        ], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$qtyItem->id => 100], $this->owner, [$roll->roll_code]);

        app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [
            ['item_id' => $rollItem->id, 'roll_codes' => [$roll->roll_code]],
            ['item_id' => $qtyItem->id, 'qty' => 50],
        ], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index'));
        $response->assertOk();

        $rollIssuers = $response->viewData('rollIssuers');
        $rollRow = $response->viewData('rolls')->firstWhere('roll_code', $roll->roll_code);
        $this->assertNotNull($rollRow);
        $this->assertSame($this->owner->id, $rollIssuers->get($rollRow->id)->created_by);

        $custodyIssuers = $response->viewData('custodyIssuers');
        $custodyKey = $response->viewData('custodyKey');
        $custodyRow = $response->viewData('custodies')->firstWhere('item_id', $qtyItem->id);
        $this->assertNotNull($custodyRow);
        $key = $custodyKey($custodyRow->technician_id, $custodyRow->item_id, $custodyRow->lot_no, $custodyRow->issued_at);
        $this->assertNotNull($custodyIssuers->get($key));
        $this->assertSame($this->owner->id, $custodyIssuers->get($key)->created_by);
    }

    #[Test]
    public function mode_teknisi_tetap_memuat_penuh_untuk_agregasi(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index', ['view' => 'teknisi']));

        $response->assertOk();
        $this->assertNotInstanceOf(LengthAwarePaginator::class, $response->viewData('serials'));

        $perTech = $response->viewData('perTechnician');
        $this->assertCount(1, $perTech);
        $this->assertSame(30, $perTech->first()['serial_count']);
    }
}
