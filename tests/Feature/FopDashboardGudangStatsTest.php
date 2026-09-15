<?php

namespace Tests\Feature;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TaskMaterial;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Widget "Pemakaian Alat Gudang (Hari Ini)" di FOP Dashboard — data ditarik
 * dari tabel Gudang/Inventory (ADHOC-54) yang sudah ada, bukan sumber baru.
 * Tiga angka: keluar (ISSUE hari ini), terpakai (task_materials kind=terpakai
 * hari ini), sisa di tangan teknisi (custody aktif, snapshot posisi sekarang).
 */
class FopDashboardGudangStatsTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Pop $otherPop;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->otherPop = Pop::create([
            'code' => 'JTS',
            'pop_code' => 'JTS',
            'registration_prefix' => 'E',
            'cid_prefix' => 'F',
            'name' => 'POP Jetis',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $category = ItemCategory::where('code', 'aksesoris_pasang')->firstOrFail();

        $this->item = Item::create([
            'code' => 'ITM-TEST-1',
            'name' => 'Konektor Fast',
            'type' => $category->code,
            'unit' => 'pcs',
            'item_category_id' => $category->id,
        ]);
    }

    private function loginAsFopWithScope(Pop $pop): User
    {
        $user = User::factory()->create();
        $fopRole = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
        $user->role_id = $fopRole->id;
        $user->save();

        app(EffectiveAccessService::class)->clearCache($user);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop->id,
        ]);

        return $user;
    }

    private function makeFopTask(Pop $pop): FopTask
    {
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        return FopTask::create([
            'task_number' => 'TFOP-GDG-'.$pop->id.'-'.uniqid(),
            'tugas' => 'Pemasangan',
            'category' => TaskType::PEMASANGAN->value,
            'status' => TaskStatus::TERJADWAL->value,
            'task_date' => now(),
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'created_by' => 1,
        ]);
    }

    public function test_gudang_stats_card_shows_correct_totals_for_all_pop_access(): void
    {
        $owner = User::factory()->create();
        $ownerRole = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']);
        $owner->role_id = $ownerRole->id;
        $owner->save();
        app(EffectiveAccessService::class)->clearCache($owner);
        $owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $fopTask = $this->makeFopTask($this->pop);

        InventoryTransaction::create([
            'type' => InventoryTransactionType::ISSUE->value,
            'item_id' => $this->item->id,
            'qty' => 20,
            'from_pop_id' => $this->pop->id,
            'to_technician_id' => $owner->id,
        ]);

        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $fopTask->customer_id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->item->id,
            'item_type' => 'aksesoris_pasang',
            'item_name' => $this->item->name,
            'qty' => 5,
            'unit' => 'pcs',
        ]);

        TechnicianCustody::create([
            'technician_id' => $owner->id,
            'issued_from_pop_id' => $this->pop->id,
            'item_id' => $this->item->id,
            'qty_remaining' => 15,
            'status' => CustodyStatus::ISSUED->value,
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee('Pemakaian Alat Gudang');
        $response->assertSee('20');
        $response->assertSee('5');
        $response->assertSee('15');
    }

    public function test_gudang_stats_excludes_other_pop_when_scope_limited(): void
    {
        $fopUser = $this->loginAsFopWithScope($this->pop);

        $fopTaskOther = $this->makeFopTask($this->otherPop);

        // Semua kejadian ini di POP LAIN — tidak boleh muncul di angka fopUser.
        InventoryTransaction::create([
            'type' => InventoryTransactionType::ISSUE->value,
            'item_id' => $this->item->id,
            'qty' => 99,
            'from_pop_id' => $this->otherPop->id,
            'to_technician_id' => $fopUser->id,
        ]);

        TaskMaterial::create([
            'fop_task_id' => $fopTaskOther->id,
            'customer_id' => $fopTaskOther->customer_id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->item->id,
            'item_type' => 'aksesoris_pasang',
            'item_name' => $this->item->name,
            'qty' => 77,
            'unit' => 'pcs',
        ]);

        TechnicianCustody::create([
            'technician_id' => $fopUser->id,
            'issued_from_pop_id' => $this->otherPop->id,
            'item_id' => $this->item->id,
            'qty_remaining' => 66,
            'status' => CustodyStatus::ISSUED->value,
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $gudangStats = $response->viewData('gudangStats');
        $this->assertSame(0.0, $gudangStats['keluar']);
        $this->assertSame(0.0, $gudangStats['terpakai']);
        $this->assertSame(0.0, $gudangStats['sisa_di_teknisi']);
    }
}
