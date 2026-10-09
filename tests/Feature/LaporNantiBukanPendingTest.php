<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerSurvey;
use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-09-26: teknisi tekan "Lapor Nanti" di /tasks-saya, task-nya
 * malah jadi Pending — label benar "Lapor Nanti" tapi tombol laporan hilang
 * untuk MTN/C-REQ/O-REQ/INFR/DEAC, dan FOP masih bisa reject/jadwal ulang.
 *
 * Akar masalahnya: Lapor Nanti disimpan sebagai `pending` + flag
 * `report_deferred`, jadi tiap kode yang cuma baca `status` memperlakukannya
 * sebagai Pending. Sekarang Lapor Nanti status sendiri (`lapor_nanti`):
 *   - semua tipe task tetap bisa kirim laporan (TaskStatus::acceptsReport()),
 *   - terkunci dari semua aksi FOP, termasuk owner (TaskStatus::isLockedFromFop()),
 *   - Pending tetap Pending: gak bisa lapor, bisa dijadwal ulang FOP.
 */
class LaporNantiBukanPendingTest extends TestCase
{
    use RefreshDatabase;

    protected User $technician;

    protected User $fopUser;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'LNT',
            'pop_code' => 'LNT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Lapor Nanti',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->technician = $this->makeUser('teknisi');
        $this->fopUser = $this->makeUser('fop');

        // Gate::define untuk permission dijalankan sekali saat boot (sebelum
        // seeder mengisi tabel permissions), jadi policy yang memanggil
        // $user->can('task.view.all') butuh registrasi ulang.
        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function makeCustomer(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'LNT-'.fake()->unique()->numerify('#####'),
            'full_name' => 'Pelanggan Lapor Nanti',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    /**
     * Task in_progress milik teknisi + FopTask terhubung (jalur produksi).
     *
     * @return array{0: Task, 1: FopTask}
     */
    protected function makeInProgressTask(TaskType $type, ?Customer $customer = null): array
    {
        $customer ??= $this->makeCustomer(match ($type) {
            TaskType::SURVEY => 'survey_in_progress',
            TaskType::PEMASANGAN => 'installation_in_progress',
            default => 'active',
        });

        $task = Task::create([
            'task_number' => 'TASK-2026-'.fake()->unique()->numerify('####'),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task '.$type->value,
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now(),
            'started_at' => now(),
            'sla_minutes' => $type->slaMinutes(),
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        // Jejak tombol "Mulai Survey/Pemasangan" — form laporannya menolak
        // dibuka tanpa catatan waktu mulai (syarat halaman, bukan soal status).
        if ($type === TaskType::SURVEY) {
            (new CustomerSurvey)->forceFill(['customer_id' => $customer->id, 'started_at' => now()])->save();
        }
        if ($type === TaskType::PEMASANGAN) {
            (new CustomerInstallation)->forceFill(['customer_id' => $customer->id, 'started_at' => now()])->save();
        }

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-'.fake()->unique()->numerify('####'),
            'task_id' => $task->id,
            'task_date' => now(),
            'category' => $type->value,
            'tugas' => $customer->id.'_Pelanggan Lapor Nanti',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Uji Lapor Nanti',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        $fopTask->technicians()->sync([$this->technician->id]);

        return [$task, $fopTask];
    }

    /**
     * @return array{0: Task, 1: FopTask}
     */
    protected function makeLaporNantiTask(TaskType $type): array
    {
        [$task, $fopTask] = $this->makeInProgressTask($type);

        $this->actingAs($this->technician)
            ->post(route('tasks.report-later', $task), ['pending_reason' => 'Dipanggil ke gangguan lain'])
            ->assertRedirect()
            ->assertSessionHas('success');

        return [$task->fresh(), $fopTask->fresh()];
    }

    private function reportUrl(Task $task, string $returnTo): string
    {
        return match ($task->task_type) {
            TaskType::SURVEY => route('customers.survey.report', ['customer' => $task->customer_id, 'return_to' => $returnTo]),
            TaskType::PEMASANGAN => route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => $returnTo]),
            TaskType::AMBIL_MODEM => route('tasks.device-retrieval.report', $task),
            default => route('tasks.maintenance.report', $task),
        };
    }

    public static function everyTaskType(): array
    {
        return array_combine(
            array_map(fn (TaskType $t) => $t->value, TaskType::cases()),
            array_map(fn (TaskType $t) => [$t], TaskType::cases())
        );
    }

    // ── Teknisi: Lapor Nanti tetap bisa dilanjutkan, semua tipe task ─────────

    #[Test]
    #[DataProvider('everyTaskType')]
    public function lapor_nanti_jadi_status_sendiri_dan_fop_task_ikut(TaskType $type): void
    {
        [$task, $fopTask] = $this->makeLaporNantiTask($type);

        $this->assertSame(TaskStatus::LAPOR_NANTI, $task->status);
        $this->assertSame(TaskStatus::LAPOR_NANTI, $fopTask->status);
        // Tim tetap nempel — beda dari Pending yang melepas tim.
        $this->assertTrue($task->isMember($this->technician->id));
        $this->assertTrue($fopTask->technicians()->where('users.id', $this->technician->id)->exists());
    }

    #[Test]
    #[DataProvider('everyTaskType')]
    public function tombol_lanjutkan_laporan_tetap_ada_di_tasks_saya(TaskType $type): void
    {
        [$task] = $this->makeLaporNantiTask($type);

        $this->assertTrue($this->technician->can('statusComplete', $task));

        $this->actingAs($this->technician)
            ->get(route('tasks.own'))
            ->assertOk()
            ->assertSee('Lapor Nanti')
            ->assertSee('Lanjutkan Laporan')
            ->assertSee($this->reportUrl($task, route('tasks.own')), false);
    }

    #[Test]
    #[DataProvider('everyTaskType')]
    public function tombol_laporan_tetap_ada_di_detail_task(TaskType $type): void
    {
        [$task] = $this->makeLaporNantiTask($type);

        $this->actingAs($this->technician)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Lapor Nanti')
            ->assertSee('Alasan Lapor Nanti')
            ->assertSee($this->reportUrl($task, route('tasks.show', $task)), false);
    }

    #[Test]
    #[DataProvider('everyTaskType')]
    public function halaman_form_laporan_bisa_dibuka_dari_lapor_nanti(TaskType $type): void
    {
        [$task] = $this->makeLaporNantiTask($type);

        $this->actingAs($this->technician)
            ->get($this->reportUrl($task, route('tasks.own')))
            ->assertOk();
    }

    #[Test]
    public function laporan_maintenance_dari_lapor_nanti_menyelesaikan_task(): void
    {
        [$task, $fopTask] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);

        $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), [
                'kendala_teknis' => 'Kabel putus, sudah disambung.',
                'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
                'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::SELESAI, $task->fresh()->status);
        $this->assertSame(TaskStatus::SELESAI, $fopTask->fresh()->status);
    }

