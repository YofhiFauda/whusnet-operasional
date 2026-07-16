<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Task 12 (scope task_type NON-SRV/PSB — SURVEY/PEMASANGAN OBSOLETE, dikunci
 * total dari sisi Task/FopTask, lihat docs/fop-task/analisa-auto-team.md).
 */
class FopTaskCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;
    private User $noPermUser;
    private User $tech;
    private Village $village;
    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $fopRole = Role::where('code', 'fop')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $salesRole = Role::where('code', 'sales')->first();

        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $this->noPermUser = User::factory()->create(['role_id' => $salesRole->id]);
        $this->tech = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Budi']);

        $city = City::create(['name' => 'Kota']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Distrik']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa', 'postal_code' => '11111']);
        $this->pop = Pop::create(['name' => 'POP', 'code' => 'POP-X', 'type' => 'branch', 'address' => 'x', 'status' => 'active', 'city_id' => $city->id]);
    }

    private function createFopTask(string $tugas = 'Perbaikan FO', array $technicianIds = []): FopTask
    {
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d') . ' 08:00:00',
            'tugas' => $tugas,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'FO Cut',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => $technicianIds ?: [$this->tech->id],
        ]);

        $response->assertRedirect(route('fop-tasks.index'));

        return FopTask::where('tugas', $tugas)->firstOrFail();
    }

    public function test_cancel_without_reason_is_rejected(): void
    {
        $fopTask = $this->createFopTask();

        $response = $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), ['status' => 'dibatalkan']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cancel_reason');

        $this->assertNotEquals(TaskStatus::DIBATALKAN->value, $fopTask->fresh()->status->value);
    }

    public function test_cancel_without_fop_tasks_cancel_permission_is_rejected(): void
    {
        $fopTask = $this->createFopTask();

        // Sales gak punya akses modul FOP Task sama sekali — authorizeAccess() duluan yang nolak (403).
        $response = $this->actingAs($this->noPermUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => 'dibatalkan',
                'cancel_reason' => 'Data ganda',
            ]);

        $response->assertStatus(403);
    }

    public function test_cancel_with_reason_and_permission_succeeds(): void
    {
        $fopTask = $this->createFopTask();

        $response = $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => 'dibatalkan',
                'cancel_reason' => 'Data ganda, sudah ada tiket lain',
            ]);

        $response->assertOk();

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::DIBATALKAN->value, $fopTask->status->value);
        $this->assertNotNull($fopTask->cancelled_at);
        $this->assertEquals('Data ganda, sudah ada tiket lain', $fopTask->cancel_reason);

        // Task eksekusi ikut kebatalin, alasan ikut kebawa (bukan pesan generik).
        $linkedTask = Task::find($fopTask->task_id);
        $this->assertEquals(TaskStatus::DIBATALKAN->value, $linkedTask->status->value);
        $this->assertEquals('Data ganda, sudah ada tiket lain', $linkedTask->cancel_reason);
    }

    public function test_cancelling_in_progress_task_notifies_technician(): void
    {
        NotificationFacade::fake();

        $fopTask = $this->createFopTask();
        $linkedTask = Task::find($fopTask->task_id);
        $linkedTask->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => 'dibatalkan',
                'cancel_reason' => 'Pelanggan minta batal',
            ])
            ->assertOk();

        NotificationFacade::assertSentTo($this->tech, \App\Notifications\AppNotification::class);
    }

    public function test_cancelling_terjadwal_task_does_not_notify_technician(): void
    {
        $fopTask = $this->createFopTask();

        // fake() dipasang SETELAH creation — bikin Task baru juga notifyTeam()
        // ("Task baru dijadwalkan"), itu bukan yang mau dicek di sini.
        NotificationFacade::fake();

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => 'dibatalkan',
                'cancel_reason' => 'Salah input POP',
            ])
            ->assertOk();

        NotificationFacade::assertNothingSent();
    }

    public function test_cancelling_only_active_task_in_team_deletes_the_team(): void
    {
        $joko = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active', 'name' => 'Joko']);
        $cagak = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active', 'name' => 'Cagak']);

        $fopTask = $this->createFopTask('Task Solo Team', [$joko->id, $cagak->id]);
        $teamId = $fopTask->fresh()->team_id;
        $this->assertNotNull($teamId);

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => 'dibatalkan',
                'cancel_reason' => 'Batal, tim bubar',
            ])
            ->assertOk();

        // rebuildTeamsForDate() otomatis jalan lagi di request update() ini —
        // task yang barusan dibatalkan gak lagi dianggap aktif (whereNotIn
        // selesai/dibatalkan), jadi team yang isinya cuma dia doang ikut kehapus.
        $this->assertDatabaseMissing('fop_task_teams', ['id' => $teamId]);
    }

    public function test_history_shows_cancel_reason(): void
    {
        $fopTask = $this->createFopTask();

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => 'dibatalkan',
                'cancel_reason' => 'Alasan unik buat dicari di riwayat',
            ])
            ->assertOk();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.history'));

        $response->assertOk();
        $response->assertSee('Alasan unik buat dicari di riwayat');
    }
}
