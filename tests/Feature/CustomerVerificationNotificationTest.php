<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\ScopeType;
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

    protected User $fopUser;

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

        $this->fopUser = $this->makeUserWithAllPopScope('fop');
    }

    private function makeUserWithAllPopScope(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
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

    /**
     * Gap dilaporkan user 2026-08-06 (docs/plan/analisa-status-implementasi-
     * notifikasi.md §8.2/§8.5): Task PSB "Menunggu ACC", Admin klik "Setujui
     * & Proses ke Tim Pemasangan" — FOP (yang bakal assign tim ke Task
     * Pemasangan barunya) harus dapet notif, bukan cuma tim survey.
     */
    public function test_process_to_team_notifies_fop_role_about_new_install_task(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::create([
            'customer_code' => 'CUST-PSB-101',
            'full_name' => 'PSB Menunggu ACC Customer',
            'primary_phone' => '081200000201',
            'status' => 'waiting_acc',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        Notification::fake();

        $this->post(route('customers.verification.process-to-team', $customer->id))
            ->assertRedirect();

        $customer->refresh();
        $this->assertSame('waiting_installation', $customer->status);

        $installTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->firstOrFail();

        Notification::assertSentTo(
            $this->fopUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::INFO
                && str_contains($notification->title, $installTask->task_number)
        );

        // Teknisi (bukan role fop) gak ikut kebagian notif ini — beda
        // penerima dari notifyTaskTeam() di atas.
        Notification::assertNotSentTo($this->teknisi, AppNotification::class);
    }

    /**
     * Task Pemasangan yang UDAH punya tim (dipakai ulang lewat jalur
     * revisi/reuse) gak notif FOP lagi — FOP udah pernah kebagian notif
     * assignment sebelumnya, jangan spam ulang tiap kali processToTeam()
     * kepanggil.
     */
    public function test_process_to_team_does_not_renotify_fop_for_task_with_existing_team(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::create([
            'customer_code' => 'CUST-PSB-102',
            'full_name' => 'PSB Reuse Task Customer',
            'primary_phone' => '081200000202',
            'status' => 'waiting_acc',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $existingInstallTask = Task::create([
            'task_number' => 'TASK-INST-0202',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan '.$customer->full_name,
            'status' => TaskStatus::PENDING->value,
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        $existingInstallTask->teamMembers()->create(['user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);

        Notification::fake();

        $this->post(route('customers.verification.process-to-team', $customer->id))
            ->assertRedirect();

        Notification::assertNotSentTo($this->fopUser, AppNotification::class);
    }
}
