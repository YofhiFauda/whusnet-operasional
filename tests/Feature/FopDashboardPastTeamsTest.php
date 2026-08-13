<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Papan "Team FOP Aktif" tidak boleh kehilangan tim hanya karena ganti hari.
 *
 * Perbaikan 2026-07-22 memangkas papan jadi "hari ini saja" untuk membunuh query
 * 300+ team per refresh. Itu kebablasan: tim yang SUDAH dijadwalkan di /fop-tasks
 * lenyap dari papan begitu lewat tengah malam, padahal task-nya masih hidup dan
 * teknisinya masih melihatnya di Task Saya.
 *
 * Aturan sekarang: hari ini SELALU tampil; tanggal lampau tampil SELAMA masih
 * punya task aktif, dibatasi 30 hari ke belakang. Tim lampau yang task-nya sudah
 * selesai/batal disaring di SQL, jadi masalah beban query yang dulu tidak kembali.
 */
class FopDashboardPastTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;

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
        $ownerRole = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']);
        $this->fopUser->role_id = $ownerRole->id;
        $this->fopUser->save();

        app(EffectiveAccessService::class)->clearCache($this->fopUser);

        $this->fopUser->roleScopes()->create([
            'role_id' => $ownerRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
    }

    /**
     * @return array{0: FopTaskTeam, 1: FopTask}
     */
    private function makeTeamWithTask(string $teamName, string $taskNumber, Carbon $date, TaskStatus $status = TaskStatus::TERJADWAL): array
    {
        $team = FopTaskTeam::create([
            'name' => $teamName,
            'work_date' => $date->copy()->startOfDay(),
            'created_by' => $this->fopUser->id,
        ]);

        $customer = Customer::factory()->create(['pop_id' => $this->pop->id]);

        $fopTask = FopTask::create([
            'task_number' => $taskNumber,
            'tugas' => 'Perbaikan '.$taskNumber,
            'category' => TaskType::MAINTENANCE->value,
            'status' => $status->value,
            'task_date' => $date,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'team_id' => $team->id,
            'created_by' => $this->fopUser->id,
        ]);

        return [$team, $fopTask];
    }

    public function test_past_team_with_active_task_still_appears_on_board(): void
    {
        [$team, $fopTask] = $this->makeTeamWithTask('Team Kemarin', 'TFOP-PAST-1', Carbon::today()->subDays(2));

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee($team->name);
        $response->assertSee($fopTask->tugas);
    }

    public function test_today_team_still_appears(): void
    {
        [$team, $fopTask] = $this->makeTeamWithTask('Team Hari Ini', 'TFOP-TODAY-1', Carbon::today()->setTime(8, 0));

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee($team->name);
        $response->assertSee($fopTask->tugas);
    }

    public function test_past_team_without_active_task_is_hidden(): void
    {
        [$teamSelesai] = $this->makeTeamWithTask('Team Rampung', 'TFOP-PAST-2', Carbon::today()->subDays(3), TaskStatus::SELESAI);
        [$teamBatal] = $this->makeTeamWithTask('Team Batal', 'TFOP-PAST-3', Carbon::today()->subDays(3), TaskStatus::DIBATALKAN);

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertDontSee($teamSelesai->name);
        $response->assertDontSee($teamBatal->name);
    }

    public function test_team_older_than_window_is_hidden(): void
    {
        [$team] = $this->makeTeamWithTask('Team Purba', 'TFOP-PAST-4', Carbon::today()->subDays(45));

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertDontSee($team->name);
    }

    public function test_pending_task_keeps_its_team_visible(): void
    {
        // Pending = kerja berhenti, tapi task belum tertutup. Timnya harus tetap
        // terlihat koordinator: `setPending()` tidak mengubah task_date dan tidak
        // ada scheduler yang menjadwalkan ulang, jadi kalau hilang dari papan ia
        // mengendap tanpa ada yang tahu.
        [$team, $fopTask] = $this->makeTeamWithTask('Team Pending', 'TFOP-PAST-5', Carbon::today()->subDay(), TaskStatus::PENDING);

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee($team->name);
        $response->assertSee($fopTask->tugas);
    }

    /**
     * Tabel beban teknisi harus memakai definisi yang sama dengan papan: task
     * yang jadwalnya lewat tetap dihitung, dan porsinya ditunjukkan terpisah.
     * "3 Task" hari ini dan "3 Task" sisa tiga hari lalu bukan beban yang sama.
     */
    public function test_teknisi_table_shows_overdue_share_of_workload(): void
    {
        $teknisi = User::factory()->create(['name' => 'Teknisi Tunggakan']);
        $teknisiRole = Role::firstOrCreate(['code' => 'teknisi'], ['name' => 'Teknisi']);
        $teknisi->role_id = $teknisiRole->id;
        $teknisi->save();
        $teknisi->roleScopes()->create([
            'role_id' => $teknisiRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        $customer = Customer::factory()->create(['pop_id' => $this->pop->id]);

        $overdue = Task::create([
            'task_number' => 'TASK-OVERDUE-1',
            'title' => 'Task tertunda',
            'task_type' => TaskType::MAINTENANCE->value,
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => Carbon::today()->subDays(2)->setTime(9, 0),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'created_by' => $this->fopUser->id,
        ]);
        $overdue->teamMembers()->create(['user_id' => $teknisi->id]);

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee('1 tertunda');
    }
}
