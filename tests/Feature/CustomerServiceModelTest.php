<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_service_can_be_saved_with_relations_and_calculations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);

        $customer = Customer::firstOrFail();
        $package = InternetPackage::firstOrFail();

        // Calculate expected bill
        $monthlyPrice = $package->monthly_price;
        $discount = 15000.00;
        $ppnPercent = 11.00;
        $discountedPrice = max(0, $monthlyPrice - $discount);
        $totalBill = $discountedPrice * (1 + $ppnPercent / 100);

        // Create CustomerService
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => $package->download_speed_mbps . ' Mbps',
            'upload_speed_snapshot' => $package->upload_speed_mbps . ' Mbps',
            'monthly_price' => $monthlyPrice,
            'discount' => $discount,
            'ppn' => $ppnPercent,
            'total_monthly_bill' => $totalBill,
            'activation_date' => '2026-06-11',
            'due_date' => '2026-07-11',
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // Assert database persistence
        $this->assertDatabaseHas('customer_services', [
            'id' => $service->id,
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $monthlyPrice,
            'discount' => $discount,
            'ppn' => $ppnPercent,
            'total_monthly_bill' => $totalBill,
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // Assert relationship
        $retrievedCustomer = Customer::findOrFail($customer->id);
        $this->assertNotNull($retrievedCustomer->customerService);
        $this->assertEquals($service->id, $retrievedCustomer->customerService->id);
        $this->assertEquals($package->name, $retrievedCustomer->customerService->package_name_snapshot);

        // Assert relationship to internet package
        $this->assertNotNull($service->internetPackage);
        $this->assertEquals($package->name, $service->internetPackage->name);
    }

    public function test_customer_service_is_deleted_on_customer_cascade(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $customer = Customer::firstOrFail();
        $package = InternetPackage::firstOrFail();

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $package->monthly_price,
            'total_monthly_bill' => $package->monthly_price,
            'service_status' => 'calon_pelanggan',
        ]);

        $this->assertDatabaseHas('customer_services', [
            'id' => $service->id,
        ]);

        // Delete Customer
        $customer->delete();

        // Assert cascade deleted
        $this->assertDatabaseMissing('customer_services', [
            'id' => $service->id,
        ]);
    }
}
