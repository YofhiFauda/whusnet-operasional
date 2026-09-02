<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Models\Village;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_address_can_be_saved_with_relations(): void
    {
        // Seed standard tables (including region master)
        $this->seed(DatabaseSeeder::class);
        $this->seed(CustomerSeeder::class);

        // Retrieve seeded records
        $customer = Customer::firstOrFail();
        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();

        // Create Customer Address
        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Pemuda No. 10',
            'province' => 'Jawa Timur',
            'city' => $city->name,
            'district' => $district->name,
            'village' => $village->name,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'latitude' => -7.8694000,
            'longitude' => 111.4621000,
            'house_photo' => 'documents/simulasi/house_test.jpg',
            'contract_photo' => 'documents/simulasi/contract_test.pdf',
        ]);

        // Assert database persistence
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Pemuda No. 10',
            'province' => 'Jawa Timur',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'latitude' => -7.8694000,
            'longitude' => 111.4621000,
        ]);

        // Assert Customer relationship
        $retrievedCustomer = Customer::findOrFail($customer->id);
        $this->assertNotNull($retrievedCustomer->customerAddress);
        $this->assertEquals($address->id, $retrievedCustomer->customerAddress->id);
        $this->assertEquals('Jl. Pemuda No. 10', $retrievedCustomer->customerAddress->full_address);

        // Assert address belongs to region master relations
        $this->assertNotNull($address->city()->first());
        $this->assertEquals($city->name, $address->city()->first()->name);
        $this->assertNotNull($address->district()->first());
        $this->assertEquals($district->name, $address->district()->first()->name);
        $this->assertNotNull($address->village()->first());
        $this->assertEquals($village->name, $address->village()->first()->name);
    }

    public function test_customer_address_is_deleted_on_customer_cascade(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(CustomerSeeder::class);
        $customer = Customer::firstOrFail();

        // Create Address
        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Mawar No. 5',
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
        ]);

        // Delete Customer
        $customer->delete();

        // Assert Address is cascade deleted
        $this->assertDatabaseMissing('customer_addresses', [
            'id' => $address->id,
        ]);
    }
}
