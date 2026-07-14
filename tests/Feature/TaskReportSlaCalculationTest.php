<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskReportSlaCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $techUser;
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeTask(string $taskNumber, int $slaMinutes = 120): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'sla_minutes' => $slaMinutes,
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_single_cycle_task_completed_without_ever_pending(): void
    {
        Carbon::setTestNow(now());
        $task = $this->makeTask('TASK-9401', slaMinutes: 120);

        $task->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);

        Carbon::setTestNow(now()->addMinutes(90));
        $task->update(['status' => TaskStatus::SELESAI->value, 'completed_at' => now()]);

        $report = TaskReport::where('task_id', $task->id)->first();

        $this->assertNotNull($report);
        $this->assertEquals(90, $report->total_duration_minutes);
        $this->assertEquals(120, $report->sla_target_minutes);
        $this->assertEquals('on_time', $report->sla_status);
        $this->assertEquals(0, $report->sla_overrun_minutes);
    }

    public function test_dual_cycle_accumulates_work_minutes_and_excludes_pending_gap(): void
    {
        Carbon::setTestNow(now());
        $task = $this->makeTask('TASK-9402', slaMinutes: 120);

        $task->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);

        // Siklus 1: 80 menit kerja, lalu Pending (Lapor Nanti / pending_fop).
        Carbon::setTestNow(now()->addMinutes(80));
        $task->update(['status' => TaskStatus::PENDING->value, 'pending_reason' => 'Nunggu alat']);

        // Jeda 3 jam sebelum dilanjut lagi — TIDAK boleh ikut terhitung sebagai waktu kerja.
        Carbon::setTestNow(now()->addHours(3));
        $task->update(['status' => TaskStatus::IN_PROGRESS->value]);

        // Siklus 2: 90 menit kerja lagi, lalu selesai.
        Carbon::setTestNow(now()->addMinutes(90));
        $task->update(['status' => TaskStatus::SELESAI->value, 'completed_at' => now()]);

        $report = TaskReport::where('task_id', $task->id)->first();

        $this->assertNotNull($report);
        $this->assertNotNull($report->pending_at);
        $this->assertNotNull($report->resumed_at);
        $this->assertEquals(170, $report->total_duration_minutes); // 80 + 90, jeda 3 jam TIDAK ikut
        $this->assertEquals('over', $report->sla_status);
        $this->assertEquals(50, $report->sla_overrun_minutes); // 170 - 120
    }

    public function test_reschedule_pause_accumulates_same_as_pending(): void
    {
        Carbon::setTestNow(now());
        $task = $this->makeTask('TASK-9403', slaMinutes: 200);

        $task->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);

        Carbon::setTestNow(now()->addMinutes(30));
        $task->update(['status' => TaskStatus::RESCHEDULE->value, 'pending_reason' => 'Pelanggan minta hari lain']);

        $report = TaskReport::where('task_id', $task->id)->first();

        $this->assertNotNull($report);
        $this->assertEquals(30, $report->total_duration_minutes);
        $this->assertNotNull($report->pending_at);
        $this->assertNull($report->completed_at);
    }

    public function test_task_report_not_created_before_first_start(): void
    {
        $task = $this->makeTask('TASK-9404');

        $this->assertNull(TaskReport::where('task_id', $task->id)->first());
    }

    /**
     * Regression: sla_target_minutes harus sama persis dengan Task::sla_minutes
     * (snapshot dari TaskType::slaMinutes() saat task dibuat) — BUKAN lookup ke
     * PackageSlaSetting (beda konsep: handling SLA vs SLA pengerjaan).
     */
    public function test_sla_target_minutes_matches_task_sla_minutes_not_package_sla_setting(): void
    {
        Carbon::setTestNow(now());
        $task = $this->makeTask('TASK-9405', slaMinutes: 180);

        $task->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);

        $report = TaskReport::where('task_id', $task->id)->first();

        $this->assertEquals($task->sla_minutes, $report->sla_target_minutes);
        $this->assertEquals(180, $report->sla_target_minutes);
    }

    public function test_task_report_is_written_even_without_linked_fop_task(): void
    {
        // Task 10 sengaja independen dari FopTask (beda dari sync status Task 9) —
        // durasi kerja harus tetap tercatat walau task ini gak punya FopTask terhubung.
        Carbon::setTestNow(now());
        $task = $this->makeTask('TASK-9406');
        $this->assertNull(FopTask::where('task_id', $task->id)->first());

        $task->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);

        $this->assertNotNull(TaskReport::where('task_id', $task->id)->first());
    }
}
