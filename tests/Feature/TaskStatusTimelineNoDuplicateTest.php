<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use App\Support\TaskAuditTimeline;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * "Riwayat Perubahan Status" di Detail Task tidak boleh menampilkan satu aksi
 * user sebagai dua baris.
 *
 * Penyebabnya dua lapis pencatat yang dua-duanya memang dibutuhkan: trait
 * `RecordsAuditLogs` di model (menangkap SEMUA jalur + kolom yang berubah) dan
 * `AuditLog::log()` manual di service/controller (memberi nama peristiwa bisnis
 * yang tak bisa disimpulkan dari perubahan kolom). Sampai 2026-08-13 service juga
 * menulis `created`/`updated` yang isinya sama persis dengan tulisan trait —
 * duplikat murni, sudah dicabut.
 *
 * Penyaringan dilakukan MURNI DI PENYAJIAN (`App\Support\TaskAuditTimeline`).
 * Baris audit tidak pernah dihapus dari database.
 */
class TaskStatusTimelineNoDuplicateTest extends TestCase
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

        $this->fopUser = User::factory()->create();
        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $this->fopUser->role_id = $fopRole->id;
        $this->fopUser->save();
        $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    private function makeTask(string $number = 'TASK-TL-0001'): Task
    {
        return Task::create([
            'task_number' => $number,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Timeline',
            'status' => TaskStatus::TERJADWAL->value,
            // TaskService::start() menolak task yang belum sampai hari jadwalnya.
            'scheduled_at' => now(),
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
    }

    private function log(Task $task, string $action, array $newValues, string $module = 'Task'): AuditLog
    {
        return AuditLog::create([
            'user_id' => $this->fopUser->id,
            'module' => $module,
            'action' => $action,
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'old_values' => null,
            'new_values' => $newValues,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);
    }

    public function test_service_no_longer_writes_duplicate_created_log(): void
    {
        $task = $this->makeTask('TASK-TL-CREATE');

        // Trait model menulis `create`; tidak boleh ada `created` kembarannya.
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'action' => 'create',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'action' => 'created',
        ]);
    }

    public function test_business_event_wins_over_generic_update_at_same_moment(): void
    {
        $task = $this->makeTask('TASK-TL-DONE');
        AuditLog::where('auditable_id', $task->id)->delete();

        // Satu klik "Selesai": trait mencatat perubahan kolom, service memberi nama.
        $this->log($task, 'update', ['status' => 'selesai', 'completed_at' => now()->toDateTimeString()], 'Task Management');
        $this->log($task, 'completed', ['status' => 'selesai']);

        $timeline = TaskAuditTimeline::for($task->fresh(['auditLogs']));

        $this->assertCount(1, $timeline, 'satu aksi harus jadi satu baris');
        $this->assertSame('completed', $timeline->first()->action);
    }

    public function test_status_change_without_business_log_is_kept(): void
    {
        $task = $this->makeTask('TASK-TL-START');
        AuditLog::where('auditable_id', $task->id)->delete();

        // Teknisi menekan "Mulai": tidak ada log bisnis bernama untuk ini, jadi
        // baris trait-nya WAJIB tetap tampil — kalau tidak, peristiwanya hilang.
        $this->log($task, 'update', ['status' => 'in_progress', 'started_at' => now()->toDateTimeString()], 'Task Management');

        $timeline = TaskAuditTimeline::for($task->fresh(['auditLogs']));

        $this->assertCount(1, $timeline);
        $this->assertSame('update', $timeline->first()->action);
    }

    public function test_cosmetic_update_is_hidden_from_timeline(): void
    {
        $task = $this->makeTask('TASK-TL-NOISE');
        AuditLog::where('auditable_id', $task->id)->delete();

        // Prefix "[Team 1]" ditempelkan FopTaskTeamService::rebuildTeamsForDate() —
        // derau mesin, bukan peristiwa yang perlu dibaca teknisi.
        $this->log($task, 'update', ['title' => '[Team 1] Maintenance Timeline'], 'Task Management');
        $this->log($task, 'update', ['updated_by' => $this->fopUser->id], 'Task Management');

        $timeline = TaskAuditTimeline::for($task->fresh(['auditLogs']));

        $this->assertCount(0, $timeline);
    }

    public function test_audit_rows_are_never_deleted_only_hidden(): void
    {
        $task = $this->makeTask('TASK-TL-KEEP');
        AuditLog::where('auditable_id', $task->id)->delete();

        $this->log($task, 'update', ['title' => '[Team 2] Maintenance Timeline'], 'Task Management');

        TaskAuditTimeline::for($task->fresh(['auditLogs']));

        // Disembunyikan dari timeline, TETAP ada di audit_logs.
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'action' => 'update',
        ]);
    }

    /**
     * "Mulai Task" dulu tidak punya log bernama — satu-satunya jejaknya baris
     * generik dari trait, jadi di timeline ia tampil sebagai "Update": peristiwa
     * yang paling sering dilakukan teknisi justru yang paling tidak terbaca.
     * Sekarang punya nama sendiri, dan kembaran generiknya ikut tersaring.
     */
    public function test_start_writes_named_event_and_hides_generic_twin(): void
    {
        $task = $this->makeTask('TASK-TL-MULAI');
        $task->teamMembers()->create(['user_id' => $this->fopUser->id]);
        AuditLog::where('auditable_id', $task->id)->delete();

        app(TaskService::class)->start($task, $this->fopUser);

        $timeline = TaskAuditTimeline::for($task->fresh(['auditLogs']));

        $this->assertCount(1, $timeline, 'Mulai Task harus jadi satu baris, bukan dua');
        $this->assertSame('started', $timeline->first()->action);
        $this->assertSame('Mulai Dikerjakan', TaskAuditTimeline::label($timeline->first()));
    }

    public function test_pending_and_report_deferred_are_named_differently(): void
    {
        $pendingTask = $this->makeTask('TASK-TL-PENDING');
        $pendingTask->teamMembers()->create(['user_id' => $this->fopUser->id]);
        $deferredTask = $this->makeTask('TASK-TL-DEFERRED');
        $deferredTask->teamMembers()->create(['user_id' => $this->fopUser->id]);

        // Dikerjakan berurutan: TaskService::start() menolak teknisi yang masih
        // memegang task lain berstatus in_progress.
        $service = app(TaskService::class);
        $service->start($pendingTask, $this->fopUser);
        $service->setPending($pendingTask->fresh(), $this->fopUser, 'Material habis');

        $service->start($deferredTask, $this->fopUser);
        $service->setPending($deferredTask->fresh(), $this->fopUser, 'Sinyal HP hilang', true);

        $pendingRow = TaskAuditTimeline::for($pendingTask->fresh(['auditLogs']))->firstWhere('action', 'pending');
        $deferredRow = TaskAuditTimeline::for($deferredTask->fresh(['auditLogs']))->firstWhere('action', 'report_deferred');

        // Keduanya berstatus `pending` di DB — bedanya cuma flag report_deferred,
        // dan itu yang membuat maknanya berlawanan (kerja berhenti vs kerja
        // selesai tapi laporan menyusul). Timeline harus membedakannya.
        $this->assertNotNull($pendingRow);
        $this->assertNotNull($deferredRow);
        $this->assertSame('Ditunda (Pending)', TaskAuditTimeline::label($pendingRow));
        $this->assertSame('Lapor Nanti', TaskAuditTimeline::label($deferredRow));
        $this->assertSame('Material habis', $pendingRow->new_values['pending_reason']);
    }

    public function test_generic_update_gets_specific_indonesian_label(): void
    {
        $task = $this->makeTask('TASK-TL-LABEL');
        AuditLog::where('auditable_id', $task->id)->delete();

        $jadwal = $this->log($task, 'update', ['scheduled_at' => now()->addDay()->toDateTimeString()], 'Task Management');

        $this->assertSame('Jadwal Diubah', TaskAuditTimeline::label($jadwal));
    }

    public function test_task_detail_page_renders_single_row_per_action(): void
    {
        $task = $this->makeTask('TASK-TL-PAGE');
        AuditLog::where('auditable_id', $task->id)->delete();

        $this->log($task, 'update', ['status' => 'dibatalkan', 'cancel_reason' => 'Salah input'], 'Task Management');
        $this->log($task, 'cancelled', ['status' => 'dibatalkan', 'cancel_reason' => 'Salah input']);

        $response = $this->actingAs($this->fopUser)->get(route('tasks.show', $task->id));

        $response->assertOk();
        $response->assertSee('Salah input');
        // Hanya baris bernama yang tampil; pasangan generiknya tidak ikut.
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'Alasan: Salah input'),
            'alasan pembatalan tidak boleh tampil dua kali'
        );
    }
}