    #[Test]
    public function durasi_kerja_tidak_menghitung_jeda_lapor_nanti(): void
    {
        Carbon::setTestNow('2026-09-26 08:00:00');
        [$task] = $this->makeInProgressTask(TaskType::MAINTENANCE);
        // TaskReport dibuka TaskObserver waktu task masuk in_progress; task di
        // atas dibuat langsung in_progress, jadi siklusnya dibuka manual.
        TaskReport::create(['task_id' => $task->id, 'started_at' => now(), 'sla_target_minutes' => 120]);

        Carbon::setTestNow('2026-09-26 09:00:00');
        $this->actingAs($this->technician)
            ->post(route('tasks.report-later', $task), ['pending_reason' => 'Dipanggil ke gangguan lain']);

        // Laporan baru dikirim 5 jam kemudian — itu bukan waktu kerja.
        Carbon::setTestNow('2026-09-26 14:00:00');
        $this->actingAs($this->technician)->post(route('tasks.complete', $task))->assertRedirect();

        $report = TaskReport::where('task_id', $task->id)->first();
        $this->assertSame(TaskStatus::SELESAI, $task->fresh()->status);
        $this->assertSame(60, $report->total_duration_minutes);
        $this->assertSame('on_time', $report->sla_status);

        Carbon::setTestNow();
    }

    // ── Pending tetap Pending ───────────────────────────────────────────────

    #[Test]
    public function pending_tidak_bisa_lapor_dan_bukan_lapor_nanti(): void
    {
        [$task] = $this->makeInProgressTask(TaskType::MAINTENANCE);

        $this->actingAs($this->technician)
            ->post(route('tasks.reschedule', $task), ['pending_reason' => 'Pelanggan minta hari lain'])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(TaskStatus::PENDING, $task->status);
        $this->assertFalse($task->isMember($this->technician->id));
        $this->assertFalse($task->status->acceptsReport());

        $this->actingAs($this->technician)
            ->post(route('tasks.complete', $task))
            ->assertForbidden();
    }

