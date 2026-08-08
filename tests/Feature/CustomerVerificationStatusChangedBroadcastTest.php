<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\CustomerVerificationStatusChanged;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * App\Events\CustomerVerificationStatusChanged — broadcast dari
 * CustomerObserver::updated() begitu kolom `status` pelanggan berubah, dari
 * jalur mana pun (CustomerWorkflowService::transition() maupun update()
 * langsung). Nutup gap "dua admin verifikasi pelanggan yang sama tanpa
 * saling tahu" (docs/plan/analisa-realtime-spa-operasional.md §2.1 no. 10).
 */
class CustomerVerificationStatusChangedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'CVS1',
            'pop_code' => 'CVS1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Verification Broadcast Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeCustomer(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'CVS-'.random_int(10000, 99999),
            'full_name' => 'Pelanggan Verifikasi Test',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    public function test_updating_customer_status_dispatches_event_with_pop_id(): void
    {
        $customer = $this->makeCustomer('installed');

        Event::fake([CustomerVerificationStatusChanged::class]);

        $customer->update(['status' => 'verification_admin']);

        Event::assertDispatched(
            CustomerVerificationStatusChanged::class,
            fn ($event) => $event->customer->id === $customer->id
                && $event->customer->pop_id === $this->pop->id
        );
    }

    public function test_updating_customer_without_changing_status_does_not_dispatch_event(): void
    {
        $customer = $this->makeCustomer('installed');

        Event::fake([CustomerVerificationStatusChanged::class]);

        $customer->update(['full_name' => 'Nama Baru Tanpa Ganti Status']);

        Event::assertNotDispatched(CustomerVerificationStatusChanged::class);
    }

    public function test_broadcast_payload_carries_customer_id_and_status(): void
    {
        $customer = $this->makeCustomer('verification_admin');

        $payload = (new CustomerVerificationStatusChanged($customer))->broadcastWith();

        $this->assertSame($customer->id, $payload['customer_id']);
        $this->assertSame('verification_admin', $payload['status']);
    }

    public function test_row_endpoint_returns_fresh_cells_for_customer_still_in_queue(): void
    {
        $admin = $this->loginAsAdmin();
        $customer = $this->makeCustomer('verification_admin');

        $response = $this->actingAs($admin)->get(route('verifications.row', $customer->id));

        $response->assertOk();
        $response->assertSee('customer-status-cell-'.$customer->id, false);
        $response->assertSee('customer-action-cell-'.$customer->id, false);
    }

    public function test_row_endpoint_returns_no_content_when_customer_left_the_queue(): void
    {
        $admin = $this->loginAsAdmin();
        $customer = $this->makeCustomer('active');

        $response = $this->actingAs($admin)->get(route('verifications.row', $customer->id));

        $response->assertNoContent();
    }

    public function test_row_endpoint_denies_technician_not_assigned_to_customer(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $customer = $this->makeCustomer('installed');

        $response = $this->actingAs($teknisi)->get(route('verifications.row', $customer->id));

        $response->assertForbidden();
    }

    public function test_row_endpoint_allows_technician_assigned_to_customer(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $this->giveAllPopScope($teknisi);
        $customer = $this->makeCustomer('installed');

        $task = Task::create([
            'task_number' => 'TASK-CVS-'.random_int(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Task assigned',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->subHour(),
            'created_by' => $teknisi->id,
            'updated_by' => $teknisi->id,
        ]);
        $task->teamMembers()->create(['user_id' => $teknisi->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($teknisi)->get(route('verifications.row', $customer->id));

        $response->assertOk();
    }
}
