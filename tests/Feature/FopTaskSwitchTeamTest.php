<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FopTaskSwitchTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private Village $village;

    private Pop $pop;

    private User $abdul;

    private User $karim;

    private User $yanto;

    private User $wito;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $fopRole = Role::where('code', 'fop')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();

        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $this->abdul = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Abdul']);
        $this->karim = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Karim']);
        $this->yanto = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Yanto']);
        $this->wito = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Wito']);

        $city = City::create(['name' => 'Kota']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Distrik']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa', 'postal_code' => '11111']);
        $this->pop = Pop::create(['name' => 'POP', 'code' => 'POP-X', 'type' => 'branch', 'address' => 'x', 'status' => 'active', 'city_id' => $city->id]);
    }

    private function createFopTask(string $tugas, array $technicianIds, ?string $taskDate = null): FopTask
    {
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => $taskDate ?? now()->format('Y-m-d').' 08:00:00',
            'tugas' => $tugas,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'i',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => $technicianIds,
        ]);

        $response->assertRedirect(route('fop-tasks.index'));

        return FopTask::where('tugas', $tugas)->firstOrFail();
    }

    public function test_switch_team_succeeds_with_chosen_technician(): void
    {
        // Task A (Tim 1): Abdul & Karim. Task E (Tim 2): Yanto & Wito.
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $team2Id = $taskE->team_id;
        $this->assertNotNull($team2Id);

        // Drag Task A dari Tim 1 ke Tim 2, pilih Yanto sebagai pengerja di Tim 2.
        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $team2Id,
            'technician_ids' => [$this->yanto->id],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $taskA->refresh();

        $this->assertSame($team2Id, $taskA->team_id);
        $this->assertEqualsCanonicalizing([$this->yanto->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_team_rejects_when_technician_not_member_of_destination_team(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->karim->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $taskA->refresh();
        $this->assertEqualsCanonicalizing([$this->abdul->id, $this->karim->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_team_succeeds_with_multiple_technicians(): void
    {
        // Task A (Tim 1): Abdul & Karim. Task E (Tim 2): Yanto & Wito.
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        // Pindahkan Task A ke Tim 2, pilih Yanto & Wito sekaligus (multi-select, seperti edit Task FOP).
        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->yanto->id, $this->wito->id],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $taskA->refresh();
        $this->assertEqualsCanonicalizing([$this->yanto->id, $this->wito->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_team_rejects_when_task_selesai(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $taskA->update(['status' => 'selesai']);

        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->yanto->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_switch_team_rejects_when_task_cancel(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $taskA->update(['status' => 'dibatalkan']);

        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->yanto->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_switch_team_rejects_when_execution_task_in_progress(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $execTask = Task::create([
            'task_number' => 'EXEC-0001',
            'pop_id' => $this->pop->id,
            'task_type' => 'MTN',
            'title' => 'Task A Exec',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 60,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $taskA->update(['task_id' => $execTask->id]);

        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->yanto->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $taskA->refresh();
        $this->assertEqualsCanonicalizing([$this->abdul->id, $this->karim->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_team_rejects_across_different_work_dates(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id], now()->format('Y-m-d').' 08:00:00');
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id], now()->addDay()->format('Y-m-d').' 08:00:00');

        $response = $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->yanto->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_switch_team_triggers_rebuild_and_dissolves_empty_origin_team(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $team1Id = $taskA->team_id;
        $team2Id = $taskE->team_id;

        $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $team2Id,
            'technician_ids' => [$this->yanto->id],
        ])->assertOk();

        // Team 1 gak lagi punya task aktif (Abdul & Karim gak lagi kepakai) — harus kehapus lewat rebuild.
        $this->assertNull(FopTaskTeam::find($team1Id));

        $team2 = FopTaskTeam::find($team2Id);
        $this->assertEqualsCanonicalizing([$this->yanto->id, $this->wito->id], $team2->members->pluck('id')->all());
    }

    public function test_switch_team_records_audit_log_entry(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $this->actingAs($this->fopUser)->postJson("/fop-tasks/{$taskA->id}/switch-team", [
            'to_team_id' => $taskE->team_id,
            'technician_ids' => [$this->yanto->id],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $taskA->id,
            'action' => 'switch_team',
        ]);
    }
}
