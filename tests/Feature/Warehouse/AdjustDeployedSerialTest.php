<?php

namespace Tests\Feature\Warehouse;

use App\Enums\InventoryTransactionType;
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
 * — SN itu udah gak ada di node tsb. Baris ledger ADJUSTMENT-nya wajib
 * menyimpan snapshot lokasi asal (`from_network_node_id` + Cabang node itu
 * di `from_pop_id`) — tanpa itu kerugian aset infrastruktur gak ketauan dari
 * titik mana & gak muncul di riwayat Gudang per cabang.
 */
class AdjustDeployedSerialTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Item $splitter;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pusat = Pop::create(['code' => 'DPL-PUSAT', 'pop_code' => 'DPP', 'registration_prefix' => 'D', 'cid_prefix' => 'P', 'name' => 'Pusat Deploy Adjust Test', 'type' => 'pusat', 'status' => 'active']);

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->splitter = Item::create(['code' => 'DPL-SPLITTER', 'name' => 'Splitter Deploy Adjust Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);

        $this->actor = User::factory()->create();
    }

    #[Test]
    public function adjust_sn_deployed_ke_damaged_mengosongkan_node_dan_ledger_menyimpan_lokasi_asal(): void
    {
        // Node ditambatkan ke Mini POP — from_pop_id harus Cabang induknya
        // (resolvePop()), bukan Mini POP mentah.
        $cabang = Pop::factory()->create(['type' => 'cabang', 'parent_id' => null]);
        $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $miniPop->id, 'name' => 'ODC Adjust Test']);
        $serial = $this->makeDeployedSerial('DPL-SN-001', $node);

        $transaction = app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::DAMAGED, 'jatuh_kena_air', $this->actor, null, 'warehouse/evidence/damaged/deployed.jpg'
        );

        $serial->refresh();
        $this->assertEquals(SerialStatus::DAMAGED, $serial->status);
        $this->assertNull($serial->current_network_node_id);

        $this->assertSame(InventoryTransactionType::ADJUSTMENT, $transaction->type);
        $this->assertSame($node->id, (int) $transaction->from_network_node_id);
        $this->assertSame($cabang->id, (int) $transaction->from_pop_id);
        $this->assertNull($transaction->from_technician_id);
    }

    #[Test]
    public function adjust_sn_non_deployed_tidak_mengisi_from_network_node_id(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->splitter, ['DPL-SN-002'], 250000, $this->actor);

        $transaction = app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::QUARANTINE, 'jatuh_kena_air', $this->actor
        );

        $this->assertNull($transaction->from_network_node_id);
        $this->assertSame($this->pusat->id, (int) $transaction->from_pop_id);
    }

    private function makeDeployedSerial(string $serialNumber, NetworkNode $node): InventorySerial
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->splitter, [$serialNumber], 250000, $this->actor);

        // State persis hasil InventoryService::deploySerial(): gak di gudang,
        // gak di teknisi, cuma nempel di node. (Alur deploy penuh sudah ditest
        // di InventoryServiceDeployTest — di sini cukup uji adjust-nya.)
        $serial->update([
            'status' => SerialStatus::DEPLOYED,
            'current_pop_id' => null,
            'current_technician_id' => null,
            'current_network_node_id' => $node->id,
        ]);

        return $serial;
    }
}
