<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TaskOwnSortingTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $this->pop = Pop::create([
            'code' => 'SMN10',
            'pop_code' => 'SMN10',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 10',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
    }

    public function test_tasks_own_orders_in_progress_then_lapor_nanti_then_terjadwal_by_priority(): void
    {
        // 1. Task Terjadwal dengan Prioritas Low
        $taskTerjadwalLow = Task::create([
            'task_number' => 'TASK-SORT-0001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Task Terjadwal Low',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addMinutes(10),
            'sla_minutes' => 120,
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $taskTerjadwalLow->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        FopTask::create([
            'task_number' => 'TFOP-SORT-0001',
            'task_id' => $taskTerjadwalLow->id,
            'pop_id' => $this->pop->id,
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Task Terjadwal Low',
            'status' => TaskStatus::TERJADWAL->value,
            'priority' => FopTaskPriority::LOW->value,
            'created_by' => $this->technician->id,
        ]);

        // 2. Task Terjadwal dengan Prioritas Urgent
        $taskTerjadwalUrgent = Task::create([
            'task_number' => 'TASK-SORT-0002',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Task Terjadwal Urgent',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addMinutes(30),
            'sla_minutes' => 60,
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $taskTerjadwalUrgent->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        FopTask::create([
            'task_number' => 'TFOP-SORT-0002',
            'task_id' => $taskTerjadwalUrgent->id,
            'pop_id' => $this->pop->id,
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Task Terjadwal Urgent',
            'status' => TaskStatus::TERJADWAL->value,
            'priority' => FopTaskPriority::URGENT->value,
            'created_by' => $this->technician->id,
        ]);

        // 3. Task Lapor Nanti (Pending + report_deferred=true)
        $taskLaporNanti = Task::create([
            'task_number' => 'TASK-SORT-0003',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Task Lapor Nanti',
            'status' => TaskStatus::PENDING->value,
            'report_deferred' => true,
            'scheduled_at' => now(),
            'sla_minutes' => 120,
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $taskLaporNanti->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        // 4. Task Dimulai (In Progress)
        $taskInProgress = Task::create([
            'task_number' => 'TASK-SORT-0004',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Task Sedang Dikerjakan',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'scheduled_at' => now(),
            'sla_minutes' => 120,
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $taskInProgress->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($this->technician)->get(route('tasks.own'));

        $response->assertOk();

        /** @var Collection $tasks */
        $tasks = $response->viewData('tasks');

        $this->assertCount(4, $tasks);

        // Assert urutan:
        // 1. Task In Progress (TASK-SORT-0004)
        // 2. Task Lapor Nanti (TASK-SORT-0003)
        // 3. Task Terjadwal Urgent (TASK-SORT-0002)
        // 4. Task Terjadwal Low (TASK-SORT-0001)
        $this->assertEquals($taskInProgress->id, $tasks->get(0)->id);
        $this->assertEquals($taskLaporNanti->id, $tasks->get(1)->id);
        $this->assertEquals($taskTerjadwalUrgent->id, $tasks->get(2)->id);
        $this->assertEquals($taskTerjadwalLow->id, $tasks->get(3)->id);
    }
}
