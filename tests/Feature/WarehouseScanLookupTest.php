<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
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
 * Scan Barang (2026-09-07, mode "scan-first") — WarehouseScanController
 * cuma read-only lookup, gak nulis apa pun ke DB. Fokus test: status SN
 * nentuin aksi yang ditawarin, dan POP scope gak bocor (SN di luar
 * jangkauan cuma dikasih tau "di luar jangkauan", bukan detail lokasinya).
 */
class WarehouseScanLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

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

        $this->pusat = Pop::create(['code' => 'SCN-PUSAT', 'pop_code' => 'SCNP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Scan Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'SCN-A', 'pop_code' => 'SCNA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Scan A', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'SCN-B', 'pop_code' => 'SCNB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Scan B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'SCN-MODEM', 'name' => 'Modem Scan Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
    }

    private function makePopAdmin(Pop $cabang): User
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $cabang->id]);

        return $user;
    }

    #[Test]
    public function sn_available_di_pusat_nawarin_aksi_kirim_transfer(): void
    {
        InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SCN-SN-001',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->pusat->id,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-001']));

        $response->assertOk()
            ->assertJson(['found' => true, 'in_scope' => true])
            ->assertJsonFragment(['label' => 'Kirim Transfer ke Cabang']);

        // 2026-09-08 — laporan user: "bisa gak Transfer langsung dari Scan
        // page tanpa pindah halaman?" — jawabannya form "Kirim Cepat" inline
        // di `scan/index.blade.php`, POST langsung ke `warehouse.transfers.store`
        // asli. Field `quick`+`from_pop_id`+`item_id`+`serial_number` WAJIB
        // ada di action-nya, itu yang dipakai JS ngisi form tersebut.
        $action = collect($response->json('actions'))->firstWhere('label', 'Kirim Transfer ke Cabang');
        $this->assertSame('transfer', $action['quick']);
        $this->assertSame($this->pusat->id, $action['from_pop_id']);
        $this->assertSame($this->modem->id, $action['item_id']);
        $this->assertSame('SCN-SN-001', $action['serial_number']);
    }

    #[Test]
    public function sn_available_di_cabang_nawarin_aksi_serah_teknisi(): void
    {
        InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SCN-SN-002',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->cabangA->id,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-002']));

        $response->assertOk()
            ->assertJsonFragment(['label' => 'Serahkan ke Teknisi']);

        // 2026-09-08 — sama alasan "Kirim Cepat" Transfer di atas, versi Issue.
        $action = collect($response->json('actions'))->firstWhere('label', 'Serahkan ke Teknisi');
        $this->assertSame('issue', $action['quick']);
        $this->assertSame($this->cabangA->id, $action['cabang_pop_id']);
        $this->assertSame($this->modem->id, $action['item_id']);
        $this->assertSame('SCN-SN-002', $action['serial_number']);
    }

    /**
     * 2026-09-08 — halaman Scan Barang (`warehouse.scan.index`) sekarang
     * MUAT daftar Cabang & Teknisi (bahan form "Kirim Cepat"), bukan cuma
     * view kosong lagi — pastikan gak nge-throw pas render dan datanya
     * kekirim ke Blade.
     */
    #[Test]
    public function halaman_scan_index_muat_daftar_cabang_dan_teknisi(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi Scan Test']);

        $response = $this->actingAs($this->owner)->get(route('warehouse.scan.index'));

        $response->assertOk()
            ->assertViewHas('cabangPops', fn ($pops) => $pops->contains('id', $this->cabangA->id))
            ->assertViewHas('technicians', fn ($techs) => $techs->contains('name', 'Teknisi Scan Test'));
    }

    #[Test]
    public function sn_issued_ke_teknisi_nawarin_aksi_reassign_dan_lapor_bap(): void
    {
        $technician = User::factory()->create();
        InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SCN-SN-003',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-003']));

        $response->assertOk()
            ->assertJsonFragment(['label' => 'Alihkan Custody'])
            ->assertJsonFragment(['label' => 'Lapor BAP / Rusak']);
    }

    /**
     * 2026-09-07 — laporan user: scan Barang Masuk lalu Transfer, dikonfirmasi,
     * tapi status SN "Dalam Transfer" (TRANSFERRED) terus, dan gak nemu jalan
     * balik ke tempat konfirmasi. Akar masalah: status TRANSFERRED di
     * `resolveActions()` cuma dikasih pesan doang, gak ada link balik ke Bon
     * Transfer (`warehouse.transfers.show`) tempat tombol "Konfirmasi
     * Penerimaan" ada — begitu staf ninggalin halaman redirect PRG abis
     * dispatch, dead-end.
     */
    #[Test]
    public function sn_transferred_nawarin_link_konfirmasi_penerimaan(): void
    {
        app(InventoryReceiveService::class)->receiveSerialized(
            $this->pusat, $this->modem, ['SCN-SN-TRF'], 250000, $this->owner
        );

        app(InventoryTransferService::class)->createTransfer(
            $this->pusat, $this->cabangA, [['item_id' => $this->modem->id, 'serial_numbers' => ['SCN-SN-TRF']]], $this->owner
        );

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-TRF']));

        $response->assertOk()
            ->assertJsonFragment(['label' => 'Konfirmasi Penerimaan Transfer']);

        $actionUrl = collect($response->json('actions'))->firstWhere('label', 'Konfirmasi Penerimaan Transfer')['url'];
        $this->assertStringContainsString('/warehouse/transfers/', $actionUrl);
    }

    #[Test]
    public function sn_belum_tercatat_nawarin_catat_barang_masuk(): void
    {
        $response = $this->actingAs($this->owner)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-GAK-ADA']));

        $response->assertOk()
            ->assertJson(['found' => false])
            ->assertJsonFragment(['label' => 'Catat sebagai Barang Masuk']);

        // 2026-09-07 — link-nya WAJIB bawa SN yang tadi discan (query
        // string `sn`), biar staf gak perlu scan/ketik ulang di halaman
        // Receive (lihat x-init $watch('scanItemId') di receive/create.blade.php).
        $actionUrl = collect($response->json('actions'))->firstWhere('label', 'Catat sebagai Barang Masuk')['url'];
        $this->assertStringContainsString('sn=SCN-SN-GAK-ADA', $actionUrl);
    }

    #[Test]
    public function pop_admin_scan_sn_di_luar_jangkauan_gak_dikasih_detail_lokasi(): void
    {
        InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SCN-SN-004',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->cabangB->id,
        ]);

        $popAdminA = $this->makePopAdmin($this->cabangA);

        $response = $this->actingAs($popAdminA)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-004']));

        $response->assertOk()
            ->assertJson(['found' => true, 'in_scope' => false])
            ->assertJsonMissing(['item_name' => $this->modem->name])
            ->assertJsonPath('actions', []);
    }

    #[Test]
    public function pop_admin_scan_sn_di_dalam_jangkauan_dikasih_aksi(): void
    {
        InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SCN-SN-005',
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $this->cabangA->id,
        ]);

        $popAdminA = $this->makePopAdmin($this->cabangA);

        $response = $this->actingAs($popAdminA)->getJson(route('warehouse.scan.lookup', ['sn' => 'SCN-SN-005']));

        $response->assertOk()
            ->assertJson(['found' => true, 'in_scope' => true])
            ->assertJsonFragment(['label' => 'Serahkan ke Teknisi']);
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.scan.index'))->assertForbidden();
        $this->actingAs($teknisi)->getJson(route('warehouse.scan.lookup', ['sn' => 'X']))->assertForbidden();
    }
}
