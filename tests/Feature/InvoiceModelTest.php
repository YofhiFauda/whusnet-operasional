<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Village;
use Carbon\Carbon;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    /**
     * Helper to create a complete customer for testing.
     */
    protected function createTestCustomer(Pop $pop, InternetPackage $package): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'WHUS-2026-0001',
            'full_name' => 'Budi Santoso',
            'gender' => 'Laki-laki',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
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
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer;
    }

    public function test_invoice_can_be_saved_with_relations_and_casts(): void
    {
        $user = User::factory()->create();

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
        $customer = $this->createTestCustomer($pop, $package);
        $customer->refresh();
        $service = $customer->customerService;

        $invoice = Invoice::create([
            'invoice_number' => 'INV-202606-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000.00,
            'discount' => 10000.00,
            'ppn' => 11.00,
            'other_fee' => 11000.00,
            'total_amount' => 166400.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 166400.00,
            'invoice_status' => 'belum_dibayar',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'INV-202606-0001',
            'invoice_status' => 'belum_dibayar',
        ]);

        // Refresh model from DB to test casts and relations
        $invoice->refresh();

        // Cast assertions
        $this->assertInstanceOf(Carbon::class, $invoice->issue_date);
        $this->assertInstanceOf(Carbon::class, $invoice->due_date);
        $this->assertEquals('2026-06-01', $invoice->issue_date->format('Y-m-d'));
        $this->assertEquals('2026-06-15', $invoice->due_date->format('Y-m-d'));

        // Decimal cast assertions
        $this->assertSame('150000.00', $invoice->subtotal);
        $this->assertSame('10000.00', $invoice->discount);
        $this->assertSame('11.00', $invoice->ppn);
        $this->assertSame('11000.00', $invoice->other_fee);
        $this->assertSame('166400.00', $invoice->total_amount);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('166400.00', $invoice->remaining_amount);

        // Relation assertions
        $this->assertEquals($customer->id, $invoice->customer->id);
        $this->assertEquals($pop->id, $invoice->pop->id);
        $this->assertEquals($service->id, $invoice->customerService->id);
        $this->assertEquals($package->id, $invoice->internetPackage->id);
        $this->assertEquals($user->id, $invoice->creator->id);

        // Reverse relation assertion on Customer
        $this->assertTrue($customer->invoices->contains($invoice));
    }
}
