<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTransitionPermission;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 bug #2 (laporan user 2026-11-13): tombol laporan Survey,
 * Pemasangan, Maintenance, C-REQ, O-REQ, INFR-REQ hilang buat teknisi yang
 * merangkap PIC gudang (role `pic_gudang`).
 *
 * Tombol laporan digerbang `TaskPolicy::statusComplete()` → `canTransition()`
 * yang membaca `workflow_transition_permissions` + pivot role. Seeder dulu
 * cuma menautkan transisi `task.execute` ke role `teknisi`, padahal
 * `Role::TECHNICIAN_CODES` = teknisi + pic_gudang — PIC gudang ditugaskan FOP
 * seperti teknisi biasa (dropdown FOP pakai TECHNICIAN_CODES), tapi gak bisa
 * mulai/lapor task-nya sendiri.
 */
class TombolLaporanPicGudangMunculTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

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
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function technicianRoles(): array
    {
        return collect(Role::TECHNICIAN_CODES)
            ->mapWithKeys(fn (string $code) => [$code => [$code]])
            ->all();
    }

    /**
     * @return array<string, array{0: string, 1: TaskType}>
     */
    public static function technicianRoleByTaskType(): array
    {
        $cases = [];

        foreach (Role::TECHNICIAN_CODES as $roleCode) {
            foreach (TaskType::cases() as $type) {
                $cases["{$roleCode} {$type->value}"] = [$roleCode, $type];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('technicianRoleByTaskType')]
    public function teknisi_dan_pic_gudang_boleh_lapor_task_sedang_dikerjakan(string $roleCode, TaskType $type): void
    {
        $user = $this->makeTechnician($roleCode);
        $task = $this->makeTask($user, $type, TaskStatus::IN_PROGRESS);

        $this->assertTrue(
            Gate::forUser($user)->allows('statusComplete', $task),
            "Role {$roleCode} harus boleh lapor task {$type->value} yang sedang dikerjakan — kalau tidak, tombol laporan hilang."
        );
    }

    #[Test]
    #[DataProvider('technicianRoles')]
    public function teknisi_dan_pic_gudang_boleh_mulai_task_terjadwal(string $roleCode): void
    {
        $user = $this->makeTechnician($roleCode);

        // C-REQ dipilih sengaja: MAINTENANCE punya fallback sendiri di
        // TaskPolicy::statusStart(), jadi gak membuktikan aturan transisinya.
        $task = $this->makeTask($user, TaskType::CREQ, TaskStatus::TERJADWAL);

        $this->assertTrue(Gate::forUser($user)->allows('statusStart', $task));
    }

    #[Test]
    #[DataProvider('technicianRoles')]
    public function kartu_tasks_saya_menampilkan_tombol_laporan(string $roleCode): void
    {
        $user = $this->makeTechnician($roleCode);
        $task = $this->makeTask($user, TaskType::MAINTENANCE, TaskStatus::IN_PROGRESS);

        $this->actingAs($user)
            ->get(route('tasks.own.card-partial', $task))
            ->assertOk()
            ->assertSee(route('tasks.maintenance.report', $task), false);
    }

    #[Test]
    public function seluruh_transisi_task_execute_tertaut_ke_semua_role_teknisi(): void
    {
        $technicianRoleIds = Role::whereIn('code', Role::TECHNICIAN_CODES)->pluck('id')->sort()->values()->all();

        $rules = WorkflowTransitionPermission::with('roles:id')
            ->where('permission_name', 'task.execute')
            ->get();

        $this->assertNotEmpty($rules);

        foreach ($rules as $rule) {
            $linked = $rule->roles->pluck('id')->all();

            foreach ($technicianRoleIds as $roleId) {
                $this->assertContains(
                    $roleId,
                    $linked,
                    "Transisi {$rule->from_status} → {$rule->to_status} wajib tertaut ke semua Role::TECHNICIAN_CODES."
                );
            }
        }
    }

    private function makeTechnician(string $roleCode): User
    {
        $user = User::factory()->create();
        $user->role_id = Role::where('code', $roleCode)->firstOrFail()->id;
        $user->save();

        return $user->fresh();
    }

    private function makeTask(User $member, TaskType $type, TaskStatus $status): Task
    {
        self::$counter++;

        $pop = Pop::firstOrCreate(['code' => 'TLP'], [
            'pop_code' => 'TLP',
            'registration_prefix' => 'T',
            'cid_prefix' => 'T',
            'name' => 'POP Tombol Laporan',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TLP-'.str_pad((string) self::$counter, 4, '0', STR_PAD_LEFT),
            'pop_id' => $pop->id,
            'task_type' => $type->value,
            'title' => 'Tombol laporan '.$type->value,
            'status' => $status->value,
            'created_by' => $member->id,
            'updated_by' => $member->id,
            'scheduled_at' => now(),
            'started_at' => $status === TaskStatus::IN_PROGRESS ? now() : null,
        ]);

        $task->teamMembers()->create(['user_id' => $member->id, 'role_in_task' => 'lead']);

        return $task->fresh();
    }
}
