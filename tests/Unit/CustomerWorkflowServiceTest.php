<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\AuditLog;
use App\Enums\WorkflowTransition;
use App\Services\CustomerWorkflowService;
use Exception;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class CustomerWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerWorkflowService();
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
    }

    private function createCustomer(string $status = 'registered'): Customer
    {
        return Customer::factory()->create(['status' => $status]);
    }

    public function test_can_transition_from_registered_to_waiting_survey()
    {
        $customer = $this->createCustomer('registered');

        $result = $this->service->transition($customer, WorkflowTransition::WAITING_SURVEY);

        $this->assertTrue($result);
        $this->assertEquals('waiting_survey', $customer->fresh()->status);
    }

    public function test_cannot_transition_from_registered_to_active_directly()
    {
        $customer = $this->createCustomer('registered');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot transition from registered to active.");

        $this->service->transition($customer, WorkflowTransition::ACTIVE);
    }

    public function test_invalid_status_throws_invalid_argument_exception()
    {
        $customer = $this->createCustomer('registered');

        $this->expectException(InvalidArgumentException::class);
        
        $this->service->transition($customer, 'invalid_status');
    }
    
    public function test_transition_creates_audit_log()
    {
        $customer = $this->createCustomer('waiting_survey');
        
        $this->service->transition($customer, WorkflowTransition::SURVEY_IN_PROGRESS, 'Starting survey');
        
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $customer->id,
            'auditable_type' => Customer::class,
            'action' => 'status_transition'
        ]);
        
        $log = AuditLog::where('auditable_id', $customer->id)->where('action', 'status_transition')->first();
        $this->assertEquals('waiting_survey', $log->old_values['status']);
        $this->assertEquals('survey_in_progress', $log->new_values['status']);
        $this->assertEquals('Starting survey', $log->new_values['note']);
    }
}