    #[Test]
    public function lapor_nanti_tidak_muncul_di_tab_pending_papan_fop(): void
    {
        [, $laporNanti] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.index', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee($laporNanti->task_number);

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.index', ['status' => 'lapor_nanti']))
            ->assertOk()
            ->assertSee($laporNanti->task_number)
            ->assertSee('Terkunci');
    }

    // ── Terkunci dari FOP (termasuk owner) ──────────────────────────────────

    public static function fopActors(): array
    {
        return ['fop' => ['fop'], 'owner (wildcard *)' => ['owner']];
    }

    #[Test]
    #[DataProvider('fopActors')]
    public function fop_tidak_bisa_reject_pending_atau_batalkan_task_lapor_nanti(string $roleCode): void
    {
        [$task, $fopTask] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);
        $actor = $roleCode === 'fop' ? $this->fopUser : $this->makeUser($roleCode);

        foreach (['fopReject', 'fopPending', 'cancel', 'cancelViaFopTask', 'edit', 'schedule', 'assignTeam', 'statusReschedule'] as $ability) {
            $this->assertFalse($actor->can($ability, $task), "{$roleCode} masih boleh {$ability} task Lapor Nanti");
        }

        $this->actingAs($actor)->post(route('tasks.fop-reject', $task), ['reject_reason' => 'Coba reject'])->assertForbidden();
        $this->actingAs($actor)->post(route('tasks.fop-pending', $task), ['pending_reason' => 'Coba pending'])->assertForbidden();
        $this->actingAs($actor)->post(route('tasks.cancel', $task), ['cancel_reason' => 'Coba batal'])->assertForbidden();

