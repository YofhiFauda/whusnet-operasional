<?php

namespace Tests\Feature\Network;

use App\Models\AuditLog;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructureReconciliationRun;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructureLegacyBackfillService;
use App\Services\InfrastructureReconciliationService;
use App\Support\InfrastructureCutover;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InfrastructureCutoverEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private InfrastructureReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'), 'anchor_pop_id' => $mini->id]);
        $this->service = app(InfrastructureReconciliationService::class);
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(0, 40));
    }

    public function test_current_future_and_invalid_periods_are_rejected(): void
    {
        foreach (['2026-11', '2026-12', '2026-99', 'invalid'] as $period) {
            $this->artisan('infrastructure:reconcile', ['--period' => $period])->assertFailed();
        }
        $this->assertSame(0, InfrastructureReconciliationRun::count());
    }

    public function test_one_manual_or_scheduled_snapshot_is_not_cycle_evidence(): void
    {
        $this->service->run('2026-10');
        $this->service->run('2026-10', trigger: 'schedule');
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
    }

    private function recordCycle(): void
    {
        for ($day = 1; $day <= 31; $day++) {
            $this->travelTo(now()->setDate(2026, 10, $day)->setTime(0, 35));
            $this->service->observe(trigger: 'schedule');
        }
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(0, 40));
        $this->service->run('2026-10', trigger: 'schedule');
    }

    public function test_complete_scheduled_cycle_and_all_mismatches_are_checked(): void
    {
        $this->recordCycle();
        $this->assertTrue($this->service->zeroForPeriod('2026-10'));
        InfrastructureReconciliationRun::where('period', '2026-10')->firstOrFail()->update(['is_zero' => false, 'diff' => ['odp' => -1]]);
        $this->service->run('2026-10', trigger: 'schedule');
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
    }

    public function test_missing_observation_and_manual_replacement_do_not_prove_cycle(): void
    {
        $this->recordCycle();
        InfrastructureReconciliationRun::whereDate('observed_at', '2026-10-15')->delete();
        $this->travelTo(now()->setDate(2026, 10, 15));
        $this->service->observe();
        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
    }

    public function test_close_command_rejects_missing_evidence(): void
    {
        $this->artisan('infrastructure:close-legacy-writes', ['--period' => '2026-10'])->assertFailed();
        $this->assertTrue(InfrastructureCutover::legacyWritesEnabled());
        $this->assertFalse(AuditLog::where('action', 'legacy_writes_closed')->exists());
    }

    private function evidence(): array
    {
        return array_fill(1, 7, ['status' => 'complete', 'reference' => 'Acceptance evidence, release 2026-11']);
    }

    public function test_closure_requires_seven_references_and_cycle_then_is_audited(): void
    {
        $this->recordCycle();
        $operator = User::factory()->create();
        foreach (range(1, 7) as $missing) {
            $evidence = $this->evidence();
            unset($evidence[$missing]);
            try {
                $this->service->closeLegacyWrites('2026-10', $evidence, $operator);
                $this->fail('Missing requirement must block closure.');
            } catch (ValidationException) {
                $this->assertTrue(InfrastructureCutover::legacyWritesEnabled());
            }
        }
        $this->service->closeLegacyWrites('2026-10', $this->evidence(), $operator);
        $this->assertFalse(InfrastructureCutover::legacyWritesEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'legacy_writes_closed', 'user_id' => $operator->id]);
    }

    public function test_ambiguous_mapping_blocks_closure(): void
    {
        $this->recordCycle();
        InfrastructureLegacyMapping::create(['legacy_type' => 'pon_port_node', 'legacy_id' => 999, 'status' => InfrastructureLegacyMapping::AMBIGUOUS, 'reason' => 'Unknown slot']);
        $this->expectException(ValidationException::class);
        $this->service->closeLegacyWrites('2026-10', $this->evidence(), User::factory()->create());
    }

    public function test_live_mismatch_blocks_stale_zero_cycle(): void
    {
        $this->recordCycle();
        $orphan = NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', 'odp')->value('id')]);
        NetworkNode::whereKey($orphan->id)->update(['anchor_pop_id' => null, 'parent_id' => null]);
        try {
            $this->service->closeLegacyWrites('2026-10', $this->evidence(), User::factory()->create());
            $this->fail('Stale evidence must not close legacy writes.');
        } catch (ValidationException) {
            $this->assertTrue(InfrastructureReconciliationRun::where('period', '2026-10')->where('is_zero', false)->exists());
            $this->assertFalse(AuditLog::where('action', 'legacy_writes_closed')->exists());
        }
    }

    public function test_empty_population_does_not_prove_cycle(): void
    {
        NetworkNode::query()->delete();
        $this->recordCycle();
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
    }

    public function test_emergency_env_fallback_is_audited_without_cycle_completion(): void
    {
        config(['infrastructure.legacy_writes_enabled' => false, 'infrastructure.legacy_emergency_reason' => 'Incident INC-42']);
        $this->assertFalse(InfrastructureCutover::legacyWritesEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'legacy_writes_emergency_disabled']);
        $this->assertFalse(AuditLog::where('action', 'legacy_writes_closed')->exists());
    }

    public function test_missing_slot_or_card_mapping_is_a_reconciliation_difference(): void
    {
        $mini = Pop::where('type', 'mini_pop')->firstOrFail();
        $chassis = NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'), 'anchor_pop_id' => $mini->id]);
        // 'olt_card' dihapus dari seeder master 2026-10-23 (ADHOC-212) —
        // provisioning sendiri, lihat InfrastructureLegacyDecisionTest.
        $cardTypeId = NetworkNodeType::firstOrCreate(['code' => 'olt_card'], ['name' => 'olt_card', 'has_ports' => true])->id;
        $card = NetworkNode::factory()->create(['node_type_id' => $cardTypeId, 'parent_id' => $chassis->id]);
        $legacy = OltSlot::create(['chassis_node_id' => $chassis->id, 'number' => 1, 'card_node_id' => $card->id]);
        $rows = $this->service->run('2026-10');
        $row = $rows->firstWhere('pop_id', $mini->parent_id);
        $this->assertFalse($row->is_zero);
        $this->assertSame(-1, $row->diff['legacy_slots']);
        $this->assertSame(-1, $row->diff['legacy_cards']);
        app(InfrastructureLegacyBackfillService::class)->syncOltSlot($legacy);
        $row = $this->service->run('2026-10')->firstWhere('pop_id', $mini->parent_id);
        $this->assertSame(0, $row->diff['legacy_slots']);
        $this->assertSame(0, $row->diff['legacy_cards']);
    }

    public function test_endpoint_fk_repair_is_idempotent_and_preserves_references(): void
    {
        $path = glob(database_path('migrations/*repair_infrastructure_action_endpoint_foreign_keys.php'))[0];
        $migration = require $path;
        $migration->up();
        $migration->up();
        foreach (['end_from_infrastructure_port_id', 'end_to_infrastructure_port_id'] as $column) {
            $this->assertTrue(Schema::hasForeignKey('task_infrastructure_actions', [$column]));
        }
        $migration->down();
        $migration->down();
        foreach (['end_from_infrastructure_port_id', 'end_to_infrastructure_port_id'] as $column) {
            $this->assertFalse(Schema::hasForeignKey('task_infrastructure_actions', [$column]));
        }
        $migration->up();
        foreach (['end_from_infrastructure_port_id', 'end_to_infrastructure_port_id'] as $column) {
            $this->assertTrue(Schema::hasForeignKey('task_infrastructure_actions', [$column]));
        }
    }

    public function test_finalization_must_be_scheduled_on_first_day_and_old_snapshots_do_not_supply_coverage(): void
    {
        $this->recordCycle();
        InfrastructureReconciliationRun::where('kind', 'closing')->delete();
        $this->travelTo(now()->setDate(2026, 11, 2));
        $this->service->run('2026-10', trigger: 'schedule');
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->service->run('2026-10', trigger: 'schedule');
        InfrastructureReconciliationRun::where('kind', 'observation')->update(['evidence_version' => null, 'observed_at' => null]);
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
    }

    public function test_mismatch_observed_with_different_period_label_still_blocks_cycle(): void
    {
        $this->recordCycle();
        $row = InfrastructureReconciliationRun::where('kind', 'observation')->firstOrFail();
        $row->update(['period' => '2026-09', 'is_zero' => false]);
        $this->assertFalse($this->service->zeroForPeriod('2026-10'));
    }

    public function test_manual_command_cannot_claim_scheduler_origin_or_backdate_observations(): void
    {
        $this->artisan('infrastructure:reconcile', ['--observe' => true, '--period' => '2026-10'])->assertFailed();
        $this->artisan('infrastructure:reconcile', ['--observe' => true])->assertSuccessful();
        $this->assertSame(['manual'], InfrastructureReconciliationRun::query()->distinct()->pluck('trigger')->all());
        $this->assertSame(['2026-11'], InfrastructureReconciliationRun::query()->distinct()->pluck('period')->all());
        $this->assertSame('2026-11-01', InfrastructureReconciliationRun::firstOrFail()->observed_at->toDateString());
    }

    public function test_close_command_reads_seven_evidence_items_and_persists_audited_switch(): void
    {
        $this->recordCycle();
        $operator = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'infra-cutover-');
        try {
            file_put_contents($path, json_encode($this->evidence()));
            $this->artisan('infrastructure:close-legacy-writes', ['--period' => '2026-10', '--actor' => $operator->id, '--evidence' => $path])->assertSuccessful();
            $audit = AuditLog::where('action', 'legacy_writes_closed')->sole();
            $this->assertSame($this->evidence(), $audit->new_values['evidence']);
            $this->assertSame('2026-10', $audit->new_values['period']);
            config(['infrastructure.legacy_writes_enabled' => true]);
            $this->assertFalse(InfrastructureCutover::legacyWritesEnabled());
        } finally {
            unlink($path);
        }
    }

    public function test_stale_nonempty_cycle_cannot_close_after_population_disappears(): void
    {
        $this->recordCycle();
        NetworkNode::query()->delete();
        $this->expectException(ValidationException::class);
        $this->service->closeLegacyWrites('2026-10', $this->evidence(), User::factory()->create());
    }

    public function test_endpoint_repair_recreates_missing_fk_in_bootstrap_schema(): void
    {
        Schema::table('task_infrastructure_actions', function (Blueprint $table): void {
            $table->dropForeign(['end_from_infrastructure_port_id']);
            $table->dropForeign(['end_to_infrastructure_port_id']);
        });
        $this->assertFalse(Schema::hasForeignKey('task_infrastructure_actions', ['end_from_infrastructure_port_id']));
        $migration = require glob(database_path('migrations/*repair_infrastructure_action_endpoint_foreign_keys.php'))[0];
        $migration->up();
        $migration->up();
        foreach (['end_from_infrastructure_port_id', 'end_to_infrastructure_port_id'] as $column) {
            $keys = collect(Schema::getForeignKeys('task_infrastructure_actions'));
            $key = $keys->first(fn (array $key): bool => $key['columns'] === [$column]);
            $this->assertSame('infrastructure_ports', $key['foreign_table']);
            $this->assertSame(['id'], $key['foreign_columns']);
            $this->assertSame('restrict', $key['on_delete']);
        }
    }

    public function test_valid_seven_attestations_cannot_replace_missing_automatic_cycle_evidence(): void
    {
        $this->service->run('2026-10');
        $this->expectException(ValidationException::class);
        $this->service->closeLegacyWrites('2026-10', $this->evidence(), User::factory()->create());
    }

    public function test_emergency_audit_is_written_once_per_config_reason(): void
    {
        config(['infrastructure.legacy_writes_enabled' => false, 'infrastructure.legacy_emergency_reason' => 'Incident INC-42']);
        InfrastructureCutover::legacyWritesEnabled();
        InfrastructureCutover::legacyWritesEnabled();
        $this->assertSame(1, AuditLog::where('action', 'legacy_writes_emergency_disabled')->count());
    }
}
