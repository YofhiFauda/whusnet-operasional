<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerAdditionalDeviceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_customer_can_have_multiple_additional_devices(): void
    {
        $pop = $this->createPop('ADD1');
        $customer = $this->createCustomer($pop, 'TEST-ADD-001');

        $customer->additionalDevices()->create(['device_type' => 'AP', 'brand' => 'TP-Link']);
        $customer->additionalDevices()->create(['device_type' => 'STB']);

        $this->assertCount(2, $customer->additionalDevices);
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(Pop $pop, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => 'Customer Additional Device '.$code,
            'primary_phone' => '0812345678',
            'pop_id' => $pop->id,
            'status' => 'installed',
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }
}
