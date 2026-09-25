<?php

namespace Tests\Feature;

use App\Enums\TaskType;
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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FopTaskBulkAssignAndWorkloadTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $technician1;

    private User $technician2;

    private Village $village;

    private Pop $pop;

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
        $this->giveAllPopScope($this->fopUser);

        $this->technician1 = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Teknisi Budi']);
        $this->technician2 = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Teknisi Joko']);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491',
        ]);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);
    }

    public function test_bulk_assign_team_successfully_updates_tasks_and_syncs_technicians(): void
    {
        $today = Carbon::today();

        $task1 = FopTask::create([
            'task_number' => 'TFOP-2026-1001',
            'task_date' => $today->copy()->setTime(9, 0),
            'category' => 'MTN',
            'status' => 'draft',
            'priority' => 'Medium',
            'tugas' => 'Perbaikan FO Jalur 1',
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
        ]);

        $task2 = FopTask::create([
            'task_number' => 'TFOP-2026-1002',
            'task_date' => $today->copy()->setTime(10, 30),
            'category' => 'C-REQ',
            'status' => 'draft',
            'priority' => 'High',
            'tugas' => 'Relokasi Drop Core',
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
        ]);

        $team = FopTaskTeam::create([
            'name' => 'Tim Delta Reaksi Cepat',
            'work_date' => $today->toDateString(),
            'pop_id' => $this->pop->id,
        ]);
        $team->members()->sync([$this->technician1->id, $this->technician2->id]);

        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.bulk-assign-team'), [
            'task_ids' => [$task1->id, $task2->id],
            'team_id' => $team->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task1->refresh();
        $task2->refresh();

        $this->assertEquals($team->id, $task1->team_id);
        $this->assertEquals($team->id, $task2->team_id);
        $this->assertEquals('terjadwal', $task1->status->value);
        $this->assertEquals('terjadwal', $task2->status->value);

        $this->assertEqualsCanonicalizing(
            [$this->technician1->id, $this->technician2->id],
            $task1->technicians->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$this->technician1->id, $this->technician2->id],
            $task2->technicians->pluck('id')->all()
        );

        $this->assertDatabaseHas('fop_task_status_history', [
            'fop_task_id' => $task1->id,
            'to_status' => 'terjadwal',
        ]);
        $this->assertDatabaseHas('fop_task_status_history', [
            'fop_task_id' => $task2->id,
            'to_status' => 'terjadwal',
        ]);
    }

    public function test_bulk_assign_team_fails_if_task_date_mismatches_team_work_date(): void
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $task = FopTask::create([
            'task_number' => 'TFOP-2026-1003',
            'task_date' => $today->copy()->setTime(9, 0),
            'category' => 'MTN',
            'status' => 'draft',
            'priority' => 'Medium',
            'tugas' => 'Perbaikan Kabel',
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
        ]);

        $teamTomorrow = FopTaskTeam::create([
            'name' => 'Tim Besok',
            'work_date' => $tomorrow->toDateString(),
            'pop_id' => $this->pop->id,
        ]);
        $teamTomorrow->members()->sync([$this->technician1->id]);

        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.bulk-assign-team'), [
            'task_ids' => [$task->id],
            'team_id' => $teamTomorrow->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['task_ids']);

        $task->refresh();
        $this->assertNull($task->team_id);
        $this->assertEquals('draft', $task->status->value);
    }

    public function test_index_calculates_technician_workload_and_displays_roster_strip(): void
    {
        $today = Carbon::today();

        $team = FopTaskTeam::create([
            'name' => 'Tim Alfa',
            'work_date' => $today->toDateString(),
            'pop_id' => $this->pop->id,
        ]);
        $team->members()->sync([$this->technician1->id]);

        $task = FopTask::create([
            'task_number' => 'TFOP-2026-1004',
            'task_date' => $today->copy()->setTime(10, 0),
            'category' => 'MTN',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'tugas' => 'Instalasi Drop Core',
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'team_id' => $team->id,
        ]);
        $task->technicians()->sync([$this->technician1->id]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));

        $response->assertStatus(200);
        $response->assertSee('Tim Kerja Hari Ini', false);
        $response->assertSee('Tim Alfa');
        $response->assertSee('Teknisi Budi');
        $response->assertSee('Teknisi Joko');
    }

    public function test_bulk_assign_team_via_json_with_linked_execution_task(): void
    {
        $today = Carbon::today();

        $execTask = Task::create([
            'task_number' => 'TSK-2026-0001',
            'task_type' => TaskType::PEMASANGAN,
            'title' => 'Instalasi Baru',
            'status' => 'terjadwal',
            'scheduled_at' => $today->copy()->setTime(9, 0),
            'pop_id' => $this->pop->id,
            'created_by' => $this->fopUser->id,
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-2001',
            'task_id' => $execTask->id,
            'task_date' => $today->copy()->setTime(9, 0),
            'category' => 'PSB',
            'status' => 'draft',
            'priority' => 'High',
            'tugas' => 'Instalasi Baru',
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
        ]);

        $team = FopTaskTeam::create([
            'name' => 'Tim Bravo',
            'work_date' => $today->toDateString(),
            'pop_id' => $this->pop->id,
        ]);
        $team->members()->sync([$this->technician1->id, $this->technician2->id]);

        $response = $this->actingAs($this->fopUser)
            ->postJson(route('fop-tasks.bulk-assign-team'), [
                'task_ids' => [$fopTask->id],
                'team_id' => $team->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'assigned_count' => 1,
        ]);

        $fopTask->refresh();
        $this->assertEquals($team->id, $fopTask->team_id);
        $this->assertEquals('terjadwal', $fopTask->status->value);

        // Verify that execution task team members are synced
        $this->assertEqualsCanonicalizing(
            [$this->technician1->id, $this->technician2->id],
            $execTask->teamMembers->pluck('user_id')->all()
        );
    }
}
