<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\WarehousePopPic;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryIssueService;
use App\Services\InventoryReassignService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use App\Services\StockRequestService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-120 Pilar 4 — Pembagian tugas PIC Gudang vs POP Admin. Lihat
 * docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md §7, §8 Kelompok
 * H/I. Pilar 1-3 (siapa teknisi, role pic_gudang) ada di TeknisiPicGudangCabangTest.
 */
class PembagianTugasPicGudangPopAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->pusat = Pop::create(['code' => 'PTG-PUSAT', 'pop_code' => 'PTGP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat PTG', 'type' => 'pusat', 'status' => 'active']);
    }

    private function createCabang(string $code, string $name): Pop
    {
        return Pop::create(['code' => $code, 'pop_code' => strtoupper($code), 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => $name, 'type' => 'cabang', 'status' => 'active']);
    }

    private function makeScopedUser(string $roleCode, string $scopeType, ?Pop $pop = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType === 'all_pop' ? ScopeType::ALL_POP : ScopeType::SELECTED_POP,
        ]);

        if ($scopeType !== 'all_pop' && $pop) {
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        }

        return $user;
    }

    private function makePicOf(Pop $pop, string $scopeType = 'selected_pop'): User
    {
        $pic = $this->makeScopedUser('pic_gudang', $scopeType, $scopeType === 'all_pop' ? null : $pop);
        WarehousePopPic::create(['pop_id' => $pop->id, 'user_id' => $pic->id]);

        return $pic;
    }

    private function seedStockAt(Pop $pop, Item $item, float $qty): void
    {
        InventoryBalance::create(['pop_id' => $pop->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => $qty]);
    }

    private function kabelItem(string $code): Item
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        return Item::create(['code' => $code, 'name' => $code, 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
    }

    // ---------------------------------------------------------------
    // H2 — InventoryIssueService
    // ---------------------------------------------------------------

    #[Test]
    public function issuing_stock_to_self_is_always_rejected(): void
    {
        $cabang = $this->createCabang('PTG-01', 'Cabang PTG 1');
        $popAdmin = $this->makeScopedUser('pop_admin', 'selected_pop', $cabang);
        $item = $this->kabelItem('PTG-K1');
        $this->seedStockAt($cabang, $item, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diri sendiri');

        app(InventoryIssueService::class)->issue($cabang, $popAdmin, [['item_id' => $item->id, 'qty' => 10]], $popAdmin);
    }

    #[Test]
    public function pop_admin_at_branch_with_active_pic_can_only_issue_to_the_pic(): void
    {
        $cabang = $this->createCabang('PTG-02', 'Cabang PTG 2');
        $popAdmin = $this->makeScopedUser('pop_admin', 'selected_pop', $cabang);
        $pic = $this->makePicOf($cabang);
        $otherTeknisi = $this->makeScopedUser('teknisi', 'selected_pop', $cabang);
        $item = $this->kabelItem('PTG-K2');
        $this->seedStockAt($cabang, $item, 100);

        $service = app(InventoryIssueService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->issue($cabang, $otherTeknisi, [['item_id' => $item->id, 'qty' => 5]], $popAdmin);
    }

    #[Test]
    public function pop_admin_at_branch_with_active_pic_can_issue_to_that_pic(): void
    {
        $cabang = $this->createCabang('PTG-03', 'Cabang PTG 3');
        $popAdmin = $this->makeScopedUser('pop_admin', 'selected_pop', $cabang);
        $pic = $this->makePicOf($cabang);
        $item = $this->kabelItem('PTG-K3');
        $this->seedStockAt($cabang, $item, 100);

        $transactions = app(InventoryIssueService::class)->issue($cabang, $pic, [['item_id' => $item->id, 'qty' => 5]], $popAdmin);

        $this->assertNotEmpty($transactions);
    }

    #[Test]
    public function pic_gudang_with_all_pop_scope_cannot_issue_from_branch_outside_its_assignment(): void
    {
        $jetis = $this->createCabang('PTG-04A', 'Jetis PTG 4A');
        $siman = $this->createCabang('PTG-04B', 'Siman PTG 4B');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $teknisiSiman = $this->makeScopedUser('teknisi', 'selected_pop', $siman);
        $item = $this->kabelItem('PTG-K4');
        $this->seedStockAt($siman, $item, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan gudang yang Anda kelola');

        app(InventoryIssueService::class)->issue($siman, $teknisiSiman, [['item_id' => $item->id, 'qty' => 5]], $pic);
    }

    #[Test]
    public function pic_gudang_can_issue_from_its_own_assigned_branch_even_with_all_pop_scope(): void
    {
        $jetis = $this->createCabang('PTG-05', 'Jetis PTG 5');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $teknisi = $this->makeScopedUser('teknisi', 'selected_pop', $jetis);
        $item = $this->kabelItem('PTG-K5');
        $this->seedStockAt($jetis, $item, 100);

        $transactions = app(InventoryIssueService::class)->issue($jetis, $teknisi, [['item_id' => $item->id, 'qty' => 5]], $pic);

        $this->assertNotEmpty($transactions);
    }

    // ---------------------------------------------------------------
    // H4 — InventoryReassignService
    // ---------------------------------------------------------------

    #[Test]
    public function reassigning_custody_to_self_is_rejected(): void
    {
        $cabang = $this->createCabang('PTG-06', 'Cabang PTG 6');
        $teknisi = $this->makeScopedUser('teknisi', 'selected_pop', $cabang);
        $item = $this->kabelItem('PTG-K6');
        $custody = TechnicianCustody::create([
            'technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
            'item_id' => $item->id,
            'lot_no' => '',
            'qty_remaining' => 50,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diri sendiri');

        app(InventoryReassignService::class)->transferCustodyToTechnician($custody, $teknisi, 'rotasi', $teknisi);
    }

    #[Test]
    public function pic_gudang_cannot_reassign_custody_originating_outside_its_assigned_branch(): void
    {
        $jetis = $this->createCabang('PTG-07A', 'Jetis PTG 7A');
        $siman = $this->createCabang('PTG-07B', 'Siman PTG 7B');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $teknisiSiman = $this->makeScopedUser('teknisi', 'selected_pop', $siman);
        $newTeknisi = $this->makeScopedUser('teknisi', 'selected_pop', $siman);
        $item = $this->kabelItem('PTG-K7');
        $custody = TechnicianCustody::create([
            'technician_id' => $teknisiSiman->id,
            'issued_from_pop_id' => $siman->id,
            'item_id' => $item->id,
            'lot_no' => '',
            'qty_remaining' => 50,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan gudang yang Anda kelola');

        app(InventoryReassignService::class)->transferCustodyToTechnician($custody, $newTeknisi, 'rotasi', $pic);
    }

    // ---------------------------------------------------------------
    // H5 — WarehouseTransferController::receive()
    // ---------------------------------------------------------------

    #[Test]
    public function pop_admin_cannot_confirm_receive_at_branch_with_active_pic(): void
    {
        $cabang = $this->createCabang('PTG-08', 'Cabang PTG 8');
        $popAdmin = $this->makeScopedUser('pop_admin', 'selected_pop', $cabang);
        $this->makePicOf($cabang);
        $item = $this->kabelItem('PTG-K8');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 1000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $cabang, [['item_id' => $item->id, 'qty' => 50]], $this->owner);

        $this->actingAs($popAdmin)->post(route('warehouse.transfers.receive', $transfer))
            ->assertForbidden();
    }

    #[Test]
    public function pic_gudang_can_confirm_receive_at_its_own_branch(): void
    {
        $cabang = $this->createCabang('PTG-09', 'Cabang PTG 9');
        $pic = $this->makePicOf($cabang);
        $item = $this->kabelItem('PTG-K9');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 1000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $cabang, [['item_id' => $item->id, 'qty' => 50]], $this->owner);

        $this->actingAs($pic)->post(route('warehouse.transfers.receive', $transfer))
            ->assertRedirect(route('warehouse.transfers.show', $transfer));

        $this->assertSame(TransferStatus::RECEIVED, $transfer->fresh()->status);
    }

    #[Test]
    public function pop_admin_can_still_confirm_receive_at_branch_without_any_pic(): void
    {
        // Regresi masa transisi (§7.2) — cabang BELUM punya PIC, perilaku lama tetap.
        $cabang = $this->createCabang('PTG-10', 'Cabang PTG 10');
        $popAdmin = $this->makeScopedUser('pop_admin', 'selected_pop', $cabang);
        $item = $this->kabelItem('PTG-K10');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 1000, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $cabang, [['item_id' => $item->id, 'qty' => 50]], $this->owner);

        $this->actingAs($popAdmin)->post(route('warehouse.transfers.receive', $transfer))
            ->assertRedirect(route('warehouse.transfers.show', $transfer));
    }

    // ---------------------------------------------------------------
    // H7 — StockRequestService
    // ---------------------------------------------------------------

    #[Test]
    public function pic_gudang_cannot_request_stock_on_behalf_of_a_branch_outside_its_assignment(): void
    {
        $jetis = $this->createCabang('PTG-11A', 'Jetis PTG 11A');
        $siman = $this->createCabang('PTG-11B', 'Siman PTG 11B');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $item = $this->kabelItem('PTG-K11');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan gudang yang Anda kelola');

        app(StockRequestService::class)->create($siman, [['item_id' => $item->id, 'qty_requested' => 10]], $pic);
    }

    // ---------------------------------------------------------------
    // H8 — InventoryAdjustmentService (level saldo POP, bukan custody sendiri)
    // ---------------------------------------------------------------

    #[Test]
    public function pic_gudang_cannot_adjust_pop_balance_outside_its_assigned_branch(): void
    {
        $jetis = $this->createCabang('PTG-12A', 'Jetis PTG 12A');
        $siman = $this->createCabang('PTG-12B', 'Siman PTG 12B');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $item = $this->kabelItem('PTG-K12');
        $this->seedStockAt($siman, $item, 50);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan gudang yang Anda kelola');

        app(InventoryAdjustmentService::class)->adjustPopBalance($siman, $item->id, -5, 'damaged', $pic);
    }

    #[Test]
    public function pic_gudang_can_report_lost_or_damaged_on_its_own_custody_regardless_of_branch_assignment(): void
    {
        // §9 Kelompok H8 — adjustCustody TIDAK terikat cabang mana pun.
        $jetis = $this->createCabang('PTG-13', 'Jetis PTG 13');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $item = $this->kabelItem('PTG-K13');
        $custody = TechnicianCustody::create([
            'technician_id' => $pic->id,
            'issued_from_pop_id' => $jetis->id,
            'item_id' => $item->id,
            'lot_no' => '',
            'qty_remaining' => 20,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $transaction = app(InventoryAdjustmentService::class)->adjustCustody($custody, -5, 'damaged', $pic, null, 'bukti.jpg');

        $this->assertSame($pic->id, $transaction->from_technician_id);
        $this->assertSame($pic->id, $transaction->created_by);
    }

    // ---------------------------------------------------------------
    // Kelompok I — penunjukan PIC & validasi 2 arah
    // ---------------------------------------------------------------

    #[Test]
    public function assigning_pic_to_a_branch_outside_the_users_narrow_scope_is_rejected(): void
    {
        $jetis = $this->createCabang('PTG-14A', 'Jetis PTG 14A');
        $siman = $this->createCabang('PTG-14B', 'Siman PTG 14B');
        $candidate = $this->makeScopedUser('pic_gudang', 'selected_pop', $jetis);
        $admin = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        $this->actingAs($admin)->post(route('warehouse.pic-gudang.store'), [
            'pop_id' => $siman->id,
            'user_id' => $candidate->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('warehouse_pop_pics', ['pop_id' => $siman->id, 'user_id' => $candidate->id]);
    }

    #[Test]
    public function assigning_pic_within_an_all_pop_scope_is_allowed(): void
    {
        $jetis = $this->createCabang('PTG-15', 'Jetis PTG 15');
        $candidate = $this->makeScopedUser('pic_gudang', 'all_pop');
        $admin = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        $this->actingAs($admin)->post(route('warehouse.pic-gudang.store'), [
            'pop_id' => $jetis->id,
            'user_id' => $candidate->id,
        ])->assertRedirect(route('warehouse.pic-gudang.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('warehouse_pop_pics', ['pop_id' => $jetis->id, 'user_id' => $candidate->id]);
    }

    #[Test]
    public function narrowing_scope_away_from_an_already_assigned_pic_branch_is_rejected(): void
    {
        $jetis = $this->createCabang('PTG-16A', 'Jetis PTG 16A');
        $siman = $this->createCabang('PTG-16B', 'Siman PTG 16B');
        $pic = $this->makePicOf($jetis, scopeType: 'all_pop');
        $admin = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        $response = $this->actingAs($admin)->put(route('users.update', $pic), [
            'name' => $pic->name,
            'email' => $pic->email,
            'status' => 'active',
            'role_id' => $pic->role_id,
            'scope_type' => 'selected_pop',
            'pop_ids' => [$siman->id], // sengaja TIDAK termasuk Jetis
        ]);

        $response->assertSessionHasErrors('scope_type');
        $this->assertTrue($pic->roleScopes()->first()->scope_type === ScopeType::ALL_POP);
    }
}
