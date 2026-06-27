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

class FopDashboardProcessToTimTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $technician;
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

        // Assign pop scope tree/selected pop for Admin
        $this->adminUser->roleScopes()->create([
            'role_id' => $adminRole->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
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
            'phone' => '0812345678',
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
            'status' => TaskStatus::TERJADWAL->value,
            'pop_id' => $this->pop->id,
        ]);

        // Check customer_installations created explicitly
        $this->assertDatabaseHas('customer_installations', [
            'customer_id' => $customer->id,
            'technician_id' => $this->technician->id,
            'installation_status' => 'scheduled',
            'installation_note' => 'Pasang kabel rapi',
            'fop_id' => $this->adminUser->id,
        ]);
    }

    public function test_fails_when_technician_has_conflict_without_override(): void
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

        $payload = [
            'technician_id' => $this->technician->id,
            'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
            'notes' => 'Bentrokan',
        ];

        // Should redirect back with errors
        $response = $this->actingAs($this->adminUser)
            ->from(route('verifications.queue'))
            ->post(route('customers.verification.process-to-team', $customer->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('conflict');

        // Verify status and DB has not changed
        $customer->refresh();
        $this->assertEquals('waiting_acc', $customer->status);
        $this->assertDatabaseMissing('customer_installations', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_can_override_conflict_if_permission_allowed(): void
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
        $overridePermission = \App\Models\Permission::where('code', 'task.conflict.override')->first();
        if ($overridePermission) {
            $adminRole->permissions()->syncWithoutDetaching([$overridePermission->id]);
        }
        app(\App\Services\EffectiveAccessService::class)->clearCache($this->adminUser);

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

