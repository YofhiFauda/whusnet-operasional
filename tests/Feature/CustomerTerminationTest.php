<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTerminationReason;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTerminationTest extends TestCase
{
    use RefreshDatabase;

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
            // Masa langganan > 1 tahun — tidak ada denda (ADHOC-69 §3.1),
            // supaya test ini murni memverifikasi status pelanggan/layanan
            // seperti sebelumnya, tanpa perlu penalty_amount.
            'activation_date' => now()->subYears(2),
        ]);

        $reason = CustomerTerminationReason::create(['name' => 'Pelanggan pindah rumah']);

        $response = $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'terminated',
            'termination_reason_id' => $reason->id,
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

    /**
     * Modal "Request Putus Langganan" di Quick Hub List (customers/index)
     * submit lewat AJAX (Accept: application/json) — lihat
     * _termination_modal.blade.php kenapa (hindari bug ADHOC-20: form POST
     * biasa di atas List bikin validasi gagal nutup modal tanpa pesan error).
     * Server wajib balas JSON, bukan redirect, untuk request semacam ini.
     */
    public function test_terminate_via_ajax_returns_json()
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

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
            'customer_code' => 'C-TST-000004',
            'full_name' => 'Test Customer Ajax',
            'primary_phone' => '081234567893',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id ?? 1,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'package_price_snapshot' => 150000,
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subYears(2),
        ]);

        $reason = CustomerTerminationReason::create(['name' => 'Pelanggan pindah rumah']);

        $response = $this->postJson(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'terminated',
        ]);
    }

    /** Validasi gagal lewat AJAX wajib balas 422 + body `errors` (bukan redirect). */
    public function test_terminate_via_ajax_without_reason_returns_422_with_errors()
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

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
            'customer_code' => 'C-TST-000005',
            'full_name' => 'Test Customer Ajax Gagal',
            'primary_phone' => '081234567894',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $response = $this->postJson(route('customers.terminate', $customer), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('termination_reason_id');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'active',
        ]);
    }

    public function test_terminate_requires_reason()
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

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

        $response->assertSessionHasErrors('termination_reason_id');
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

        $reason = CustomerTerminationReason::create(['name' => 'Test reason']);

        $response = $this->actingAs($user)->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
        ]);

        $response->assertForbidden();
    }
}
