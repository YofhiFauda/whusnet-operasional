<?php

namespace Tests\Feature;

use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
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
use Tests\TestCase;

/**
 * Analisa UI/UX gudang Fase 7 (§M1/M2) — Traceability dapat filter POP dan
 * pencarian daftar (SN/roll via teknisi, nomor transfer, nama/kode barang).
 * Scope POP tetap jalur otorisasi tunggal: kandidat lintas cabang tidak bocor.
 */
class WarehouseTraceabilitySearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Item $modem;

    private User $teknisiA;

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

        $this->pusat = Pop::create(['code' => 'TRS-PUSAT', 'pop_code' => 'TRSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat TRS', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'TRS-A', 'pop_code' => 'TRSA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang TRS A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'TRS-B', 'pop_code' => 'TRSB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang TRS B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'TRS-MODEM', 'name' => 'Modem TRS', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->teknisiA = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Budi Teknisi TRS']);

        // Pusat terima 2 SN → transfer SN-A ke cabang A lalu issue ke teknisi A;
        // SN-B transfer ke cabang B (di luar scope pop_admin A nanti).
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['TRS-SN-A', 'TRS-SN-B'], 300000, $this->owner);

        $tA = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangA, [['item_id' => $this->modem->id, 'serial_numbers' => ['TRS-SN-A']]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($tA, ['TRS-SN-A'], [], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabangA, $this->teknisiA, [['item_id' => $this->modem->id, 'serial_numbers' => ['TRS-SN-A']]], $this->owner);

        $tB = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabangB, [['item_id' => $this->modem->id, 'serial_numbers' => ['TRS-SN-B']]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($tB, ['TRS-SN-B'], [], $this->owner);
    }

    #[Test]
    public function cari_by_nama_teknisi_mengembalikan_sn_yang_dipegang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['q' => 'Budi Teknisi']));

        $response->assertOk()->assertSee('TRS-SN-A');
        $results = $response->viewData('results');
        $this->assertTrue($results->contains(fn ($r) => $r['serial']?->serial_number === 'TRS-SN-A'));
    }

    #[Test]
    public function cari_by_nama_barang_mengembalikan_kandidat(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['q' => 'Modem TRS']));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->viewData('results')->count());
    }

    #[Test]
    public function cari_by_nomor_transfer_mengembalikan_sn_terkait(): void
    {
        $ref = InventoryTransaction::whereNotNull('serial_id')->whereNotNull('reference_number')->value('reference_number');
        $this->assertNotNull($ref);

        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['q' => $ref]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->viewData('results')->count());
    }

    #[Test]
    public function filter_pop_membatasi_hasil_ke_satu_cabang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['q' => 'Modem TRS', 'pop_id' => $this->cabangB->id]));

        $response->assertOk();
        $results = $response->viewData('results');
        $this->assertTrue($results->contains(fn ($r) => $r['serial']?->serial_number === 'TRS-SN-B'));
        $this->assertFalse($results->contains(fn ($r) => $r['serial']?->serial_number === 'TRS-SN-A'));
    }

    #[Test]
    public function pop_admin_tidak_menemukan_sn_di_luar_scope_lewat_pencarian(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.traceability.index', ['q' => 'Modem TRS']));

        $response->assertOk()->assertDontSee('TRS-SN-B');
        $results = $response->viewData('results');
        $this->assertTrue($results->contains(fn ($r) => $r['serial']?->serial_number === 'TRS-SN-A'));
        $this->assertFalse($results->contains(fn ($r) => $r['serial']?->serial_number === 'TRS-SN-B'));
    }

    /**
     * Analisa §M2 (perbaikan 2026-10-08) — scope POP sekarang diterapkan DI
     * QUERY, bukan ambil `limit(100)` baris mentah lalu difilter scope di PHP
     * baru dipotong N. Bukti BUKAN cuma performa: sebelum fix ini, kalau
     * >100 SN di luar scope kebetulan match kata kunci dan tersortir LEBIH
     * DULU dari SN yang valid, `limit(100)` cuma dapat yang di luar scope
     * semua → `filter()` membuang semuanya → hasil kosong, padahal ada 1 SN
     * valid yang harusnya ketemu. 101 baris di luar scope di sini sengaja
     * disortir SEBELUM SN target (nama diawali huruf lebih kecil dari target).
     */
    #[Test]
    public function sn_valid_tetap_ketemu_walau_101_sn_di_luar_scope_lebih_dulu_tersortir(): void
    {
        $modem = Item::create(['code' => 'TRS-BULK', 'name' => 'Modem Bulk Scope Test', 'item_category_id' => ItemCategory::where('code', 'media_converter')->firstOrFail()->id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);

        $now = now();
        $outOfScopeRows = collect(range(1, 101))->map(fn ($n) => [
            'item_id' => $modem->id,
            'serial_number' => sprintf('BIGSCALE-%03d', $n), // "B..." — tersortir SEBELUM "ZZZ-..." di bawah
            'status' => 'available',
            'current_pop_id' => $this->cabangB->id, // di luar scope pop_admin A
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        InventorySerial::insert($outOfScopeRows);

        InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'ZZZ-BIGSCALE-TARGET', // "Z..." — tersortir PALING AKHIR
            'status' => 'available',
            'current_pop_id' => $this->cabangA->id, // DALAM scope pop_admin A
        ]);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdminA = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $popAdminA->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.traceability.index', ['q' => 'BIGSCALE']));

        $response->assertOk();
        $this->assertTrue(
            $response->viewData('results')->contains(fn ($r) => $r['serial']?->serial_number === 'ZZZ-BIGSCALE-TARGET'),
            'SN valid di scope harus tetap ketemu walau 101 SN di luar scope tersortir lebih dulu'
        );
    }

    #[Test]
    public function lookup_sn_langsung_tetap_jalan_tanpa_mode_cari(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['sn' => 'TRS-SN-A']));

        $response->assertOk()->assertSee('TRS-SN-A');
        $this->assertNotNull($response->viewData('serial'));
        $this->assertCount(0, $response->viewData('results'));
    }
}
