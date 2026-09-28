<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
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

        // FOP User
        $this->fopUser = User::factory()->create();
        $fopRole = Role::where('code', 'fop')->first();
        $this->fopUser->role_id = $fopRole->id;
        $this->fopUser->save();

        // Assign pop scope tree/selected pop for FOP
        $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        // Technician User
        $this->techUser = User::factory()->create();
        $techRole = Role::where('code', 'teknisi')->first();
        $this->techUser->role_id = $techRole->id;
        $this->techUser->save();

        // Assign pop scope tree/selected pop for Tech
        $this->techUser->roleScopes()->create([
            'role_id' => $techRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
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

    /**
     * Approve Survey TIDAK lagi lewat halaman Task (commit 8dab63f,
     * TaskController::review()): hasil survey wajib diverifikasi Admin/CS
     * di halaman Verifikasi & Pemasangan (processToTeam), jalur yang juga
     * meneruskan pelanggan ke TIM Pemasangan. Test lama masih mengharapkan
     * approve langsung dari sini — diperbarui 2026-09-28 mengikuti aturan itu.
     */
    public function test_fop_approve_survey_dari_halaman_task_diarahkan_ke_verifikasi(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-001',
            'full_name' => 'John Doe',
            'primary_phone' => '0812345678',
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
        $response->assertSessionHas('error');

        // Tidak ada yang berubah — keputusan tetap di halaman Verifikasi.
        $task->refresh();
        $this->assertEquals('pending', $task->fop_review_status);

        $customer->refresh();
        $this->assertEquals('waiting_acc', $customer->status);
    }

    public function test_fop_can_reject_completed_survey_task(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-001',
            'full_name' => 'John Doe',
            'primary_phone' => '0812345678',
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
     * Review "Pending" = Pending ASLI (keputusan user 2026-09-28): tim
     * dilepas, FopTask balik ke antrian, pelanggan kembali ke antrean survey
     * supaya task yang dijadwal ulang bisa di-"Mulai" lagi. Dulu tim tetap
     * nempel tapi teknisi gak bisa lapor ulang maupun Mulai — task tertahan.
     */
    public function test_review_pending_survey_melepas_tim_dan_mengembalikan_pelanggan_ke_antrean_survey(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-RVP',
            'full_name' => 'Pelanggan Review Pending',
            'primary_phone' => '0812345678',
            'status' => 'waiting_acc',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
        $task = Task::create([
            'task_number' => 'TASK-2026-0091',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Selesai',
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'scheduled_at' => now(),
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-0091',
            'task_id' => $task->id,
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'Survey',
            'issue' => 'Survey ulang',
            'status' => TaskStatus::SELESAI->value,
            'priority' => 'Medium',
        ]);
        $fopTask->technicians()->sync([$this->techUser->id]);

        $this->actingAs($this->fopUser)
            ->post(route('tasks.review', $task->id), ['action' => 'pending', 'reason' => 'Titik ODP salah, survey ulang'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals(TaskStatus::PENDING, $task->status);
        $this->assertFalse($task->isMember($this->techUser->id));
        $this->assertEquals(TaskStatus::PENDING, $fopTask->fresh()->status);
        $this->assertSame([], $fopTask->fresh()->technicians()->pluck('users.id')->all());
        $this->assertEquals('waiting_survey', $customer->fresh()->status);
        // transition(WAITING_SURVEY) tidak membuat Task survey kedua — task Pending ini yang dijadwal ulang.
        $this->assertSame(1, Task::where('customer_id', $customer->id)->where('task_type', TaskType::SURVEY->value)->count());
    }

    public function test_review_pending_pemasangan_mengembalikan_pelanggan_ke_antrean_pemasangan(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-RVP2',
            'full_name' => 'Pelanggan Review Pending PSB',
            'primary_phone' => '0812345679',
            'status' => 'installed',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
        $task = Task::create([
            'task_number' => 'TASK-2026-0092',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Selesai',
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $this->actingAs($this->fopUser)
            ->post(route('tasks.review', $task->id), ['action' => 'pending', 'reason' => 'Kabel belum rapi'])
            ->assertSessionHas('success');

        $this->assertEquals(TaskStatus::PENDING, $task->fresh()->status);
        $this->assertEquals('waiting_installation', $customer->fresh()->status);
    }

    public function test_mulai_ulang_mengosongkan_waktu_selesai_sesi_lama(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-0093',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance dijadwal ulang',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'work_finished_at' => now()->subDay(),
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->techUser->id, 'role_in_task' => 'lead']);

        app(TaskService::class)->start($task, $this->techUser);

        $this->assertNull($task->fresh()->work_finished_at);

        app(TaskService::class)->complete($task->fresh(), $this->techUser);

        $task->refresh();
        $this->assertNotNull($task->work_finished_at);
        $this->assertEquals($task->completed_at->toDateTimeString(), $task->work_finished_at->toDateTimeString());
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

        $fopTask = FopTask::create([
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
