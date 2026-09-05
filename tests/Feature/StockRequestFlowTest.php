<?php

namespace Tests\Feature;

use App\Enums\StockRequestStatus;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\StockRequestService;
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
 * Permintaan Stok Cabang→Pusat (2026-09-03) — jawaban gap "cabang habis
 * stok, Pusat gak sadar". Cabang inisiatif ngirim sinyal eksplisit, TAMPIL
 * di antrean Pusat (proaktif) — bukan Pusat nunggu notice sendiri.
 */
class StockRequestFlowTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'SRQ-PUSAT', 'pop_code' => 'SRQP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat SRQ Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangA = Pop::create(['code' => 'SRQ-A', 'pop_code' => 'SRQA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang SRQ A (Jetis)', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangB = Pop::create(['code' => 'SRQ-B', 'pop_code' => 'SRQB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang SRQ B', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'SRQ-KABEL', 'name' => 'Kabel SRQ Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
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
    public function pop_admin_cabang_ajukan_permintaan_stok(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);

        $response = $this->actingAs($popAdminA)->post(route('warehouse.stock-requests.store'), [
            'cabang_pop_id' => $this->cabangA->id,
            'notes' => 'Stok kabel di Jetis tinggal 5m, kebutuhan minggu ini 100m',
            'lines' => [
                ['item_id' => $this->kabel->id, 'qty_requested' => 100],
            ],
        ]);

        $stockRequest = StockRequest::firstOrFail();
        $response->assertRedirect(route('warehouse.stock-requests.show', $stockRequest));

        $this->assertStringStartsWith('REQ-', $stockRequest->reference_number);
        $this->assertEquals($this->cabangA->id, $stockRequest->cabang_pop_id);
        $this->assertEquals(StockRequestStatus::PENDING, $stockRequest->status);
        $this->assertEquals($popAdminA->id, $stockRequest->requested_by);
        $this->assertEquals(100, (float) $stockRequest->items->first()->qty_requested);
    }

    #[Test]
    public function pusat_lihat_permintaan_pending_lintas_cabang_tanpa_diminta(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);

        $popAdminB = $this->makePopAdmin($this->cabangB);
        app(StockRequestService::class)->create($this->cabangB, [['item_id' => $this->kabel->id, 'qty_requested' => 30]], $popAdminB);

        // Owner (representasi admin Pusat, hasAllPopAccess) buka antrean —
        // dua-duanya kelihatan TANPA perlu dikasih tau manual, ini inti
        // fix-nya: sinyal aktif dari cabang, bukan Pusat notice sendiri.
        $response = $this->actingAs($this->owner)->get(route('warehouse.stock-requests.index'));

        $response->assertOk()
            ->assertSee('Cabang SRQ A (Jetis)')
            ->assertSee('Cabang SRQ B');
    }

    #[Test]
    public function pop_admin_cabang_a_gak_lihat_permintaan_cabang_b(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);

        $popAdminB = $this->makePopAdmin($this->cabangB);
        app(StockRequestService::class)->create($this->cabangB, [['item_id' => $this->kabel->id, 'qty_requested' => 30]], $popAdminB);

        $response = $this->actingAs($popAdminA)->get(route('warehouse.stock-requests.index'));

        $response->assertOk()
            ->assertSee('Cabang SRQ A (Jetis)')
            ->assertDontSee('Cabang SRQ B');
    }

    #[Test]
    public function admin_pusat_fulfill_permintaan(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        $stockRequest = app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);

        $response = $this->actingAs($this->owner)->post(route('warehouse.stock-requests.fulfill', $stockRequest), [
            'notes' => 'Transfer TRF-xxx udah dikirim',
        ]);

        $response->assertRedirect(route('warehouse.stock-requests.show', $stockRequest));
        $stockRequest->refresh();
        $this->assertEquals(StockRequestStatus::FULFILLED, $stockRequest->status);
        $this->assertEquals($this->owner->id, $stockRequest->decided_by);
    }

    #[Test]
    public function admin_pusat_tolak_permintaan_wajib_isi_alasan(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        $stockRequest = app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);

        $noReason = $this->actingAs($this->owner)->post(route('warehouse.stock-requests.reject', $stockRequest), []);
        $noReason->assertSessionHasErrors('reason');

        $response = $this->actingAs($this->owner)->post(route('warehouse.stock-requests.reject', $stockRequest), [
            'reason' => 'Stok Pusat juga lagi kosong, tunggu PO baru',
        ]);

        $response->assertRedirect(route('warehouse.stock-requests.show', $stockRequest));
        $stockRequest->refresh();
        $this->assertEquals(StockRequestStatus::REJECTED, $stockRequest->status);
        $this->assertEquals('Stok Pusat juga lagi kosong, tunggu PO baru', $stockRequest->decision_notes);
    }

    #[Test]
    public function pengaju_sendiri_bisa_batalkan_permintaannya(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        $stockRequest = app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);

        $response = $this->actingAs($popAdminA)->post(route('warehouse.stock-requests.cancel', $stockRequest));

        $response->assertRedirect(route('warehouse.stock-requests.show', $stockRequest));
        $stockRequest->refresh();
        $this->assertEquals(StockRequestStatus::CANCELLED, $stockRequest->status);
    }

    #[Test]
    public function bukan_pengaju_gak_bisa_batalkan_punya_orang_lain(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        $stockRequest = app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);

        // Teknisi lain di cabang yang sama TIDAK punya permission
        // warehouse_stock_request.cancel sama sekali — pola pengujian yang
        // benar buat "role gak berwenang" beda dari "role berwenang tapi
        // bukan pemilik" (dites terpisah gak perlu, permission-nya emang
        // gak dikasih ke role selain pop_admin/admin/owner).
        $popAdminA2 = $this->makePopAdmin($this->cabangA); // admin cabang A lain, py permission cancel tapi BUKAN pengaju

        $response = $this->actingAs($popAdminA2)->post(route('warehouse.stock-requests.cancel', $stockRequest));

        $response->assertForbidden();
        $stockRequest->refresh();
        $this->assertEquals(StockRequestStatus::PENDING, $stockRequest->status);
    }

    #[Test]
    public function permintaan_yang_udah_diputuskan_gak_bisa_diproses_ulang(): void
    {
        $popAdminA = $this->makePopAdmin($this->cabangA);
        $stockRequest = app(StockRequestService::class)->create($this->cabangA, [['item_id' => $this->kabel->id, 'qty_requested' => 50]], $popAdminA);
        app(StockRequestService::class)->fulfill($stockRequest, $this->owner);

        $response = $this->actingAs($this->owner)->post(route('warehouse.stock-requests.fulfill', $stockRequest));

        $response->assertSessionHas('error');
        $stockRequest->refresh();
        $this->assertEquals(StockRequestStatus::FULFILLED, $stockRequest->status);
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.stock-requests.index'))->assertForbidden();
        $this->actingAs($teknisi)->get(route('warehouse.stock-requests.create'))->assertForbidden();
    }
}
