<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
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
 * Halaman "Konfirmasi Barang Transfer" (warehouse.transfers.pending) — daftar
 * ringkas semua transfer in_transit, dipisah dua: yang bisa dikonfirmasi di
 * scope user ini (actionable), dan yang cuma dikirim dari sini tapi
 * konfirmasinya di cabang lain (awaitingOtherSide). Read-only, gak nulis
 * apa pun — POP scope wajib sama seperti Traceability (out-of-scope = gak
 * kelihatan di kedua daftar, bukan 403).
 */
class WarehouseTransferPendingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

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

        $this->pusat = Pop::create(['code' => 'TP-PUSAT', 'pop_code' => 'TPP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat Pending Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'TP-CABANG', 'pop_code' => 'TPC', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Cabang Pending Test', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'TP-KABEL', 'name' => 'Dropcore Pending Test', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, $this->owner);
    }

    private function dispatchTransfer(): InventoryTransfer
    {
        return app(InventoryTransferService::class)->createTransfer(
            $this->pusat,
            $this->cabang,
            [['item_id' => $this->kabel->id, 'qty' => 50]],
            $this->owner,
        );
    }

    #[Test]
    public function halaman_pending_menampilkan_transfer_in_transit_sebagai_actionable(): void
    {
        $transfer = $this->dispatchTransfer();

        $this->actingAs($this->owner)->get(route('warehouse.transfers.pending'))
            ->assertOk()
            ->assertSee('Perlu Dikonfirmasi Di Sini')
            ->assertSee($transfer->reference_number)
            ->assertSee('Tinjau &amp; Konfirmasi', false);
    }

    #[Test]
    public function pop_admin_cabang_tujuan_melihat_transfer_sebagai_actionable(): void
    {
        $transfer = $this->dispatchTransfer();

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdmin->pops()->attach($this->cabang->id);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->cabang->id]);

        $this->actingAs($popAdmin)->get(route('warehouse.transfers.pending'))
            ->assertOk()
            ->assertSee($transfer->reference_number)
            ->assertSee('Tinjau &amp; Konfirmasi', false);
    }

    #[Test]
    public function pop_admin_cabang_pengirim_melihat_transfer_sebagai_menunggu_cabang_lain(): void
    {
        $transfer = $this->dispatchTransfer();

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdmin->pops()->attach($this->pusat->id);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pusat->id]);

        $response = $this->actingAs($popAdmin)->get(route('warehouse.transfers.pending'))
            ->assertOk()
            ->assertSee('Terkirim, Menunggu Cabang Tujuan')
            ->assertSee($transfer->reference_number)
            ->assertSee('Dalam Perjalanan');

        // Transfer ini gak boleh nongol di daftar actionable pop_admin pusat —
        // dia gak punya scope ke cabang tujuan buat konfirmasi.
        $response->assertDontSee('Tinjau &amp; Konfirmasi', false);
    }

    #[Test]
    public function pop_admin_di_luar_scope_sama_sekali_tidak_melihat_transfer(): void
    {
        $transfer = $this->dispatchTransfer();

        $cabangLain = Pop::create(['code' => 'TP-LAIN', 'pop_code' => 'TPL', 'registration_prefix' => 'C', 'cid_prefix' => 'F', 'name' => 'Cabang Lain Pending Test', 'type' => 'cabang', 'status' => 'active']);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);
        $popAdmin->pops()->attach($cabangLain->id);

        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangLain->id]);

        $this->actingAs($popAdmin)->get(route('warehouse.transfers.pending'))
            ->assertOk()
            ->assertDontSee($transfer->reference_number);
    }

    #[Test]
    public function user_tanpa_permission_warehouse_transfer_view_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.transfers.pending'))
            ->assertForbidden();
    }
}
