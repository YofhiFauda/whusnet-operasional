<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
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

class TaskOwnExcludesCancelledTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: `indexOwn()` nge-include task ke /tasks-saya kalau
     * `scheduled_at` hari ini TANPA cek status — jadi task yang udah
     * Dibatalkan (misal via Cancel dari Task FOP) tetap nongol di
     * dashboard teknisi walau gak ada kerjaan lagi buat task itu.
     */
    public function test_cancelled_task_scheduled_today_is_excluded_from_tasks_own(): void
    {
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

        $pop = Pop::create([
            'code' => 'SMN6',
            'pop_code' => 'SMN6',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 6',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $technician = User::factory()->create(['role_id' => $teknisiRole->id]);

        $task = Task::create([
            'task_number' => 'TASK-CANCEL-OWN-0001',
            'pop_id' => $pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => TaskStatus::DIBATALKAN->value,
            'scheduled_at' => now(),
            'cancelled_at' => now(),
            'cancel_reason' => 'Test cancel',
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($technician)->get(route('tasks.own'));

        $response->assertOk();
        $response->assertDontSee('TASK-CANCEL-OWN-0001');
    }
}
