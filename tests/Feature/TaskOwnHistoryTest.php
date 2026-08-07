<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
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

/**
 * Riwayat Task Saya (`/tasks-saya/riwayat`) — arsip task SELESAI milik
 * teknisi login. Beda dari papan `/tasks-saya` yang cuma nampilin task
 * hari ini/aktif dan buang task selesai lama dari daftar.
 */
class TaskOwnHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teknisi;

    protected User $otherTeknisi;

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

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();

        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->otherTeknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->teknisi->roleScopes()->create([
            'role_id' => $teknisiRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
        $this->otherTeknisi->roleScopes()->create([
            'role_id' => $teknisiRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    private function makeTask(string $number, TaskStatus $status, User $member): Task
    {
        $task = Task::create([
            'task_number' => $number,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Task '.$number,
            'status' => $status->value,
            'created_by' => $member->id,
            'updated_by' => $member->id,
            'completed_at' => $status === TaskStatus::SELESAI ? now() : null,
        ]);

        TaskTeam::create([
            'task_id' => $task->id,
            'user_id' => $member->id,
            'role_in_task' => 'lead',
        ]);

        return $task;
    }

    public function test_riwayat_hanya_menampilkan_task_selesai_milik_teknisi_login(): void
    {
        $this->makeTask('TASK-HIST-0001', TaskStatus::SELESAI, $this->teknisi);
        $this->makeTask('TASK-HIST-0002', TaskStatus::TERJADWAL, $this->teknisi);
        $this->makeTask('TASK-HIST-0003', TaskStatus::SELESAI, $this->otherTeknisi);

        $response = $this->actingAs($this->teknisi)
            ->get(route('tasks.own.history'));

        $response->assertOk();
        $response->assertSee('TASK-HIST-0001');
        $response->assertDontSee('TASK-HIST-0002');
        $response->assertDontSee('TASK-HIST-0003');
    }
}
