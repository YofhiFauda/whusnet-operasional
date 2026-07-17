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
use App\Models\Invoice;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFinalVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
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
            'customer_code' => 'D00C000001',
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

    public function test_unauthorized_user_cannot_final_verify(): void
    {
        $csRole = Role::query()->where('name', 'Teknisi')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

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

        $response = $this->from('/verifications/queue')->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-08',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 16500,
            'prorate_amount' => 0,
            'total_amount' => 166500,
        ]);

        $response->assertStatus(403);
        
        $customer->refresh();
        $this->assertEquals('installed', $customer->status);
    }

    public function test_can_final_verify_and_generate_invoice(): void
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

        $response = $this->from('/verifications/queue')->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-08',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 16500,
            'prorate_amount' => 0,
            'total_amount' => 166500,
        ]);

        $response->assertRedirect('/verifications/queue');
        $response->assertSessionHas('success');

        $customer->refresh();

        // Customer assertions
        $this->assertEquals('active', $customer->status);
        $this->assertNotNull($customer->cid);

        // Service assertions
        $service = $customer->customerService;
        $this->assertEquals('aktif', $service->service_status);

        // Invoice assertions
        $invoice = Invoice::where('customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('2026-06', $invoice->billing_period);
        $this->assertEquals(166500, $invoice->total_amount);
        $this->assertEquals('belum_dibayar', $invoice->invoice_status->value);
    }
}
