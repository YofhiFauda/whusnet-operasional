<?php

namespace Tests\Feature;

use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regresi: assignToTeam() ngebersihin teknisi dari task lain yang bentrok
 * jadwal dengan manggil TaskService::update() SEKALI PER task bentrok.
 * Sebelum fix, tiap panggilan itu bawa notifyDroppedMembers() sendiri —
 * teknisi yang bentrok di N task kena N toast/notif "Task Dilepas" terpisah
 * untuk SATU aksi assign admin (ketauan 2026-11-09 lewat laporan user:
 * assign 1 task ke teknisi Joko, device Joko nerima >3 toast).
 */
class FopTaskAssignToTeamNotificationFloodTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    private function makeTask(array $technicianIds, array $overrides = []): FopTask
    {
        self::$counter++;

        $task = FopTask::create(array_merge([
            'task_number' => 'TFOP-TEST-'.str_pad((string) self::$counter, 4, '0', STR_PAD_LEFT),
            'task_date' => Carbon::parse('2026-07-10')->setTime(8, 0),
            'category' => 'MTN',
            'tugas' => 'Task '.self::$counter,
            'issue' => 'Issue',
            'status' => 'terjadwal',
            'priority' => 'Medium',
        ], $overrides));

        $task->technicians()->sync($technicianIds);

        return $task->fresh(['technicians']);
    }

    public function test_dropping_technician_from_multiple_conflicting_tasks_sends_one_consolidated_notification(): void
    {
        Notification::fake();

        $joko = User::factory()->create(['name' => 'Joko']);
        $cagak = User::factory()->create(['name' => 'Cagak']);
        $budi = User::factory()->create(['name' => 'Budi']);

        // Dua task LAIN di tanggal yang sama, Joko ada di keduanya, masing-masing
        // sudah punya team_id yang berbeda dari team tujuan.
        $team1 = FopTaskTeam::create([
            'name' => 'Team 1',
            'work_date' => '2026-07-10',
            'created_by' => 1,
        ]);
        $taskA = $this->makeTask([$joko->id, $cagak->id]);
        $taskA->team_id = $team1->id;
        $taskA->save();

        $team2 = FopTaskTeam::create([
            'name' => 'Team 2',
            'work_date' => '2026-07-10',
            'created_by' => 1,
        ]);
        $taskB = $this->makeTask([$joko->id, $budi->id]);
        $taskB->team_id = $team2->id;
        $taskB->save();

        // Task target: Joko di-assign ke team baru lewat task ini.
        $taskC = $this->makeTask([$joko->id]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $actor = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);

        $response = $this->actingAs($actor)->post("/fop-tasks/{$taskC->id}/assign-to-team", []);

        $response->assertSessionHasNoErrors();

        // Joko dicabut dari task A dan B keduanya...
        $this->assertNotContains($joko->id, $taskA->fresh()->technicians->pluck('id')->all());
        $this->assertNotContains($joko->id, $taskB->fresh()->technicians->pluck('id')->all());

        // ...tapi cuma kena SATU notifikasi gabungan, bukan satu per task yang bentrok.
        Notification::assertSentTimes(AppNotification::class, 1);
        Notification::assertSentTo($joko, AppNotification::class, function (AppNotification $notification) use ($taskA, $taskB) {
            return str_contains($notification->message, $taskA->fresh()->task_number)
                && str_contains($notification->message, $taskB->fresh()->task_number);
        });
    }
}
