<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
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
 * ADHOC-54 Fase 8 — UI Gudang (Dashboard, Transfer, Issue). Owner dipakai
 * sebagai actor (wildcard `*`, punya semua permission `warehouse*`).
 */
class WarehouseTransferAndIssueTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'WT-PUSAT', 'pop_code' => 'WTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat WT', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'WT-CABANG', 'pop_code' => 'WTC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang WT', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'WT-KABEL', 'name' => 'Dropcore WT', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 300, 5000, null, $this->owner);
    }

    #[Test]
    public function dashboard_bisa_diakses(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.index'))->assertOk();
    }

    #[Test]
    public function halaman_transfers_create_bisa_dibuka_tanpa_error(): void
    {
        // Render-level guard (2026-09-04) — lihat komentar sama di
        // WarehouseReceiveTest::halaman_create_bisa_dibuka_tanpa_error().
        $this->actingAs($this->owner)->get(route('warehouse.transfers.create'))
            ->assertOk()
            ->assertSee('Stok Siap Kirim');
    }

    #[Test]
    public function halaman_issues_create_bisa_dibuka_tanpa_error(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.issues.create'))
            ->assertOk()
            ->assertSee('Stok di Cabang Ini');
    }

    #[Test]
    public function transfer_full_flow_buat_lalu_terima(): void
    {
        $create = $this->actingAs($this->owner)->post(route('warehouse.transfers.store'), [
            'from_pop_id' => $this->pusat->id,
            'to_pop_id' => $this->cabang->id,
            'lines' => [
                ['item_id' => $this->kabel->id, 'qty' => 100],
            ],
        ]);

        $transfer = InventoryTransfer::firstOrFail();
        $create->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::IN_TRANSIT, $transfer->status);
        $this->assertEquals(200, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->kabel->id)->value('qty'));

        $this->actingAs($this->owner)->get(route('warehouse.transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Konfirmasi Terima');

        $receive = $this->actingAs($this->owner)->post(route('warehouse.transfers.receive', $transfer), [
            'confirmed_quantities' => [$this->kabel->id => 100],
        ]);

        $transfer->refresh();
        $receive->assertRedirect(route('warehouse.transfers.show', $transfer));
        $this->assertSame(TransferStatus::RECEIVED, $transfer->status);
        $this->assertEquals(100, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->kabel->id)->value('qty'));
    }

    #[Test]
    public function issue_full_flow_dari_cabang_ke_teknisi(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        // Pindahkan dulu stok ke cabang (transfer + receive) biar ada yang di-issue.
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $this->kabel->id, 'qty' => 100],
        ], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => 100], $this->owner);

        $store = $this->actingAs($this->owner)->post(route('warehouse.issues.store'), [
            'cabang_pop_id' => $this->cabang->id,
            'technician_id' => $teknisi->id,
            'lines' => [
                ['item_id' => $this->kabel->id, 'qty' => 30],
            ],
        ]);

        $txn = InventoryTransaction::where('type', 'issue')->firstOrFail();
        $store->assertRedirect(route('warehouse.issues.show', $txn->reference_number));

        $this->actingAs($this->owner)->get(route('warehouse.issues.show', $txn->reference_number))
            ->assertOk()
            ->assertSee($teknisi->name);

        $this->assertEquals(70, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->kabel->id)->value('qty'));
    }

    #[Test]
    public function pop_admin_tanpa_scope_tidak_bisa_akses_transfer_create(): void
    {
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id]);

        $this->actingAs($popAdmin)->get(route('warehouse.transfers.create'))->assertForbidden();
    }
}
