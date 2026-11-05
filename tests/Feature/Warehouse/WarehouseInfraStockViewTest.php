<?php

namespace Tests\Feature\Warehouse;

use App\Enums\ScopeType;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\IndonesianDate;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman Gudang "Barang Terpasang di Infrastruktur" — state SAAT INI
 * (bukan log pemakaian per-periode seperti /warehouse/usage), khusus
 * Gudang Pusat (`admin_gudang`), tanpa akses `network_nodes.*` (Peta
 * Infrastruktur/Master Node tetap tertutup dari Gudang).
 */
class WarehouseInfraStockViewTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private NetworkNode $olt;

    private User $technician;

    private User $actor;

    private FopTask $fopTask;

    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->cabang = Pop::factory()->create(['name' => 'Cabang Siman', 'type' => 'cabang']);
        $this->olt = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabang->id, 'name' => 'OLT Siman 01', 'code' => 'OLT-SIMAN-01']);

        $this->technician = User::factory()->create();
        $this->actor = User::factory()->create();

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-INFRASTOCK-0001',
            'task_date' => now(),
            'category' => 'INFR REQ',
            'tugas' => 'Uji Infra Stock',
            'pop_id' => $this->cabang->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);

        $this->category = ItemCategory::create(['code' => 'infra_stock_test', 'name' => 'Infra Stock Test', 'default_unit' => 'pcs']);
    }

    private function adminGudang(): User
    {
        $role = Role::where('code', 'admin_gudang')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);

        return $user;
    }

    private function makeItem(string $code, string $trackingType, string $unit = 'pcs'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => "Item {$code}",
            'unit' => $unit,
            'item_category_id' => $this->category->id,
            'tracking_type' => $trackingType,
            'ownership_mode' => 'infrastructure_asset',
        ]);
    }

    #[Test]
    public function user_without_infra_stock_permission_is_forbidden(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)
            ->get(route('warehouse.infra_stock.index'))
            ->assertForbidden();
    }

    /**
     * `pic_gudang` dan `pop_admin` punya `warehouse.view` penuh tapi
     * SENGAJA tidak diberi `warehouse.infra_stock.view` (keputusan user:
     * "untuk sekarang admin_gudang saja dahulu") — gerbang permission
     * halaman ini TIDAK di-OR dengan `warehouse.view` supaya role lain yang
     * kebetulan punya `warehouse.view` tidak ikut tembus.
     */
    #[Test]
    public function pic_gudang_with_generic_warehouse_view_is_still_forbidden(): void
    {
        $picRole = Role::where('code', 'pic_gudang')->firstOrFail();
        $pic = User::factory()->create(['role_id' => $picRole->id]);
        $pic->roleScopes()->create(['role_id' => $picRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->actingAs($pic)
            ->get(route('warehouse.infra_stock.index'))
            ->assertForbidden();
    }

    #[Test]
    public function default_tab_is_sn_and_shows_deployed_serial_but_not_serials_still_in_warehouse(): void
    {
        $modem = $this->makeItem('INFRA-SN-01', 'serialized');
        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-INFRA-DEPLOYED',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);

        app(InventoryService::class)->deploySerial($serial, $this->olt, $this->fopTask, [$this->technician], $this->actor);

        // SN lain yang masih di gudang (AVAILABLE) — tidak boleh nongol di
        // halaman "terpasang di infra".
        InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-MASIH-DI-GUDANG',
            'status' => 'available',
            'current_pop_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index'))
            ->assertOk();

        $response->assertSee('SN-INFRA-DEPLOYED');
        $response->assertSee($this->cabang->name);
        $response->assertSee($this->olt->code);
        $response->assertDontSee('SN-MASIH-DI-GUDANG');
    }

    #[Test]
    public function qty_tab_shows_quantity_balance_and_not_sn_or_roll_data(): void
    {
        $closure = $this->makeItem('INFRA-QTY-01', 'quantity');
        $modem = $this->makeItem('INFRA-SN-02', 'serialized');
        $kabel = $this->makeItem('INFRA-ROLL-02', 'roll', 'meter');

        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'item_id' => $closure->id,
            'qty_remaining' => 10,
            'unit_price_snapshot' => 12000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        app(InventoryService::class)->deployQuantity([$this->technician], $closure, 5, $this->olt, $this->fopTask, $this->actor);

        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-TAB-ISOLATION',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);
        app(InventoryService::class)->deploySerial($serial, $this->olt, $this->fopTask, [$this->technician], $this->actor);

        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-TAB-ISOLATION',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);
        app(InventoryService::class)->deployRoll($roll, 40, $this->olt, [$this->technician], $this->fopTask, $this->actor);

        $response = $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'qty']))
            ->assertOk();

        $response->assertSee('Item INFRA-QTY-01');
        $response->assertSee($this->olt->code);
        $response->assertDontSee('SN-TAB-ISOLATION');
        $response->assertDontSee('Item INFRA-ROLL-02');
    }

    #[Test]
    public function roll_tab_shows_total_meters_deployed_to_infra(): void
    {
        $kabel = $this->makeItem('INFRA-ROLL-01', 'roll', 'meter');

        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-INFRASTOCK-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);

        app(InventoryService::class)->deployRoll($roll, 40, $this->olt, [$this->technician], $this->fopTask, $this->actor);

        $response = $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'roll']))
            ->assertOk();

        $response->assertSee('Item INFRA-ROLL-01');
        $response->assertSee('40');
        $response->assertSee($this->cabang->name);
    }

    /**
     * Permintaan user 2026-11-05: "kabel tersebut dimana terpasang" — breadcrumb
     * Roll sebelumnya cuma sampai level Cabang (`to_pop_id`), padahal ledger
     * DEPLOY kabel sudah mencatat node persis (`to_network_node_id`) sama seperti
     * SN/Qty. Sekarang breadcrumb Roll ikut sampai node, bukan berhenti di Cabang.
     */
    #[Test]
    public function roll_tab_shows_precise_node_not_just_cabang(): void
    {
        $kabel = $this->makeItem('INFRA-ROLL-LOKASI', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-LOKASI-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);
        app(InventoryService::class)->deployRoll($roll, 40, $this->olt, [$this->technician], $this->fopTask, $this->actor);

        $response = $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'roll']))
            ->assertOk();

        $response->assertSee($this->olt->code);
        // Permintaan user 2026-11-05 "pastikan lokasi pemasangan pada Roll
        // terlihat jelas": breadcrumb di tab Roll gak boleh numpang tipis
        // jadi teks abu-abu biasa seperti kolom lain — dikasih bobot/kontras
        // lebih kuat (span sendiri) biar end user gak kelewat liat lokasinya.
        $response->assertSee('<span class="font-semibold text-slate-800 dark:text-slate-100">', false);
    }

    /**
     * Sebelum optimasi, halaman ini `->get()` SELURUH baris lalu di-filter di
     * PHP — OOM & lambat begitu datanya ribuan (laporan user 2026-11-05).
     * Query sekarang WAJIB paginate di level DB: baris di luar halaman 1
     * tidak boleh ikut ter-render sama sekali.
     */
    #[Test]
    public function sn_tab_is_paginated_at_database_level(): void
    {
        $modem = $this->makeItem('INFRA-SN-PAGE', 'serialized');

        for ($i = 1; $i <= 30; $i++) {
            $serialNumber = sprintf('SN-PAGE-%02d', $i);
            InventorySerial::create([
                'item_id' => $modem->id,
                'serial_number' => $serialNumber,
                'status' => 'deployed',
                'current_network_node_id' => $this->olt->id,
            ]);
        }

        $admin = $this->adminGudang();

        $page1 = $this->actingAs($admin)
            ->get(route('warehouse.infra_stock.index', ['tab' => 'sn']))
            ->assertOk();
        $page1->assertSee('SN-PAGE-01');
        $page1->assertDontSee('SN-PAGE-30');

        $page2 = $this->actingAs($admin)
            ->get(route('warehouse.infra_stock.index', ['tab' => 'sn', 'page' => 2]))
            ->assertOk();
        $page2->assertSee('SN-PAGE-30');
        $page2->assertDontSee('SN-PAGE-01');
    }

    #[Test]
    public function sn_row_links_to_asset_traceability_history(): void
    {
        $modem = $this->makeItem('INFRA-SN-TRACE', 'serialized');
        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-TRACE-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);
        app(InventoryService::class)->deploySerial($serial, $this->olt, $this->fopTask, [$this->technician], $this->actor);

        $expectedUrl = route('warehouse.traceability.index', ['sn' => 'SN-TRACE-001']);

        $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'sn']))
            ->assertOk()
            ->assertSee($expectedUrl, false);
    }

    #[Test]
    public function roll_row_links_to_mutation_history_filtered_by_item_and_cabang(): void
    {
        $kabel = $this->makeItem('INFRA-ROLL-TRACE', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-TRACE-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);
        app(InventoryService::class)->deployRoll($roll, 40, $this->olt, [$this->technician], $this->fopTask, $this->actor);

        $expectedUrl = route('warehouse.history.index', ['search' => 'INFRA-ROLL-TRACE', 'pop_id' => $this->cabang->id]);

        $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'roll']))
            ->assertOk()
            ->assertSee($expectedUrl);
    }

    #[Test]
    public function sn_row_shows_deploy_date_and_technician_name_styled_as_tracable(): void
    {
        $modem = $this->makeItem('INFRA-SN-DATE', 'serialized');
        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-DATE-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);
        $this->technician->update(['name' => 'Budi Teknisi']);

        app(InventoryService::class)->deploySerial($serial, $this->olt, $this->fopTask, [$this->technician], $this->actor);

        $response = $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'sn']))
            ->assertOk();

        $response->assertSee('Budi Teknisi');
        $response->assertSee(IndonesianDate::date(now()));
        // Trace dipicu dari TEKS SN-nya sendiri (link <a>), BUKAN dari baris
        // (card) — keputusan user 2026-11-05: klik di luar teks SN tidak
        // boleh ikut nge-trace. Class ini yang dicek biar gak sengaja
        // kehapus pas refactor nanti.
        $response->assertSee('text-sky-600 dark:text-sky-400 hover:underline">SN-DATE-001</a>', false);
        $response->assertDontSee("window.location='", false);
    }

    #[Test]
    public function roll_row_shows_latest_deploy_date_and_technician_name_styled_as_tracable(): void
    {
        $kabel = $this->makeItem('INFRA-ROLL-DATE', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-DATE-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);
        $this->technician->update(['name' => 'Siti Teknisi']);

        app(InventoryService::class)->deployRoll($roll, 40, $this->olt, [$this->technician], $this->fopTask, $this->actor);

        $response = $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'roll']))
            ->assertOk();

        $response->assertSee('Siti Teknisi');
        $response->assertSee(IndonesianDate::date(now()));
        $response->assertSee('text-sky-600 dark:text-sky-400 hover:underline">Item INFRA-ROLL-DATE</a>', false);
        $response->assertDontSee("window.location='", false);
    }
}
