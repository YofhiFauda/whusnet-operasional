<?php

namespace Tests\Feature\Master;

use App\Models\NetworkNodeType;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NetworkNodeTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function admin_can_store_new_network_node_type(): void
    {
        $this->loginAsAdmin();

        $response = $this->post(route('master.network-node-types.store'), [
            'code' => 'repeater',
            'name' => 'Repeater',
            'has_ports' => '1',
            'monitors_capacity' => '1',
            'max_ports' => 8,
            'red_zone_threshold' => 90,
            'is_active' => '1',
            'sort_order' => 7,
        ]);

        $response->assertRedirect(route('master.network-node-types.index'));

        $this->assertDatabaseHas('network_node_types', [
            'code' => 'repeater',
            'name' => 'Repeater',
            'has_ports' => true,
            'monitors_capacity' => true,
            'max_ports' => 8,
            'red_zone_threshold' => 90,
            'is_active' => true,
            'sort_order' => 7,
        ]);
    }

    #[Test]
    public function duplicate_code_on_store_is_rejected(): void
    {
        $this->loginAsAdmin();

        NetworkNodeType::create([
            'code' => 'odc',
            'name' => 'ODC',
            'has_ports' => true,
            'monitors_capacity' => true,
            'max_ports' => 128,
            'red_zone_threshold' => 80,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->post(route('master.network-node-types.store'), [
            'code' => 'odc',
            'name' => 'ODC Duplikat',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertEquals(1, NetworkNodeType::where('code', 'odc')->count());
    }

    #[Test]
    public function flag_fields_round_trip_correctly_when_unchecked(): void
    {
        $this->loginAsAdmin();

        // Checkbox tidak dicentang berarti request gak mengirim field sama
        // sekali — normalisasi via $request->boolean() wajib tetap
        // menghasilkan false, bukan dibiarkan default true dari kolom.
        $this->post(route('master.network-node-types.store'), [
            'code' => 'backbone_test',
            'name' => 'Backbone Test',
            'max_ports' => null,
            'red_zone_threshold' => null,
            'sort_order' => 3,
        ]);

        $type = NetworkNodeType::where('code', 'backbone_test')->first();

        $this->assertNotNull($type);
        $this->assertFalse($type->has_ports);
        $this->assertFalse($type->monitors_capacity);
        $this->assertNull($type->max_ports);
        $this->assertNull($type->red_zone_threshold);
        $this->assertFalse($type->is_active);
        $this->assertSame(3, $type->sort_order);
    }

    #[Test]
    public function admin_can_update_existing_network_node_type(): void
    {
        $this->loginAsAdmin();

        $type = NetworkNodeType::create([
            'code' => 'pon_port_test',
            'name' => 'PON Port Test',
            'has_ports' => false,
            'monitors_capacity' => false,
            'max_ports' => null,
            'red_zone_threshold' => null,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $response = $this->put(route('master.network-node-types.update', $type), [
            'code' => 'pon_port_test',
            'name' => 'PON Port Test Diubah',
            'has_ports' => '1',
            'monitors_capacity' => '1',
            'max_ports' => 64,
            'red_zone_threshold' => 75,
            'is_active' => '1',
            'sort_order' => 6,
        ]);

        $response->assertRedirect(route('master.network-node-types.index'));

        $type->refresh();

        $this->assertSame('PON Port Test Diubah', $type->name);
        $this->assertTrue($type->has_ports);
        $this->assertTrue($type->monitors_capacity);
        $this->assertSame(64, $type->max_ports);
        $this->assertSame(75, $type->red_zone_threshold);
        $this->assertSame(6, $type->sort_order);
    }

    #[Test]
    public function admin_can_destroy_network_node_type_without_dependents(): void
    {
        $this->loginAsAdmin();

        $type = NetworkNodeType::create([
            'code' => 'to_delete',
            'name' => 'Dihapus',
            'has_ports' => false,
            'monitors_capacity' => false,
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $response = $this->delete(route('master.network-node-types.destroy', $type));

        $response->assertRedirect();
        $this->assertDatabaseMissing('network_node_types', ['id' => $type->id]);
    }

    /**
     * Gejala yang dijaga: NetworkNodeTypeSeeder pakai updateOrCreate, jadi
     * tiap db:seed ulang (deploy) mereset ambang Red Zone/max port yang
     * sudah diubah admin lewat halaman ini balik ke nilai seed.
     */
    #[Test]
    public function reseed_does_not_overwrite_admin_edits_to_node_types(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);

        $odp = NetworkNodeType::where('code', 'odp')->firstOrFail();
        $this->assertSame(80, (int) $odp->red_zone_threshold);

        $odp->update(['red_zone_threshold' => 65, 'max_ports' => 48, 'name' => 'ODP Custom Admin']);

        $this->seed(NetworkNodeTypeSeeder::class);

        $odp->refresh();
        $this->assertSame(65, (int) $odp->red_zone_threshold);
        $this->assertSame(48, (int) $odp->max_ports);
        $this->assertSame('ODP Custom Admin', $odp->name);
        $this->assertSame(1, NetworkNodeType::where('code', 'odp')->count());
        $this->assertSame(11, NetworkNodeType::count());
        $this->assertTrue(NetworkNodeType::where('code', 'olt_chassis')->exists());
        $this->assertDatabaseHas('network_node_type_rules', [
            'child_type_id' => $odp->id,
            'parent_type_id' => NetworkNodeType::where('code', 'odc')->value('id'),
        ]);
    }

    #[Test]
    public function admin_can_manage_allowed_parent_types_and_pop_anchors(): void
    {
        $this->loginAsAdmin();

        $parent = NetworkNodeType::create(['code' => 'odc', 'name' => 'ODC', 'has_ports' => true]);
        $child = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);

        $this->put(route('master.network-node-types.update', $child), [
            'code' => 'odp',
            'name' => 'ODP',
            'is_active' => '1',
            'rules_configured' => '1',
            'allowed_parent_type_ids' => [$parent->id],
            'allowed_anchor_pop_types' => ['mini_pop'],
        ])->assertRedirect(route('master.network-node-types.index'));

        $this->assertDatabaseHas('network_node_type_rules', [
            'child_type_id' => $child->id,
            'parent_type_id' => $parent->id,
        ]);
        $this->assertDatabaseHas('network_node_type_rules', [
            'child_type_id' => $child->id,
            'anchor_pop_type' => 'mini_pop',
        ]);

        $this->get(route('master.network-node-types.edit', $child))
            ->assertOk()->assertSee('Induk yang Diizinkan');
    }
}
