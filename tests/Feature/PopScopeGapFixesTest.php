<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Distribution;
use App\Models\FopTask;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi buat docs/plan/analisa-celah-scope-pop.md — tiap kasus di sini
 * membuktikan satu temuan yang sebelumnya bocor/IDOR sekarang ke-tutup.
 * Actor selalu punya scope `selected_pop` yang cuma nyakup POP A — POP B
 * harus di luar jangkauan di SEMUA endpoint yang diuji.
 */
class PopScopeGapFixesTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->popA = Pop::create([
            'code' => 'PSG-A', 'pop_code' => 'PGA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Scope Gap A', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->popB = Pop::create([
            'code' => 'PSG-B', 'pop_code' => 'PGB', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Scope Gap B', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    /**
     * Actor ber-role $roleCode, scope selected_pop cuma ke $this->popA.
     */
    private function makeScopedUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->popA->id]);

        return $user;
    }

    /**
     * `task.view.all`/`task.manage` gak dipunyai role `fop` secara default
     * di seeder (cuma `fop_tasks.*` — beda permission, lihat
     * RolePermissionSeeder) — grant manual sama seperti pola
     * SurveyInstallationQueueScopeTest::grantInstallationValidatePermission().
     */
    private function grantTaskPermission(Role $role, string $code): void
    {
        $permission = Permission::where('code', $code)->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    private function makeCustomer(Pop $pop, string $name): Customer
    {
        return Customer::create([
            'customer_code' => 'PSG-'.rand(10000, 99999),
            'full_name' => $name,
            'primary_phone' => '081200000000',
            'status' => 'registered',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    // ── Temuan #1: FopTaskController::index()/history() tanpa scope ────

    public function test_fop_task_index_hides_tasks_outside_scope(): void
    {
        $fop = $this->makeScopedUser('fop');

        FopTask::create([
            'task_number' => 'TFOP-PSG-0001', 'task_date' => now(), 'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Task POP A', 'pop_id' => $this->popA->id, 'status' => TaskStatus::DRAFT->value,
            'priority' => 'Medium',
        ]);
        FopTask::create([
            'task_number' => 'TFOP-PSG-0002', 'task_date' => now(), 'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Task POP B', 'pop_id' => $this->popB->id, 'status' => TaskStatus::DRAFT->value,
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($fop)->get(route('fop-tasks.index'));

        $response->assertOk();
        $response->assertSee('Task POP A');
        $response->assertDontSee('Task POP B');
    }

    // ── Temuan #12: FopTaskController per-record (update/destroy/showHistory) ──

    public function test_fop_task_update_rejects_actor_outside_scope(): void
    {
        $fop = $this->makeScopedUser('fop');

        $taskB = FopTask::create([
            'task_number' => 'TFOP-PSG-0003', 'task_date' => now(), 'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Task POP B', 'pop_id' => $this->popB->id, 'status' => TaskStatus::DRAFT->value,
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($fop)->putJson(route('fop-tasks.update', $taskB), [
            'tugas' => 'Coba ubah dari luar scope',
        ]);

        $response->assertForbidden();
    }

    // ── Temuan #2/#3: CustomerSurveyController/CustomerVerificationController::index() ──

    public function test_survey_queue_hides_customers_outside_scope_for_non_technician(): void
    {
        $helpdesk = $this->makeScopedUser('helpdesk');

        $this->makeCustomer($this->popA, 'Survey Customer A')->update(['status' => 'waiting_survey']);
        $this->makeCustomer($this->popB, 'Survey Customer B')->update(['status' => 'waiting_survey']);

        $response = $this->actingAs($helpdesk)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee('Survey Customer A');
        $response->assertDontSee('Survey Customer B');
    }

    public function test_verification_queue_hides_customers_outside_scope_for_non_technician(): void
    {
        $helpdesk = $this->makeScopedUser('helpdesk');

        $this->makeCustomer($this->popA, 'Verif Customer A')->update(['status' => 'waiting_acc']);
        $this->makeCustomer($this->popB, 'Verif Customer B')->update(['status' => 'waiting_acc']);

        $response = $this->actingAs($helpdesk)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('Verif Customer A');
        $response->assertDontSee('Verif Customer B');
    }

    // ── Temuan #4: TaskController::getTeknisiForUser() anti-pattern ────

    public function test_teknisi_dropdown_on_edit_form_excludes_technicians_outside_actor_scope(): void
    {
        // Aktor discope selected_pop ke POP A saja (lewat makeScopedUser()) —
        // TaskPolicy::edit() (temuan #9) sekarang JUGA menuntut task-nya
        // sendiri ada di POP yang sama, jadi task dibuat di POP A biar
        // authorize('edit') lolos dan getTeknisiForUser() beneran kepanggil.
        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $this->grantTaskPermission($fopRole, 'task.manage');
        $this->grantTaskPermission($fopRole, 'task.assign.team');
        $fop = $this->makeScopedUser('fop');

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisiPopA = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Teknisi POP A']);
        $scopeA = UserRoleScope::create(['user_id' => $teknisiPopA->id, 'role_id' => $teknisiRole->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scopeA->id, 'pop_id' => $this->popA->id]);

        $teknisiPopB = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Teknisi POP B']);
        $scopeB = UserRoleScope::create(['user_id' => $teknisiPopB->id, 'role_id' => $teknisiRole->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scopeB->id, 'pop_id' => $this->popB->id]);

        $customer = $this->makeCustomer($this->popA, 'Pelanggan Task');
        $task = Task::create([
            'task_number' => 'TASK-PSG-0001', 'customer_id' => $customer->id, 'pop_id' => $this->popA->id,
            'task_type' => TaskType::MAINTENANCE->value, 'title' => 'MTN', 'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(), 'created_by' => $fop->id, 'updated_by' => $fop->id,
        ]);

        $response = $this->actingAs($fop)->get(route('tasks.edit', $task));

        $response->assertOk();
        $response->assertSee('Teknisi POP A');
        $response->assertDontSee('Teknisi POP B');
    }

    // ── Temuan #8: CustomerController show/edit/update/destroy IDOR ────

    public function test_customer_show_rejects_actor_outside_scope(): void
    {
        $helpdesk = $this->makeScopedUser('helpdesk');
        $customerB = $this->makeCustomer($this->popB, 'Pelanggan POP B');

        $response = $this->actingAs($helpdesk)->get(route('customers.show', $customerB));

        $response->assertForbidden();
    }

    public function test_customer_show_allows_actor_within_scope(): void
    {
        $helpdesk = $this->makeScopedUser('helpdesk');
        $customerA = $this->makeCustomer($this->popA, 'Pelanggan POP A');

        $response = $this->actingAs($helpdesk)->get(route('customers.show', $customerA));

        $response->assertOk();
    }

    // ── Temuan #9: TaskPolicy::view()/edit() tanpa cek pop_id ───────────

    public function test_task_show_rejects_actor_outside_scope_despite_view_all_permission(): void
    {
        $this->grantTaskPermission(Role::where('code', 'fop')->firstOrFail(), 'task.view.all');
        $fop = $this->makeScopedUser('fop');

        $customerB = $this->makeCustomer($this->popB, 'Pelanggan Task B');
        $taskB = Task::create([
            'task_number' => 'TASK-PSG-0002', 'customer_id' => $customerB->id, 'pop_id' => $this->popB->id,
            'task_type' => TaskType::MAINTENANCE->value, 'title' => 'MTN B', 'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(), 'created_by' => $fop->id, 'updated_by' => $fop->id,
        ]);

        $response = $this->actingAs($fop)->get(route('tasks.show', $taskB));

        $response->assertForbidden();
    }

    // ── Temuan #10: Master\DistributionController tanpa scope ──────────

    public function test_distribution_index_hides_records_outside_scope(): void
    {
        $admin = $this->makeScopedUser('admin');

        Distribution::create(['pop_id' => $this->popA->id, 'code' => 'DIST-A', 'name' => 'Distribusi A']);
        Distribution::create(['pop_id' => $this->popB->id, 'code' => 'DIST-B', 'name' => 'Distribusi B']);

        $response = $this->actingAs($admin)->get(route('master.distribusi.index'));

        $response->assertOk();
        $response->assertSee('Distribusi A');
        $response->assertDontSee('Distribusi B');
    }

    public function test_distribution_update_rejects_actor_outside_scope(): void
    {
        $admin = $this->makeScopedUser('admin');
        $distB = Distribution::create(['pop_id' => $this->popB->id, 'code' => 'DIST-C', 'name' => 'Distribusi C']);

        $response = $this->actingAs($admin)->put(route('master.distribusi.update', $distB), [
            'pop_id' => $this->popB->id,
            'code' => 'DIST-C',
            'name' => 'Distribusi C Diubah',
        ]);

        $response->assertForbidden();
    }

    // ── Temuan #11: Master\PopController per-record tanpa scope ────────

    public function test_pop_show_rejects_actor_outside_scope(): void
    {
        $admin = $this->makeScopedUser('admin');

        $response = $this->actingAs($admin)->get(route('master.pop.show', $this->popB));

        $response->assertForbidden();
    }

    public function test_pop_show_allows_actor_within_scope(): void
    {
        $admin = $this->makeScopedUser('admin');

        $response = $this->actingAs($admin)->get(route('master.pop.show', $this->popA));

        $response->assertOk();
    }
}
