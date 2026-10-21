<?php

namespace Tests\Feature;

use App\Enums\InfrastructurePortKind;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\Village;
use App\Services\EffectiveAccessService;
use App\Services\FopTaskInfrastructureScopeService;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructurePositionService;
use App\Support\InfrastructureLocation;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Akar scope Task FOP O-REQ/INFR-REQ = batas subtree (§10.3). Lokasi aksi sah
 * = izin POP aktor ∩ Cabang task ∩ subtree akar scope.
 */
class FopTaskInfrastructureScopeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private Pop $miniA1;

    private Pop $miniA2;

    private Pop $miniB1;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    private NetworkNode $switchA;

    private InfrastructureSlot $slotA1;

    private InfrastructureSlot $slotA2;

    private InfrastructurePort $ponA11;

    private InfrastructurePort $ponB11;

    private InfrastructurePort $switchPort;

    private NetworkNode $odpA;

    private NetworkNode $odpB;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);

        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang A']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang B']);
        $this->miniA1 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id, 'name' => 'A-M1']);
        $this->miniA2 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id, 'name' => 'A-M2']);
        $this->miniB1 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangB->id, 'name' => 'B-M1']);

        $positions = app(InfrastructurePositionService::class);
        $this->oltA = $this->node('olt_chassis', $this->miniA1, 'OLT-A');
        $this->oltB = $this->node('olt_chassis', $this->miniA1, 'OLT-B');
        $this->switchA = $this->node('switch_mini_pop', $this->miniA1, 'SW-A');
        $this->slotA1 = $positions->createSlot($this->oltA, 1);
        $this->slotA2 = $positions->createSlot($this->oltA, 2);
        $this->ponA11 = $positions->createPort($this->slotA1, 1, InfrastructurePortKind::PON);
        $this->ponB11 = $positions->createPort($positions->createSlot($this->oltB, 1), 1, InfrastructurePortKind::PON);
        $this->switchPort = $positions->createPort($positions->ensureLogicalSlot($this->switchA), 1, InfrastructurePortKind::SWITCH);
        $this->odpA = $this->node('odp', $this->miniA1, 'ODP-A');
        $this->odpB = $this->node('odp', $this->miniA1, 'ODP-B');
        $connections = app(InfrastructureConnectionService::class);
        $connections->connect($this->ponA11, $this->owner, targetNode: $this->odpA, verified: true);
        $connections->connect($this->ponB11, $this->owner, targetNode: $this->odpB, verified: true);
    }

    private function node(string $typeCode, Pop $anchor, string $code): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $anchor->id,
            'code' => $code,
            'name' => $code,
        ]);
    }

    private function fopTask(Pop $pop, string $category = 'INFR REQ'): FopTask
    {
        return FopTask::create([
            'task_number' => 'TFOP-SCOPE-'.fake()->unique()->numerify('####'),
            'task_date' => now(),
            'category' => $category,
            'tugas' => 'Uji scope',
            'pop_id' => $pop->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);
    }

    private function resolver(): InfrastructureLocationResolver
    {
        return app(InfrastructureLocationResolver::class);
    }

    private function scopes(): FopTaskInfrastructureScopeService
    {
        return app(FopTaskInfrastructureScopeService::class);
    }

    private function allowed(FopTask $task, InfrastructureLocation $location, ?User $actor = null): bool
    {
        try {
            $this->scopes()->assertLocationInTaskScope($task, $location, $actor ?? $this->owner);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @param  array<string, int|null>  $input
     */
    private function scopedTask(array $input, ?Pop $pop = null): FopTask
    {
        $task = $this->fopTask($pop ?? $this->cabangA);
        $root = $this->scopes()->resolveRequestedRoot($task->pop, $input);
        $this->scopes()->applyScope($task, $root, $this->owner);

        return $task->fresh();
    }

    private function selectedPopUser(Pop ...$pops): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        foreach ($pops as $pop) {
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        }
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function resolver_menurunkan_leluhur_berbasis_id_termasuk_odp_lewat_uplink_pon(): void
    {
        $location = $this->resolver()->forNode($this->odpA);

        $this->assertSame(
            ['pop:'.$this->cabangA->id, 'mini_pop:'.$this->miniA1->id, 'node:'.$this->oltA->id, 'slot:'.$this->slotA1->id, 'port:'.$this->ponA11->id, 'node:'.$this->odpA->id],
            $location->ancestorKeys(),
        );
        $this->assertSame($this->cabangA->id, $location->cabangId);
        $this->assertSame('Cabang A → A-M1 → OLT-A → Slot 1 → PON 1 → ODP-A', $location->breadcrumb());
    }

    #[Test]
    public function root_kosong_jatuh_ke_pop_cabang_task(): void
    {
        $task = $this->fopTask($this->cabangA);

        $root = $this->scopes()->rootFor($task);

        $this->assertSame(InfrastructureLocation::POP, $root->kind);
        $this->assertSame($this->cabangA->id, $root->id);
        $this->assertTrue($this->allowed($task, $this->resolver()->forPop($this->miniA2)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forPop($this->miniB1)));
    }

    #[Test]
    public function scope_pop_mencakup_pop_itu_dan_semua_turunannya(): void
    {
        $task = $this->scopedTask([]);

        $this->assertTrue($this->allowed($task, $this->resolver()->forPop($this->cabangA)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forPort($this->switchPort)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forNode($this->odpB)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forPop($this->cabangB)));
    }

    #[Test]
    public function scope_mini_pop_menolak_mini_pop_saudara_dan_induk(): void
    {
        $task = $this->scopedTask(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->assertSame('mini_pop:'.$this->miniA1->id, $this->scopes()->rootFor($task)->key());
        $this->assertTrue($this->allowed($task, $this->resolver()->forPop($this->miniA1)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forNode($this->oltB)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forPop($this->miniA2)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forPop($this->cabangA)));
    }

    #[Test]
    public function scope_olt_mencakup_slot_pon_odp_turunan_dan_menolak_saudara(): void
    {
        $task = $this->scopedTask(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id]);

        $this->assertTrue($this->allowed($task, $this->resolver()->forNode($this->oltA)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forSlot($this->slotA2)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forPort($this->ponA11)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forNode($this->odpA)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forNode($this->oltB)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forNode($this->odpB)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forNode($this->switchA)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forPop($this->miniA1)));
    }

    #[Test]
    public function scope_slot_menolak_slot_lain_pada_olt_sama(): void
    {
        $task = $this->scopedTask(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id, 'scope_slot_id' => $this->slotA1->id]);

        $this->assertTrue($this->allowed($task, $this->resolver()->forPort($this->ponA11)));
        $this->assertTrue($this->allowed($task, $this->resolver()->forNode($this->odpA)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forSlot($this->slotA2)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forNode($this->oltA)));
    }

    #[Test]
    public function scope_switch_mencakup_slot_dan_port_switch_saja(): void
    {
        $task = $this->scopedTask(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->switchA->id]);

        $this->assertTrue($this->allowed($task, $this->resolver()->forPort($this->switchPort)));
        $this->assertFalse($this->allowed($task, $this->resolver()->forNode($this->oltA)));
    }

    #[Test]
    public function akar_scope_dengan_celah_atau_induk_salah_ditolak(): void
    {
        $task = $this->fopTask($this->cabangA);
        $cases = [
            'perangkat tanpa mini pop' => ['scope_device_id' => $this->oltA->id],
            'mini pop cabang lain' => ['scope_mini_pop_id' => $this->miniB1->id],
            'perangkat bukan anak mini pop' => ['scope_mini_pop_id' => $this->miniA2->id, 'scope_device_id' => $this->oltA->id],
            'slot milik perangkat lain' => ['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltB->id, 'scope_slot_id' => $this->slotA1->id],
            'odp di bawah switch' => ['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->switchA->id, 'scope_slot_id' => $this->switchPort->infrastructure_slot_id, 'scope_port_id' => $this->switchPort->id, 'scope_odp_id' => $this->odpA->id],
            'odp bukan turunan pon' => ['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id, 'scope_slot_id' => $this->slotA1->id, 'scope_port_id' => $this->ponA11->id, 'scope_odp_id' => $this->odpB->id],
            'odp bukan perangkat' => ['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->odpA->id],
        ];

        foreach ($cases as $label => $input) {
            try {
                $this->scopes()->resolveRequestedRoot($task->pop, $input);
                $this->fail("Kasus '{$label}' seharusnya ditolak.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function aktor_satu_cabang_tidak_boleh_aksi_atau_scope_di_cabang_lain(): void
    {
        $actorA = $this->selectedPopUser($this->cabangA);
        $taskB = $this->fopTask($this->cabangB);

        $this->assertFalse($this->allowed($taskB, $this->resolver()->forPop($this->miniB1), $actorA));

        $this->expectException(ValidationException::class);
        $this->scopes()->applyScope($taskB, $this->resolver()->forPop($this->miniB1), $actorA);
    }

    #[Test]
    public function perubahan_scope_setelah_assignment_diaudit_dan_laporan_lama_tetap_memakai_snapshot_lama(): void
    {
        $task = $this->scopedTask(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id]);
        $executionTask = Task::create([
            'task_number' => 'TASK-SCOPE-'.uniqid(),
            'pop_id' => $this->cabangA->id,
            'task_type' => 'INFR REQ',
            'title' => 'Uji',
            'status' => 'in_progress',
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);
        $task->update(['task_id' => $executionTask->id]);
        $action = TaskInfrastructureAction::create([
            'task_id' => $executionTask->id,
            'fop_task_id' => $task->id,
            'action_key' => (string) Str::uuid(),
            'action_type' => 'inspect',
            'mutation_hash' => str_repeat('a', 64),
            'mutation' => ['action_type' => 'inspect'],
            'performed_by' => $this->owner->id,
            'performed_at' => now(),
            'scope_snapshot' => $task->scope_snapshot,
        ]);

        $this->scopes()->applyScope($task, $this->resolver()->forPop($this->miniA1), $this->owner);

        $task->refresh();
        $this->assertSame($this->miniA1->id, $task->scope_pop_id);
        $this->assertNull($task->scope_network_node_id);
        $this->assertSame('node:'.$this->oltA->id, $action->fresh()->scope_snapshot['key']);
        $this->assertSame('mini_pop:'.$this->miniA1->id, $task->scope_snapshot['key']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => FopTask::class, 'auditable_id' => $task->id, 'action' => 'scope_change']);
    }

    #[Test]
    public function form_fop_menyimpan_akar_scope_terdalam(): void
    {
        $user = $this->selectedPopUser($this->cabangA);
        $technician = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id, 'status' => 'active']);
        $village = Village::create([
            'district_id' => District::create(['city_id' => City::create(['name' => 'Kota'])->id, 'name' => 'Kec'])->id,
            'name' => 'Desa', 'postal_code' => '63491',
        ]);

        $this->actingAs($user)->post(route('fop-tasks.store'), [
            'category' => 'INFR REQ',
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => 'Ganti kartu OLT-A',
            'village_id' => $village->id,
            'pop_id' => $this->cabangA->id,
            'issue' => 'Kartu mati',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$technician->id],
            'scope_mini_pop_id' => $this->miniA1->id,
            'scope_device_id' => $this->oltA->id,
            'scope_slot_id' => $this->slotA1->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('fop-tasks.index'));

        $task = FopTask::where('tugas', 'Ganti kartu OLT-A')->firstOrFail();
        $this->assertSame($this->slotA1->id, $task->scope_infrastructure_slot_id);
        $this->assertNull($task->scope_network_node_id);
        $this->assertSame('Cabang A → A-M1 → OLT-A → Slot 1', $task->scope_snapshot['breadcrumb']);
    }

    #[Test]
    public function form_fop_menolak_scope_untuk_kategori_non_infrastruktur(): void
    {
        $task = $this->fopTask($this->cabangA, 'MTN');

        $this->expectException(ValidationException::class);
        $this->scopes()->applyScope($task, $this->resolver()->forPop($this->miniA1), $this->owner);
    }
}
