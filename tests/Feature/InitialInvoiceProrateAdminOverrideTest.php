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
 * Gejala yang dijaga: `prorate_amount_override` adalah SATU-SATUNYA nominal
 * turunan yang boleh ditimpa admin di form verifikasi (nego harga / koreksi
 * pembulatan). Kalau kosong, server tetap pakai hasil hitung prorata otomatis
 * — bedanya dengan subtotal/ppn/total_amount yang selalu diabaikan
 * (lihat InitialInvoiceProrateIgnoresClientAmountTest).
 */
class InitialInvoiceProrateAdminOverrideTest extends TestCase
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
            'customer_code' => 'D00C000010',
            'full_name' => 'Agus Setiawan',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234500010',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 10',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 10',
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

    public function test_admin_bisa_menimpa_biaya_berlangganan_prorata(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(300000, 0);

        // Aktivasi 16 Juni: hitungan otomatis = 14/30 x 300.000 = 140.000,
        // admin negosiasi jadi 100.000.
        $response = $this->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-06-16',
            'prorate_amount_override' => 100000,
        ]);

        $response->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertEquals(100000, (float) $invoice->prorate_amount);
        $this->assertEquals(100000, (float) $invoice->subtotal);
        $this->assertEquals(100000, (float) $invoice->total_amount);
    }

    public function test_prorata_otomatis_dipakai_kalau_override_kosong(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(300000, 0);

        $response = $this->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-06-16',
        ]);

        $response->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertEquals(140000, (float) $invoice->prorate_amount);
    }

    public function test_override_negatif_ditolak(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(300000, 0);

        $response = $this->from('/verifications/queue')->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-06-16',
            'prorate_amount_override' => -50000,
        ]);

        $response->assertSessionHasErrors('prorate_amount_override');
        $this->assertDatabaseCount('invoices', 0);
    }
}
