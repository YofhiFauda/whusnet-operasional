<?php

namespace Tests\Unit;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Task 6 (Registrasi Aset Lama): SN yang sudah lama berdiri di lapangan tapi
 * belum pernah masuk ledger sama sekali — beda dari `deploySerial()` yang
 * SYARATKAN SN sudah `ISSUED` ke custody teknisi lewat `FopTask`.
 * `registerExistingInfraSerial()` membuat SN baru langsung `DEPLOYED`, TANPA
 * `FopTask`, dengan `reason` wajib (bukan opsional kayak `notes` di DEPLOY).
 */
class InventoryServiceRegisterInfraTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(string $ownershipMode = 'infrastructure_asset', string $trackingType = 'serialized'): Item
    {
        $category = ItemCategory::where('code', 'infra_register_test')->first()
            ?? ItemCategory::create([
                'code' => 'infra_register_test',
                'name' => 'Kategori Uji Registrasi Infra',
                'default_unit' => 'pcs',
            ]);

        return Item::create([
            'code' => 'REG-'.strtoupper($trackingType).'-'.strtoupper($ownershipMode),
            'name' => 'Barang Uji Registrasi Infra',
            'unit' => 'pcs',
            'item_category_id' => $category->id,
            'tracking_type' => $trackingType,
            'ownership_mode' => $ownershipMode,
        ]);
    }

    #[Test]
    public function test_registrasi_aset_lama_membuat_sn_deployed_dan_ledger_register_infra(): void
    {
        $item = $this->makeItem();
        $node = NetworkNode::factory()->create();
        $actor = User::factory()->create();
        $reason = 'Opname awal, chassis sudah berdiri sejak 2023';

        $serial = app(InventoryService::class)->registerExistingInfraSerial(
            'SN-LEGACY-001',
            $item,
            $node,
            $reason,
            $actor,
        );

        $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
        $this->assertSame($node->id, (int) $serial->current_network_node_id);

        $transaction = InventoryTransaction::where('serial_id', $serial->id)->sole();
        $this->assertSame(InventoryTransactionType::REGISTER_INFRA, $transaction->type);
        $this->assertSame($node->id, (int) $transaction->to_network_node_id);
        $this->assertSame($reason, $transaction->reason);
        $this->assertNull($transaction->fop_task_id);
    }

    #[Test]
    public function test_registrasi_tanpa_alasan_ditolak(): void
    {
        $item = $this->makeItem();
        $node = NetworkNode::factory()->create();
        $actor = User::factory()->create();

        try {
            app(InventoryService::class)->registerExistingInfraSerial('SN-LEGACY-002', $item, $node, '', $actor);
            $this->fail('registerExistingInfraSerial() seharusnya menolak reason kosong.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $this->assertSame(0, InventorySerial::count());
        $this->assertSame(0, InventoryTransaction::count());
    }

    #[Test]
    public function test_registrasi_item_bukan_infrastructure_asset_ditolak(): void
    {
        $item = $this->makeItem('installable');
        $node = NetworkNode::factory()->create();
        $actor = User::factory()->create();

        try {
            app(InventoryService::class)->registerExistingInfraSerial('SN-LEGACY-003', $item, $node, 'Opname awal', $actor);
            $this->fail('registerExistingInfraSerial() seharusnya menolak item installable.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $this->assertSame(0, InventorySerial::count());
        $this->assertSame(0, InventoryTransaction::count());
    }

    #[Test]
    public function test_registrasi_serial_number_yang_sudah_ada_ditolak(): void
    {
        $item = $this->makeItem();
        $node = NetworkNode::factory()->create();
        $actor = User::factory()->create();

        InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'SN-LEGACY-004',
            'status' => SerialStatus::DEPLOYED->value,
            'current_network_node_id' => $node->id,
        ]);

        try {
            app(InventoryService::class)->registerExistingInfraSerial('SN-LEGACY-004', $item, $node, 'Opname awal', $actor);
            $this->fail('registerExistingInfraSerial() seharusnya menolak SN yang sudah terdaftar.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $this->assertSame(1, InventorySerial::count());
        $this->assertSame(0, InventoryTransaction::count());
    }
}
