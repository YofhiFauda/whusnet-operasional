<?php

namespace Tests\Feature\Network;

use App\Enums\ScopeType;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Task 7 (halaman Registrasi Aset Lama): halaman create tersendiri (BUKAN
 * modal — aturan 3 pola aksi CLAUDE.md: mutasi ledger → halaman create,
 * supaya `withErrors()->withInput()` balik ke create, bukan nutup modal di
 * atas List kosong) yang memanggil `InventoryService::registerExistingInfraSerial()`
 * (Task 6).
 *
 * Permission `network_nodes.register_infra` BELUM digrant ke role manapun
 * di `RolePermissionSeeder` produksi (itu Task 8, dijalankan setelah task
 * ini) — jadi test sukses di sini men-attach permission-nya LANGSUNG ke
 * role `fop` di dalam test (independen dari role matrix produksi), dan test
 * penolakan memakai role `fop` TANPA attach itu (fop cuma punya
 * `network_nodes.view` dari seeder produksi).
 */
class InfraAssetRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Actor ber-role $roleCode, scope `selected_pop` cuma ke $pop — pola yang
     * sama dipakai `AuthorizesNetworkNodeScopeTest`/`NetworkNodeAssetViewTest`.
     */
    private function makeScopedUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function grantRegisterInfra(string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $permission = Permission::firstOrCreate(
            ['code' => 'network_nodes.register_infra'],
            ['name' => 'Registrasi Aset Infrastruktur Lama', 'feature_id' => null, 'action_id' => null, 'module' => 'test', 'description' => 'test']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    private function makeItem(string $ownershipMode = 'infrastructure_asset', string $trackingType = 'serialized'): Item
    {
        $category = ItemCategory::where('code', 'infra_register_view_test')->first()
            ?? ItemCategory::create([
                'code' => 'infra_register_view_test',
                'name' => 'Kategori Uji Registrasi Infra (View)',
                'default_unit' => 'pcs',
            ]);

        return Item::create([
            'code' => 'REG-VIEW-'.strtoupper($trackingType).'-'.strtoupper($ownershipMode),
            'name' => 'Barang Uji Registrasi Infra View',
            'unit' => 'pcs',
            'item_category_id' => $category->id,
            'tracking_type' => $trackingType,
            'ownership_mode' => $ownershipMode,
        ]);
    }

    #[Test]
    public function test_user_tanpa_permission_register_infra_ditolak(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        // fop punya network_nodes.view dari RolePermissionSeeder produksi,
        // tapi BELUM punya network_nodes.register_infra (Task 8 belum jalan).
        $user = $this->makeScopedUser('fop', $cabang);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $item = $this->makeItem();

        $response = $this->actingAs($user)->post(route('network.nodes.register-asset.store', $node), [
            'item_id' => $item->id,
            'serial_number' => 'SN-LEGACY-DITOLAK',
            'reason' => 'Opname awal',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, InventorySerial::count());
    }

    #[Test]
    public function test_registrasi_sukses_redirect_ke_detail_node_dengan_sn_baru(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->grantRegisterInfra('fop');
        $user = $this->makeScopedUser('fop', $cabang);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $item = $this->makeItem();

        $response = $this->actingAs($user)->post(route('network.nodes.register-asset.store', $node), [
            'item_id' => $item->id,
            'serial_number' => 'SN-LEGACY-SUKSES',
            'reason' => 'Opname awal, chassis sudah berdiri sejak 2023',
        ]);

        $response->assertRedirect(route('network.nodes.show', $node));
        $response->assertSessionHas('success');

        $show = $this->actingAs($user)->get(route('network.nodes.show', $node));
        $show->assertOk();
        $show->assertSee('SN-LEGACY-SUKSES');
    }

    #[Test]
    public function test_registrasi_gagal_validasi_tampil_di_halaman_create_bukan_list(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->grantRegisterInfra('fop');
        $user = $this->makeScopedUser('fop', $cabang);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $item = $this->makeItem();

        $response = $this->actingAs($user)
            ->from(route('network.nodes.register-asset.create', $node))
            ->post(route('network.nodes.register-asset.store', $node), [
                'item_id' => $item->id,
                'serial_number' => 'SN-LEGACY-GAGAL',
                // 'reason' sengaja tidak diisi.
            ]);

        $response->assertRedirect(route('network.nodes.register-asset.create', $node));
        $response->assertSessionHasErrors('reason');
        $this->assertSame(0, InventorySerial::count());
    }
}
