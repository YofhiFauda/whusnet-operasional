<?php

namespace Tests\Unit;

use App\Models\InfrastructureBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Task 8: kolom lokasi `network_node_id` di ledger (`inventory_transactions`)
 * & SN (`inventory_serials`), plus tabel `infrastructure_balances` — stok
 * QUANTITY/ROLL yang dideploy ke `network_nodes` (beda dari
 * `inventory_balances` yang khusus gudang `pusat`/`cabang`).
 */
class InfrastructureBalanceModelTest extends TestCase
{
    use RefreshDatabase;

    private function createItem(): Item
    {
        $category = ItemCategory::create([
            'code' => 'infra_test_cat',
            'name' => 'Kategori Uji Infrastruktur',
            'default_unit' => 'pcs',
            'equipment_class' => 'pasif',
            'is_system' => false,
            'is_active' => true,
        ]);

        return Item::create([
            'code' => 'INFRA-ITEM',
            'name' => 'Barang Uji Infrastruktur',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
        ]);
    }

    #[Test]
    public function infrastructure_balance_tersimpan_dan_relasi_resolve(): void
    {
        $item = $this->createItem();
        $node = NetworkNode::factory()->create();

        $balance = InfrastructureBalance::create([
            'item_id' => $item->id,
            'network_node_id' => $node->id,
            'lot_no' => '',
            'qty' => 10.5,
        ]);

        $this->assertDatabaseHas('infrastructure_balances', [
            'id' => $balance->id,
            'item_id' => $item->id,
            'network_node_id' => $node->id,
        ]);

        $this->assertSame($node->id, $balance->networkNode->id);
        $this->assertSame($item->id, $balance->item->id);
        $this->assertEquals(10.5, $balance->qty);
    }

    #[Test]
    public function inventory_serial_current_network_node_resolve(): void
    {
        $item = $this->createItem();
        $node = NetworkNode::factory()->create();

        $serial = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'INFRA-SN-0001',
            'status' => 'installed',
            'current_network_node_id' => $node->id,
        ]);

        $this->assertSame($node->id, $serial->currentNetworkNode->id);
    }

    #[Test]
    public function inventory_transaction_to_dan_from_network_node_resolve(): void
    {
        $item = $this->createItem();
        $toNode = NetworkNode::factory()->create();
        $fromNode = NetworkNode::factory()->create();

        $toTransaction = InventoryTransaction::create([
            'type' => 'receive',
            'item_id' => $item->id,
            'qty' => 5,
            'to_network_node_id' => $toNode->id,
        ]);

        $this->assertSame($toNode->id, $toTransaction->toNetworkNode->id);

        $fromTransaction = InventoryTransaction::create([
            'type' => 'adjustment',
            'item_id' => $item->id,
            'qty' => 2,
            'from_network_node_id' => $fromNode->id,
        ]);

        $this->assertSame($fromNode->id, $fromTransaction->fromNetworkNode->id);
    }
}
