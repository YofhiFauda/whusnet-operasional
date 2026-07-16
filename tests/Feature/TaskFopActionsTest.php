<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Task;
use App\Enums\TaskType;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFopActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;
    protected User $techUser;
    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);
        $this->seed(\Database\Seeders\WorkflowTransitionPermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // FOP User
        $this->fopUser = User::factory()->create();
        $fopRole = Role::where('code', 'fop')->first();
        $this->fopUser->role_id = $fopRole->id;
        $this->fopUser->save();

        // Assign pop scope tree/selected pop for FOP
        $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);

        // Technician User
        $this->techUser = User::factory()->create();
        $techRole = Role::where('code', 'teknisi')->first();
        $this->techUser->role_id = $techRole->id;
        $this->techUser->save();

        // Assign pop scope tree/selected pop for Tech
        $this->techUser->roleScopes()->create([
            'role_id' => $techRole->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);
    }

    public function test_fop_can_reject_pending_task(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pending',
            'status' => TaskStatus::PENDING->value,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->fopUser)
            ->post(route('tasks.fop-reject', $task->id), [
                'reject_reason' => 'Lokasi tidak terjangkau',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertEquals('rejected', $task->fop_review_status);
        $this->assertEquals('Lokasi tidak terjangkau', $task->reject_reason);
    }

    public function test_fop_can_set_scheduled_task_to_pending(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0002',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Scheduled',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addDay(),
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->fopUser)
            ->post(route('tasks.fop-pending', $task->id), [
                'pending_reason' => 'Teknisi berhalangan',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertEquals('Teknisi berhalangan', $task->pending_reason);
    }

    public function test_fop_can_approve_completed_survey_task(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-001',
            'full_name' => 'John Doe',
            'phone' => '0812345678',
            'status' => 'waiting_acc',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0003',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Selesai',
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->fopUser)
            ->post(route('tasks.review', $task->id), [
                'action' => 'approve',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals('approved', $task->fop_review_status);

        $customer->refresh();
        $this->assertEquals('waiting_installation', $customer->status);
    }

    public function test_fop_can_reject_completed_survey_task(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-001',
            'full_name' => 'John Doe',
            'phone' => '0812345678',
            'status' => 'waiting_acc',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0004',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Selesai',
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->fopUser)
            ->post(route('tasks.review', $task->id), [
                'action' => 'reject',
                'reason' => 'Foto kurang jelas',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::IN_PROGRESS->value, $task->status->value);
        $this->assertEquals('rejected', $task->fop_review_status);
        $this->assertEquals('Foto kurang jelas', $task->reject_reason);

        $customer->refresh();
        $this->assertEquals('survey_in_progress', $customer->status);
    }

    public function test_fop_can_pending_completed_survey_task(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0005',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Selesai',
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->fopUser)
            ->post(route('tasks.review', $task->id), [
                'action' => 'pending',
                'reason' => 'Menunggu data tambahan',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertEquals('pending', $task->fop_review_status);
        $this->assertEquals('Menunggu data tambahan', $task->pending_reason);
    }

    /**
     * Sejak 2026-07-15 (docs/project_status_label_unifikasi.md), "Pending"
     * cuma 1 logic di sistem — siapapun yang trigger (teknisi top-level ATAU
     * FOP manual), tim HARUS dilepas + jadwal ke-rebuild. Sebelumnya
     * `fopPending` cuma ganti status doang, tim tetap nempel — itu yang
     * bikin "2 kelakuan beda buat 1 nama status" dan sekarang disatuin.
     */
    public function test_fop_pending_releases_team_and_rebuilds_schedule(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0007',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addDay(),
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        $fopTask = \App\Models\FopTask::create([
            'task_number' => 'TFOP-2026-0007',
            'task_date' => $task->scheduled_at,
            'category' => 'MTN',
            'tugas' => 'Maintenance Rutin',
            'issue' => 'Sinyal lemah',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'task_id' => $task->id,
        ]);
        $fopTask->technicians()->attach($this->techUser->id);

        $response = $this->actingAs($this->fopUser)
            ->post(route('tasks.fop-pending', $task->id), [
                'pending_reason' => 'Teknisi berhalangan',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING->value, $task->status->value);
        $this->assertFalse($task->teamMembers()->where('user_id', $this->techUser->id)->exists());

        $fopTask->refresh();
        $this->assertNull($fopTask->team_id);
        $this->assertCount(0, $fopTask->technicians);
    }

    public function test_unauthorized_user_cannot_perform_fop_actions(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0006',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Scheduled',
            'status' => TaskStatus::TERJADWAL->value,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $response = $this->actingAs($this->techUser)
            ->post(route('tasks.fop-pending', $task->id), [
                'pending_reason' => 'Teknisi coba pending',
            ]);

        $response->assertStatus(403);
    }
}
