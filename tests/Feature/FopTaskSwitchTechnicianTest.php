<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FopTaskSwitchTechnicianTest extends TestCase
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

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

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
            'task_date' => $taskDate ?? now()->format('Y-m-d') . ' 08:00:00',
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

    public function test_switch_technician_succeeds_in_one_submit(): void
    {
        // Task A (Tim 1): Abdul & Karim. Task E (Tim 2): Yanto & Wito.
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        // Abdul dipindah dari Task A ke Task E, Karim jadi pengganti di Task A.
        $response = $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
            'replacement_technician_id' => $this->karim->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $taskA->refresh();
        $taskE->refresh();

        $this->assertEqualsCanonicalizing([$this->karim->id], $taskA->technicians->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->yanto->id, $this->wito->id, $this->abdul->id], $taskE->technicians->pluck('id')->all());
    }

    public function test_switch_rejects_when_replacement_is_same_as_departing_technician(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $response = $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
            'replacement_technician_id' => $this->abdul->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $taskA->refresh();
        // Task A tidak berubah sama sekali (rollback total, gak kosong teknisi).
        $this->assertEqualsCanonicalizing([$this->abdul->id, $this->karim->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_rejects_when_replacement_missing_from_request(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $response = $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
        ]);

        $response->assertStatus(422);

        $taskA->refresh();
        $this->assertEqualsCanonicalizing([$this->abdul->id, $this->karim->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_rejects_when_replacement_is_in_progress_elsewhere(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        // Karim lagi in_progress di task eksekusi lain.
        $busyExecTask = Task::create([
            'task_number' => 'BUSY-0001',
            'pop_id' => $this->pop->id,
            'task_type' => 'MTN',
            'title' => 'Task Sibuk',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'fop_id' => $this->fopUser->id,
            'sla_minutes' => 60,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $busyExecTask->teamMembers()->create(['user_id' => $this->karim->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
            'replacement_technician_id' => $this->karim->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);

        $taskA->refresh();
        $this->assertEqualsCanonicalizing([$this->abdul->id, $this->karim->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_rejects_across_different_days(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id], now()->format('Y-m-d') . ' 08:00:00');
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id], now()->addDay()->format('Y-m-d') . ' 08:00:00');

        $response = $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
            'replacement_technician_id' => $this->karim->id,
        ]);

        $response->assertStatus(422);

        $taskA->refresh();
        $this->assertEqualsCanonicalizing([$this->abdul->id, $this->karim->id], $taskA->technicians->pluck('id')->all());
    }

    public function test_switch_triggers_rebuild_and_updates_team_rosters(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $team1Id = $taskA->team_id;
        $team2Id = $taskE->team_id;
        $this->assertNotNull($team1Id);
        $this->assertNotNull($team2Id);

        $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
            'replacement_technician_id' => $this->karim->id,
        ])->assertOk();

        $taskA->refresh();
        $taskE->refresh();

        // Team asal & tujuan ke-rebuild — roster harus reflect pertukaran teknisi.
        $this->assertNotNull($taskA->team_id);
        $this->assertNotNull($taskE->team_id);

        $team1 = \App\Models\FopTaskTeam::find($taskA->team_id);
        $team2 = \App\Models\FopTaskTeam::find($taskE->team_id);

        $this->assertEqualsCanonicalizing([$this->karim->id], $team1->members->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->yanto->id, $this->wito->id, $this->abdul->id], $team2->members->pluck('id')->all());
    }

    public function test_switch_records_audit_log_entries(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->abdul->id, $this->karim->id]);
        $taskE = $this->createFopTask('Task E', [$this->yanto->id, $this->wito->id]);

        $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->abdul->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskE->id,
            'replacement_technician_id' => $this->karim->id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $taskA->id,
            'action' => 'switch_technician_out',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $taskE->id,
            'action' => 'switch_technician_in',
        ]);
    }
}
