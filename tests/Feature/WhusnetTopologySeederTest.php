<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\Pop;
use App\Services\NetworkNodeService;
use Database\Seeders\WhusnetTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhusnetTopologySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_membentuk_hierarki_dan_hanya_memetakan_port_pelanggan_yang_pasti(): void
    {
        $pusat = Pop::factory()->create(['name' => 'WHUSNET', 'type' => 'pusat', 'parent_id' => null]);
        $jetis = Pop::factory()->create(['name' => 'Jetis', 'type' => 'cabang', 'parent_id' => null]);
        $sandya = Pop::factory()->create(['name' => 'Sandya', 'type' => 'cabang', 'parent_id' => null]);
        $c1 = Pop::factory()->create(['code' => 'C1', 'name' => 'C1', 'type' => 'mini_pop', 'parent_id' => $jetis->id]);
        $d1 = Pop::factory()->create(['code' => 'D1', 'name' => 'D1', 'type' => 'mini_pop', 'parent_id' => $sandya->id]);

        foreach (['server', 'backbone', 'aggregation_switch', 'olt_chassis', 'pon_port', 'feeder', 'odp', 'drop_cable', 'ont'] as $code) {
            NetworkNodeType::firstOrCreate(['code' => $code], ['name' => $code, 'has_ports' => $code === 'odp']);
        }
        $odpType = NetworkNodeType::where('code', 'odp')->firstOrFail();
        $odp = NetworkNode::create([
            'code' => 'ODP-JTS-13', 'name' => 'ODP JETIS 13',
            'node_type_id' => $odpType->id, 'anchor_pop_id' => $jetis->id, 'status' => 'active',
        ]);
        $firstPort = $odp->ports()->create(['number' => 1]);
        $secondPort = $odp->ports()->create(['number' => 2]);
        $thirdPort = $odp->ports()->create(['number' => 3]);
        $fourthPort = $odp->ports()->create(['number' => 4]);

        $matched = Customer::factory()->create(['pop_id' => $jetis->id]);
        $otherBranch = Customer::factory()->create(['pop_id' => $sandya->id]);
        $ambiguousA = Customer::factory()->create(['pop_id' => $jetis->id]);
        $ambiguousB = Customer::factory()->create(['pop_id' => $jetis->id]);
        $notMatched = Customer::factory()->create(['pop_id' => $jetis->id]);
        $inventoryMatched = Customer::factory()->create(['pop_id' => $jetis->id]);
        $deviceMatched = Customer::factory()->create(['pop_id' => $jetis->id]);
        CustomerDevice::create(['customer_id' => $deviceMatched->id, 'device_type' => 'ONT', 'serial_number' => 'DEVICE-ONT-003']);
        $modemCategory = ItemCategory::firstOrCreate(['code' => 'modem_ont'], ['name' => 'Modem / ONT']);
        $modemItem = Item::create(['code' => 'ONT-TEST-01', 'name' => 'ONT Test', 'type' => 'modem_ont', 'unit' => 'pcs', 'item_category_id' => $modemCategory->id]);
        InventorySerial::create(['item_id' => $modemItem->id, 'serial_number' => 'INVENTORY-ONT-002', 'status' => 'installed', 'customer_id' => $inventoryMatched->id]);
        foreach ([
            [$matched, 'ODP-JTS-13', '1'],
            [$otherBranch, 'ODP-JTS-13', '2'],
            [$ambiguousA, 'ODP-JTS-13', '2'],
            [$ambiguousB, 'ODP-JTS-13', '2'],
            [$notMatched, 'ODP 13', '3'],
            [$inventoryMatched, 'ODP-JTS-13', '3'],
            [$deviceMatched, 'ODP-JTS-13', '4'],
        ] as [$customer, $label, $number]) {
            CustomerTechnicalDetail::create([
                'customer_id' => $customer->id, 'odp_number' => $label, 'odp_port' => $number,
                'router_or_ont_serial' => $customer->id === $matched->id ? 'REAL-ONT-SN-001' : null,
            ]);
        }

        $this->seed(WhusnetTopologySeeder::class);
        $this->seed(WhusnetTopologySeeder::class);

        $this->assertSame($pusat->id, $jetis->fresh()->parent_id);
        $this->assertSame($pusat->id, $sandya->fresh()->parent_id);
        $core = NetworkNode::where('code', 'CORE-WHUSNET-PUSAT')->firstOrFail();
        $this->assertSame($pusat->id, $core->anchor_pop_id);
        $this->assertSame($core->id, NetworkNode::where('code', 'BB-PUSAT-SANDYA-01')->firstOrFail()->parent_id);
        $this->assertSame($sandya->id, NetworkNode::where('code', 'AGG-SANDYA-01')->firstOrFail()->anchor_pop_id);
        foreach ([$jetis, $sandya] as $branch) {
            $mini = Pop::where('parent_id', $branch->id)->where('type', 'mini_pop')->sole();
            $suffix = strtoupper($branch->name);
            $olt = NetworkNode::where('code', "OLT-{$suffix}-01")->firstOrFail();
            $pon = NetworkNode::where('code', "PON-{$suffix}-01")->firstOrFail();
            $fdr = NetworkNode::where('code', "FDR-{$suffix}-01")->firstOrFail();
            $generatedOdp = NetworkNode::where('code', "ODP-{$suffix}-01")->firstOrFail();
            $this->assertSame($branch->id === $jetis->id ? $c1->id : $d1->id, $mini->id);
            $backboneCode = "BB-PUSAT-{$suffix}-01";
            $aggregationCode = "AGG-{$suffix}-01";
            $this->assertSame(NetworkNode::where('code', $backboneCode)->value('id'), $branch->fresh()->uplink_node_id);
            $this->assertSame(NetworkNode::where('code', $aggregationCode)->value('id'), $mini->fresh()->uplink_node_id);
            $this->assertSame($mini->id, $olt->anchor_pop_id);
            $this->assertSame($olt->id, $pon->parent_id);
            $this->assertSame($pon->id, $fdr->parent_id);
            $this->assertSame($fdr->id, $generatedOdp->parent_id);
            $placement = app(NetworkNodeService::class);
            $placement->assertTypePlacement($pon->nodeType, $olt, null);
            $placement->assertTypePlacement($fdr->nodeType, $pon, null);
            $placement->assertTypePlacement($generatedOdp->nodeType, $fdr, null);
        }
        $dc = NetworkNode::where('code', "CUST-{$matched->id}-DC")->firstOrFail();
        $this->assertSame($odp->id, $dc->parent_id);
        $placement = app(NetworkNodeService::class);
        $placement->assertTypePlacement($dc->nodeType, $odp, null);
        $this->assertSame(0, NetworkNode::whereHas('nodeType', fn ($query) => $query->where('code', 'ont'))->count());
        $this->assertSame('DEVICE-ONT-003', $deviceMatched->customerDevice->serial_number);
        $this->assertSame('INVENTORY-ONT-002', $inventoryMatched->inventorySerials->first()->serial_number);
        $this->assertSame(2, Pop::where('type', 'mini_pop')->count());
        $this->assertFalse(Pop::where('code', 'TOPO-JETIS-MINI')->exists());
        $this->assertFalse(Pop::where('code', 'TOPO-SANDYA-MINI')->exists());
        $this->assertSame(17, NetworkNode::count());
        $this->assertSame($matched->id, $firstPort->assignments()->firstOrFail()->customer_id);
        $this->assertTrue($firstPort->fresh()->is_used);
        $this->assertFalse($secondPort->fresh()->is_used);
        $this->assertTrue($thirdPort->fresh()->is_used);
        $this->assertTrue($fourthPort->fresh()->is_used);
        $this->assertSame(1, $firstPort->assignments()->count());
        $this->assertSame(3, NetworkPortAssignment::count());
    }
}
