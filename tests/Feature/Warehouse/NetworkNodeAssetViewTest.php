<?php

namespace Tests\Feature\Warehouse;

use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Models\InfrastructureBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
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
 * Task 13: halaman Master Node Jaringan (`network.nodes.index`, Task 7)
 * sekarang juga menampilkan aset Gudang yang nempel di tiap node — SN
 * `DEPLOYED` (`deployedSerials()`) dan saldo infrastruktur
 * (`infrastructureBalances()`) — bukan cuma kapasitas port.
 *
 * Role aktor dipilih `fop` (bukan `helpdesk`) karena cuma role itu yang
 * dipegang `network_nodes.view` di `RolePermissionSeeder` — sama seperti
 * `OdpNetworkManagementTest` (Task 7) memakai `fop` untuk skenario scope
 * yang menembus middleware `permission` sungguhan.
 */
class NetworkNodeAssetViewTest extends TestCase
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

    private function createItem(string $code, string $name): Item
    {
        $category = ItemCategory::create([
            'code' => 'infra_asset_view_cat_'.$code,
            'name' => 'Kategori Uji Aset Node '.$code,
            'default_unit' => 'pcs',
            'equipment_class' => 'pasif',
            'is_system' => false,
            'is_active' => true,
        ]);

        return Item::create([
            'code' => $code,
            'name' => $name,
            'item_category_id' => $category->id,
            'unit' => 'pcs',
        ]);
    }

    /**
     * Actor ber-role $roleCode, scope `selected_pop` cuma ke $pop — sama
     * seperti teknik `AuthorizesNetworkNodeScopeTest` (Task 9).
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

    #[Test]
    public function node_dengan_sn_deployed_dan_saldo_infrastruktur_menampilkan_keduanya_bersebelahan_dengan_port(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $user = $this->makeScopedUser('fop', $cabang);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $node->ports()->create(['number' => 1, 'is_used' => true]);

        $modem = $this->createItem('NNV-MODEM', 'Modem Aset Node');
        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'NNV-SN-0001',
            'status' => SerialStatus::DEPLOYED,
            'current_network_node_id' => $node->id,
        ]);

        $kabel = $this->createItem('NNV-KABEL', 'Kabel Aset Node');
        $balance = InfrastructureBalance::create([
            'item_id' => $kabel->id,
            'network_node_id' => $node->id,
            'lot_no' => 'LOT-01',
            'qty' => 25.5,
        ]);

        $response = $this->actingAs($user)->get(route('network.nodes.index'));

        $response->assertOk();
        $response->assertSee($serial->serial_number);
        $response->assertSee($modem->name);
        $response->assertSee($kabel->name);
        $response->assertSee('Port 1');
        $response->assertSee($balance->lot_no);
    }

    #[Test]
    public function sn_deployed_di_node_cabang_lain_tidak_terlihat_oleh_user_di_luar_scope(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $user = $this->makeScopedUser('fop', $cabangA);

        NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'parent_id' => null]);

        $nodeAsing = NetworkNode::factory()->create(['anchor_pop_id' => $cabangB->id, 'parent_id' => null]);
        $modem = $this->createItem('NNV-ASING', 'Modem Aset Asing');
        $serialAsing = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'NNV-SN-ASING',
            'status' => SerialStatus::DEPLOYED,
            'current_network_node_id' => $nodeAsing->id,
        ]);

        $response = $this->actingAs($user)->get(route('network.nodes.index'));

        $response->assertOk();
        $response->assertDontSee($serialAsing->serial_number);
    }

    #[Test]
    public function node_tanpa_aset_terpasang_tetap_tampil_tanpa_header_aset_terpasang(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $user = $this->makeScopedUser('fop', $cabang);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $node->ports()->create(['number' => 1, 'is_used' => false]);

        $response = $this->actingAs($user)->get(route('network.nodes.index'));

        $response->assertOk();
        $response->assertSee($node->code);
        $response->assertDontSee('Aset Terpasang');
    }
}
