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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bug RBAC dilaporkan user (Issue 3): guard assignment yang dipasang di
 * start() (lihat SurveyInstallationScheduleGuardTest) gak dipasang di
 * report()/store() — permission generik customers.detail.survey.update /
 * installation.update SENDIRIAN cukup buat isi laporan survey/pemasangan
 * pelanggan MANA PUN, gak peduli assignment. Keputusan eksplisit user:
 * SEMUA non-full-access wajib assignment, TERMASUK NOC — gak ada
 * pengecualian role. Cuma hasFullAccess() (Owner/wildcard) yang bypass.
 */
class SurveyInstallationReportAssignmentGuardTest extends TestCase
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
            'code' => 'SMN-RPTGRD',
            'pop_code' => 'RPG',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Report Guard Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function makeCustomer(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'RPG-'.rand(10000, 99999),
            'full_name' => 'Pelanggan Report Guard Test',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function makeActiveTask(Customer $customer, TaskType $type, ?User $technician = null): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-RPG-'.rand(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task Report Guard Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician?->id ?? 1,
            'updated_by' => $technician?->id ?? 1,
        ]);

        if ($technician) {
            $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);
        }

        return $task;
    }

    // ── Survey report() ─────────────────────────────────────────────

    public function test_technician_not_assigned_cannot_view_survey_report_page(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress');
        $this->makeActiveTask($customer, TaskType::SURVEY, $otherTechnician);

        $response = $this->actingAs($technician)->get(route('customers.survey.report', $customer->id));

        $response->assertStatus(403);
    }

    public function test_noc_not_assigned_cannot_view_survey_report_page(): void
    {
        $noc = $this->makeUser('noc');
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress');
        $this->makeActiveTask($customer, TaskType::SURVEY, $technician);

        $response = $this->actingAs($noc)->get(route('customers.survey.report', $customer->id));

        $response->assertStatus(403);
    }

    public function test_owner_can_view_survey_report_page_without_assignment(): void
    {
        $owner = $this->loginAsAdmin();
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress');
        $this->makeActiveTask($customer, TaskType::SURVEY, $technician);
        $customer->latestSurvey()->create(['started_at' => now()]);

        $response = $this->actingAs($owner)->get(route('customers.survey.report', $customer->id));

        $response->assertStatus(200);
    }

    // ── Survey store() ──────────────────────────────────────────────

    public function test_technician_not_assigned_cannot_submit_survey_store(): void
    {
        Storage::fake('public');

        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress');
        $this->makeActiveTask($customer, TaskType::SURVEY, $otherTechnician);

        $response = $this->actingAs($technician)->post(route('customers.survey.store', $customer->id), [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 10,
            'nearest_odp' => 'ODP-1',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'difficulty_level' => 'MUDAH',
        ]);

        $response->assertStatus(403);
    }

    public function test_noc_not_assigned_cannot_submit_survey_store(): void
    {
        Storage::fake('public');

        $noc = $this->makeUser('noc');
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress');
        $this->makeActiveTask($customer, TaskType::SURVEY, $technician);

        $response = $this->actingAs($noc)->post(route('customers.survey.store', $customer->id), [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 10,
            'nearest_odp' => 'ODP-1',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'difficulty_level' => 'MUDAH',
        ]);

        $response->assertStatus(403);
    }

    public function test_assigned_technician_can_submit_survey_store(): void
    {
        Storage::fake('public');

        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('survey_in_progress');
        $this->makeActiveTask($customer, TaskType::SURVEY, $technician);

        $response = $this->actingAs($technician)->post(route('customers.survey.store', $customer->id), [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 10,
            'nearest_odp' => 'ODP-1',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'difficulty_level' => 'MUDAH',
        ]);

        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();
    }

    // ── Installation report() ───────────────────────────────────────

    public function test_technician_not_assigned_cannot_view_installation_report_page(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('installation_in_progress');
        $this->makeActiveTask($customer, TaskType::PEMASANGAN, $otherTechnician);
        $customer->installations()->create(['installation_status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));

        $response->assertStatus(403);
    }

    public function test_noc_not_assigned_cannot_view_installation_report_page(): void
    {
        $noc = $this->makeUser('noc');
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('installation_in_progress');
        $this->makeActiveTask($customer, TaskType::PEMASANGAN, $technician);
        $customer->installations()->create(['installation_status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($noc)->get(route('customers.installation.report', $customer->id));

        $response->assertStatus(403);
    }

    // ── Installation store() ────────────────────────────────────────

    public function test_technician_not_assigned_cannot_submit_installation_store(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('installation_in_progress');
        $this->makeActiveTask($customer, TaskType::PEMASANGAN, $otherTechnician);

        $response = $this->actingAs($technician)->post(route('customers.installation.store', $customer->id), [
            'installation_status' => 'in_progress',
            'device_type' => 'ont',
        ]);

        $response->assertStatus(403);
    }

    public function test_noc_not_assigned_cannot_submit_installation_store(): void
    {
        $noc = $this->makeUser('noc');
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('installation_in_progress');
        $this->makeActiveTask($customer, TaskType::PEMASANGAN, $technician);

        $response = $this->actingAs($noc)->post(route('customers.installation.store', $customer->id), [
            'installation_status' => 'in_progress',
            'device_type' => 'ont',
        ]);

        $response->assertStatus(403);
    }

    public function test_assigned_technician_can_submit_installation_store(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('installation_in_progress');
        $this->makeActiveTask($customer, TaskType::PEMASANGAN, $technician);

        $response = $this->actingAs($technician)->post(route('customers.installation.store', $customer->id), [
            'installation_status' => 'in_progress',
            'device_type' => 'ont',
        ]);

        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();
    }
}
