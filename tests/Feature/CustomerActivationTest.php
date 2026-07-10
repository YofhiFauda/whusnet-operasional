<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use App\Models\AuditLog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    protected function createCompleteCustomer(Pop $pop, InternetPackage $package): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'D00C000001',  // format baru: {cid_prefix}00{registration_prefix}{######}
            'full_name' => 'Budi Santoso',
            'gender' => 'Laki-laki',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'customer_status' => 'menunggu_pemasangan',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 12',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 12',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'province' => 'Jawa Timur',
            'city' => 'Ponorogo',
            'district' => $district->name,
            'village' => $village->name,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '20 Mbps',
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        return $customer;
    }

    public function test_unauthorized_user_cannot_activate_customer(): void
    {
        $csRole = Role::query()->where('name', 'Helpdesk')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);
        /** @var User $user */

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createCompleteCustomer($pop, $package);

        $this->actingAs($user);

        $response = $this->post("/customers/{$customer->id}/activate");

        $response->assertStatus(403);
        
        $customer->refresh();
        $this->assertEquals('installed', $customer->status);
        $this->assertNull($customer->cid);
    }

    public function test_cannot_activate_incomplete_customer(): void
    {
        $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Customer with missing address/village etc.
        $customer = Customer::create([
            'customer_code' => 'WHUS-2026-0001',
            'full_name' => 'Budi Santoso',
            'gender' => 'Laki-laki',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'pop_id' => $pop->id,
        ]);

        $response = $this->post("/customers/{$customer->id}/activate");

        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHas('error');
        
        $customer->refresh();
        $this->assertEquals('installed', $customer->status);
        $this->assertNull($customer->cid);
    }

    public function test_can_activate_complete_customer(): void
    {
        $user = $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createCompleteCustomer($pop, $package);

        $response = $this->post("/customers/{$customer->id}/activate");

        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHas('success');

        $customer->refresh();

        // Customer assertions
        $this->assertEquals('active', $customer->status);
        $this->assertEquals('aktif', $customer->customer_status);
        $this->assertEquals('siap_billing', $customer->data_completeness_status);
        $this->assertNotNull($customer->cid);
        // CID format: {cid_prefix}{mini_pop_or_olt}{dist_code}{request_id}_{DESA}_{NAMA}
        // POP ini belum mini POP, jadi memakai fallback olt='0' dan dist='XX'
        // Contoh: D0XXC000001_BABADAN_BUDISANTOSO
        $this->assertStringStartsWith('D', $customer->cid);
        $this->assertStringContainsString('C000001', $customer->cid);

        // Service assertions
        $service = $customer->customerService;
        $this->assertEquals('aktif', $service->service_status);
        $this->assertEquals('active', $service->billing_status);

        // Audit log assertions
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'Data Pelanggan',
            'action' => 'activate',
            'auditable_type' => get_class($customer),
            'auditable_id' => $customer->id,
        ]);

        $log = AuditLog::where('auditable_id', $customer->id)->firstOrFail();
        $this->assertNotNull($log->old_values);
        $this->assertNotNull($log->new_values);
        $this->assertEquals('active', $log->new_values['status']);
    }
}
