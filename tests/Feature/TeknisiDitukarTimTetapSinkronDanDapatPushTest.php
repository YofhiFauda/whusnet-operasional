<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\TaskScheduled;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\AppPushNotification;
use App\Services\TaskService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Temuan review ADHOC-297:
 *  #6  `TaskService::update(notify: false)` (dipakai assign/bulk assign ke
 *      tim) dulu ikut menahan broadcast `TaskScheduled('removed')` ⇒ kartu
 *      task yang sudah dilepas tetap tampil di HP teknisi sampai reload dan
 *      diketuk berujung 403.
 *  #10 Push "Jadwal Baru" cuma terkirim saat task DIBUAT — teknisi yang masuk
 *      lewat ganti tim / reassign (penugasan baru buat dia) tidak dapat push.
 */
class TeknisiDitukarTimTetapSinkronDanDapatPushTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $lama;

    private User $baru;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $this->actor = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $this->lama = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $this->baru = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $this->pop = Pop::create([
            'code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function makeTask(TaskType $type): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-REV-'.uniqid(),
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task review',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addHour(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        TaskTeam::create(['task_id' => $task->id, 'user_id' => $this->lama->id, 'role_in_task' => 'lead']);

        return $task;
    }

    #[Test]
    public function update_tanpa_notifikasi_tetap_broadcast_removed_ke_teknisi_yang_dilepas(): void
    {
        $task = $this->makeTask(TaskType::MAINTENANCE);

        Notification::fake();
        Event::fake([TaskScheduled::class]);

        app(TaskService::class)->update($task, ['team_member_ids' => [$this->baru->id]], $this->actor, notify: false);

        Event::assertDispatched(TaskScheduled::class, fn (TaskScheduled $event) => $event->eventType === 'removed'
            && $event->targetUserIds === [$this->lama->id]);
        Event::assertDispatched(TaskScheduled::class, fn (TaskScheduled $event) => $event->eventType === 'team_changed');

        // Notifikasi in-app tetap ditahan — caller mengirim notif gabungannya sendiri.
        Notification::assertNothingSent();
    }

    #[Test]
    public function teknisi_yang_masuk_lewat_ganti_tim_dapat_push_jadwal_baru(): void
    {
        $task = $this->makeTask(TaskType::PEMASANGAN);

        Notification::fake();

        app(TaskService::class)->update($task, ['team_member_ids' => [$this->lama->id, $this->baru->id]], $this->actor);

        Notification::assertSentTo($this->baru, AppPushNotification::class, fn (AppPushNotification $push) => str_starts_with($push->title, 'Jadwal Baru'));
        Notification::assertNotSentTo($this->lama, AppPushNotification::class);
        Notification::assertSentTo($this->lama, AppNotification::class);
    }

    #[Test]
    public function tipe_task_yang_tidak_disetujui_tidak_push_saat_ganti_tim(): void
    {
        $task = $this->makeTask(TaskType::CREQ);

        Notification::fake();

        app(TaskService::class)->update($task, ['team_member_ids' => [$this->lama->id, $this->baru->id]], $this->actor);

        Notification::assertNotSentTo($this->baru, AppPushNotification::class);
    }

    #[Test]
    public function teknisi_pengganti_reassign_dapat_push_jadwal_baru(): void
    {
        $task = $this->makeTask(TaskType::SURVEY);

        Notification::fake();
        Event::fake([TaskScheduled::class]);

        app(TaskService::class)->reassignTeam($task, $this->lama->id, $this->baru->id, $this->actor->id);

        Notification::assertSentTo($this->baru, AppPushNotification::class, fn (AppPushNotification $push) => str_starts_with($push->title, 'Jadwal Baru'));
        Notification::assertNotSentTo($this->lama, AppPushNotification::class);
    }
}
