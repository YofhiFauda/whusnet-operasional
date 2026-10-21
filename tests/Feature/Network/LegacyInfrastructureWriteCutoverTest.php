<?php

namespace Tests\Feature\Network;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Models\FopTask;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jalur tulis representasi lama (§16.4 #1, #4) ditutup lewat SATU saklar
 * `infrastructure.legacy_writes_enabled`. Default TERBUKA: data aplikasi
 * nyata (audit Task 1) — rancangan melarang menutupnya sebelum tujuh bukti
 * cutover lengkap. Begitu ditutup, baris lama tetap terbaca.
 */
class LegacyInfrastructureWriteCutoverTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $cabang;

    private NetworkNode $chassis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->chassis = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id])->id,
        ]);
    }

    private function closeLegacyWrites(): void
    {
        config(['infrastructure.legacy_writes_enabled' => false]);
    }

    #[Test]
    public function default_jalur_lama_masih_terbuka_selama_bukti_cutover_belum_lengkap(): void
    {
        $this->assertTrue(config('infrastructure.legacy_writes_enabled'));

        $this->actingAs($this->owner)->post(route('network.nodes.olt-slots.store', $this->chassis), ['slot_count' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, OltSlot::count());
    }

    #[Test]
    public function slot_olt_lama_tidak_bisa_ditulis_setelah_cutover(): void
    {
        $this->closeLegacyWrites();

        $this->actingAs($this->owner)->post(route('network.nodes.olt-slots.store', $this->chassis), ['slot_count' => 2])
            ->assertSessionHasErrors('slot_count');
        $this->assertSame(0, OltSlot::count());
    }

    #[Test]
    public function kartu_olt_lama_tidak_bisa_dipasang_ke_slot_lama_setelah_cutover(): void
    {
        $slot = OltSlot::create(['chassis_node_id' => $this->chassis->id, 'number' => 1]);
        $card = NetworkNode::factory()->child($this->chassis)->create(['node_type_id' => NetworkNodeType::where('code', 'olt_card')->value('id')]);
        $this->closeLegacyWrites();

        $this->actingAs($this->owner)->post(route('network.nodes.olt-slots.card', [$this->chassis, $slot]), ['network_node_id' => $card->id])
            ->assertSessionHasErrors('network_node_id');
        $this->assertNull($slot->fresh()->card_node_id);
    }

    #[Test]
    public function node_pon_dan_kartu_lama_tidak_bisa_dibuat_setelah_cutover(): void
    {
        $this->closeLegacyWrites();

        foreach (['pon_port', 'olt_card'] as $code) {
            $this->actingAs($this->owner)->post(route('network.nodes.store'), [
                'code' => 'LAMA-'.$code, 'name' => 'Node lama', 'node_type_id' => NetworkNodeType::where('code', $code)->value('id'),
                'parent_id' => $this->chassis->id, 'placement' => 'node',
            ])->assertSessionHasErrors('node_type_id');
        }
        $this->assertFalse(NetworkNode::where('code', 'like', 'LAMA-%')->exists());
    }

    #[Test]
    public function laporan_format_node_lama_ditolak_setelah_cutover_dan_baris_lama_tetap_terbaca(): void
    {
        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        $technician = User::factory()->create(['role_id' => $teknisi->id]);
        $technician->roleScopes()->create(['role_id' => $teknisi->id, 'scope_type' => ScopeType::ALL_POP]);
        $task = Task::create([
            'task_number' => 'TASK-CUT-1', 'pop_id' => $this->cabang->id, 'task_type' => 'INFR REQ', 'title' => 'Uji',
            'status' => TaskStatus::IN_PROGRESS->value, 'created_by' => $technician->id, 'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);
        FopTask::create(['task_number' => 'TFOP-CUT-1', 'task_date' => now(), 'category' => 'INFR REQ', 'tugas' => 'Uji',
            'pop_id' => $this->cabang->id, 'task_id' => $task->id, 'issue' => 'Uji',
            'status' => TaskStatus::DRAFT->value, 'priority' => FopTaskPriority::MEDIUM->value]);
        $this->closeLegacyWrites();

        $this->actingAs($technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Format lama.',
            'infra_actions' => [['action_key' => (string) Str::uuid(), 'action_type' => 'inspect', 'network_node_id' => $this->chassis->id]],
        ])->assertSessionHasErrors('infra_actions');

        $this->actingAs($technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Form tunggal lama.',
            'infra_target_network_node_id' => $this->chassis->id,
            'infra_material_action' => 'retrieve',
            'infra_material_source_transaction_id' => 999,
            'infra_material_qty' => 1,
            'infra_material_reason' => 'Uji',
        ])->assertSessionHasErrors('infra_target_network_node_id');

        $this->assertSame(0, TaskInfrastructureAction::count());
        $this->withoutVite();
        $this->actingAs($this->owner)->get(route('network.nodes.show', $this->chassis))->assertOk();
    }
}
