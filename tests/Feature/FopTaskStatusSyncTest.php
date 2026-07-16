<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FopTaskStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $techUser;
    protected User $fopUser;
    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);
        $this->seed(\Database\Seeders\WorkflowTransitionPermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->techUser = User::factory()->create();
        $techRole = Role::where('code', 'teknisi')->first();
        $this->techUser->role_id = $techRole->id;
        $this->techUser->save();

        $this->techUser->roleScopes()->create([
            'role_id' => $techRole->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);

        $fopRole = Role::where('code', 'fop')->first();
        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
    }

    protected function makeLinkedTask(string $taskNumber, string $status = 'terjadwal'): array
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => $status,
            'scheduled_at' => now(),
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-' . $taskNumber,
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan',
            'issue' => 'FO CUT',
            'status' => $status,
            'priority' => 'High',
            'task_id' => $task->id,
        ]);

        return [$task, $fopTask];
    }

    public function test_task_in_progress_syncs_fop_task_to_in_progress(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9201');

        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::IN_PROGRESS->value, $fopTask->status->value);

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertNotNull($history);
        $this->assertEquals('in_progress', $history->to_status);
        $this->assertEquals('Sedang Dikerjakan', $history->label());
    }

    public function test_task_pending_with_report_deferred_syncs_lapor_nanti_label(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9203', 'in_progress');

        $task->update([
            'status' => TaskStatus::PENDING,
            'pending_reason' => 'Sinyal jelek',
            'report_deferred' => true,
        ]);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $fopTask->status->value);

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertEquals('lapor_nanti', $history->to_status);
        $this->assertEquals('Lapor Nanti', $history->label());
    }

    public function test_task_pending_without_report_deferred_syncs_pending_fop_label(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9204', 'in_progress');

        $task->update([
            'status' => TaskStatus::PENDING,
            'pending_reason' => 'FOP set pending',
        ]);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $fopTask->status->value);

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertEquals('pending_fop', $history->to_status);
    }

    public function test_task_selesai_with_pending_review_syncs_selesai_menunggu_verifikasi_label(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9205', 'in_progress');

        $task->update(['status' => TaskStatus::SELESAI, 'fop_review_status' => 'pending', 'completed_at' => now()]);

        $fopTask->refresh();
        // FopTask.status sekarang mirror Task.status apa adanya — laporan yang
        // masih ditinjau TETAP status 'selesai' (bukan didemosikan balik ke
        // in_progress), nuansa "perlu review" cuma di label histori/badge.
        $this->assertEquals(TaskStatus::SELESAI->value, $fopTask->status->value);

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertEquals('selesai_menunggu_verifikasi', $history->to_status);
        $this->assertEquals('Selesai — Menunggu Verifikasi', $history->label());
    }

    public function test_task_approved_syncs_fop_task_to_selesai(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9206', 'selesai');
        $task->update(['fop_review_status' => 'pending']);
        FopTaskStatusHistory::query()->delete();

        $task->update(['fop_review_status' => 'approved']);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::SELESAI->value, $fopTask->status->value);

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertEquals('selesai', $history->to_status);
    }

    public function test_task_dibatalkan_syncs_fop_task_to_dibatalkan(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9207', 'terjadwal');

        $task->update(['status' => TaskStatus::DIBATALKAN, 'cancelled_at' => now(), 'cancel_reason' => 'Data ganda']);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::DIBATALKAN->value, $fopTask->status->value);
    }

    public function test_no_history_written_when_task_has_no_linked_fop_task(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-9208',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin (no FopTask)',
            'status' => 'terjadwal',
            'scheduled_at' => now(),
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        $this->assertEquals(0, FopTaskStatusHistory::count());
    }

    public function test_manually_cancelled_fop_task_is_not_overwritten_by_later_task_sync(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9209', 'in_progress');

        $fopTask->update(['status' => TaskStatus::DIBATALKAN]);
        FopTaskStatusHistory::query()->delete();

        $task->update(['status' => TaskStatus::SELESAI, 'fop_review_status' => 'pending', 'completed_at' => now()]);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::DIBATALKAN->value, $fopTask->status->value);
        $this->assertEquals(0, FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->count());
    }

    public function test_no_op_change_still_ignored_when_status_unchanged(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9210', 'terjadwal');

        $task->update(['title' => 'Judul Baru Tanpa Ganti Status']);

        $this->assertEquals(0, FopTaskStatusHistory::count());
    }

    public function test_cancelling_fop_task_also_cancels_linked_task(): void
    {
        [$task, $fopTask] = $this->makeLinkedTask('TASK-9211', 'in_progress');

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), ['status' => 'dibatalkan'])
            ->assertOk();

        $task->refresh();
        $this->assertEquals(TaskStatus::DIBATALKAN->value, $task->status->value);
    }

    public function test_cancelling_fop_task_without_linked_task_does_not_error(): void
    {
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-9212',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan',
            'issue' => 'FO CUT',
            'status' => 'draft',
            'priority' => 'High',
        ]);

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), ['status' => 'dibatalkan'])
            ->assertOk();

        $this->assertEquals(TaskStatus::DIBATALKAN->value, $fopTask->fresh()->status->value);
    }
}
