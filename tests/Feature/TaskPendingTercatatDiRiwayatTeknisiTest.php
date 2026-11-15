<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMemberRelease;
use App\Models\TaskTeam;
use App\Models\User;
use App\Services\TaskService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-298: Pending melepas tim (`task_teams` dihapus) supaya task
 * balik ke antrean FOP. Dulu akibatnya task yang di-pending hilang total dari
 * sisi teknisi — tidak di Tasks Saya, tidak di Riwayat (Riwayat cuma Selesai)
 * — seolah kunjungan itu tidak pernah ada.
 *
 * Sekarang tiap anggota tim yang dilepas dicatat di `task_member_releases`,
 * dan Riwayat Task Saya = gabungan Selesai + Pending, urut waktu.
 */
class TaskPendingTercatatDiRiwayatTeknisiTest extends TestCase
{
    use RefreshDatabase;

    protected User $teknisi;

    protected User $rekan;

    protected User $lain;

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
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();

        foreach (['teknisi', 'rekan', 'lain'] as $property) {
            $user = User::factory()->create(['role_id' => $teknisiRole->id]);
            $user->roleScopes()->create([
                'role_id' => $teknisiRole->id,
                'scope_type' => ScopeType::ALL_POP->value,
            ]);
            $this->{$property} = $user;
        }

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    /**
     * @param  list<User>  $members
     */
    private function makeTask(string $number, TaskStatus $status, array $members): Task
    {
        $task = Task::create([
            'task_number' => $number,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance rutin',
            'status' => $status->value,
            'scheduled_at' => now(),
            'started_at' => $status === TaskStatus::IN_PROGRESS ? now() : null,
            'completed_at' => $status === TaskStatus::SELESAI ? now() : null,
            'created_by' => $members[0]->id,
            'updated_by' => $members[0]->id,
        ]);

        foreach ($members as $index => $member) {
            TaskTeam::create([
                'task_id' => $task->id,
                'user_id' => $member->id,
                'role_in_task' => $index === 0 ? 'lead' : 'teknisi',
            ]);
        }

        return $task;
    }

    #[Test]
    public function pending_oleh_teknisi_langsung_tercatat_di_riwayat_seluruh_tim(): void
    {
        $task = $this->makeTask('TASK-PND-0001', TaskStatus::IN_PROGRESS, [$this->teknisi, $this->rekan]);

        $this->actingAs($this->teknisi)
            ->post(route('tasks.reschedule', $task), ['pending_reason' => 'Pelanggan tidak di rumah sampai sore'])
            ->assertRedirect(route('tasks.own'));

        $this->assertSame(TaskStatus::PENDING, $task->fresh()->status);
        $this->assertSame(0, $task->teamMembers()->count(), 'Tim tetap dilepas supaya task balik ke antrean FOP.');

        foreach ([$this->teknisi, $this->rekan] as $member) {
            $this->actingAs($member)
                ->get(route('tasks.own.history'))
                ->assertOk()
                ->assertSee('TASK-PND-0001')
                ->assertSee('Pending')
                ->assertSee('Pelanggan tidak di rumah sampai sore');
        }

        $this->actingAs($this->lain)
            ->get(route('tasks.own.history'))
            ->assertOk()
            ->assertDontSee('TASK-PND-0001');

        $release = TaskMemberRelease::where('task_id', $task->id)->where('user_id', $this->teknisi->id)->firstOrFail();
        $this->assertSame($this->teknisi->id, $release->released_by);
        $this->assertSame('reschedule', $release->source);
    }

    #[Test]
    public function pending_oleh_fop_juga_tercatat_di_riwayat_teknisi_yang_dilepas(): void
    {
        $task = $this->makeTask('TASK-PND-0002', TaskStatus::TERJADWAL, [$this->teknisi]);

        app(TaskService::class)->releaseTeamAndSetPending($task, 'Material belum datang', 'pending', $this->lain->id, notifyTeam: false);

        $this->actingAs($this->teknisi)
            ->get(route('tasks.own.history'))
            ->assertOk()
            ->assertSee('TASK-PND-0002')
            ->assertSee('Material belum datang');
    }

    #[Test]
    public function entri_pending_tetap_ada_setelah_dijadwal_ulang_dan_diselesaikan(): void
    {
        $task = $this->makeTask('TASK-PND-0003', TaskStatus::IN_PROGRESS, [$this->teknisi]);

        $this->travelTo(now()->subDay());
        app(TaskService::class)->releaseTeamAndSetPending($task, 'Hujan deras', 'reschedule', $this->teknisi->id, notifyTeam: false);
        $this->travelBack();

        // FOP menjadwal ulang ke teknisi yang sama, lalu selesai.
        TaskTeam::create(['task_id' => $task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);
        $task->update(['status' => TaskStatus::SELESAI->value, 'completed_at' => now()]);

        $response = $this->actingAs($this->teknisi)->get(route('tasks.own.history'));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'TASK-PND-0003'), 'Satu entri Selesai + satu entri Pending.');
        // Terbaru dulu: Selesai (hari ini) sebelum Pending (kemarin).
        $response->assertSeeInOrder(['Lihat Laporan', 'Hujan deras']);
    }

    #[Test]
    public function entri_pending_tidak_menautkan_detail_task_yang_sudah_bukan_milik_teknisi(): void
    {
        $task = $this->makeTask('TASK-PND-0004', TaskStatus::TERJADWAL, [$this->teknisi]);

        app(TaskService::class)->releaseTeamAndSetPending($task, 'Akses jalan ditutup', 'reschedule', $this->teknisi->id, notifyTeam: false);

        // Teknisi sudah bukan anggota tim → tasks.show 403; jangan tampilkan tautan mati.
        $this->actingAs($this->teknisi)
            ->get(route('tasks.own.history'))
            ->assertOk()
            ->assertSee('Akses jalan ditutup')
            ->assertDontSee(route('tasks.show', $task), false);
    }

    #[Test]
    public function riwayat_selesai_lama_tetap_tampil(): void
    {
        $this->makeTask('TASK-PND-0005', TaskStatus::SELESAI, [$this->teknisi]);
        $this->makeTask('TASK-PND-0006', TaskStatus::TERJADWAL, [$this->teknisi]);

        $this->actingAs($this->teknisi)
            ->get(route('tasks.own.history'))
            ->assertOk()
            ->assertSee('TASK-PND-0005')
            ->assertDontSee('TASK-PND-0006');
    }
}
