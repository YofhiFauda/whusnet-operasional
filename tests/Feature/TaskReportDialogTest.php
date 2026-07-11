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

class TaskReportDialogTest extends TestCase
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

        $this->techUser->roleScopes()->create([
            'role_id' => $techRole->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);

        // Gate::define untuk permission dijalankan sekali saat boot (sebelum seeder
        // ini mengisi tabel permissions di test yang pakai RefreshDatabase), jadi
        // policy yang memanggil $user->can('task.view.all') butuh registrasi ulang.
        foreach (\App\Models\Permission::all() as $permission) {
            if ($permission->code) {
                \Illuminate\Support\Facades\Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    protected function makeInProgressTask(string $taskNumber): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_lapor_nanti_reuses_status_pending_and_sets_report_deferred(): void
    {
        $task = $this->makeInProgressTask('TASK-2026-9001');

        $response = $this->actingAs($this->techUser)
            ->post(route('tasks.pending', $task->id), [
                'pending_reason' => 'Kendala sinyal di lokasi',
                'report_deferred' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertEquals('Kendala sinyal di lokasi', $task->pending_reason);
        $this->assertTrue($task->report_deferred);

        // Assignment tidak lepas.
        $this->assertTrue($task->teamMembers()->where('user_id', $this->techUser->id)->exists());
    }

    public function test_pending_without_report_deferred_flag_defaults_false(): void
    {
        $task = $this->makeInProgressTask('TASK-2026-9002');

        $this->actingAs($this->techUser)
            ->post(route('tasks.pending', $task->id), [
                'pending_reason' => 'Alasan lain',
            ]);

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertFalse($task->report_deferred);
    }

    public function test_report_choice_dialog_renders_on_task_show_page(): void
    {
        $task = $this->makeInProgressTask('TASK-2026-9003');

        $response = $this->actingAs($this->techUser)->get(route('tasks.show', $task->id));

        $response->assertOk();
        $response->assertSee('Lapor Sekarang');
        $response->assertSee('Lapor Nanti');
    }

    public function test_report_choice_dialog_renders_on_own_tasks_page(): void
    {
        $task = $this->makeInProgressTask('TASK-2026-9004');

        $response = $this->actingAs($this->techUser)->get(route('tasks.own'));

        $response->assertOk();
        $response->assertSee('Lapor Sekarang');
        $response->assertSee('Lapor Nanti');
    }
}
