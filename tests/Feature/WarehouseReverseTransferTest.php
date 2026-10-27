<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use App\Services\WarehouseTransferActionQueue;
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

class WarehouseReverseTransferTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stok_biasa_dapat_dikirim_cabang_ke_pusat_dan_baru_masuk_saat_dikonfirmasi(): void
    {
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, WarehouseFeatureSeeder::class, RolePermissionSeeder::class, ItemCategorySeeder::class]);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pusat = Pop::create(['code' => 'REV-PUSAT', 'pop_code' => 'RVP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Reverse', 'type' => 'pusat', 'status' => 'active']);
        $cabang = Pop::create(['code' => 'REV-CABANG', 'pop_code' => 'RVC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Reverse', 'type' => 'cabang', 'status' => 'active']);
        $item = Item::create(['code' => 'REV-KABEL', 'name' => 'Kabel Reverse', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $item, 100, 770, $owner);
        $service = app(InventoryTransferService::class);
        $forward = $service->createTransfer($pusat, $cabang, [['item_id' => $item->id, 'qty' => 40]], $owner);
        $forwardLine = $forward->transactions()->whereNotNull('from_pop_id')->firstOrFail();
        $service->receiveTransfer($forward, [], ['_lines' => [$forwardLine->id => 40]], $owner);

        $reverse = $service->createReverseTransfer($cabang, $pusat, [['item_id' => $item->id, 'qty' => 20]], $owner);

        $this->assertTrue(app(WarehouseTransferActionQueue::class)->forUser($owner)->whereKey($reverse->id)->exists());
        $this->assertSame($cabang->id, $reverse->from_pop_id);
        $this->assertSame($pusat->id, $reverse->to_pop_id);
        $this->assertEquals(20, InventoryBalance::where('pop_id', $cabang->id)->where('item_id', $item->id)->value('qty'));
        $this->assertEquals(60, InventoryBalance::where('pop_id', $pusat->id)->where('item_id', $item->id)->value('qty'));
        $reverseLine = $reverse->transactions()->whereNotNull('from_pop_id')->firstOrFail();
        $service->receiveTransfer($reverse, [], ['_lines' => [$reverseLine->id => 20]], $owner);
        $this->assertSame(TransferStatus::RECEIVED, $reverse->fresh()->status);
        $this->assertEquals(80, InventoryBalance::where('pop_id', $pusat->id)->where('item_id', $item->id)->value('qty'));
        $this->actingAs($owner)->get(route('warehouse.transfers.create', ['direction' => 'reverse']))
            ->assertOk()->assertSee('Cabang → Pusat');

        $this->post(route('warehouse.transfers.store'), [
            'direction' => 'reverse',
            'from_pop_id' => $cabang->id,
            'to_pop_id' => $pusat->id,
            'lines' => [['item_id' => $item->id, 'qty' => 5]],
        ])->assertRedirect();

        $this->assertEquals(15, InventoryBalance::where('pop_id', $cabang->id)->where('item_id', $item->id)->value('qty'));

        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $role->id]);
        $popAdmin->pops()->attach($cabang->id);
        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        $this->actingAs($popAdmin)->post(route('warehouse.transfers.store'), [
            'direction' => 'forward',
            'from_pop_id' => $pusat->id,
            'to_pop_id' => $cabang->id,
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertForbidden();
    }
}
