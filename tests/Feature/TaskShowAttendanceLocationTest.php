<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
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
 * Regresi — `started_via`/`started_distance_meters` (absen QR, Fase 3)
 * tercatat sejak lama tapi gak pernah ditampilkan di halaman Detail Task
 * (ADHOC audit dashboard, 2026-10-12). Blok "Lokasi Mulai Kerja" baru wajib
 * muncul dengan flag yang benar.
 */
class TaskShowAttendanceLocationTest extends TestCase
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
            'code' => 'ATT',
            'pop_code' => 'ATT',
            'registration_prefix' => 'A',
            'cid_prefix' => 'A',
            'name' => 'POP Attendance',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
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

    #[Test]
    public function blok_lokasi_mulai_kerja_muncul_dengan_flag_perlu_review(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-ATT-001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Attendance Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->fopUser->id,
            'started_via' => 'qr_scan',
            'started_distance_meters' => 300,
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('tasks.show', $task->id));

        $response->assertOk();
        $response->assertSee('Lokasi Mulai Kerja');
        $response->assertSee('Perlu Review');
        $response->assertSee('300');
        $response->assertSee('qr_scan');
    }

    #[Test]
    public function blok_lokasi_mulai_kerja_tidak_muncul_kalau_tidak_pernah_absen_qr(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-ATT-002',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Manual',
            'status' => TaskStatus::PENDING->value,
            'created_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('tasks.show', $task->id));

        $response->assertOk();
        $response->assertDontSee('Lokasi Mulai Kerja');
    }
}
