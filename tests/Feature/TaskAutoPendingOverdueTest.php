<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `tasks:auto-pending-overdue` — task `terjadwal`/`in_progress` yang tanggal
 * jadwalnya sudah lewat tapi belum `selesai` wajib di-pending (lepas tim,
 * balik ke antrian FOP) tiap tengah malam. Lihat docs/TASKS.md § catatan
 * Fase 2.3 & `routes/console.php`.
 */
class TaskAutoPendingOverdueTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $techUser;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function makeTask(string $taskNumber, string $status, ?Carbon $scheduledAt): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'started_at' => $status === TaskStatus::IN_PROGRESS->value ? now() : null,
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_terjadwal_task_overdue_kemarin_jadi_pending_dan_lepas_tim(): void
    {
        $task = $this->makeTask('TASK-2026-9201', TaskStatus::TERJADWAL->value, Carbon::yesterday());

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertStringContainsString('Otomatis', $task->pending_reason);
        $this->assertFalse($task->teamMembers()->where('user_id', $this->techUser->id)->exists());
    }

    public function test_in_progress_task_overdue_lama_juga_jadi_pending(): void
    {
        $task = $this->makeTask('TASK-2026-9202', TaskStatus::IN_PROGRESS->value, now()->subDays(5));

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
    }

    public function test_task_terjadwal_hari_ini_tidak_disentuh(): void
    {
        $task = $this->makeTask('TASK-2026-9203', TaskStatus::TERJADWAL->value, now());

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskStatus::TERJADWAL->value, $task->status->value);
        $this->assertTrue($task->teamMembers()->where('user_id', $this->techUser->id)->exists());
    }

    public function test_task_terjadwal_masa_depan_tidak_disentuh(): void
    {
        $task = $this->makeTask('TASK-2026-9204', TaskStatus::TERJADWAL->value, now()->addDays(3));

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskStatus::TERJADWAL->value, $task->status->value);
    }

    public function test_task_selesai_dibatalkan_dan_pending_tidak_disentuh(): void
    {
        $selesai = $this->makeTask('TASK-2026-9205', TaskStatus::SELESAI->value, Carbon::yesterday());
        $dibatalkan = $this->makeTask('TASK-2026-9206', TaskStatus::DIBATALKAN->value, Carbon::yesterday());
        $pending = $this->makeTask('TASK-2026-9207', TaskStatus::PENDING->value, Carbon::yesterday());

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $this->assertEquals(TaskStatus::SELESAI->value, $selesai->refresh()->status->value);
        $this->assertEquals(TaskStatus::DIBATALKAN->value, $dibatalkan->refresh()->status->value);
        $this->assertEquals(TaskStatus::PENDING->value, $pending->refresh()->status->value);
    }

    public function test_task_tanpa_scheduled_at_tidak_disentuh(): void
    {
        $task = $this->makeTask('TASK-2026-9208', TaskStatus::TERJADWAL->value, null);

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskStatus::TERJADWAL->value, $task->status->value);
    }

    public function test_fop_task_terkait_ikut_di_pending_dan_tim_dilepas(): void
    {
        $task = $this->makeTask('TASK-2026-9209', TaskStatus::IN_PROGRESS->value, Carbon::yesterday());

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-9209',
            'task_date' => Carbon::yesterday(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan FO Cut',
            'issue' => 'FO CUT di tiang 5',
            'status' => 'in_progress',
            'priority' => 'High',
            'pop_id' => $this->pop->id,
            'task_id' => $task->id,
        ]);
        $fopTask->technicians()->attach($this->techUser->id);

        $this->artisan('tasks:auto-pending-overdue')->assertExitCode(0);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $fopTask->status->value);
        $this->assertNull($fopTask->team_id);
        $this->assertCount(0, $fopTask->technicians);
    }
}
