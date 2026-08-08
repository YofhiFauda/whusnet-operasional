<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FopDashboardProcessToTimTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $technician;

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

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Admin User
        $this->adminUser = User::factory()->create();
        $adminRole = Role::where('code', 'admin')->first();
        $this->adminUser->role_id = $adminRole->id;
        $this->adminUser->save();

        $taskManagePermission = Permission::where('code', 'task.manage')->first();
        if ($taskManagePermission) {
            $adminRole->permissions()->syncWithoutDetaching([$taskManagePermission->id]);
        }

        // Assign pop scope tree/selected pop for Admin
        $this->adminUser->roleScopes()->create([
            'role_id' => $adminRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        // Technician
        $this->technician = User::factory()->create();
        $techRole = Role::where('code', 'teknisi')->first();
        $this->technician->role_id = $techRole->id;
        $this->technician->save();
    }

    public function test_admin_can_process_to_tim_successfully(): void
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

        $payload = [
            'technician_id' => $this->technician->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'notes' => 'Pasang kabel rapi',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('customers.verification.process-to-team', $customer->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check customer status transitioned
        $customer->refresh();
        $this->assertEquals('waiting_installation', $customer->status);

        // Check task created
        $this->assertDatabaseHas('tasks', [
            'customer_id' => $customer->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'status' => TaskStatus::PENDING->value,
            'pop_id' => $this->pop->id,
        ]);

        // Check customer_installations created explicitly
        $this->assertDatabaseHas('customer_installations', [
            'customer_id' => $customer->id,
            'installation_status' => 'scheduled',
            'fop_id' => $this->adminUser->id,
        ]);
    }

    public function test_fails_when_technician_has_conflict_without_override(): void
    {
        $scheduledTime = now()->addDay()->startOfHour();

        // Create conflicting task
        $conflictingTask = Task::create([
            'task_number' => 'TASK-2026-0001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Conflicting Survey',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => $scheduledTime,
            'sla_minutes' => 120,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
        $conflictingTask->teamMembers()->create([
            'user_id' => $this->technician->id,
            'role_in_task' => 'lead',
        ]);

        $task = Task::create([
            'task_number' => 'TASK-2026-0002',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Installation Task',
            'status' => TaskStatus::TERJADWAL->value,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $payload = [
            'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
            'team_member_ids' => [$this->technician->id],
        ];

        // Should redirect back with errors
        $response = $this->actingAs($this->adminUser)
            ->from(route('verifications.queue'))
            ->put(route('tasks.update', $task->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('conflict');
    }

    public function test_can_override_conflict_if_permission_allowed(): void
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

        $scheduledTime = now()->addDay()->startOfHour();

        // Create conflicting task
        $conflictingTask = Task::create([
            'task_number' => 'TASK-2026-0001',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Conflicting Survey',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => $scheduledTime,
            'sla_minutes' => 120,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
        $conflictingTask->teamMembers()->create([
            'user_id' => $this->technician->id,
            'role_in_task' => 'lead',
        ]);

        // Add override permission to Admin role manually for testing
        $adminRole = Role::where('code', 'admin')->first();
        $overridePermission = Permission::where('code', 'task.conflict.override')->first();
        if ($overridePermission) {
            $adminRole->permissions()->syncWithoutDetaching([$overridePermission->id]);
        }
        app(EffectiveAccessService::class)->clearCache($this->adminUser);

        $payload = [
            'technician_id' => $this->technician->id,
            'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
            'notes' => 'Overridden notes',
            'conflict_override' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('customers.verification.process-to-team', $customer->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check status transitioned
        $customer->refresh();
        $this->assertEquals('waiting_installation', $customer->status);
    }
}
