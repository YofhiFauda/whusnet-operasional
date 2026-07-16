<?php

namespace Tests\Feature\Services;

use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Pop;
use App\Models\User;
use App\Services\FopTaskTeamService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FopTaskTeamServiceTest extends TestCase
{
    use RefreshDatabase;

    private FopTaskTeamService $service;
    private Carbon $date;
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FopTaskTeamService::class);
        $this->date = Carbon::parse('2026-07-10');
    }

    private function makeTask(array $technicianIds, array $overrides = []): FopTask
    {
        self::$counter++;

        $task = FopTask::create(array_merge([
            'task_number' => 'TFOP-TEST-' . str_pad((string) self::$counter, 4, '0', STR_PAD_LEFT),
            'task_date' => $overrides['task_date'] ?? $this->date->copy()->setTime(8, 0),
            'category' => 'MTN',
            'tugas' => 'Task ' . self::$counter,
            'issue' => 'Issue',
            'status' => 'terjadwal',
            'priority' => 'Medium',
        ], $overrides));

        $task->technicians()->sync($technicianIds);

        return $task->fresh(['technicians']);
    }

    /**
     * Sama kayak makeTask(), tapi juga bikinin execution Task (tabel `tasks`)
     * beneran lewat TaskService, biar sync title (FopTaskTeam -> Task) bisa dites.
     */
    private function makeTaskWithExecution(array $technicianIds, array $overrides = []): FopTask
    {
        $fopTask = $this->makeTask($technicianIds, $overrides);

        $pop = Pop::create([
            'code' => 'POP-' . self::$counter,
            'name' => 'POP Test ' . self::$counter,
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $fopTask->pop_id = $pop->id;
        $fopTask->save();

        $actor = User::factory()->create();

        $execTask = app(TaskService::class)->create([
            'pop_id' => $pop->id,
            'task_type' => 'MTN',
            'title' => 'FOP: ' . $fopTask->tugas,
            'team_member_ids' => $technicianIds,
            'scheduled_at' => $fopTask->task_date,
            'conflict_override' => true,
        ], $actor);

        $fopTask->task_id = $execTask->id;
        $fopTask->save();

        return $fopTask->fresh(['technicians']);
    }

    public function test_scenario_a_multi_technician_task_forms_new_team(): void
    {
        $andi = User::factory()->create(['name' => 'Andi Wijaya']);
        $budi = User::factory()->create(['name' => 'Budi Santoso']);

        $task = $this->makeTask([$andi->id, $budi->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task->refresh();
        $this->assertNotNull($task->team_id);

        $team = FopTaskTeam::find($task->team_id);
        $this->assertEqualsCanonicalizing([$andi->id, $budi->id], $team->members->pluck('id')->all());
    }

    public function test_new_teams_are_named_sequentially_and_names_stay_fixed_after_roster_changes(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $yanto = User::factory()->create(['name' => 'Yanto']);
        $wito = User::factory()->create(['name' => 'Wito']);
        $candra = User::factory()->create(['name' => 'Candra']);

        $task1 = $this->makeTask([$andi->id, $budi->id]);
        $task2 = $this->makeTask([$yanto->id, $wito->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task1->refresh();
        $task2->refresh();
        $team1 = FopTaskTeam::find($task1->team_id);
        $team2 = FopTaskTeam::find($task2->team_id);

        $this->assertEqualsCanonicalizing(['Team 1', 'Team 2'], [$team1->name, $team2->name]);

        // Roster team1 nambah anggota baru (Candra ikut lewat overlap) — nama gak boleh berubah.
        $this->makeTask([$budi->id, $candra->id]);
        $this->service->rebuildTeamsForDate($this->date);

        $team1->refresh();
        $this->assertEquals('Team 1', $team1->name);
        $this->assertContains($candra->id, $team1->members->pluck('id')->all());
    }

    public function test_scenario_b_overlapping_technician_bridges_two_tasks_into_one_team(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $candra = User::factory()->create(['name' => 'Candra']);

        $task1 = $this->makeTask([$andi->id, $budi->id]);
        $task2 = $this->makeTask([$budi->id, $candra->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task1->refresh();
        $task2->refresh();

        $this->assertNotNull($task1->team_id);
        $this->assertEquals($task1->team_id, $task2->team_id);

        $team = FopTaskTeam::find($task1->team_id);
        $this->assertEqualsCanonicalizing([$andi->id, $budi->id, $candra->id], $team->members->pluck('id')->all());
    }

    public function test_scenario_c1_solo_task_auto_merges_into_existing_team(): void
    {
        $karim = User::factory()->create(['name' => 'Karim']);
        $joko = User::factory()->create(['name' => 'Joko']);

        $taskB = $this->makeTask([$karim->id, $joko->id]);
        $taskC = $this->makeTask([$joko->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $taskB->refresh();
        $taskC->refresh();

        $this->assertNotNull($taskC->team_id);
        $this->assertEquals($taskB->team_id, $taskC->team_id);
    }

    public function test_scenario_c2_solo_task_without_overlap_stays_teamless(): void
    {
        $dedi = User::factory()->create(['name' => 'Dedi']);

        $task = $this->makeTask([$dedi->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task->refresh();
        $this->assertNull($task->team_id);
    }

    public function test_scenario_c2_manual_drop_in_is_pinned_and_survives_next_rebuild(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $dedi = User::factory()->create(['name' => 'Dedi']);

        $this->makeTask([$andi->id, $budi->id]);
        $soloTask = $this->makeTask([$dedi->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $soloTask->refresh();
        $this->assertNull($soloTask->team_id);

        $team = FopTaskTeam::whereHas('fopTasks', fn ($q) => $q->where('id', '!=', $soloTask->id))->first();

        $soloTask->team_id = $team->id;
        $soloTask->manual_override_at = now();
        $soloTask->save();

        $this->service->rebuildTeamsForDate($this->date);

        $soloTask->refresh();
        $this->assertEquals($team->id, $soloTask->team_id);

        $team->refresh();
        $this->assertContains($dedi->id, $team->members->pluck('id')->all());
    }

    public function test_scenario_c3_task_pulling_from_two_existing_teams_is_not_auto_merged(): void
    {
        $karim = User::factory()->create(['name' => 'Karim']);
        $abdul = User::factory()->create(['name' => 'Abdul']);
        $yanto = User::factory()->create(['name' => 'Yanto']);
        $wito = User::factory()->create(['name' => 'Wito']);

        $this->makeTask([$abdul->id, $karim->id]);
        $this->makeTask([$yanto->id, $wito->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $taskF = $this->makeTask([$karim->id, $wito->id]);

        $result = $this->service->rebuildTeamsForDate($this->date);

        $taskF->refresh();
        $this->assertNull($taskF->team_id);
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals($taskF->id, $result['conflicts'][0]['task_id']);
        $this->assertCount(2, $result['conflicts'][0]['candidates']);
    }

    public function test_c3_conflict_candidate_team_survives_even_when_its_only_task_is_the_one_conflicted(): void
    {
        // Beda dari test C3 di atas: di sini Team 2 CUMA punya 1 task (taskC), dan task
        // itu SENDIRI yang jadi sumber konflik — begitu taskC di-null-in team_id-nya,
        // Team 2 kelihatan "gak aktif lagi" di pass yang SAMA. Team 2 harus tetap idup
        // (jadi kandidat conflict yang valid buat FOP pilih), bukan ke-cleanup diam-diam.
        $wito = User::factory()->create(['name' => 'Wito']);
        $joko = User::factory()->create(['name' => 'Joko']);
        $abdul = User::factory()->create(['name' => 'Abdul']);
        $ajis = User::factory()->create(['name' => 'Ajis']);

        $taskB = $this->makeTask([$wito->id, $joko->id]); // Team 1
        $this->service->rebuildTeamsForDate($this->date);

        $taskC = $this->makeTask([$abdul->id, $ajis->id]); // Team 2 (satu-satunya task Team 2)
        $this->service->rebuildTeamsForDate($this->date);

        $taskB->refresh();
        $taskC->refresh();
        $team1Id = $taskB->team_id;
        $team2Id = $taskC->team_id;
        $this->assertNotNull($team1Id);
        $this->assertNotNull($team2Id);
        $this->assertNotEquals($team1Id, $team2Id);

        // Wito (Team 1) ditambahin ke Task C juga — Task C sekarang narik dari 2 team beda.
        $taskC->technicians()->sync([$wito->id, $abdul->id, $ajis->id]);
        $result = $this->service->rebuildTeamsForDate($this->date);

        $taskC->refresh();
        $this->assertNull($taskC->team_id, 'Task C harus jadi conflict (C3), bukan auto-merge.');
        $this->assertCount(1, $result['conflicts']);
        $this->assertCount(2, $result['conflicts'][0]['candidates']);

        // Team 1 & Team 2 DUA-DUANYA harus tetap ada — Team 2 gak boleh ke-cleanup
        // cuma karena task tunggalnya (taskC) lagi nunggu keputusan FOP.
        $this->assertNotNull(FopTaskTeam::find($team1Id), 'Team 1 gak boleh kehapus.');
        $this->assertNotNull(FopTaskTeam::find($team2Id), 'Team 2 gak boleh kehapus walau task satu-satunya lagi konflik.');

        $team1 = FopTaskTeam::find($team1Id);
        $team2 = FopTaskTeam::find($team2Id);
        $this->assertEqualsCanonicalizing([$wito->id, $joko->id], $team1->members->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$abdul->id, $ajis->id], $team2->members->pluck('id')->all());
    }

    public function test_rebuild_deletes_empty_team_when_bridging_task_is_cancelled(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $candra = User::factory()->create(['name' => 'Candra']);

        $task1 = $this->makeTask([$andi->id, $budi->id]);
        $task2 = $this->makeTask([$budi->id, $candra->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task1->refresh();
        $teamId = $task1->team_id;
        $this->assertNotNull($teamId);

        $task1->status = 'dibatalkan';
        $task1->save();
        $task2->status = 'dibatalkan';
        $task2->save();

        $this->service->rebuildTeamsForDate($this->date);

        $this->assertDatabaseMissing('fop_task_teams', ['id' => $teamId]);
    }

    public function test_shrinking_multi_technician_task_to_solo_keeps_its_team_alive(): void
    {
        $harto = User::factory()->create(['name' => 'Harto']);
        $joko = User::factory()->create(['name' => 'Joko']);

        $taskA = $this->makeTask([$harto->id, $joko->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $taskA->refresh();
        $teamId = $taskA->team_id;
        $this->assertNotNull($teamId);

        // Harto dicabut dari Task A lewat edit teknisi biasa — Joko sendirian tersisa.
        $taskA->technicians()->sync([$joko->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $taskA->refresh();
        $this->assertEquals($teamId, $taskA->team_id, 'Joko masih pegang task aktif di team ini, team_id gak boleh ke-null-in.');

        $team = FopTaskTeam::find($teamId);
        $this->assertNotNull($team, 'Team gak boleh kehapus selama masih ada task aktif yang nunjuk ke dia.');
        $this->assertEqualsCanonicalizing([$joko->id], $team->members->pluck('id')->all());
    }

    public function test_execution_task_title_gets_synced_with_real_team_name_after_rebuild(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);

        $task = $this->makeTaskWithExecution([$andi->id, $budi->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task->refresh();
        $team = FopTaskTeam::find($task->team_id);
        $this->assertNotNull($team);

        $execTask = $task->task()->first();
        $this->assertEquals("[{$team->name}] FOP: {$task->tugas}", $execTask->title);
    }

    public function test_execution_task_title_updates_when_team_merges_via_bridge(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $candra = User::factory()->create(['name' => 'Candra']);

        $task1 = $this->makeTaskWithExecution([$andi->id, $budi->id]);
        $this->service->rebuildTeamsForDate($this->date);

        $task2 = $this->makeTaskWithExecution([$budi->id, $candra->id]);
        $this->service->rebuildTeamsForDate($this->date);

        $task1->refresh();
        $task2->refresh();
        $team = FopTaskTeam::find($task1->team_id);
        $this->assertEquals($team->id, $task2->team_id);

        $this->assertEquals("[{$team->name}] FOP: {$task1->tugas}", $task1->task()->first()->title);
        $this->assertEquals("[{$team->name}] FOP: {$task2->tugas}", $task2->task()->first()->title);
    }

    public function test_execution_task_title_has_no_team_prefix_when_task_has_no_team(): void
    {
        $dedi = User::factory()->create(['name' => 'Dedi']);

        $task = $this->makeTaskWithExecution([$dedi->id]);

        $this->service->rebuildTeamsForDate($this->date);

        $task->refresh();
        $this->assertNull($task->team_id);

        $execTask = $task->task()->first();
        $this->assertEquals("FOP: {$task->tugas}", $execTask->title);
    }

    public function test_execution_task_title_syncs_after_manual_assign_to_team(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $dedi = User::factory()->create(['name' => 'Dedi']);

        $this->makeTaskWithExecution([$andi->id, $budi->id]);
        $this->service->rebuildTeamsForDate($this->date);

        $soloTask = $this->makeTaskWithExecution([$dedi->id]);
        $this->service->rebuildTeamsForDate($this->date);

        $soloTask->refresh();
        $this->assertNull($soloTask->team_id);
        $this->assertEquals("FOP: {$soloTask->tugas}", $soloTask->task()->first()->title);

        // Simulasi FopTaskController::assignToTeam(): FOP drop-in manual Dedi ke team Andi&Budi.
        $team = FopTaskTeam::whereHas('fopTasks', fn ($q) => $q->where('id', '!=', $soloTask->id))->first();
        $soloTask->team_id = $team->id;
        $soloTask->manual_override_at = now();
        $soloTask->save();

        $this->service->rebuildTeamsForDate($this->date);

        $soloTask->refresh();
        $execTask = $soloTask->task()->first();
        $this->assertEquals("[{$team->name}] FOP: {$soloTask->tugas}", $execTask->title);
    }

    public function test_conflict_detection_for_user_scenario(): void
    {
        $joko = User::factory()->create(['name' => 'Joko']);
        $cagak = User::factory()->create(['name' => 'Cagak']);
        $tri = User::factory()->create(['name' => 'Tri']);
        $suci = User::factory()->create(['name' => 'Suci']);

        // Task X ditugaskan Joko solo, Joko ada di TIM 1 (kita bikin team dan assign manual Joko dan Task X ke team tsb)
        $taskX = $this->makeTask([$joko->id]);
        $team1 = FopTaskTeam::create([
            'name' => 'Team 1',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team1->members()->sync([$joko->id]);
        $taskX->team_id = $team1->id;
        $taskX->manual_override_at = now();
        $taskX->save();

        // Task A ditugaskan Joko dan Cagak
        $taskA = $this->makeTask([$joko->id, $cagak->id]);
        $taskA->team_id = $team1->id;
        $taskA->save();

        // Task Y ditugaskan Tri solo, Tri ada di TIM 2
        $taskY = $this->makeTask([$tri->id]);
        $team2 = FopTaskTeam::create([
            'name' => 'Team 2',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team2->members()->sync([$tri->id]);
        $taskY->team_id = $team2->id;
        $taskY->manual_override_at = now();
        $taskY->save();

        // Task B ditugaskan Suci dan Tri
        $taskB = $this->makeTask([$suci->id, $tri->id]);
        $taskB->team_id = $team2->id;
        $taskB->save();

        // Rebuild awal agar roster team ter-sinkronisasi
        $this->service->rebuildTeamsForDate($this->date);

        // Pastikan Task A dan B memiliki team_id masing-masing
        $taskA->refresh();
        $taskB->refresh();
        $this->assertEquals($team1->id, $taskA->team_id);
        $this->assertEquals($team2->id, $taskB->team_id);

        // Sekarang buat Task C ditugaskan ke Cagak dan Suci
        $taskC = $this->makeTask([$cagak->id, $suci->id]);

        // Panggil rebuild
        $result = $this->service->rebuildTeamsForDate($this->date);

        // Assert: Harus terdeteksi konflik karena Cagak dari Team 1 dan Suci dari Team 2
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals($taskC->id, $result['conflicts'][0]['task_id']);
        $this->assertCount(2, $result['conflicts'][0]['candidates']);
    }

    public function test_conflict_detection_when_editing_task_with_existing_team_id(): void
    {
        $joko = User::factory()->create(['name' => 'Joko']);
        $cagak = User::factory()->create(['name' => 'Cagak']);
        $tri = User::factory()->create(['name' => 'Tri']);
        $suci = User::factory()->create(['name' => 'Suci']);

        // Task A ditugaskan Joko dan Cagak, masuk TIM 1
        $taskA = $this->makeTask([$joko->id, $cagak->id]);
        $team1 = FopTaskTeam::create([
            'name' => 'Team 1',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team1->members()->sync([$joko->id, $cagak->id]);
        $taskA->team_id = $team1->id;
        $taskA->save();

        // Task B ditugaskan Suci dan Tri, masuk TIM 2
        $taskB = $this->makeTask([$suci->id, $tri->id]);
        $team2 = FopTaskTeam::create([
            'name' => 'Team 2',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team2->members()->sync([$suci->id, $tri->id]);
        $taskB->team_id = $team2->id;
        $taskB->save();

        // Task C sebelumnya adalah solo task Cagak yang disave dan dimasukkan ke TIM 1
        $taskC = $this->makeTask([$cagak->id]);
        $taskC->team_id = $team1->id;
        $taskC->save();

        $this->service->rebuildTeamsForDate($this->date);

        // Sekarang kita edit Task C, menugaskan Cagak dan Suci
        $taskC->technicians()->sync([$cagak->id, $suci->id]);

        // Panggil rebuild
        $result = $this->service->rebuildTeamsForDate($this->date);

        // Assert: Harus terdeteksi konflik karena Cagak dari Team 1 dan Suci dari Team 2
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals($taskC->id, $result['conflicts'][0]['task_id']);
        $this->assertCount(2, $result['conflicts'][0]['candidates']);
    }

    public function test_conflict_detection_when_task_under_review_has_lower_id_than_conflict_source(): void
    {
        $joko = User::factory()->create(['name' => 'Joko']);
        $cagak = User::factory()->create(['name' => 'Cagak']);
        $tri = User::factory()->create(['name' => 'Tri']);
        $suci = User::factory()->create(['name' => 'Suci']);

        // Bikin Task C dulu (ID lebih kecil)
        $taskC = $this->makeTask([$cagak->id]);
        
        // Task A ditugaskan Joko dan Cagak, masuk TIM 1
        $taskA = $this->makeTask([$joko->id, $cagak->id]);
        $team1 = FopTaskTeam::create([
            'name' => 'Team 1',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team1->members()->sync([$joko->id, $cagak->id]);
        $taskA->team_id = $team1->id;
        $taskA->save();

        // Hubungkan Task C lama ke TIM 1
        $taskC->team_id = $team1->id;
        $taskC->save();

        // Task B ditugaskan Suci dan Tri, masuk TIM 2
        $taskB = $this->makeTask([$suci->id, $tri->id]);
        $team2 = FopTaskTeam::create([
            'name' => 'Team 2',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team2->members()->sync([$suci->id, $tri->id]);
        $taskB->team_id = $team2->id;
        $taskB->save();

        $this->service->rebuildTeamsForDate($this->date);

        // Sekarang kita edit Task C (ID lebih kecil), menugaskan Cagak dan Suci
        $taskC->technicians()->sync([$cagak->id, $suci->id]);
        $taskC->team_id = null;
        $taskC->save();

        // Panggil rebuild
        $result = $this->service->rebuildTeamsForDate($this->date);

        // Assert: Harus terdeteksi konflik karena Cagak dari Team 1 dan Suci dari Team 2
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals($taskC->id, $result['conflicts'][0]['task_id']);
        $this->assertCount(2, $result['conflicts'][0]['candidates']);
    }

    public function test_resolving_conflict_removes_technician_from_other_team_tasks(): void
    {
        $joko = User::factory()->create(['name' => 'Joko']);
        $cagak = User::factory()->create(['name' => 'Cagak']);
        $tri = User::factory()->create(['name' => 'Tri']);
        $suci = User::factory()->create(['name' => 'Suci']);

        // Task A ditugaskan Joko dan Cagak, masuk TIM 1
        $taskA = $this->makeTask([$joko->id, $cagak->id]);
        $team1 = FopTaskTeam::create([
            'name' => 'Team 1',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team1->members()->sync([$joko->id, $cagak->id]);
        $taskA->team_id = $team1->id;
        $taskA->save();

        // Task B ditugaskan Suci dan Tri, masuk TIM 2
        $taskB = $this->makeTask([$suci->id, $tri->id]);
        $team2 = FopTaskTeam::create([
            'name' => 'Team 2',
            'work_date' => $this->date->toDateString(),
            'created_by' => 1
        ]);
        $team2->members()->sync([$suci->id, $tri->id]);
        $taskB->team_id = $team2->id;
        $taskB->save();

        $taskC = $this->makeTask([$cagak->id, $suci->id]);

        $this->service->rebuildTeamsForDate($this->date);

        // Simulasi HTTP request ke assignToTeam untuk meletakkan Task C di TIM 2 (ID = $team2->id)
        $role = \App\Models\Role::create(['name' => 'Owner', 'code' => 'owner']);
        $fopUser = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        // Bind route parameters
        $response = $this->actingAs($fopUser)->post("/fop-tasks/{$taskC->id}/assign-to-team", [
            'team_id' => $team2->id,
        ]);

        $response->assertSessionHasNoErrors();

        // Assertions:
        // 1. Task C masuk ke TIM 2
        $taskC->refresh();
        $this->assertEquals($team2->id, $taskC->team_id);
        $this->assertNotNull($taskC->manual_override_at);

        // 2. Cagak (yang tadinya di TIM 1 via Task A) dipindahkan ke TIM 2, sehingga dicabut dari Task A
        $taskA->refresh();
        $this->assertNotContains($cagak->id, $taskA->technicians->pluck('id')->all());
        $this->assertContains($joko->id, $taskA->technicians->pluck('id')->all());

        // 3. Suci tidak dilepas dari Task B (karena Task B dan Task C sama-sama di TIM 2)
        $taskB->refresh();
        $this->assertContains($suci->id, $taskB->technicians->pluck('id')->all());
        $this->assertContains($tri->id, $taskB->technicians->pluck('id')->all());

        // 4. Roster TIM 1 hanya berisi Joko setelah rebuild
        $team1->refresh();
        $this->assertEqualsCanonicalizing([$joko->id], $team1->members->pluck('id')->all());

        // 5. Roster TIM 2 berisi Cagak, Suci, Tri
        $team2->refresh();
        $this->assertEqualsCanonicalizing([$cagak->id, $suci->id, $tri->id], $team2->members->pluck('id')->all());
    }
}
