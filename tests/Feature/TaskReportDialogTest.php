<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
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

class TaskReportDialogTest extends TestCase
{
    use RefreshDatabase;

    protected User $techUser;

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

        $this->techUser = User::factory()->create();
        $techRole = Role::where('code', 'teknisi')->first();
        $this->techUser->role_id = $techRole->id;
        $this->techUser->save();

        $this->techUser->roleScopes()->create([
            'role_id' => $techRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        // Gate::define untuk permission dijalankan sekali saat boot (sebelum seeder
        // ini mengisi tabel permissions di test yang pakai RefreshDatabase), jadi
        // policy yang memanggil $user->can('task.view.all') butuh registrasi ulang.
        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
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

    /**
     * Nutup gap Task 6 checklist poin ke-4 ("Sinkron ke FopTask: status jadi
     * lapor_nanti") yang tadinya BLOCKED nunggu Task 9 (TaskObserver + tabel
     * fop_task_status_history). Sekarang Task 9 udah `Done` — verifikasi end-to-end
     * lewat jalur HTTP asli Task 6 (`tasks.pending` + `report_deferred=1`), bukan
     * cuma lewat model langsung kayak `FopTaskStatusSyncTest`.
     */
    public function test_lapor_nanti_syncs_fop_task_status_and_writes_dedicated_history_row(): void
    {
        $task = $this->makeInProgressTask('TASK-2026-9005');
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-9005',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan',
            'issue' => 'FO CUT',
            'status' => 'in_progress',
            'priority' => 'High',
            'task_id' => $task->id,
        ]);

        $this->actingAs($this->techUser)
            ->post(route('tasks.pending', $task->id), [
                'pending_reason' => 'Kendala sinyal di lokasi',
                'report_deferred' => '1',
            ])
            ->assertRedirect();

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $fopTask->status->value);

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertNotNull($history);
        $this->assertEquals('lapor_nanti', $history->to_status);
        $this->assertEquals('Lapor Nanti', $history->label());

        // Beda entry dari FOP-side "Set Pending" (pending_fop) & dari reschedule
        // Task 7 (pending_reschedule) — walau fop_tasks.status sama-sama Pending.
        $this->assertNotEquals('pending_fop', $history->to_status);
        $this->assertNotEquals('pending_reschedule', $history->to_status);
    }

    /**
     * Nutup gap Task 6 checklist poin ke-5 ("Badge/warna beda dari tombol Pending
     * Task 7 di dashboard FOP") — badge status realtime Task 9 di
     * `fop_tasks/index.blade.php` sekarang nampilin label granular per transisi,
     * jadi "Lapor Nanti" gak ketuker sama "Pending (Reschedule)"/"Pending" biasa.
     */
    public function test_lapor_nanti_shows_distinct_badge_label_on_fop_tasks_dashboard(): void
    {
        $task = $this->makeInProgressTask('TASK-2026-9006');
        FopTask::create([
            'task_number' => 'TFOP-2026-9006',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan',
            'issue' => 'FO CUT',
            'status' => 'in_progress',
            'priority' => 'High',
            'task_id' => $task->id,
        ]);

        $this->actingAs($this->techUser)
            ->post(route('tasks.pending', $task->id), [
                'pending_reason' => 'Kendala sinyal di lokasi',
                'report_deferred' => '1',
            ]);

        $fopRole = Role::where('code', 'fop')->first();
        $fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        $response = $this->actingAs($fopUser)->get(route('fop-tasks.index'));

        $response->assertOk();
        $response->assertSee('Lapor Nanti');
    }
}
