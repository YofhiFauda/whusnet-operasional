<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Bug (laporan user 2026-11-20): roll kabel di tangan teknisi tidak muncul di
 * "Stok Saya" (/tasks-saya). Root cause: `TaskController::indexOwn()` cuma
 * query `TechnicianCustody` (qty/batch) & `InventorySerial` (serialized) —
 * `InventoryRoll` (tracking_type ROLL) tidak pernah diambil sama sekali.
 */
class TaskOwnStokSayaShowsRollTest extends TestCase
{
    use RefreshDatabase;

    private function seedRbac(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    public function test_roll_kabel_di_tangan_teknisi_muncul_di_stok_saya(): void
    {
        $this->seedRbac();

        $pop = Pop::create([
            'code' => 'SMN7', 'pop_code' => 'SMN7', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Sooko 7', 'type' => 'cabang', 'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $technician = User::factory()->create(['role_id' => $teknisiRole->id]);

        $category = ItemCategory::create([
            'code' => 'stok_saya_roll_cat', 'name' => 'Kategori Roll Stok Saya', 'default_unit' => 'meter',
            'equipment_class' => 'pasif', 'is_system' => false, 'is_active' => true,
        ]);
        $item = Item::create([
            'code' => 'STOK-SAYA-ROLL', 'name' => 'Kabel Fiber Stok Saya', 'unit' => 'meter',
            'item_category_id' => $category->id, 'tracking_type' => 'roll',
        ]);

        InventoryRoll::create([
            'item_id' => $item->id,
            'roll_code' => 'ROLL-STOK-SAYA-0001',
            'length_total' => 100,
            'length_remaining' => 80,
            'status' => RollStatus::ISSUED,
            'issued_from_pop_id' => $pop->id,
            'current_technician_id' => $technician->id,
        ]);

        $response = $this->actingAs($technician)->get(route('tasks.own'));

        $response->assertOk();
        $response->assertSee('ROLL-STOK-SAYA-0001');
    }
}
