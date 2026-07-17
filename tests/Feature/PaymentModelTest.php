<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\User;
use App\Models\Village;
use Carbon\Carbon;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
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
            'data_completeness_status' => 'siap_billing',
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

    public function test_payment_can_be_saved_with_relations_and_status(): void
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
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_amount' => 166500.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 166500.00,
            'invoice_status' => 'belum_dibayar',
            'created_by' => $user->id,
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-202606-0001',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 100000.00,
            'received_by' => $user->id,
            'proof_file' => 'payments/proof-001.jpg',
            'payment_status' => 'pending',
            'note' => 'Pembayaran awal.',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_number' => 'PAY-202606-0001',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_status' => 'pending',
        ]);

        $payment->refresh();

        $this->assertInstanceOf(Carbon::class, $payment->payment_date);
        $this->assertEquals('2026-06-13', $payment->payment_date->format('Y-m-d'));
        $this->assertSame('100000.00', $payment->amount);

        $this->assertEquals($invoice->id, $payment->invoice->id);
        $this->assertEquals($customer->id, $payment->customer->id);
        $this->assertEquals($pop->id, $payment->pop->id);
        $this->assertEquals($user->id, $payment->receiver->id);

        $this->assertTrue($invoice->payments->contains($payment));
        $this->assertTrue($customer->payments->contains($payment));
        $this->assertTrue($pop->payments->contains($payment));
    }
}
