<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskStatusPanelLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $fopUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->fopUser = User::factory()->create(['role_id' => Role::where('code', 'fop')->first()->id]);
        $this->fopUser->pops()->attach($this->pop->id);
    }

    private function createTask(): Task
    {
        return Task::create([
            'task_number' => 'TASK-2026-9001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Live Panel',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
    }

    #[Test]
    public function status_panel_endpoint_returns_current_task_status(): void
    {
        $this->loginAsAdmin();
        $task = $this->createTask();

        $response = $this->get(route('tasks.status-panel', $task));

        $response->assertOk();
        $response->assertSee('id="task-status-panel"', false);
        $response->assertSee($task->status->label());
    }

    #[Test]
    public function status_panel_endpoint_rejects_technician_not_assigned_to_task(): void
    {
        $task = $this->createTask();

        $outsider = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);

        $response = $this->actingAs($outsider)->get(route('tasks.status-panel', $task));

        $response->assertForbidden();
    }
}
