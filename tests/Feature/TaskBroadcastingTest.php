<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\TaskCompleted;
use App\Events\TaskScheduled;
use App\Events\TaskStarted;
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
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;

    protected User $technician;

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

        $this->fopUser = User::factory()->create();
        $fopRole = Role::where('code', 'fop')->first();
        $this->fopUser->role_id = $fopRole?->id;
        $this->fopUser->save();

        $this->fopUser->pops()->attach($this->pop->id);

        $this->technician = User::factory()->create();
        $techRole = Role::where('code', 'teknisi')->first();
        $this->technician->role_id = $techRole?->id;
        $this->technician->save();
    }

    public function test_task_start_broadcasts_task_started_event(): void
    {
        Event::fake([TaskStarted::class]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan Baru',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        app(TaskService::class)->start($task, $this->technician);

        Event::assertDispatched(TaskStarted::class, function ($event) use ($task) {
            return $event->task->id === $task->id
                && $event->broadcastOn()[0]->name === 'private-fop.'.$this->pop->id
                && $event->broadcastWith()['status'] === TaskStatus::IN_PROGRESS->value;
        });
    }

    public function test_task_complete_broadcasts_task_completed_event(): void
    {
        Event::fake([TaskCompleted::class]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0002',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan Selesai',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'started_at' => now()->subHour(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        app(TaskService::class)->complete($task, $this->technician);

        Event::assertDispatched(TaskCompleted::class, function ($event) use ($task) {
            return $event->task->id === $task->id
                && $event->broadcastOn()[0]->name === 'private-fop.'.$this->pop->id
                && $event->broadcastWith()['status'] === TaskStatus::SELESAI->value;
        });
    }

    public function test_task_create_broadcasts_task_scheduled_event(): void
    {
        Event::fake([TaskScheduled::class]);

        $data = [
            'task_type' => TaskType::SURVEY->value,
            'pop_id' => $this->pop->id,
            'title' => 'Survey Jadwal Baru',
            'scheduled_at' => now()->addDay(),
            'team_member_ids' => [$this->technician->id],
        ];

        $task = app(TaskService::class)->create($data, $this->fopUser);

        Event::assertDispatched(TaskScheduled::class, function ($event) use ($task) {
            $channels = $event->broadcastOn();

            return $event->task->id === $task->id
                && count($channels) === 1
                && $channels[0]->name === 'private-teknisi.'.$this->technician->id;
        });
    }

    public function test_task_update_team_change_without_reschedule_broadcasts_task_scheduled(): void
    {
        Event::fake([TaskScheduled::class]);

        $newTechnician = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0003',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Ganti Tim',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addDay(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        // Ganti tim doang, scheduled_at TIDAK ikut dikirim — ini jalur yang sebelumnya
        // silent (guard $rescheduled gak kesentuh sama sekali).
        app(TaskService::class)->update($task, [
            'team_member_ids' => [$newTechnician->id],
        ], $this->fopUser);

        Event::assertDispatched(TaskScheduled::class, function ($event) use ($newTechnician) {
            $channels = $event->broadcastOn();

            return $event->eventType === 'team_changed'
                && count($channels) === 1
                && $channels[0]->name === 'private-teknisi.'.$newTechnician->id;
        });

        Event::assertDispatched(TaskScheduled::class, function ($event) {
            $channels = $event->broadcastOn();

            return $event->eventType === 'removed'
                && count($channels) === 1
                && $channels[0]->name === 'private-teknisi.'.$this->technician->id;
        });
    }

    public function test_task_cancel_before_start_broadcasts_task_scheduled_cancelled(): void
    {
        Event::fake([TaskScheduled::class]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0004',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Dibatalkan Sebelum Mulai',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addDay(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        // Task belum in_progress — sebelumnya guard $wasInProgress bikin ini silent.
        app(TaskService::class)->cancel($task, $this->fopUser, 'Pelanggan batal');

        Event::assertDispatched(TaskScheduled::class, function ($event) {
            $channels = $event->broadcastOn();

            return $event->eventType === 'cancelled'
                && count($channels) === 1
                && $channels[0]->name === 'private-teknisi.'.$this->technician->id;
        });
    }
}
