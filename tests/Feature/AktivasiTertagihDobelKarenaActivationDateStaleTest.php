<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
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
 * Gejala yang dijaga: pelanggan daftar Juni, aktif 21 Juli, lalu menerima DUA
 * tagihan untuk periode Juli — satu AWAL (prorata, dari verifikasi) dan satu
 * BULANAN penuh (dari cron).
 *
 * Sebabnya `customer_services.activation_date` diisi `registration_date` waktu
 * pendaftaran dan dulu tidak pernah ditimpa saat aktivasi. Satu-satunya lapis
 * penjaga yang bisa membedakan kasus ini adalah pengecekan bulan aktivasi di
 * GenerateMonthlyInvoicesCommand; dua lapis lainnya (query `alreadyExists` dan
 * InvoiceObserver::creating) di-scope per `invoice_type` sehingga AWAL dan
 * BULANAN dianggap bukan duplikat satu sama lain, dan tabel `invoices` tidak
 * punya unique index sebagai backstop.
 */
class AktivasiTertagihDobelKarenaActivationDateStaleTest extends TestCase
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

    /**
     * Pelanggan siap verifikasi dengan `activation_date` warisan pendaftaran —
     * sengaja diisi tanggal daftar, persis seperti CustomerController::store.
     */
    private function createCustomerDaftarJuni(float $monthlyPrice = 110000): Customer
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
            'customer_code' => 'D00C000021',
            'full_name' => 'Masudah Yuni Fitri',
            'gender' => 'Perempuan',
            'phone' => '081234500021',
            'primary_phone' => '081234500021',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'customer_status' => 'menunggu_pemasangan',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 21',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 21',
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
            'ppn' => 0.00,
            'total_monthly_bill' => $monthlyPrice,
            // Warisan pendaftaran: tanggal DAFTAR, bukan tanggal layanan menyala.
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        return $customer;
    }

    private function aktivasi(Customer $customer, string $issueDate): void
    {
        $this->post(route('customers.verification.final', $customer->id), [
            'billing_period' => substr($issueDate, 0, 7),
            'issue_date' => $issueDate,
            'due_date' => $issueDate,
            'extra_installation_fee' => 0,
            'extra_cable_fee' => 0,
            'extra_pole_fee' => 0,
        ])->assertSessionHas('success');
    }

    public function test_aktivasi_menimpa_activation_date_dengan_tanggal_terbit(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerDaftarJuni();

        $this->aktivasi($customer, '2026-07-21');

        $service = $customer->fresh()->customerService;

        $this->assertSame(
            '2026-07-21',
            $service->activation_date->format('Y-m-d'),
            'activation_date harus tanggal layanan menyala, bukan tanggal daftar.'
        );
    }

    public function test_bulan_aktivasi_tidak_dapat_tagihan_bulanan_kedua(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerDaftarJuni();

        $this->aktivasi($customer, '2026-07-21');

        // Cron bulanan jalan di bulan yang sama dengan aktivasi.
        $this->travelTo('2026-07-25 07:00:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $invoices = Invoice::where('customer_id', $customer->id)->get();

        $this->assertCount(1, $invoices, 'Bulan aktivasi hanya boleh punya satu tagihan (AWAL).');
        $this->assertSame(InvoiceType::AWAL->value, $invoices->first()->invoice_type->value ?? $invoices->first()->invoice_type);
        $this->assertSame('2026-07', $invoices->first()->billing_period);
    }

    public function test_bulan_setelah_aktivasi_tetap_dapat_tagihan_bulanan(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerDaftarJuni();

        $this->aktivasi($customer, '2026-07-21');

        $this->travelTo('2026-08-01 07:00:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $bulanan = Invoice::where('customer_id', $customer->id)
            ->where('invoice_type', InvoiceType::BULANAN->value)
            ->get();

        $this->assertCount(1, $bulanan, 'Skip bulan aktivasi tidak boleh ikut mematikan bulan berikutnya.');
        $this->assertSame('2026-08', $bulanan->first()->billing_period);
        $this->assertEquals(110000, (float) $bulanan->first()->total_amount);
    }
}
