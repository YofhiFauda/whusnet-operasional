<?php

namespace Tests\Feature\Warehouse;

use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryReceiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SN yang DEPLOYED ke titik infrastruktur (ODC/ODP/splitter) lalu ditarik
 * status lost/damaged/scrapped/quarantine wajib melepas `current_network_node_id`
 * — SN itu udah gak ada di node tsb. `current_technician_id` SENGAJA
 * dibiarkan sebagai jejak forensik "terakhir dipegang siapa".
 */
class AdjustDeployedSerialTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Item $modem;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pusat = Pop::create(['code' => 'DPL-PUSAT', 'pop_code' => 'DPP', 'registration_prefix' => 'D', 'cid_prefix' => 'P', 'name' => 'Pusat Deploy Adjust Test', 'type' => 'pusat', 'status' => 'active']);

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'DPL-MODEM', 'name' => 'Modem Deploy Adjust Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $this->actor = User::factory()->create();
    }

    #[Test]
    public function adjust_sn_deployed_ke_damaged_mengosongkan_current_network_node_id(): void
    {
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $this->pusat->id, 'name' => 'ODC Adjust Test']);
        $serial = $this->makeDeployedSerial('DPL-SN-001', $node);
        $technician = User::factory()->create();
        $serial->update(['current_technician_id' => $technician->id]);

        app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::DAMAGED, 'jatuh_kena_air', $this->actor, null, 'warehouse/evidence/damaged/deployed.jpg'
        );

        $serial->refresh();
        $this->assertEquals(SerialStatus::DAMAGED, $serial->status);
        $this->assertNull($serial->current_network_node_id);
        $this->assertEquals($technician->id, $serial->current_technician_id);
    }

    private function makeDeployedSerial(string $serialNumber, NetworkNode $node): InventorySerial
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, [$serialNumber], 250000, $this->actor);

        // Simulasi SN yang sudah di-deploy ke node infrastruktur — cukup
        // manipulasi state langsung (uji adjust-nya saja, bukan alur deploy
        // penuh yang sudah ditest di InventoryServiceDeployTest).
        $serial->update([
            'status' => SerialStatus::DEPLOYED,
            'current_pop_id' => null,
            'current_network_node_id' => $node->id,
        ]);

        return $serial;
    }
}
