<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use App\Services\WarehouseStockPositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Analisa UI/UX gudang Fase 3 — ringkasan "Posisi Stok" per item. Memastikan
 * total, per gudang, dipegang teknisi, dan bermasalah dihitung dari sumber
 * yang benar, dan TIDAK melebar keluar scope POP.
 */
class WarehouseStockPositionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Item $kabel;

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

        $this->pusat = Pop::create(['code' => 'WSP-PUSAT', 'pop_code' => 'WSPP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Posisi', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'WSP-A', 'pop_code' => 'WSPA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Posisi A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'WSP-B', 'pop_code' => 'WSPB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Posisi B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'WSP-KABEL', 'name' => 'Kabel Posisi', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        // Pusat terima 300. Cabang A dapat 100, cabang B dapat 50 → pusat sisa 150.
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, $this->owner);
        $this->transferKe($this->cabangA, 100);
        $this->transferKe($this->cabangB, 50);
    }

    #[Test]
    public function total_menjumlah_semua_gudang_dalam_scope(): void
    {
        $rows = $this->summary(collect([$this->pusat->id, $this->cabangA->id, $this->cabangB->id]));

        $row = $rows->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);
        $this->assertNotNull($row);
        $this->assertSame(300.0, $row['total']);
        $this->assertCount(3, $row['per_pop']);
    }

    #[Test]
    public function scope_membatasi_total_hanya_ke_gudang_yang_boleh_dilihat(): void
    {
        // Aktor cuma punya Cabang A dalam scope → total TIDAK boleh ikut 300 gudang lain.
        $rows = $this->summary(collect([$this->cabangA->id]));

        $row = $rows->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);
        $this->assertSame(100.0, $row['total']);
        $this->assertCount(1, $row['per_pop']);
        $this->assertSame($this->cabangA->id, $row['per_pop']->first()['pop_id']);
    }

    #[Test]
    public function filter_gudang_di_luar_scope_tidak_membocorkan_data(): void
    {
        $rows = app(WarehouseStockPositionService::class)->summarize(
            collect([$this->cabangA->id]),
            popFilter: $this->pusat->id,
        );

        $this->assertCount(0, $rows);
    }

    #[Test]
    public function dipegang_teknisi_dihitung_terpisah_dari_stok_gudang(): void
    {
        $teknisi = User::factory()->create();
        app(InventoryIssueService::class)->issue($this->cabangA, $teknisi, [
            ['item_id' => $this->kabel->id, 'qty' => 30],
        ], $this->owner);

        $row = $this->summary(collect([$this->cabangA->id]))->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);

        $this->assertSame(70.0, $row['total'], 'sisa di gudang setelah issue');
        $this->assertSame(30.0, $row['held'], 'yang dipegang teknisi');
    }

    #[Test]
    public function unit_bermasalah_muncul_di_kolom_karantina(): void
    {
        $modem = Item::create(['code' => 'WSP-MODEM', 'name' => 'Modem Posisi', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        // Receive serialized hanya boleh di Gudang Pusat (cabang terima lewat transfer).
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WSPQ00001', 'WSPQ00002', 'WSPQ00003'], 350000, $this->owner);
        InventorySerial::where('serial_number', 'WSPQ00002')->update(['status' => SerialStatus::QUARANTINE->value]);

        $row = $this->summary(collect([$this->pusat->id]))->firstWhere(fn ($r) => $r['item']->id === $modem->id);

        $this->assertSame(2.0, $row['total'], 'unit karantina tidak dihitung sebagai tersedia');
        $this->assertSame(1.0, $row['problem']);
    }

    #[Test]
    public function rincian_lot_quantity_tersimpan_per_gudang(): void
    {
        $row = $this->summary(collect([$this->cabangA->id]))->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);
        $lots = $row['per_pop']->first()['lots'];

        $this->assertNotEmpty($lots);
        $this->assertSame(100.0, collect($lots)->sum('qty'));
    }

    #[Test]
    public function halaman_ringkasan_tampil_dan_mode_default_tetap_per_lot(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan']));

        $response->assertOk()
            ->assertSee('Ringkasan per Item')
            ->assertSee('Total Tersedia')
            ->assertSee('Kabel Posisi')
            ->assertSee('Rincian lot per gudang');
        $this->assertSame('ringkasan', $response->viewData('viewMode'));

        $default = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));
        $this->assertSame('lot', $default->viewData('viewMode'));
        $default->assertDontSee('Rincian lot per gudang');
    }

    /**
     * Analisa §U1/U2 (2026-10-08) — rincian lot dibuka lewat drawer
     * slide-over (`<x-ui.drawer>`), BUKAN `<details>` HTML native lagi.
     * Chip dispatch `lot-drawer-data` (isi) + `open-drawer` (tampil),
     * komponen drawer-nya SATU instance (name="lot-detail") buat semua baris.
     */
    #[Test]
    public function rincian_lot_dibuka_lewat_drawer_bukan_details_native(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan']));

        $response->assertOk()
            ->assertDontSee('<details', false)
            ->assertSee("\$dispatch('lot-drawer-data'", false)
            ->assertSee("\$dispatch('open-drawer', 'lot-detail')", false)
            ->assertSee('Rincian Lot per Gudang');
    }

    /**
     * Analisa §U3 — "Dipegang" harus menyebut siapa pemegangnya, bukan cuma angka.
     */
    #[Test]
    public function pemegang_menyebut_nama_teknisi_dan_jumlahnya(): void
    {
        $teknisi = User::factory()->create(['name' => 'Teknisi Uji Pegang']);
        app(InventoryIssueService::class)->issue($this->cabangA, $teknisi, [
            ['item_id' => $this->kabel->id, 'qty' => 30],
        ], $this->owner);

        $row = $this->summary(collect([$this->cabangA->id]))->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);

        $this->assertCount(1, $row['holders']);
        $this->assertSame('Teknisi Uji Pegang', $row['holders']->first()['name']);
        $this->assertSame(30.0, $row['holders']->first()['qty']);
    }

    /**
     * Analisa §V4 — penginput transaksi terakhir harus tampil, bukan cuma di ledger.
     */
    #[Test]
    public function transaksi_terakhir_menyebut_penginput_dan_referensi(): void
    {
        $row = $this->summary(collect([$this->pusat->id, $this->cabangA->id, $this->cabangB->id]))
            ->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);

        $this->assertNotNull($row['last_activity']);
        $this->assertSame($this->owner->name, $row['last_activity']['by']);
        $this->assertNotEmpty($row['last_activity']['type']);
        $this->assertNotEmpty($row['last_activity']['ref']);
    }

    #[Test]
    public function halaman_ringkasan_menampilkan_pemegang_dan_terakhir_diubah(): void
    {
        $teknisi = User::factory()->create(['name' => 'Teknisi Uji Halaman']);
        app(InventoryIssueService::class)->issue($this->cabangA, $teknisi, [
            ['item_id' => $this->kabel->id, 'qty' => 30],
        ], $this->owner);

        $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan']))
            ->assertOk()
            ->assertSee('Teknisi Uji Halaman')
            ->assertSee('Terakhir Diubah')
            ->assertSee('oleh');
    }

    /**
     * Analisa §U1 — kolom In Transit: barang yang transfernya belum dikonfirmasi
     * terhitung terpisah dari stok gudang.
     */
    #[Test]
    public function in_transit_menghitung_transfer_belum_dikonfirmasi(): void
    {
        // Transfer 40 ke cabang B TANPA receiveTransfer → tetap in-transit.
        app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->kabel->id, 'qty' => 40]], $this->owner);

        $row = $this->summary(collect([$this->pusat->id, $this->cabangA->id, $this->cabangB->id]))
            ->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);

        $this->assertSame(40.0, $row['in_transit']);
    }

    #[Test]
    public function ringkasan_bisa_disort_by_total_desc(): void
    {
        $modem = Item::create(['code' => 'WSP-SORT', 'name' => 'Modem Sort Posisi', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WSPSORT1'], 350000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan', 'sort' => 'total', 'dir' => 'desc']));

        $response->assertOk()->assertSee('In Transit', false)->assertSee('aria-sort="descending"', false);
        $summary = $response->viewData('summary');
        // Kabel total 300 > modem 1 → kabel di atas saat total desc.
        $this->assertSame($this->kabel->id, $summary->first()['item']->id);
        $this->assertSame('total', $response->viewData('summarySort'));
    }

    /**
     * Analisa §U5 (keputusan user 2026-10-07) — HANYA SN punya kondisi asli.
     * Quantity & roll: "Tidak dilacak per unit" (tidak punya alur retur yang
     * bisa mengubah kondisinya, beda dari SN).
     */
    #[Test]
    public function kondisi_sn_dijumlah_per_status_sedangkan_quantity_dan_roll_tidak_dilacak(): void
    {
        $modem = Item::create(['code' => 'WSP-COND', 'name' => 'Modem Kondisi Posisi', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WSPC1', 'WSPC2', 'WSPC3'], 350000, $this->owner);
        InventorySerial::where('serial_number', 'WSPC2')->update(['condition' => ItemCondition::USED_GOOD->value]);

        $rows = $this->summary(collect([$this->pusat->id]));

        $modemRow = $rows->firstWhere(fn ($r) => $r['item']->id === $modem->id);
        $this->assertNotNull($modemRow['condition_summary']);
        $newCount = $modemRow['condition_summary']->firstWhere('condition', ItemCondition::NEW);
        $usedCount = $modemRow['condition_summary']->firstWhere('condition', ItemCondition::USED_GOOD);
        $this->assertSame(2, $newCount['count']);
        $this->assertSame(1, $usedCount['count']);

        $kabelRow = $rows->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);
        $this->assertNull($kabelRow['condition_summary']);
    }

    #[Test]
    public function halaman_ringkasan_tampilkan_kondisi_sn_dan_label_tidak_dilacak_untuk_quantity(): void
    {
        $modem = Item::create(['code' => 'WSP-CONDPAGE', 'name' => 'Modem Kondisi Halaman', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WSPCP1'], 350000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan', 'pop_id' => $this->pusat->id]));

        $response->assertOk()
            ->assertSee('Kondisi')
            ->assertSee('Tidak dilacak per unit')
            ->assertSee('Baru: 1', false);
    }

    /**
     * Analisa §U2 — drill-down ke level SN/roll di ringkasan. Reuse modal
     * "Daftar Serial Number"/"Daftar Roll Kabel" yang sama dengan mode per-lot
     * (event `open-serial-modal`/`open-roll-modal`), bukan endpoint baru.
     * Quantity TIDAK dapat tombol — gak punya identitas per-unit buat
     * di-drill-down, berhenti di lot (sudah via `<details>`).
     */
    #[Test]
    public function ringkasan_sn_bisa_drill_down_ke_modal_serial_number(): void
    {
        $modem = Item::create(['code' => 'WSP-DRILL-SN', 'name' => 'Modem Drill SN', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WSPDSN1'], 350000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan', 'pop_id' => $this->pusat->id]));

        $response->assertOk()
            ->assertSee("\$dispatch('open-serial-modal'", false)
            ->assertSee('Lihat daftar SN di gudang ini', false);
    }

    #[Test]
    public function ringkasan_roll_bisa_drill_down_ke_modal_roll_kabel(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $fo = Item::create(['code' => 'WSP-DRILL-ROLL', 'name' => 'Kabel FO Drill', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 1, null, 1500000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan', 'pop_id' => $this->pusat->id]));

        $response->assertOk()
            ->assertSee("\$dispatch('open-roll-modal'", false)
            ->assertSee('Lihat daftar roll di gudang ini', false);
    }

    #[Test]
    public function ringkasan_quantity_tidak_dapat_tombol_drill_down(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan', 'pop_id' => $this->pusat->id, 'search' => $this->kabel->name]));

        $response->assertOk();
        $row = $response->viewData('summary')->firstWhere(fn ($r) => $r['item']->id === $this->kabel->id);
        $this->assertNotNull($row);
        $this->assertSame('quantity', $row['item']->tracking_type->value);
        // "$dispatch('open-serial-modal'" SENDIRI selalu ada di halaman ini
        // (quick-search Alpine di stock/index.blade.php, lepas dari baris
        // ringkasan) — gak bisa dipakai buat assertDontSee apa adanya. Yang
        // diperiksa: id barang quantity ini TIDAK PERNAH dirender literal
        // sebagai `itemId: {id}` oleh baris ringkasan (quick-search pakai
        // `item.itemId` Alpine, bukan angka PHP literal, jadi gak ketiban).
        $response->assertDontSee("itemId: {$this->kabel->id},", false);
    }

    #[Test]
    public function ringkasan_badge_karantina_pakai_design_token(): void
    {
        $modem = Item::create(['code' => 'WSP-QBADGE', 'name' => 'Modem Q Badge', 'item_category_id' => $this->kabel->item_category_id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['WSPQB1', 'WSPQB2'], 350000, $this->owner);
        InventorySerial::where('serial_number', 'WSPQB1')->update(['status' => SerialStatus::QUARANTINE->value]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan']));

        $response->assertOk()->assertSee('badge-error', false);
    }

    #[Test]
    public function ringkasan_sort_parameter_asing_diabaikan(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['view' => 'ringkasan', 'sort' => 'DROP']));

        $response->assertOk();
        $this->assertNull($response->viewData('summarySort'));
    }

    private function summary(Collection $popIds): Collection
    {
        return app(WarehouseStockPositionService::class)->summarize($popIds);
    }

    private function transferKe(Pop $tujuan, int $qty): void
    {
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $tujuan, [['item_id' => $this->kabel->id, 'qty' => $qty]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => $qty], $this->owner);
    }
}
