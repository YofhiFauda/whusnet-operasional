<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTerminationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_user_can_terminate_customer_and_service()
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-TST-000001',
            'full_name' => 'Test Customer',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id ?? 1,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'package_price_snapshot' => 150000,
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
        ]);

        $response = $this->post(route('customers.terminate', $customer), [
            'reason' => 'Pelanggan pindah rumah',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'terminated',
        ]);

        $this->assertDatabaseHas('customer_services', [
            'id' => $service->id,
            'service_status' => 'berhenti',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'user_id' => $user->id,
            'action' => 'terminate',
        ]);

        $activity = AuditLog::where('action', 'terminate')
            ->where('auditable_id', $customer->id)
            ->first();

        $this->assertEquals('Pelanggan pindah rumah', $activity->new_values['reason']);
    }

    public function test_terminate_requires_reason()
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-TST-000002',
            'full_name' => 'Test Customer 2',
            'primary_phone' => '081234567891',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $response = $this->post(route('customers.terminate', $customer), []);

        $response->assertSessionHasErrors('reason');
    }

    public function test_user_without_permission_cannot_terminate()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-TST-000003',
            'full_name' => 'Test Customer 3',
            'primary_phone' => '081234567892',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('customers.terminate', $customer), [
            'reason' => 'Test reason',
        ]);

        $response->assertForbidden();
    }
}
