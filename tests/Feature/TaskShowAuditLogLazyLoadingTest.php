<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\AuditLog;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TaskShowAuditLogLazyLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;

    protected Pop $pop;

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

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->fopUser = User::factory()->create();
        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $this->fopUser->role_id = $fopRole->id;
        $this->fopUser->save();

        $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        // Re-register gate definitions because test seeders run after initial boot
        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    public function test_task_show_page_renders_without_lazy_loading_violation_for_audit_logs(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-TEST-9901',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Audit Log Test',
            'status' => TaskStatus::PENDING->value,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        // Create an audit log record associated with the task and user
        AuditLog::create([
            'user_id' => $this->fopUser->id,
            'module' => 'Task',
            'action' => 'pending',
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'old_values' => null,
            'new_values' => ['pending_reason' => 'Maintenance test reason'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);

        // Make request and check if page renders successfully without throwing any lazy loading violations
        $response = $this->actingAs($this->fopUser)
            ->get(route('tasks.show', $task->id));

        $response->assertOk();
        $response->assertSee('TASK-TEST-9901');
        $response->assertSee('Oleh:');
        $response->assertSee($this->fopUser->name);
    }
}
