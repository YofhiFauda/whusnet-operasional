<?php

namespace Tests\Feature\Network;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\User;
use App\Services\NetworkPortAssignmentService;
use App\Services\PonCapacityService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kapasitas PON manual bersatuan pelanggan aktif (keputusan user 21-10-2026,
 * §16.3; API disesuaikan Task 9 rancangan "Slot & PON sebagai node"
 * 2026-10-24: PON sekarang baris `network_nodes`, bukan `InfrastructurePort`
 * terpisah, dan pemakaian dihitung dari ODP anak langsung (`parent_id`)
 * tanpa konsep koneksi terverifikasi — itu sudah dihapus bersama
 * `InfrastructureConnection` untuk uplink.
 */
class PonCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Pop $miniPop;

    private User $owner;

    private NetworkNode $pon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->owner = User::factory()->create();
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $this->pon = $this->makePon(capacity: 10, threshold: 80);
    }

    private function makePon(?int $capacity, ?int $threshold): NetworkNode
    {
        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
        $slot = NetworkNode::factory()->slot($olt, 1)->create();

        return NetworkNode::factory()->pon($slot, 1)->create([
            'capacity' => $capacity,
            'red_zone_threshold' => $threshold,
        ]);
    }

    /**
     * ODP anak langsung `$pon` + `$customers` pelanggan aktif pada port-portnya.
     *
     * @return list<Customer>
     */
    private function odpWithCustomers(int $customers, ?NetworkNode $pon = null): array
    {
        $odp = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->firstOrFail()->id,
            'parent_id' => ($pon ?? $this->pon)->id,
            'anchor_pop_id' => null,
        ]);

        $created = [];
        for ($number = 1; $number <= $customers; $number++) {
            $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => $number]);
            $customer = Customer::factory()->create(['pop_id' => $this->miniPop->parent_id]);
            app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);
            $created[] = $customer;
        }

        return $created;
    }

    private function capacity(): PonCapacityService
    {
        return app(PonCapacityService::class);
    }

    #[Test]
    public function pemakaian_dihitung_dari_odp_anak_langsung(): void
    {
        $this->odpWithCustomers(3);

        $summary = $this->capacity()->summary($this->pon);

        $this->assertSame(3, $summary['used']);
        $this->assertSame(10, $summary['capacity']);
        $this->assertSame('pelanggan aktif', $summary['unit_label']);
        $this->assertArrayNotHasKey('unverified', $summary);
    }

    #[Test]
    public function satu_pelanggan_dengan_banyak_ont_dihitung_satu(): void
    {
        [$customer] = $this->odpWithCustomers(1);
        $category = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'ONT-X', 'name' => 'ONT', 'unit' => 'pcs', 'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        foreach (['ONT-1', 'ONT-2'] as $serial) {
            InventorySerial::create(['item_id' => $item->id, 'serial_number' => $serial, 'status' => SerialStatus::INSTALLED->value, 'customer_id' => $customer->id]);
        }

        $this->assertSame(1, $this->capacity()->summary($this->pon)['used']);
    }

    #[Test]
    public function odp_di_pon_lain_tidak_dihitung(): void
    {
        $lainPon = $this->makePon(capacity: 5, threshold: 80);
        $this->odpWithCustomers(2, pon: $lainPon);

        $summary = $this->capacity()->summary($this->pon);

        $this->assertSame(0, $summary['used']);
        $this->assertSame(2, $this->capacity()->summary($lainPon)['used']);
    }

    #[Test]
    public function red_zone_null_bila_kapasitas_atau_ambang_kosong(): void
    {
        $noCapacity = $this->makePon(capacity: null, threshold: 80);
        $noThreshold = $this->makePon(capacity: 10, threshold: null);
        $this->odpWithCustomers(9, pon: $noThreshold);

        $this->assertNull($this->capacity()->summary($noCapacity)['red_zone']);
        $this->assertNull($this->capacity()->summary($noThreshold)['red_zone']);
        $this->assertSame(9, $this->capacity()->summary($noThreshold)['used']);
    }

    #[Test]
    public function tanpa_pemakaian_bukan_red_zone(): void
    {
        $summary = $this->capacity()->summary($this->pon);

        $this->assertSame(0, $summary['used']);
        $this->assertSame(10, $summary['capacity']);
        $this->assertSame('pelanggan aktif', $summary['unit_label']);
        $this->assertFalse($summary['red_zone']);
        $this->assertFalse($summary['is_full']);
    }

    #[Test]
    public function tepat_di_ambang_menjadi_red_zone_dan_di_bawahnya_tidak(): void
    {
        $this->odpWithCustomers(7);
        $this->assertFalse($this->capacity()->summary($this->pon)['red_zone']);

        $this->odpWithCustomers(1);
        $summary = $this->capacity()->summary($this->pon);
        $this->assertSame(8, $summary['used']);
        $this->assertTrue($summary['red_zone']);
    }

    #[Test]
    public function di_atas_ambang_dan_penuh(): void
    {
        $this->odpWithCustomers(10);

        $summary = $this->capacity()->summary($this->pon);
        $this->assertTrue($summary['red_zone']);
        $this->assertTrue($summary['is_full']);
    }

    #[Test]
    public function kapasitas_dan_ambang_divalidasi(): void
    {
        foreach ([[0, 80], [10, 0], [10, 101]] as [$capacity, $threshold]) {
            try {
                $this->capacity()->updateCapacity($this->pon, $capacity, $threshold, $this->owner);
                $this->fail("Nilai {$capacity}/{$threshold} harus ditolak.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function kapasitas_di_bawah_pemakaian_ditolak(): void
    {
        $this->odpWithCustomers(3);

        try {
            $this->capacity()->updateCapacity($this->pon, 2, 80, $this->owner);
            $this->fail('Kapasitas di bawah pemakaian harus ditolak.');
        } catch (ValidationException) {
        }
        $this->assertSame(10, $this->pon->fresh()->capacity);

        $this->capacity()->updateCapacity($this->pon, 3, 90, $this->owner);

        $this->assertSame(3, $this->pon->fresh()->capacity);
        $this->assertSame(90, $this->pon->fresh()->red_zone_threshold);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => NetworkNode::class, 'auditable_id' => $this->pon->id, 'action' => 'capacity_change', 'user_id' => $this->owner->id,
        ]);
    }

    #[Test]
    public function bukan_pon_ditolak(): void
    {
        $switch = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
        $switchSlot = NetworkNode::factory()->slot($switch, 1)->create();
        $port = NetworkNode::factory()->switchPort($switchSlot, 1)->create();

        $this->expectException(ValidationException::class);
        $this->capacity()->updateCapacity($port, 10, 80, $this->owner);
    }
}
