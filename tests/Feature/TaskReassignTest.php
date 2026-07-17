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

/**
 * Regresi Task 0 (ANALISA_REDUNDANSI_LOGIC.md §0) — reassignTeam() sempat
 * loose-compare enum TaskStatus ke string literal ('terjadwal'/'in_progress')
 * lewat in_array() non-strict, jadi kondisi selalu true & reassign SELALU
 * gagal apapun status task-nya. File ini sebelumnya nol coverage.
 */
class TaskReassignTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $oldTech;
    protected User $newTech;
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

        $adminRole = Role::where('code', 'admin')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        $this->oldTech = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Teknisi Lama']);
        $this->newTech = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Teknisi Baru']);

        foreach (\App\Models\Permission::all() as $permission) {
            if ($permission->code) {
                \Illuminate\Support\Facades\Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    protected function makeTask(string $taskNumber, string $status, ?User $member = null): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => $status,
            'scheduled_at' => now()->addDay(),
            'sla_minutes' => 120,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $task->teamMembers()->create([
            'user_id' => ($member ?? $this->oldTech)->id,
            'role_in_task' => 'lead',
        ]);

        return $task;
    }

    public function test_reassign_succeeds_when_task_terjadwal(): void
    {
        $task = $this->makeTask('TASK-2026-8001', TaskStatus::TERJADWAL->value);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('tasks.team.update', $task), [
                'old_user_id' => $this->oldTech->id,
                'new_user_id' => $this->newTech->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($task->teamMembers()->where('user_id', $this->newTech->id)->exists());
        $this->assertFalse($task->teamMembers()->where('user_id', $this->oldTech->id)->exists());
    }

    public function test_reassign_succeeds_when_task_in_progress(): void
    {
        $task = $this->makeTask('TASK-2026-8002', TaskStatus::IN_PROGRESS->value);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('tasks.team.update', $task), [
                'old_user_id' => $this->oldTech->id,
                'new_user_id' => $this->newTech->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($task->teamMembers()->where('user_id', $this->newTech->id)->exists());
    }

    /**
     * Regresi utama Task 0: sebelum fix, kondisi status-guard selalu true
     * (enum vs string literal gak pernah match), jadi request ini SELALU
     * gagal dgn pesan "Hanya task terjadwal atau in progress..." meskipun
     * statusnya emang terjadwal/in_progress. Test di atas (2 kasus sukses)
     * udah nutup itu; test ini pastikan guard-nya BENERAN masih nolak status
     * lain (bukan cuma keputusan luck krn always-true kebetulan match "gagal").
     */
    public function test_reassign_rejected_when_task_status_not_terjadwal_or_in_progress(): void
    {
        $task = $this->makeTask('TASK-2026-8003', TaskStatus::SELESAI->value);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('tasks.team.update', $task), [
                'old_user_id' => $this->oldTech->id,
                'new_user_id' => $this->newTech->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            'Hanya task terjadwal atau in progress',
            session('error')
        );

        $this->assertTrue($task->teamMembers()->where('user_id', $this->oldTech->id)->exists());
    }

    public function test_reassign_rejected_when_old_user_not_team_member(): void
    {
        $task = $this->makeTask('TASK-2026-8004', TaskStatus::TERJADWAL->value);
        $notMember = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('tasks.team.update', $task), [
                'old_user_id' => $notMember->id,
                'new_user_id' => $this->newTech->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('tidak ditemukan dalam tim', session('error'));
    }

    public function test_reassign_rejected_on_schedule_conflict(): void
    {
        $sharedTime = now()->addDay();

        $task = $this->makeTask('TASK-2026-8005', TaskStatus::TERJADWAL->value);
        $task->update(['scheduled_at' => $sharedTime]);

        // newTech udah punya task lain yang jadwalnya bentrok
        $conflictTask = Task::create([
            'task_number' => 'TASK-2026-8006',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Task Bentrok',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => $sharedTime,
            'sla_minutes' => 120,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
        $conflictTask->teamMembers()->create(['user_id' => $this->newTech->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('tasks.team.update', $task), [
                'old_user_id' => $this->oldTech->id,
                'new_user_id' => $this->newTech->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('jadwal yang bentrok', session('error'));

        $this->assertTrue($task->teamMembers()->where('user_id', $this->oldTech->id)->exists());
    }
}
