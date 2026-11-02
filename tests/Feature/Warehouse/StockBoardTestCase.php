<?php

namespace Tests\Feature\Warehouse;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dasar tes papan Kelola Stok: seeding RBAC gudang + tiga gudang + helper
 * item dan user ber-scope, supaya tiap tes papan tidak mengulang setUp panjang.
 */
abstract class StockBoardTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Pop $pusat;

    protected Pop $cabangA;

    protected Pop $cabangB;

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

        $this->pusat = $this->buatPop('SB-PUSAT', 'Pusat Papan', 'pusat');
        $this->cabangA = $this->buatPop('SB-A', 'Cabang Papan A', 'cabang');
        $this->cabangB = $this->buatPop('SB-B', 'Cabang Papan B', 'cabang');
    }

    protected function buatPop(string $code, string $name, string $type): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => substr(str_replace('-', '', $code), 0, 8),
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    protected function buatItem(string $code, string $name, string $tracking, string $unit = 'pcs', ?float $meterPerRoll = null, ?string $categoryCode = 'kabel_dropcore'): Item
    {
        $category = $categoryCode === null ? null : ItemCategory::where('code', $categoryCode)->firstOrFail();

        return Item::create([
            'code' => $code,
            'name' => $name,
            'item_category_id' => $category?->id,
            'unit' => $unit,
            'tracking_type' => $tracking,
            'meter_per_roll' => $meterPerRoll,
        ]);
    }

    /**
     * @param  array<int, int>  $popIds
     */
    protected function userDenganScope(array $popIds): User
    {
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
        foreach ($popIds as $popId) {
            $scope->targets()->create(['pop_id' => $popId]);
        }

        return $user;
    }
}
