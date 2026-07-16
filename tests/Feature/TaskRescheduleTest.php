<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRescheduleTest extends TestCase
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

        foreach (\App\Models\Permission::all() as $permission) {
            if ($permission->code) {
                \Illuminate\Support\Facades\Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    protected function makeTask(string $taskNumber, string $status = 'in_progress'): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => $status,
            'started_at' => now(),
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_reschedule_sets_status_and_releases_technician_assignment(): void
    {
        $task = $this->makeTask('TASK-2026-9101');

        $response = $this->actingAs($this->techUser)
            ->post(route('tasks.reschedule', $task), [
                'pending_reason' => 'Pelanggan minta hari lain',
            ]);

        $response->assertRedirect(route('tasks.own'));
        $response->assertSessionHas('success');

        $task->refresh();
        // TaskStatus::RESCHEDULE dihapus (2026-07-15) — dileburin jadi `pending`
        // biasa, perilaku lepas-tim TETAP sama. Lihat docs/project_status_label_unifikasi.md.
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertEquals('Pelanggan minta hari lain', $task->pending_reason);
        $this->assertFalse($task->teamMembers()->where('user_id', $this->techUser->id)->exists());
    }

    public function test_reschedule_requires_reason(): void
    {
        $task = $this->makeTask('TASK-2026-9102');

        $response = $this->actingAs($this->techUser)
            ->post(route('tasks.reschedule', $task), []);

        $response->assertSessionHasErrors('pending_reason');

        $task->refresh();
        $this->assertEquals(TaskStatus::IN_PROGRESS->value, $task->status->value);
        $this->assertTrue($task->teamMembers()->where('user_id', $this->techUser->id)->exists());
    }

    public function test_reschedule_syncs_related_fop_task_and_resets_team(): void
    {
        $task = $this->makeTask('TASK-2026-9103');

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-9103',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan FO Cut',
            'issue' => 'FO CUT di tiang 5',
            'status' => 'in_progress',
            'priority' => 'High',
            'task_id' => $task->id,
        ]);
        $fopTask->technicians()->attach($this->techUser->id);

        $this->actingAs($this->techUser)
            ->post(route('tasks.reschedule', $task), [
                'pending_reason' => 'Infra belum siap',
            ])
            ->assertRedirect(route('tasks.own'));

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $fopTask->status->value);
        $this->assertEquals('Infra belum siap', $fopTask->pending_reason);
        $this->assertNull($fopTask->team_id);
        $this->assertCount(0, $fopTask->technicians);
    }

    public function test_only_task_member_can_reschedule(): void
    {
        $task = $this->makeTask('TASK-2026-9104');

        $otherTech = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);

        $response = $this->actingAs($otherTech)
            ->post(route('tasks.reschedule', $task), [
                'pending_reason' => 'Bukan task saya',
            ]);

        $response->assertForbidden();
    }

    public function test_reschedule_not_allowed_when_task_already_selesai(): void
    {
        $task = $this->makeTask('TASK-2026-9105', 'selesai');

        $response = $this->actingAs($this->techUser)
            ->post(route('tasks.reschedule', $task), [
                'pending_reason' => 'Coba reschedule task selesai',
            ]);

        $response->assertForbidden();
    }

    /**
     * Nutup gap Task 7 checklist poin ke-5 ("Riwayat catat histori reschedule
     * lengkap di tabel dedicated") yang tadinya BLOCKED — tabel
     * `fop_task_status_history` belum ada waktu Task 7 dikerjakan. Task 9
     * (TaskObserver) sekarang nulis baris histori otomatis tiap kali
     * `Task.status` berubah.
     *
     * Sejak 2026-07-15 (docs/project_status_label_unifikasi.md), `RESCHEDULE`
     * dileburin jadi `pending` biasa — entry histori-nya sekarang `pending_fop`
     * (sama kayak FOP "Set Pending" manual), BUKAN `pending_reschedule` lagi.
     * Beda dari `lapor_nanti` (Task 6, report_deferred=true) yang TETAP kondisi
     * tersendiri.
     */
    public function test_reschedule_writes_dedicated_status_history_row(): void
    {
        $task = $this->makeTask('TASK-2026-9106');
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-9106',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan FO Cut',
            'issue' => 'FO CUT di tiang 5',
            'status' => 'in_progress',
            'priority' => 'High',
            'task_id' => $task->id,
        ]);

        $this->actingAs($this->techUser)
            ->post(route('tasks.reschedule', $task), [
                'pending_reason' => 'Infra belum siap',
            ])
            ->assertRedirect(route('tasks.own'));

        $history = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->latest('changed_at')->first();
        $this->assertNotNull($history);
        $this->assertEquals('pending_fop', $history->to_status);
        $this->assertEquals('Pending', $history->label());
        $this->assertNotEquals('lapor_nanti', $history->to_status);
    }
}
