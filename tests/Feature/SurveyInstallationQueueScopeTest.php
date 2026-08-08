<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Permission;
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
 * Bug RBAC dilaporkan user: Teknisi bisa lihat SEMUA pelanggan di antrean
 * Survey / Verifikasi & Pemasangan, bukan cuma yang Task-nya dijadwalkan
 * buat dirinya. Root cause: CustomerSurveyController::index() &
 * CustomerVerificationController::index() gak pernah scope query-nya per
 * teknisi — cuma gerbang permission generik customers.detail.survey.view/
 * installation.view. NOC/FOP/Admin/Owner (hasFullAccess) tetap harus liat
 * semua buat supervisi.
 */
class SurveyInstallationQueueScopeTest extends TestCase
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
            'code' => 'SMN-QSCOPE',
            'pop_code' => 'QSC',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Queue Scope Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        // all_pop — testfile ini menguji pembatasan per-assignment (teknisi),
        // bukan pembatasan POP scope. Tanpa ini applyUserScope() bakal deny-
        // by-default (scope belum di-setup ≠ akses penuh, lihat CLAUDE.md
        // § POP Scope) dan bikin assertion "sees all" salah sebab, bukan
        // salah baca.
        $this->giveAllPopScope($user);

        return $user;
    }

    private function makeCustomer(string $status, string $name): Customer
    {
        return Customer::create([
            'customer_code' => 'QSC-'.rand(10000, 99999),
            'full_name' => $name,
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function assignTask(Customer $customer, TaskType $type, User $technician): void
    {
        $task = Task::create([
            'task_number' => 'TASK-QSC-'.rand(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task assigned',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->subHour(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);
    }

    // ── Survey queue ───────────────────────────────────────────────

    public function test_technician_only_sees_customers_assigned_to_them_in_survey_queue(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');

        $assignedCustomer = $this->makeCustomer('waiting_survey', 'Pelanggan Assigned');
        $notAssignedCustomer = $this->makeCustomer('waiting_survey', 'Pelanggan Not Assigned');

        $this->assignTask($assignedCustomer, TaskType::SURVEY, $technician);
        $this->assignTask($notAssignedCustomer, TaskType::SURVEY, $otherTechnician);

        $response = $this->actingAs($technician)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee('Pelanggan Assigned');
        $response->assertDontSee('Pelanggan Not Assigned');
    }

    public function test_technician_with_no_task_at_all_sees_nothing_in_survey_queue(): void
    {
        $technician = $this->makeUser('teknisi');
        $this->makeCustomer('waiting_survey', 'Pelanggan Tanpa Task');

        $response = $this->actingAs($technician)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertDontSee('Pelanggan Tanpa Task');
    }

    public function test_noc_sees_all_customers_in_survey_queue(): void
    {
        $noc = $this->makeUser('noc');
        $technician = $this->makeUser('teknisi');

        $assignedCustomer = $this->makeCustomer('waiting_survey', 'Pelanggan A');
        $notAssignedCustomer = $this->makeCustomer('waiting_survey', 'Pelanggan B');
        $this->assignTask($assignedCustomer, TaskType::SURVEY, $technician);

        $response = $this->actingAs($noc)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee('Pelanggan A');
        $response->assertSee('Pelanggan B');
    }

    public function test_owner_sees_all_customers_in_survey_queue(): void
    {
        $owner = $this->loginAsAdmin();
        $this->makeCustomer('waiting_survey', 'Pelanggan Owner Lihat');

        $response = $this->actingAs($owner)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee('Pelanggan Owner Lihat');
    }

    // ── Verifikasi & Pemasangan queue ──────────────────────────────

    public function test_technician_only_sees_customers_assigned_to_them_in_installation_queue(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');

        $assignedCustomer = $this->makeCustomer('waiting_installation', 'Pasang Assigned');
        $notAssignedCustomer = $this->makeCustomer('waiting_installation', 'Pasang Not Assigned');

        $this->assignTask($assignedCustomer, TaskType::PEMASANGAN, $technician);
        $this->assignTask($notAssignedCustomer, TaskType::PEMASANGAN, $otherTechnician);

        $response = $this->actingAs($technician)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('Pasang Assigned');
        $response->assertDontSee('Pasang Not Assigned');
    }

    public function test_technician_with_no_task_at_all_sees_nothing_in_installation_queue(): void
    {
        $technician = $this->makeUser('teknisi');
        $this->makeCustomer('waiting_installation', 'Pasang Tanpa Task');

        $response = $this->actingAs($technician)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertDontSee('Pasang Tanpa Task');
    }

    public function test_fop_sees_all_customers_in_installation_queue(): void
    {
        $fop = $this->makeUser('fop');
        $technician = $this->makeUser('teknisi');

        $assignedCustomer = $this->makeCustomer('waiting_installation', 'Pasang C');
        $notAssignedCustomer = $this->makeCustomer('waiting_installation', 'Pasang D');
        $this->assignTask($assignedCustomer, TaskType::PEMASANGAN, $technician);

        $response = $this->actingAs($fop)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('Pasang C');
        $response->assertSee('Pasang D');
    }

    // ── Akses langsung /verifications/{id}/admin (bypass query index) ──
    //
    // Route ini digerbangin middleware customers.detail.installation.validate
    // di routes/web.php — role teknisi/fop gak punya permission ini secara
    // default (cuma NOC/Admin/pop_admin/Owner). Tapi permission itu bisa
    // di-grant manual lewat Role Matrix UI kapan aja (customization runtime,
    // gak balik ke seeder default sampai di-reset) — makanya test di bawah
    // grant permission-nya manual ke teknisi, buat simulasikan skenario nyata
    // yang dilaporkan user (teknisi bisa buka /verifications/{id}/admin) dan
    // pastiin guard assignment-nya tetap jalan meski permission-nya ada.

    private function grantInstallationValidatePermission(Role $role): void
    {
        $permission = Permission::where('code', 'customers.detail.installation.validate')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function test_technician_with_validate_permission_but_not_assigned_cannot_open_verification_admin_page(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->grantInstallationValidatePermission($teknisiRole);

        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');

        $customer = $this->makeCustomer('waiting_installation', 'Pasang Direct Not Assigned');
        $this->assignTask($customer, TaskType::PEMASANGAN, $otherTechnician);

        $response = $this->actingAs($technician)->get(route('customers.verification.admin', $customer));

        $response->assertForbidden();
    }

    public function test_technician_with_validate_permission_but_no_task_at_all_cannot_open_verification_admin_page(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->grantInstallationValidatePermission($teknisiRole);

        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('waiting_installation', 'Pasang Direct No Task');

        $response = $this->actingAs($technician)->get(route('customers.verification.admin', $customer));

        $response->assertForbidden();
    }

    public function test_technician_with_validate_permission_and_assigned_via_survey_task_can_open_verification_admin_page(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->grantInstallationValidatePermission($teknisiRole);

        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('waiting_acc', 'Pasang Direct Survey Assigned');
        $this->assignTask($customer, TaskType::SURVEY, $technician);

        $response = $this->actingAs($technician)->get(route('customers.verification.admin', $customer));

        $response->assertOk();
    }

    public function test_noc_can_open_verification_admin_page_directly_without_assignment(): void
    {
        $noc = $this->makeUser('noc');
        $customer = $this->makeCustomer('waiting_installation', 'Pasang Direct NOC');

        $response = $this->actingAs($noc)->get(route('customers.verification.admin', $customer));

        $response->assertOk();
    }
}
