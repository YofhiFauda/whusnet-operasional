<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regresi: sebelum ini tugas gak pernah nyimpen siapa teknisi yang menekan
 * "Selesai" — cuma ada `updated_by` (generic, ke-overwrite update apapun).
 * `completed_by` mesti keisi sekali pas complete() jalan dan gak berubah lagi.
 * Lihat juga guard status di TaskService::complete() buat kasus 1 tim 2 orang
 * berebut nge-klik selesai — yang kedua wajib ditolak.
 */
class TaskCompletedByTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;

    protected User $teknisiA;

    protected User $teknisiB;

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
        $this->teknisiA = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);
        $this->teknisiB = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);
    }

    protected function makeInProgressTask(): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0099',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'started_at' => now()->subMinutes(30),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->teknisiA->id, 'role_in_task' => 'lead']);
        $task->teamMembers()->create(['user_id' => $this->teknisiB->id, 'role_in_task' => 'anggota']);

        return $task;
    }

    public function test_complete_records_the_technician_who_completed_it(): void
    {
        $task = $this->makeInProgressTask();

        $completed = app(TaskService::class)->complete($task, $this->teknisiA);

        $this->assertSame($this->teknisiA->id, $completed->completed_by);
        $this->assertTrue($completed->completedBy->is($this->teknisiA));
    }

    public function test_second_team_member_cannot_double_complete_after_first_report(): void
    {
        $task = $this->makeInProgressTask();

        app(TaskService::class)->complete($task, $this->teknisiA);
        $task->refresh();

        // Teknisi B (anggota tim yang sama) coba kirim laporan setelah
        // Teknisi A duluan — status udah SELESAI, guard di complete() wajib nolak.
        try {
            app(TaskService::class)->complete($task, $this->teknisiB);
            $this->fail('Expected complete() to abort when task is already selesai.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $task->refresh();

        // completed_by tetap Teknisi A, gak ketiban Teknisi B.
        $this->assertSame($this->teknisiA->id, $task->completed_by);
    }
}
