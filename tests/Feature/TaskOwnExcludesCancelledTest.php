<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);
        $this->seed(\Database\Seeders\WorkflowTransitionPermissionSeeder::class);

        foreach (\App\Models\Permission::all() as $permission) {
            if ($permission->code) {
                \Illuminate\Support\Facades\Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
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
