<?php

namespace Tests\Feature;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OltSlotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_hanya_chassis_olt_memiliki_slot_dan_kartu_mengisi_sebagai_anak(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $branch->id]);
        $chassisType = NetworkNodeType::firstOrCreate(['code' => 'olt_chassis'], ['name' => 'Chassis OLT', 'has_ports' => false]);
        $cardType = NetworkNodeType::firstOrCreate(['code' => 'olt_card'], ['name' => 'Kartu OLT', 'has_ports' => true]);
        $chassis = NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id, 'node_type_id' => $chassisType->id,
            'code' => 'OLT-CHASSIS-A', 'name' => 'Chassis A',
        ]);
        $card = NetworkNode::factory()->create([
            'parent_id' => $chassis->id, 'anchor_pop_id' => null, 'node_type_id' => $cardType->id,
            'code' => 'CARD-A', 'name' => 'Kartu A',
        ]);
        $bukanChassis = NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id, 'node_type_id' => $cardType->id,
            'code' => 'BUKAN-CHASSIS', 'name' => 'Bukan Chassis',
        ]);

        $this->actingAs($owner)->post(route('network.nodes.olt-slots.store', $bukanChassis), ['slot_count' => 2])
            ->assertSessionHasErrors('slot_count');

        $this->actingAs($owner)->post(route('network.nodes.olt-slots.store', $chassis), ['slot_count' => 2])
            ->assertRedirect(route('network.nodes.show', $chassis));
        $this->assertSame([1, 2], $chassis->oltSlots()->orderBy('number')->pluck('number')->all());

        $slot = OltSlot::where('chassis_node_id', $chassis->id)->where('number', 1)->firstOrFail();
        $this->actingAs($owner)->post(route('network.nodes.olt-slots.card', [$chassis, $slot]), ['network_node_id' => $card->id])
            ->assertRedirect(route('network.nodes.show', $chassis));
        $this->assertSame($card->id, $slot->fresh()->card_node_id);
    }

    public function test_kartu_yang_bukan_anak_chassis_ini_ditolak(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $branch->id]);
        $chassisType = NetworkNodeType::firstOrCreate(['code' => 'olt_chassis'], ['name' => 'Chassis OLT', 'has_ports' => false]);
        $cardType = NetworkNodeType::firstOrCreate(['code' => 'olt_card'], ['name' => 'Kartu OLT', 'has_ports' => true]);
        $chassisA = NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id, 'node_type_id' => $chassisType->id,
            'code' => 'OLT-CHASSIS-A',
        ]);
        $chassisB = NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id, 'node_type_id' => $chassisType->id,
            'code' => 'OLT-CHASSIS-B',
        ]);
        $cardOfB = NetworkNode::factory()->create([
            'parent_id' => $chassisB->id, 'anchor_pop_id' => null, 'node_type_id' => $cardType->id,
            'code' => 'CARD-B',
        ]);
        $chassisA->oltSlots()->create(['number' => 1]);
        $slot = OltSlot::where('chassis_node_id', $chassisA->id)->firstOrFail();

        $this->actingAs($owner)->post(route('network.nodes.olt-slots.card', [$chassisA, $slot]), ['network_node_id' => $cardOfB->id])
            ->assertSessionHasErrors('network_node_id');
        $this->assertNull($slot->fresh()->card_node_id);
    }

    public function test_chassis_boleh_anchor_cabang_langsung_tanpa_mini_pop(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $chassisType = NetworkNodeType::firstOrCreate(['code' => 'olt_chassis'], ['name' => 'Chassis OLT', 'has_ports' => false]);
        $chassis = NetworkNode::factory()->create([
            'anchor_pop_id' => $branch->id, 'node_type_id' => $chassisType->id,
            'code' => 'OLT-CHASSIS-CABANG',
        ]);

        $this->actingAs($owner)->post(route('network.nodes.olt-slots.store', $chassis), ['slot_count' => 1])
            ->assertRedirect(route('network.nodes.show', $chassis));
        $this->assertSame([1], $chassis->oltSlots()->orderBy('number')->pluck('number')->all());
    }

    public function test_satu_mini_pop_boleh_punya_4_chassis_slot_terpisah_per_chassis(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $branch->id]);
        $chassisType = NetworkNodeType::firstOrCreate(['code' => 'olt_chassis'], ['name' => 'Chassis OLT', 'has_ports' => false]);

        $chassisList = collect(range(1, 4))->map(fn (int $i) => NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id, 'node_type_id' => $chassisType->id,
            'code' => "OLT-CHASSIS-{$i}",
        ]));

        foreach ($chassisList as $chassis) {
            $this->actingAs($owner)->post(route('network.nodes.olt-slots.store', $chassis), ['slot_count' => 2])
                ->assertRedirect(route('network.nodes.show', $chassis));
        }

        foreach ($chassisList as $chassis) {
            $this->assertSame([1, 2], $chassis->oltSlots()->orderBy('number')->pluck('number')->all());
        }

        $this->assertSame(8, OltSlot::whereIn('chassis_node_id', $chassisList->pluck('id'))->count());
    }
}
