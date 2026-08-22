<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\TaskCompleted;
use App\Events\TaskScheduled;
use App\Events\TaskStarted;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
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

    /**
     * Regresi: papan /fop-tasks & kartu Task Teknisi (anggota tim lain) gak
     * ke-refresh otomatis kalau start dipencet dari halaman lain (bukan dari
     * papan FOP) — TaskStarted cuma disiarkan ke `fop.{pop_id}` (Dashboard
     * FOP), gak ke `fop-tasks.{pop_id}` (papan) atau `teknisi.{user_id}`
     * anggota tim lain. Sekarang tiga-tiganya harus kebagian.
     */
    public function test_task_start_broadcasts_to_fop_tasks_board_and_team_members(): void
    {
        Event::fake([TaskStarted::class]);

        $teammate = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0005',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Tim',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        $task->teamMembers()->create(['user_id' => $teammate->id, 'role_in_task' => 'member']);

        app(TaskService::class)->start($task, $this->technician);

        Event::assertDispatched(TaskStarted::class, function ($event) use ($teammate) {
            $channelNames = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

            return in_array('private-fop.'.$this->pop->id, $channelNames, true)
                && in_array('private-fop-tasks.'.$this->pop->id, $channelNames, true)
                && in_array('private-teknisi.'.$this->technician->id, $channelNames, true)
                && in_array('private-teknisi.'.$teammate->id, $channelNames, true)
                // Task ini gak punya FopTask terhubung — papan gak boleh crash, cukup null.
                && $event->broadcastWith()['fop_task_id'] === null;
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

    /**
     * `fop_task_id` di payload dipakai papan /fop-tasks buat nentuin baris mana
     * yang di-refresh — beda dari `id` (Task.id). Harus resolve ke FopTask yang
     * beneran terhubung (`fop_tasks.task_id`), bukan Task.id itu sendiri.
     */
    public function test_task_completed_payload_resolves_linked_fop_task_id(): void
    {
        Event::fake([TaskCompleted::class]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0006',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Bertaut FopTask',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'started_at' => now()->subHour(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $city = City::create(['name' => 'Kota Test Broadcast']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Distrik Test Broadcast']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Test Broadcast', 'postal_code' => '11111']);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-0006',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Maintenance Bertaut FopTask',
            'village_id' => $village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => FopTaskPriority::MEDIUM->value,
            'task_id' => $task->id,
        ]);

        app(TaskService::class)->complete($task, $this->technician);

        Event::assertDispatched(TaskCompleted::class, function ($event) use ($fopTask) {
            return $event->broadcastWith()['fop_task_id'] === $fopTask->id;
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
