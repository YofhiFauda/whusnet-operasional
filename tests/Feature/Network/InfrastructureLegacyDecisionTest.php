<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructurePortKind;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureLegacyBackfillService;
use App\Services\InfrastructureLegacyDecisionService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Data nyata: kasus ambigu dipetakan EKSPLISIT oleh operator (§7.3, §16.4) —
 * dengan alasan, pelaku, dan audit. Tidak ada pemetaan otomatis.
 */
class InfrastructureLegacyDecisionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private NetworkNode $chassis;

    private NetworkNode $pon;

    private NetworkNode $odp;

    private NetworkNode $agg;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->operator = User::factory()->create();
        $this->chassis = $this->node('olt_chassis', ['anchor_pop_id' => $this->miniPop->id]);
        OltSlot::create(['chassis_node_id' => $this->chassis->id, 'number' => 1]);
        $this->pon = $this->node('pon_port', ['parent_id' => $this->chassis->id]);
        $feeder = $this->node('odc', ['parent_id' => $this->pon->id]);
        $this->odp = $this->node('odp', ['parent_id' => $feeder->id]);
        $this->agg = $this->node('aggregation_switch', ['anchor_pop_id' => $this->cabang->id]);
        app(InfrastructureLegacyBackfillService::class)->run();
    }

    private function node(string $typeCode, array $placement): NetworkNode
    {
        // Tipe representasi lama (pon_port/olt_card/aggregation_switch/odc)
        // dihapus dari seeder master 2026-10-23 (ADHOC-212) — bukan bagian
        // hierarki resmi lagi. Alat keputusan data lama tetap harus bisa
        // dites terhadap fixture-nya, jadi tes ini provisioning tipe sendiri
        // (firstOrCreate), bukan gantung ke baris seeder permanen.
        $typeId = NetworkNodeType::firstOrCreate(['code' => $typeCode], ['name' => $typeCode, 'has_ports' => false])->id;

        return NetworkNode::factory()->create(array_merge([
            'node_type_id' => $typeId, 'parent_id' => null, 'anchor_pop_id' => null,
        ], $placement));
    }

    private function mapping(string $type, int $id): InfrastructureLegacyMapping
    {
        return InfrastructureLegacyMapping::where('legacy_type', $type)->where('legacy_id', $id)->sole();
    }

    private function decisions(): InfrastructureLegacyDecisionService
    {
        return app(InfrastructureLegacyDecisionService::class);
    }

    #[Test]
    public function operator_memetakan_pon_node_ke_pon_kanonis_dengan_jejak_keputusan(): void
    {
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();

        $port = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 3, $this->operator, 'Label fisik PON 3');

        $this->assertSame(InfrastructurePortKind::PON, $port->kind);
        $this->assertSame($this->pon->id, $port->legacy_network_node_id);
        $mapping = $this->mapping('pon_port_node', $this->pon->id);
        $this->assertSame('mapped', $mapping->status);
        $this->assertSame($port->id, $mapping->new_id);
        $this->assertSame($this->operator->id, $mapping->decided_by);
        $this->assertSame('Label fisik PON 3', $mapping->decision);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureLegacyMapping::class, 'auditable_id' => $mapping->id, 'action' => 'legacy_decision']);
    }

    #[Test]
    public function slot_dari_chassis_lain_ditolak(): void
    {
        $other = $this->node('olt_chassis', ['anchor_pop_id' => $this->miniPop->id]);
        $slot = InfrastructureSlot::create(['network_node_id' => $other->id, 'number' => 9, 'kind' => 'olt', 'status' => 'belum_didata', 'capacity_unit' => 'pon']);

        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'Salah chassis');
    }

    #[Test]
    public function operator_menyambung_uplink_odp_ke_pon(): void
    {
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $port = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'PON 1');

        $connection = $this->decisions()->mapOdpUplink($this->mapping('odp_uplink', $this->odp->id), $port, $this->operator, 'Dicek di lapangan', verified: true);

        $this->assertTrue($connection->isVerified());
        $this->assertSame($this->odp->id, $connection->target_network_node_id);
        $this->assertSame('mapped', $this->mapping('odp_uplink', $this->odp->id)->status);
        $this->assertSame($this->odp->fresh()->parent_id, NetworkNode::where('node_type_id', NetworkNodeType::where('code', 'odc')->value('id'))->value('id'),
            'Parent lama tidak diubah — koneksi terpisah dari lokasi.');
    }

    #[Test]
    public function operator_memindah_switch_agregasi_ke_mini_pop_dalam_cabang_yang_sama(): void
    {
        $node = $this->decisions()->mapAggregationSwitch($this->mapping('aggregation_switch_node', $this->agg->id), $this->miniPop, $this->operator, 'Switch fisik di C1');

        $this->assertSame('switch_mini_pop', $node->nodeType->code);
        $this->assertSame($this->miniPop->id, $node->anchor_pop_id);
        $this->assertSame(1, InfrastructureSlot::where('network_node_id', $node->id)->where('is_logical', true)->count());
        $this->assertSame('mapped', $this->mapping('aggregation_switch_node', $this->agg->id)->status);
    }

    #[Test]
    public function mini_pop_cabang_lain_ditolak_untuk_switch(): void
    {
        $otherMini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => Pop::factory()->create(['type' => 'cabang'])->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->mapAggregationSwitch($this->mapping('aggregation_switch_node', $this->agg->id), $otherMini, $this->operator, 'Salah cabang');
    }

    #[Test]
    public function keputusan_wajib_alasan_dan_tidak_bisa_diulang(): void
    {
        $mapping = $this->mapping('odp_uplink', $this->odp->id);

        try {
            $this->decisions()->reject($mapping, $this->operator, ' ');
            $this->fail('Alasan wajib.');
        } catch (InvalidArgumentException) {
        }

        $this->decisions()->reject($mapping, $this->operator, 'ODP sudah dibongkar');
        $this->assertSame('rejected', $mapping->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->reject($mapping->fresh(), $this->operator, 'Ulang');
    }

    #[Test]
    public function command_menampilkan_daftar_ambigu(): void
    {
        $this->artisan('infrastructure:legacy-mappings')
            ->expectsOutputToContain('pon_port_node')
            ->expectsOutputToContain('aggregation_switch_node')
            ->assertSuccessful();
        $this->assertSame(0, InfrastructureConnection::count());
        $this->assertSame(0, InfrastructurePort::count());
    }

    #[Test]
    public function command_menjalankan_keputusan_eksplisit(): void
    {
        $mapping = $this->mapping('aggregation_switch_node', $this->agg->id);

        $this->artisan('infrastructure:legacy-mappings', [
            '--decide' => $mapping->id, '--action' => 'map-switch', '--mini-pop' => $this->miniPop->id,
            '--operator' => $this->operator->id, '--reason' => 'Switch di rak C1',
        ])->assertSuccessful();

        $this->assertSame('mapped', $mapping->fresh()->status);
        $this->artisan('infrastructure:legacy-mappings', [
            '--decide' => $mapping->id, '--action' => 'reject', '--operator' => $this->operator->id, '--reason' => 'Ulang',
        ])->assertFailed();
    }

    #[Test]
    public function mapped_ancestor_blocks_conflicting_odp_without_override(): void
    {
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'PON 1');
        $other = app(InfrastructurePositionService::class)->createPort($slot, 2, InfrastructurePortKind::PON);
        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->mapOdpUplink($this->mapping('odp_uplink', $this->odp->id), $other, $this->operator, 'Different PON');
    }

    #[Test]
    public function override_of_ancestor_requires_and_audits_reason(): void
    {
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $expected = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'PON 1');
        $other = app(InfrastructurePositionService::class)->createPort($slot, 2, InfrastructurePortKind::PON);
        $mapping = $this->mapping('odp_uplink', $this->odp->id);
        try {
            $this->decisions()->mapOdpUplink($mapping, $other, $this->operator, 'Different PON', override: true);
            $this->fail('Override reason required.');
        } catch (InvalidArgumentException) {
            $this->assertSame('ambiguous', $mapping->fresh()->status);
        }
        $this->decisions()->mapOdpUplink($mapping, $other, $this->operator, 'Different PON', override: true, overrideReason: 'Cable rerouted');
        $audit = AuditLog::where('action', 'legacy_odp_uplink_override')->sole();
        $this->assertSame($expected->id, $audit->old_values['source_infrastructure_port_id']);
        $this->assertSame($other->id, $audit->new_values['source_infrastructure_port_id']);
        $this->assertSame('Cable rerouted', $audit->new_values['reason']);
    }

    #[Test]
    public function switch_remap_requires_explicit_self_uplink_clear(): void
    {
        $this->miniPop->update(['uplink_node_id' => $this->agg->id]);
        $mapping = $this->mapping('aggregation_switch_node', $this->agg->id);
        try {
            $this->decisions()->mapAggregationSwitch($mapping, $this->miniPop, $this->operator, 'Switch in C1');
            $this->fail('Choose to clear self uplink.');
        } catch (InvalidArgumentException) {
            $this->assertSame('aggregation_switch', $this->agg->fresh()->nodeType->code);
        }
        $this->decisions()->mapAggregationSwitch($mapping, $this->miniPop, $this->operator, 'Switch in C1', clearSelfUplink: true);
        $this->assertNull($this->miniPop->fresh()->uplink_node_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'legacy_switch_uplink_clear', 'auditable_id' => $this->miniPop->id]);
    }

    #[Test]
    public function legacy_capacity_requires_operator_confirmation_of_customer_unit(): void
    {
        $this->pon->update(['capacity' => 64]);
        $this->pon->nodeType->update(['red_zone_threshold' => 85]);
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $port = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'Confirmed active customer unit', confirmedCapacityUnit: 'active_customers');
        $this->assertSame(64, $port->capacity);
        $this->assertSame(85, $port->red_zone_threshold);
        $audit = AuditLog::where('action', 'legacy_pon_capacity')->sole();
        $this->assertSame('active_customers', $audit->new_values['confirmed_capacity_unit']);
    }

    #[Test]
    public function unknown_capacity_unit_is_not_inferred(): void
    {
        $this->pon->update(['capacity' => 64]);
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $port = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'Unit unknown');
        $this->assertNull($port->capacity);
        $this->assertNull($port->red_zone_threshold);
    }

    #[Test]
    public function command_confirms_capacity_unit_explicitly(): void
    {
        $this->pon->update(['capacity' => 64]);
        $mapping = $this->mapping('pon_port_node', $this->pon->id);
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $this->artisan('infrastructure:legacy-mappings', [
            '--decide' => $mapping->id, '--action' => 'map-pon', '--slot' => $slot->id, '--pon' => 1,
            '--operator' => $this->operator->id, '--reason' => 'Confirmed unit',
            '--capacity-unit' => 'active_customers',
        ])->assertSuccessful();
        $this->assertSame(64, InfrastructurePort::findOrFail($mapping->fresh()->new_id)->capacity);
    }

    #[Test]
    public function command_warns_when_capacity_unit_is_unknown(): void
    {
        $this->pon->update(['capacity' => 64]);
        $mapping = $this->mapping('pon_port_node', $this->pon->id);
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $this->artisan('infrastructure:legacy-mappings', [
            '--decide' => $mapping->id, '--action' => 'map-pon', '--slot' => $slot->id, '--pon' => 1,
            '--operator' => $this->operator->id, '--reason' => 'Unit unknown',
        ])->expectsOutputToContain('Satuan kapasitas belum dikonfirmasi')->assertSuccessful();
        $this->assertNull(InfrastructurePort::findOrFail($mapping->fresh()->new_id)->capacity);
    }

    private function orphanForRecovery(): InfrastructureLegacyMapping
    {
        $this->seed(RoleSeeder::class);
        $this->operator->update(['role_id' => Role::where('code', 'owner')->sole()->id]);
        app(EffectiveAccessService::class)->clearCache($this->operator);
        $parentId = $this->odp->parent_id;
        $this->odp->update(['parent_id' => null, 'anchor_pop_id' => null]);
        DB::table('network_nodes')->where('id', $parentId)->delete();

        return $this->mapping('odp_uplink', $this->odp->id);
    }

    #[Test]
    public function orphan_location_preview_proves_mini_pop_without_mutating_data(): void
    {
        $mapping = $this->orphanForRecovery();
        $auditCount = AuditLog::count();
        $plan = $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Restore proven location');

        $this->assertSame($this->miniPop->id, $plan['mini_pop_id']);
        $this->assertSame($this->cabang->id, $plan['cabang_id']);
        $this->assertTrue($plan['dry_run']);
        $this->assertNotEmpty($plan['proof']['audit_ids']);
        $this->assertNull($this->odp->fresh()->anchor_pop_id);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertArrayNotHasKey('location_recovery', $mapping->fresh()->evidence);
    }

    #[Test]
    public function orphan_location_apply_retains_ambiguity_and_audits_full_proof(): void
    {
        $mapping = $this->orphanForRecovery();
        $originalEvidence = $mapping->evidence;
        $plan = $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Restore proven location', dryRun: false);

        $this->assertNull($this->odp->fresh()->parent_id);
        $this->assertSame($this->miniPop->id, $this->odp->fresh()->anchor_pop_id);
        $this->assertSame('ambiguous', $mapping->fresh()->status);
        $this->assertNull($mapping->fresh()->decided_by);
        $this->assertSame($originalEvidence['parent_id'], $mapping->fresh()->evidence['parent_id']);
        $this->assertSame($plan['proof'], $mapping->fresh()->evidence['location_recovery']['proof']);
        $audit = AuditLog::where('action', 'legacy_orphan_location_restore')->sole();
        $this->assertSame($this->operator->id, $audit->user_id);
        $this->assertNull($audit->old_values['anchor_pop_id']);
        $this->assertSame($this->miniPop->id, $audit->new_values['anchor_pop_id']);
        $this->assertSame($plan['proof'], $audit->new_values['proof']);
        $this->assertSame(0, InfrastructureConnection::count());
        $this->assertSame(0, InfrastructurePort::count());
    }

    #[Test]
    public function orphan_recovery_refuses_conflicting_mapping_evidence(): void
    {
        $mapping = $this->orphanForRecovery();
        $mapping->update(['evidence' => [...$mapping->evidence, 'parent_id' => 9999]]);
        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Conflict');
    }

    #[Test]
    public function orphan_recovery_refuses_newer_placement_than_mapping_snapshot(): void
    {
        $mapping = $this->orphanForRecovery();
        $mapping->forceFill(['created_at' => now()->subDay()])->save();
        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Stale snapshot');
    }

    #[Test]
    public function orphan_recovery_refuses_missing_audit_provenance(): void
    {
        $mapping = $this->orphanForRecovery();
        AuditLog::where('auditable_type', NetworkNode::class)->where('auditable_id', $mapping->evidence['parent_id'])->delete();
        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Missing audit');
    }

    #[Test]
    public function orphan_recovery_refuses_scope_branch_mismatch(): void
    {
        $mapping = $this->orphanForRecovery();
        $mapping->update(['pop_id' => Pop::factory()->create(['type' => 'cabang'])->id]);
        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Wrong branch');
    }

    #[Test]
    public function restore_location_command_previews_then_requires_apply_flag(): void
    {
        $mapping = $this->orphanForRecovery();
        $args = ['--decide' => $mapping->id, '--action' => 'restore-location', '--operator' => $this->operator->id, '--reason' => 'Proven location'];
        $this->artisan('infrastructure:legacy-mappings', $args)->expectsOutputToContain('"dry_run": true')->assertSuccessful();
        $this->assertNull($this->odp->fresh()->anchor_pop_id);
        $this->artisan('infrastructure:legacy-mappings', [...$args, '--apply' => true])->expectsOutputToContain('"dry_run": false')->assertSuccessful();
        $this->assertSame($this->miniPop->id, $this->odp->fresh()->anchor_pop_id);
    }

    #[Test]
    public function orphan_recovery_refuses_cycles_in_historical_parent_ids(): void
    {
        $mapping = $this->orphanForRecovery();
        $parentId = $mapping->evidence['parent_id'];
        $audit = AuditLog::where('auditable_type', NetworkNode::class)->where('auditable_id', $parentId)->where('action', 'create')->sole();
        $audit->update(['new_values' => [...$audit->new_values, 'parent_id' => $parentId]]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('melingkar');
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Cycle');
    }

    #[Test]
    public function orphan_recovery_refuses_live_ancestry_conflicting_with_audit(): void
    {
        $mapping = $this->orphanForRecovery();
        $this->chassis->update(['anchor_pop_id' => $this->cabang->id]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('berbeda dari bukti audit');
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Changed live ancestry');
    }

    #[Test]
    public function orphan_recovery_refuses_chain_ending_at_branch_instead_of_mini_pop(): void
    {
        $mapping = $this->orphanForRecovery();
        $this->chassis->update(['anchor_pop_id' => $this->cabang->id]);
        $audit = AuditLog::where('auditable_type', NetworkNode::class)->where('auditable_id', $this->chassis->id)->where('action', 'create')->sole();
        $audit->update(['new_values' => [...$audit->new_values, 'anchor_pop_id' => $this->cabang->id]]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mini POP');
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'No proven Mini POP');
    }

    #[Test]
    public function orphan_recovery_rejects_customers_outside_the_proven_branch(): void
    {
        $mapping = $this->orphanForRecovery();
        $other = Pop::factory()->create(['type' => 'cabang']);
        $customer = Customer::factory()->create(['pop_id' => $other->id]);
        $port = NetworkNodePort::create(['network_node_id' => $this->odp->id, 'number' => 1]);
        NetworkPortAssignment::create(['network_node_port_id' => $port->id, 'customer_id' => $customer->id, 'active_key' => 1, 'assigned_at' => now()]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope pelanggan aktif');
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Wrong customer branch', dryRun: false);
    }

    #[Test]
    public function orphan_recovery_does_not_allow_operator_outside_branch_scope(): void
    {
        $mapping = $this->orphanForRecovery();
        $outsider = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator tidak memiliki scope');
        $this->decisions()->restoreOrphanLocation($mapping, $outsider, 'Wrong actor');
    }

    #[Test]
    public function orphan_recovery_refuses_second_apply_after_location_is_restored(): void
    {
        $mapping = $this->orphanForRecovery();
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Proven', dryRun: false);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ODP yatim');
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Again', dryRun: false);
    }

    #[Test]
    public function restored_orphan_still_checks_proven_legacy_pon_ancestor(): void
    {
        $mapping = $this->orphanForRecovery();
        $this->decisions()->restoreOrphanLocation($mapping, $this->operator, 'Proven', dryRun: false);
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'PON confirmed');
        $other = app(InfrastructurePositionService::class)->createPort($slot, 2, InfrastructurePortKind::PON);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PON tujuan berbeda');
        $this->decisions()->mapOdpUplink($mapping->fresh(), $other, $this->operator, 'Different PON');
    }
}
