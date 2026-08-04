<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationReportLinkPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-VLINK',
            'pop_code' => 'VLK',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Link Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function makeCustomer(string $status, string $name): Customer
    {
        return Customer::create([
            'customer_code' => 'VLK-'.rand(10000, 99999),
            'full_name' => $name,
            'primary_phone' => '081234567890',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function assignTask(Customer $customer, TaskType $type, User $technician): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-VLK-'.rand(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task assigned',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => now()->subHour(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_user_with_validate_permission_sees_verification_admin_link_in_survey_queue(): void
    {
        $admin = $this->loginAsAdmin();
        $customer = $this->makeCustomer('waiting_survey', 'Survey Customer Admin');

        $response = $this->actingAs($admin)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee(route('customers.verification.admin', $customer));
    }

    public function test_technician_without_validate_permission_sees_survey_report_link_when_in_progress(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress', 'Survey Customer Tech');
        $this->assignTask($customer, TaskType::SURVEY, $technician);

        $response = $this->actingAs($technician)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee(route('customers.survey.report', $customer));
        $response->assertDontSee(route('customers.verification.admin', $customer));
    }

    public function test_user_with_validate_permission_sees_verification_admin_link_in_verification_queue(): void
    {
        $admin = $this->loginAsAdmin();
        $customer = $this->makeCustomer('waiting_acc', 'Verification Customer Admin');

        $response = $this->actingAs($admin)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee(route('customers.verification.admin', $customer));
    }

    public function test_technician_without_validate_permission_sees_installation_report_link_in_verification_queue(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('installation_in_progress', 'Install Customer Tech');
        $this->assignTask($customer, TaskType::PEMASANGAN, $technician);

        $response = $this->actingAs($technician)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee(route('customers.installation.report', $customer));
        $response->assertDontSee(route('customers.verification.admin', $customer));
    }
}