        $this->assertSame(TaskStatus::LAPOR_NANTI, $task->fresh()->status);
        $this->assertSame(TaskStatus::LAPOR_NANTI, $fopTask->fresh()->status);
        $this->assertTrue($task->fresh()->isMember($this->technician->id));
    }

    #[Test]
    #[DataProvider('fopActors')]
    public function papan_fop_tidak_bisa_mengubah_task_lapor_nanti(string $roleCode): void
    {
        [$task, $fopTask] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);
        $actor = $roleCode === 'fop' ? $this->fopUser : $this->makeUser($roleCode);
        $otherTechnician = $this->makeUser('teknisi');

        $this->actingAs($actor)->put(route('fop-tasks.update', $fopTask), [
            'status' => TaskStatus::PENDING->value,
            'pending_reason' => 'Jadwal ulang',
            'client_request_date' => now()->addDay()->toDateString(),
        ])->assertStatus(422);

        $this->actingAs($actor)->put(route('fop-tasks.update', $fopTask), [
            'status' => TaskStatus::DIBATALKAN->value,
            'cancel_reason' => 'Batal',
        ])->assertStatus(422);

        $this->actingAs($actor)->put(route('fop-tasks.update', $fopTask), [
            'technicians' => [$otherTechnician->id],
        ])->assertStatus(422);

        $this->actingAs($actor)->delete(route('fop-tasks.destroy', $fopTask))->assertStatus(422);
        $this->actingAs($actor)->post(route('fop-tasks.assign-to-team', $fopTask), [])->assertStatus(422);

        $fopTask->refresh();
        $this->assertSame(TaskStatus::LAPOR_NANTI, $fopTask->status);
        $this->assertSame(TaskStatus::LAPOR_NANTI, $task->fresh()->status);
        $this->assertSame([$this->technician->id], $fopTask->technicians()->pluck('users.id')->all());
    }

    #[Test]
    public function fop_tidak_bisa_memilih_status_lapor_nanti_secara_manual(): void
    {
        [, $fopTask] = $this->makeInProgressTask(TaskType::MAINTENANCE);

        $this->actingAs($this->fopUser)
            ->put(route('fop-tasks.update', $fopTask), ['status' => TaskStatus::LAPOR_NANTI->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(TaskStatus::IN_PROGRESS, $fopTask->fresh()->status);
    }

    #[Test]
    public function teknisi_bebas_mulai_task_lain_setelah_lapor_nanti(): void
    {
        [$first] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);
        [$second] = $this->makeInProgressTask(TaskType::MAINTENANCE);
        $second->update(['status' => TaskStatus::TERJADWAL->value, 'started_at' => null]);

        $this->actingAs($this->technician)
            ->post(route('tasks.start', $second))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(TaskStatus::IN_PROGRESS, $second->fresh()->status);
        $this->assertSame(TaskStatus::LAPOR_NANTI, $first->fresh()->status);
    }

    #[Test]
    public function bulk_assign_menolak_kalau_task_eksekusinya_lapor_nanti_walau_fop_task_belum_sinkron(): void
    {
        [$task, $fopTask] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);
        // Simulasi FopTask yang status-nya tertinggal (tidak ikut ter-sync).
        FopTask::whereKey($fopTask->id)->update(['status' => TaskStatus::TERJADWAL->value]);
        $team = FopTaskTeam::create(['name' => 'Team Uji', 'work_date' => now()->toDateString()]);

        $this->actingAs($this->makeUser('owner'))
            ->post(route('fop-tasks.bulk-assign-team'), ['task_ids' => [$fopTask->id], 'team_id' => $team->id]);

        $this->assertNotSame($team->id, $fopTask->fresh()->team_id);
        $this->assertSame([$this->technician->id], $fopTask->fresh()->technicians()->pluck('users.id')->all());
    }

    // ── SLA berhenti saat Lapor Nanti (keputusan user 2026-09-28) ─────────────

    #[Test]
    public function sla_berhenti_saat_lapor_nanti_dan_tidak_telat_walau_laporan_menyusul(): void
    {
        Carbon::setTestNow('2026-09-28 08:00:00');
        [$task] = $this->makeInProgressTask(TaskType::MAINTENANCE);

        Carbon::setTestNow('2026-09-28 09:00:00');
        $this->actingAs($this->technician)
            ->post(route('tasks.report-later', $task), ['pending_reason' => 'Dipanggil ke gangguan lain']);

        // Sehari kemudian — SLA MTN jauh terlewati kalau jeda ikut dihitung.
        Carbon::setTestNow('2026-09-29 10:00:00');
        $task->refresh();
        $this->assertSame('2026-09-28 09:00:00', $task->work_finished_at->toDateTimeString());
        $this->assertFalse($task->isOverSla());
        $this->assertSame(60, $task->actualDurationMinutes());

        // Kartu Tasks Saya: tidak ada countdown, yang tampil jam kerja selesai.
        $this->actingAs($this->technician)->get(route('tasks.own'))
            ->assertOk()
            ->assertSee('menunggu laporan')
            ->assertDontSee('Melewati SLA');

        // Laporan dikirim besoknya tetap tidak dianggap telat.
        $this->actingAs($this->technician)->post(route('tasks.complete', $task))->assertRedirect();
        $task->refresh();
        $this->assertSame(TaskStatus::SELESAI, $task->status);
        $this->assertFalse($task->isOverSla());
        $this->assertSame(60, $task->actualDurationMinutes());

        Carbon::setTestNow();
    }

    // ── Backlog FOP Analytics (keputusan user 2026-09-28) ─────────────────────

    #[Test]
    public function backlog_analytics_menampilkan_lapor_nanti_dengan_label_sendiri(): void
    {
        [$task] = $this->makeLaporNantiTask(TaskType::MAINTENANCE);

        $this->actingAs($this->makeUser('owner'))
            ->get(route('fop.analytics'))
            ->assertOk()
            ->assertSee($task->task_number)
            ->assertSee('Lapor Nanti');
    }
    // ── Migrasi data lama ────────────────────────────────────────────────────

    #[Test]
    public function migrasi_mengubah_pending_report_deferred_lama_jadi_lapor_nanti(): void
    {
        $migration = require database_path('migrations/2026_09_26_110000_promote_lapor_nanti_to_task_status.php');
        $migration->down();
        $this->assertTrue(Schema::hasColumn('tasks', 'report_deferred'));

        [$deferred, $deferredFop] = $this->makeInProgressTask(TaskType::MAINTENANCE);
        [$pending, $pendingFop] = $this->makeInProgressTask(TaskType::MAINTENANCE);
        DB::table('tasks')->where('id', $deferred->id)->update(['status' => 'pending', 'report_deferred' => true]);
        DB::table('fop_tasks')->where('id', $deferredFop->id)->update(['status' => 'pending']);
        DB::table('tasks')->where('id', $pending->id)->update(['status' => 'pending', 'report_deferred' => false]);
        DB::table('fop_tasks')->where('id', $pendingFop->id)->update(['status' => 'pending']);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('tasks', 'report_deferred'));
        $this->assertSame('lapor_nanti', DB::table('tasks')->where('id', $deferred->id)->value('status'));
        $this->assertSame('lapor_nanti', DB::table('fop_tasks')->where('id', $deferredFop->id)->value('status'));
        $this->assertSame('pending', DB::table('tasks')->where('id', $pending->id)->value('status'));
        $this->assertSame('pending', DB::table('fop_tasks')->where('id', $pendingFop->id)->value('status'));
    }
}
