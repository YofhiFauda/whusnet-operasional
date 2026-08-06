<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Notifikasi hasil verifikasi (approve/reject/revisi) ke teknisi yang
 * laporannya diperiksa — sebelumnya nol notifikasi sama sekali
 * (docs/plan/analisa-status-implementasi-notifikasi.md §5).
 */
class CustomerVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
    }

    private function makeTaskWithTeam(Customer $customer, TaskType $taskType, string $taskNumber): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => $taskType->value,
            'title' => $taskType->value.' '.$customer->full_name,
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $task->teamMembers()->create(['user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);

        FopTask::create([
            'task_number' => 'TFOP-'.$taskNumber,
            'category' => $taskType->value,
            'task_id' => $task->id,
            'tugas' => $task->title,
            'customer_id' => $customer->id,
            'issue' => 'PSB',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => 'low',
        ]);

        return $task;
    }

    public function test_reject_notifies_task_team_with_error_type(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::create([
            'customer_code' => 'CUST-SURV-101',
            'full_name' => 'Survey Reject Customer',
            'primary_phone' => '081200000101',
            'status' => 'surveyed',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $surveyTask = $this->makeTaskWithTeam($customer, TaskType::SURVEY, 'TASK-SURV-0101');

        Notification::fake();

        $this->post(route('customers.verification.reject', $customer->id), [
            'reason' => 'Alamat tidak valid',
        ])->assertRedirect();

        Notification::assertSentTo(
            $this->teknisi,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::ERROR
                && str_contains($notification->title, $surveyTask->task_number)
        );
    }

    public function test_revisi_notifies_install_team_with_warning_type(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::create([
            'customer_code' => 'CUST-INST-101',
            'full_name' => 'Install Revisi Customer',
            'primary_phone' => '081200000102',
            'status' => 'verification_admin',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $installTask = $this->makeTaskWithTeam($customer, TaskType::PEMASANGAN, 'TASK-INST-0101');

        Notification::fake();

        $this->post(route('customers.verification.revisi', $customer->id), [
            'reason' => 'Foto ODP kurang jelas.',
        ])->assertRedirect();

        Notification::assertSentTo(
            $this->teknisi,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::WARNING
                && str_contains($notification->title, $installTask->task_number)
        );
    }
}
