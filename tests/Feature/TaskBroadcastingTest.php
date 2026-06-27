<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\TaskCompleted;
use App\Events\TaskScheduled;
use App\Events\TaskStarted;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskEvidence;
use App\Models\User;
use App\Services\TaskService;
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

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);

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
                && $event->broadcastOn()[0]->name === 'private-fop.' . $this->pop->id
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

        TaskChecklist::create([
            'task_id' => $task->id,
            'item' => 'Cek Kabel',
            'is_required' => true,
            'is_checked' => true,
            'checked_at' => now(),
            'sort_order' => 1,
        ]);

        TaskEvidence::create([
            'task_id' => $task->id,
            'file_path' => 'evidences/test.jpg',
            'caption' => 'Bukti foto',
            'uploaded_by' => $this->technician->id,
        ]);

        app(TaskService::class)->complete($task, $this->technician);

        Event::assertDispatched(TaskCompleted::class, function ($event) use ($task) {
            return $event->task->id === $task->id
                && $event->broadcastOn()[0]->name === 'private-fop.' . $this->pop->id
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
                && $channels[0]->name === 'private-teknisi.' . $this->technician->id;
        });
    }
}
