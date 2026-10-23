<?php

namespace Tests\Feature\Network;

use App\Enums\SerialStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructureReconciliationRun;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructureDummyResetService;
use App\Services\InfrastructureLegacyBackfillService;
use App\Services\InfrastructurePositionService;
use App\Services\InfrastructureReconciliationService;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Bukti cutover §16.4 #6 (rekonsiliasi ODP/pelanggan/port aktif/aset per
 * Cabang antara data lama dan proyeksi baru) dan reset dummy yang aman
 * (§16.4 pengantar: tidak boleh menyentuh pelanggan/stok/transaksi/histori).
 */
class InfrastructureReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private NetworkNode $odp;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->operator = User::factory()->create();
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->odp = $this->node('odp', $this->miniPop, 'ODP-REKON');
        $port = NetworkNodePort::create(['network_node_id' => $this->odp->id, 'number' => 1]);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, Customer::factory()->create(['pop_id' => $this->cabang->id]), $this->operator);
        $item = Item::create(['code' => 'ASET-R', 'name' => 'Aset', 'unit' => 'pcs',
            'item_category_id' => ItemCategory::create(['code' => 'rekon', 'name' => 'Rekon', 'default_unit' => 'pcs'])->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-REKON', 'status' => SerialStatus::DEPLOYED->value, 'current_mini_pop_id' => $this->miniPop->id]);
    }

    private function node(string $typeCode, Pop $anchor, string $code): NetworkNode
    {
        // 'odc' dihapus dari seeder master 2026-10-23 (ADHOC-212) —
        // provisioning sendiri, lihat InfrastructureLegacyDecisionTest.
        $typeId = NetworkNodeType::firstOrCreate(['code' => $typeCode], ['name' => $typeCode, 'has_ports' => false])->id;

        return NetworkNode::factory()->create([
            'node_type_id' => $typeId,
            'anchor_pop_id' => $anchor->id, 'code' => $code,
        ]);
    }

    #[Test]
    public function rekonsiliasi_mencatat_selisih_nol_per_cabang(): void
    {
        $run = app(InfrastructureReconciliationService::class)->run('2026-10');

        $row = $run->firstWhere('pop_id', $this->cabang->id);
        $this->assertSame(['odp' => 1, 'customers' => 1, 'used_ports' => 1, 'assets' => 1, 'legacy_slots' => 0, 'legacy_cards' => 0], $row->legacy_metrics);
        $this->assertSame($row->legacy_metrics, $row->projection_metrics);
        $this->assertTrue($row->is_zero);
        $this->assertFalse(app(InfrastructureReconciliationService::class)->zeroForPeriod('2026-10'), 'Satu snapshot manual tidak membuktikan satu siklus.');
    }

    #[Test]
    public function rekonsiliasi_menghitung_port_aktif_feeder_lama_juga(): void
    {
        $feeder = $this->node('odc', $this->miniPop, 'FDR-REKON');
        NetworkNodePort::create(['network_node_id' => $feeder->id, 'number' => 1, 'is_used' => true]);

        $row = app(InfrastructureReconciliationService::class)->run('2026-10')->firstWhere('pop_id', $this->cabang->id);

        $this->assertSame(2, $row->legacy_metrics['used_ports']);
        $this->assertSame(2, $row->projection_metrics['used_ports']);
        $this->assertTrue($row->is_zero);
    }

    #[Test]
    public function rekonsiliasi_mencakup_aset_server_di_pop_pusat(): void
    {
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $server = $this->node('server', $pusat, 'SERVER-REKON');
        $item = Item::where('code', 'ASET-R')->sole();
        InventorySerial::create([
            'item_id' => $item->id, 'serial_number' => 'SN-PUSAT',
            'status' => SerialStatus::DEPLOYED->value, 'current_network_node_id' => $server->id,
        ]);

        $row = app(InfrastructureReconciliationService::class)->run('2026-10')->firstWhere('pop_id', $pusat->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row->legacy_metrics['assets']);
        $this->assertSame(1, $row->projection_metrics['assets']);
        $this->assertTrue($row->is_zero);
    }

    #[Test]
    public function node_yatim_tanpa_cabang_memunculkan_selisih(): void
    {
        // ODP "melayang" (parent menunjuk node yang di luar pohon cabang)
        // dihitung di data lama per anchor, tetapi tidak terjangkau proyeksi.
        $orphanParent = NetworkNode::factory()->create(['anchor_pop_id' => null, 'parent_id' => null]);
        NetworkNode::query()->whereKey($orphanParent->id)->update(['anchor_pop_id' => null]);
        $legacyOdp = $this->node('odp', $this->miniPop, 'ODP-YATIM');
        $legacyOdp->update(['anchor_pop_id' => null, 'parent_id' => $orphanParent->id]);

        $service = app(InfrastructureReconciliationService::class);
        $service->run('2026-11');

        $this->assertFalse($service->zeroForPeriod('2026-11'));
        $this->assertSame(1, InfrastructureReconciliationRun::where('period', '2026-11')->whereNull('pop_id')->count(),
            'Data lama yang tidak bisa ditautkan ke Cabang dicatat terpisah sebagai selisih.');
    }

    #[Test]
    public function command_rekonsiliasi_menyimpan_bukti(): void
    {
        $this->artisan('infrastructure:reconcile', ['--period' => '2026-10'])->assertSuccessful();

        $this->assertSame(1, InfrastructureReconciliationRun::where('period', '2026-10')->where('pop_id', $this->cabang->id)->count());
        $this->assertFalse(InfrastructureReconciliationRun::where('period', '2026-10')->where('is_zero', false)->exists());
    }

    #[Test]
    public function reset_dummy_menolak_node_yang_memegang_pelanggan(): void
    {
        $this->expectException(RuntimeException::class);
        app(InfrastructureDummyResetService::class)->reset(['ODP-REKON'], $this->operator, dryRun: false);
    }

    #[Test]
    public function reset_dummy_hanya_menghapus_node_terdaftar_yang_bersih(): void
    {
        $dummy = $this->node('odc', $this->miniPop, 'DUMMY-FDR');
        NetworkNodePort::create(['network_node_id' => $dummy->id, 'number' => 1]);
        $assignments = NetworkPortAssignment::count();

        $plan = app(InfrastructureDummyResetService::class)->reset(['DUMMY-FDR'], $this->operator, dryRun: true);
        $this->assertSame(['DUMMY-FDR'], $plan['nodes']);
        $this->assertNotNull($dummy->fresh(), 'Dry-run tidak menghapus apa pun.');

        app(InfrastructureDummyResetService::class)->reset(['DUMMY-FDR'], $this->operator, dryRun: false);

        $this->assertNull($dummy->fresh());
        $this->assertNotNull($this->odp->fresh());
        $this->assertSame($assignments, NetworkPortAssignment::count());
        $this->assertSame(1, InventorySerial::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'dummy_topology_reset']);
    }

    #[Test]
    public function reset_dummy_menolak_node_dengan_keputusan_mapping_legacy(): void
    {
        $dummy = $this->node('odc', $this->miniPop, 'DUMMY-MAPPED');
        InfrastructureLegacyMapping::create([
            'legacy_type' => 'odp_uplink', 'legacy_id' => $dummy->id,
            'status' => InfrastructureLegacyMapping::REJECTED,
            'pop_id' => $this->cabang->id, 'reason' => 'Keputusan operator',
            'decision' => 'Jangan sambungkan', 'decided_by' => $this->operator->id,
            'decided_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        app(InfrastructureDummyResetService::class)->reset(['DUMMY-MAPPED'], $this->operator, dryRun: false);
    }

    #[Test]
    public function reset_dummy_menolak_node_dengan_riwayat_audit(): void
    {
        $dummy = $this->node('odc', $this->miniPop, 'DUMMY-AUDIT');
        AuditLog::create([
            'user_id' => $this->operator->id, 'module' => 'Node Jaringan', 'action' => 'move',
            'auditable_type' => NetworkNode::class, 'auditable_id' => $dummy->id,
            'old_values' => null, 'new_values' => ['code' => $dummy->code],
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        app(InfrastructureDummyResetService::class)->reset(['DUMMY-AUDIT'], $this->operator, dryRun: false);
    }

    #[Test]
    public function reset_dummy_menolak_node_dengan_anak_yang_tidak_terdaftar(): void
    {
        $parent = $this->node('odc', $this->miniPop, 'DUMMY-INDUK');
        NetworkNode::factory()->child($parent)->create(['code' => 'ANAK-NYATA']);

        $this->expectException(RuntimeException::class);
        app(InfrastructureDummyResetService::class)->reset(['DUMMY-INDUK'], $this->operator, dryRun: false);
    }

    #[Test]
    public function reset_dummy_card_preserves_real_chassis_slot_and_canonical_mapping(): void
    {
        $chassis = $this->node('olt_chassis', $this->miniPop, 'REAL-CHASSIS');
        // 'olt_card' dihapus dari seeder master 2026-10-23 (ADHOC-212) —
        // provisioning sendiri, lihat helper node() di atas.
        $cardTypeId = NetworkNodeType::firstOrCreate(['code' => 'olt_card'], ['name' => 'olt_card', 'has_ports' => true])->id;
        $card = NetworkNode::factory()->create([
            'node_type_id' => $cardTypeId,
            'parent_id' => $chassis->id, 'anchor_pop_id' => null, 'code' => 'DUMMY-CARD',
        ]);
        $legacy = OltSlot::create(['chassis_node_id' => $chassis->id, 'number' => 1, 'card_node_id' => $card->id]);
        app(InfrastructureLegacyBackfillService::class)->run();
        $canonical = InfrastructureSlot::where('legacy_olt_slot_id', $legacy->id)->sole();

        app(InfrastructureDummyResetService::class)->reset(['DUMMY-CARD'], $this->operator, dryRun: false);

        $this->assertNull($card->fresh());
        $this->assertNull($legacy->fresh()->card_node_id);
        $this->assertNotNull($canonical->fresh());
        $this->assertDatabaseHas('infrastructure_legacy_mappings', ['legacy_type' => 'olt_slot', 'legacy_id' => $legacy->id]);
        $this->assertDatabaseMissing('infrastructure_legacy_mappings', ['legacy_type' => 'olt_card_node', 'legacy_id' => $card->id]);
    }

    #[Test]
    public function reset_dummy_chassis_cleans_automatic_slot_mapping(): void
    {
        $chassis = $this->node('olt_chassis', $this->miniPop, 'DUMMY-CHASSIS');
        $legacy = OltSlot::create(['chassis_node_id' => $chassis->id, 'number' => 1]);
        app(InfrastructureLegacyBackfillService::class)->run();

        app(InfrastructureDummyResetService::class)->reset(['DUMMY-CHASSIS'], $this->operator, dryRun: false);

        $this->assertNull($chassis->fresh());
        $this->assertDatabaseMissing('infrastructure_legacy_mappings', ['legacy_type' => 'olt_slot', 'legacy_id' => $legacy->id]);
    }

    #[Test]
    public function reset_refuses_legacy_slot_linked_to_other_canonical_chassis(): void
    {
        $dummy = $this->node('olt_chassis', $this->miniPop, 'DUMMY-CROSS');
        $real = $this->node('olt_chassis', $this->miniPop, 'REAL-CROSS');
        $legacy = OltSlot::create(['chassis_node_id' => $dummy->id, 'number' => 1]);
        InfrastructureSlot::create(['network_node_id' => $real->id, 'number' => 1, 'kind' => 'olt', 'legacy_olt_slot_id' => $legacy->id, 'capacity_unit' => 'pon', 'status' => 'belum_didata']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slot lama dirujuk posisi kanonis di luar reset');
        app(InfrastructureDummyResetService::class)->reset(['DUMMY-CROSS'], $this->operator, dryRun: false);
    }

    #[Test]
    public function reset_preserves_audited_capacity_history_on_canonical_slot(): void
    {
        $dummy = $this->node('olt_chassis', $this->miniPop, 'DUMMY-SLOT-AUDIT');
        $slot = app(InfrastructurePositionService::class)->createSlot($dummy, 1);
        app(InfrastructurePositionService::class)->updateSlotCapacity($slot, 8, $this->operator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('riwayat audit posisi');
        app(InfrastructureDummyResetService::class)->reset(['DUMMY-SLOT-AUDIT'], $this->operator, dryRun: false);
    }
}
