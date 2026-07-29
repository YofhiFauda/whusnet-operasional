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

/**
 * Bug RBAC dilaporkan user: Teknisi bisa "Mulai Survey"/"Start Proses"
 * pemasangan TANPA melalui penjadwalan FOP sama sekali. Root cause:
 * CustomerSurveyController::start() & CustomerInstallationController::start()
 * cuma ngecek keanggotaan tim kalau Task berstatus Terjadwal KETEMU
 * (`if ($task) { abort_unless(...); }`) — kalau gak ketemu (paling sering
 * karena Task-nya masih Draft/belum dijadwalkan FOP), guard-nya di-skip
 * total dan teknisi mana pun yang punya permission generik
 * customers.detail.survey.update/installation.update bisa mulai kerja.
 */
class SurveyInstallationScheduleGuardTest extends TestCase
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
            'code' => 'SMN-GUARD',
            'pop_code' => 'GRD',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Guard Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeTeknisi(): User
    {
        $role = Role::where('code', 'teknisi')->first();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function makeCustomer(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'GRD-'.rand(10000, 99999),
            'full_name' => 'Pelanggan Guard Test',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function makeScheduledTask(Customer $customer, TaskType $type, User $technician): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-GRD-'.rand(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task terjadwal',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->subHour(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return $task;
    }

    // ── Survey ─────────────────────────────────────────────────────

    public function test_technician_without_any_scheduled_task_cannot_start_survey(): void
    {
        $technician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_survey');

        // TIDAK ada Task Survey sama sekali buat customer ini.
        $this->actingAs($technician)
            ->post(route('customers.survey.start', $customer))
            ->assertForbidden();

        $this->assertSame('waiting_survey', $customer->fresh()->status);
    }

    /**
     * Kasus paling sering kejadian di lapangan: Task ADA, tapi masih Draft
     * (FOP belum jadwalin teknisi) — bukan Terjadwal. Ini WAJIB tetep ditolak,
     * bukan diloloskan cuma karena query `where('status', TERJADWAL)` gak nemu.
     */
    public function test_technician_cannot_start_survey_when_task_exists_but_not_yet_scheduled(): void
    {
        $technician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_survey');

        Task::create([
            'task_number' => 'TASK-GRD-DRAFT',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Belum dijadwalkan',
            'status' => TaskStatus::DRAFT->value,
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $this->actingAs($technician)
            ->post(route('customers.survey.start', $customer))
            ->assertForbidden();
    }

    public function test_technician_not_on_the_assigned_team_cannot_start_survey(): void
    {
        $assignedTechnician = $this->makeTeknisi();
        $outsiderTechnician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_survey');

        $this->makeScheduledTask($customer, TaskType::SURVEY, $assignedTechnician);

        $this->actingAs($outsiderTechnician)
            ->post(route('customers.survey.start', $customer))
            ->assertForbidden();
    }

    public function test_technician_assigned_to_scheduled_task_can_start_survey(): void
    {
        $technician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_survey');

        $task = $this->makeScheduledTask($customer, TaskType::SURVEY, $technician);

        $this->actingAs($technician)
            ->post(route('customers.survey.start', $customer))
            ->assertRedirect();

        $this->assertSame('survey_in_progress', $customer->fresh()->status);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
    }

    public function test_full_access_user_can_start_survey_without_being_scheduled(): void
    {
        $owner = $this->loginAsAdmin();
        $customer = $this->makeCustomer('waiting_survey');

        $this->actingAs($owner)
            ->post(route('customers.survey.start', $customer))
            ->assertRedirect();

        $this->assertSame('survey_in_progress', $customer->fresh()->status);
    }

    // ── Instalasi ──────────────────────────────────────────────────

    public function test_technician_without_any_scheduled_task_cannot_start_installation(): void
    {
        $technician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_installation');

        $this->actingAs($technician)
            ->post(route('customers.installation.start', $customer))
            ->assertForbidden();

        $this->assertSame('waiting_installation', $customer->fresh()->status);
    }

    public function test_technician_cannot_start_installation_when_task_exists_but_not_yet_scheduled(): void
    {
        $technician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_installation');

        Task::create([
            'task_number' => 'TASK-GRD-DRAFT-INST',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Belum dijadwalkan',
            'status' => TaskStatus::DRAFT->value,
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $this->actingAs($technician)
            ->post(route('customers.installation.start', $customer))
            ->assertForbidden();
    }

    public function test_technician_not_on_the_assigned_team_cannot_start_installation(): void
    {
        $assignedTechnician = $this->makeTeknisi();
        $outsiderTechnician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_installation');

        $this->makeScheduledTask($customer, TaskType::PEMASANGAN, $assignedTechnician);

        $this->actingAs($outsiderTechnician)
            ->post(route('customers.installation.start', $customer))
            ->assertForbidden();
    }

    public function test_technician_assigned_to_scheduled_task_can_start_installation(): void
    {
        $technician = $this->makeTeknisi();
        $customer = $this->makeCustomer('waiting_installation');

        $task = $this->makeScheduledTask($customer, TaskType::PEMASANGAN, $technician);

        $this->actingAs($technician)
            ->post(route('customers.installation.start', $customer))
            ->assertRedirect();

        $this->assertSame('installation_in_progress', $customer->fresh()->status);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
    }

    public function test_full_access_user_can_start_installation_without_being_scheduled(): void
    {
        $owner = $this->loginAsAdmin();
        $customer = $this->makeCustomer('waiting_installation');

        $this->actingAs($owner)
            ->post(route('customers.installation.start', $customer))
            ->assertRedirect();

        $this->assertSame('installation_in_progress', $customer->fresh()->status);
    }
}
