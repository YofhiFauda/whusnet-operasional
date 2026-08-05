<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TaskEvidenceController — upload/hapus foto bukti dari tasks/show.blade.php.
 * Bentuk response JSON di sini (evidence.id/url/caption, evidence_count,
 * can_complete) dikonsumsi langsung oleh state Alpine `evidenceSection()` buat
 * update grid foto tanpa reload (docs/plan/analisa-realtime-spa-operasional.md
 * §2.1 no. 5 — evidence upload teknisi sebelumnya window.location.reload()).
 */
class TaskEvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $techUser;

    private Pop $pop;

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
            'code' => 'TEV1',
            'pop_code' => 'TEV1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Evidence Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->techUser = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $this->techUser->roleScopes()->create([
            'role_id' => $teknisiRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        Storage::fake('public');
    }

    private function makeInProgressTask(): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-TEV-'.random_int(1000, 9999),
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_upload_returns_evidence_shape_consumed_by_frontend_state(): void
    {
        $task = $this->makeInProgressTask();
        $photo = UploadedFile::fake()->image('bukti.jpg');

        $response = $this->actingAs($this->techUser)->postJson(route('tasks.evidences.store', $task), [
            'photo' => $photo,
            'caption' => 'Kondisi ODP',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'evidence' => ['id', 'url', 'caption', 'uploaded_by'],
            'can_complete',
            'evidence_count',
        ]);
        $this->assertSame('Kondisi ODP', $response->json('evidence.caption'));
        $this->assertSame(1, $response->json('evidence_count'));
    }

    public function test_delete_returns_updated_evidence_count(): void
    {
        // edit() policy cuma ngizinin task yang statusnya masih editable
        // (draft/terjadwal) — beda dari upload yang butuh in_progress/pending.
        $task = Task::create([
            'task_number' => 'TASK-TEV-'.random_int(1000, 9999),
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => TaskStatus::TERJADWAL->value,
            'created_by' => $this->techUser->id,
            'updated_by' => $this->techUser->id,
        ]);

        $evidence = TaskEvidence::create([
            'task_id' => $task->id,
            'uploaded_by' => $this->techUser->id,
            'file_path' => 'task-evidence/dummy.jpg',
        ]);
        Storage::disk('public')->put($evidence->file_path, 'dummy');

        $fopRole = Role::where('code', 'fop')->first();
        $fopUser = User::factory()->create(['role_id' => $fopRole->id, 'status' => 'active']);

        $response = $this->actingAs($fopUser)
            ->deleteJson(route('tasks.evidences.destroy', [$task, $evidence]));

        $response->assertOk();
        $response->assertJson(['success' => true, 'evidence_count' => 0]);
        $this->assertDatabaseMissing('task_evidences', ['id' => $evidence->id]);
    }

    public function test_non_team_member_cannot_upload_evidence(): void
    {
        $task = $this->makeInProgressTask();
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $outsider = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $response = $this->actingAs($outsider)->postJson(route('tasks.evidences.store', $task), [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertForbidden();
    }
}
