<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gejala yang dijaga: form verifikasi mengirim prorate_amount/subtotal/ppn/
 * total_amount sebagai input readonly hasil hitungan JavaScript. Readonly cuma
 * penghalang UI — POST manual bisa mengirim nominal apa pun dan dulu langsung
 * masuk ke invoice. Server harus menghitung ulang dan mengabaikan kiriman.
 */
class InitialInvoiceProrateIgnoresClientAmountTest extends TestCase
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

    private function createCustomerSiapVerifikasi(float $monthlyPrice, float $ppnRate): Customer
    {
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
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'D00C000009',
            'full_name' => 'Sri Wahyuni',
            'gender' => 'Perempuan',
            'primary_phone' => '081234500009',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 9',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 9',
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
            'monthly_price' => $monthlyPrice,
            'discount' => 0.00,
            'ppn' => $ppnRate,
            'total_monthly_bill' => $monthlyPrice * (1 + $ppnRate / 100),
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        return $customer;
    }

    public function test_nominal_kiriman_klien_diabaikan_dan_dihitung_ulang_server(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(300000, 0);

        // Aktivasi 16 Juni: 14 dari 30 hari (hari aktivasi digratiskan).
        $response = $this->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-16',
            'due_date' => '2026-06-26',
            'extra_installation_fee' => 100000,
            'extra_cable_fee' => 0,
            'extra_pole_fee' => 0,
            // Nominal palsu dari klien — harus diabaikan seluruhnya.
            'subtotal' => 1,
            'discount' => 0,
            'ppn' => 0,
            'prorate_amount' => 1,
            'total_amount' => 1,
        ]);

        $response->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        // Aktivasi 16 Juni: hari aktivasi digratiskan, 30 - 16 = 14 hari.
        $this->assertEquals(140000, (float) $invoice->prorate_amount);
        $this->assertEquals(240000, (float) $invoice->subtotal);
        $this->assertEquals(240000, (float) $invoice->total_amount);
        $this->assertEquals(240000, (float) $invoice->remaining_amount);
    }

    public function test_ppn_disimpan_sebagai_persen_bukan_nominal(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(200000, 11);

        // Aktivasi tanggal 1 Juni: 30 - 1 = 29 dari 30 hari.
        $this->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'ppn' => 22000, // klien kirim nominal; kolom invoices.ppn adalah PERSEN
        ]);

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        // 29/30 × 200.000 = 193.333,33 → 193.333; + PPN 11% = 214.599,63
        $this->assertEquals(11, (float) $invoice->ppn);
        $this->assertEquals(193333, (float) $invoice->subtotal);
        $this->assertEquals(214599.63, (float) $invoice->total_amount);
    }

    public function test_biaya_tambahan_negatif_ditolak(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(200000, 0);

        $response = $this->from('/verifications/queue')->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'extra_installation_fee' => -500000,
        ]);

        $response->assertSessionHasErrors('extra_installation_fee');
        $this->assertDatabaseCount('invoices', 0);
    }
}
