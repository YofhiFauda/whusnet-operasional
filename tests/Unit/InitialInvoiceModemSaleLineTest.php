<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\User;
use App\Models\Village;
use App\Services\InitialInvoiceService;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gejala yang dijaga: nominal "Jual Modem" (ownership modem sewa/beli) wajib
 * ikut jadi komponen `subtotal` tagihan AWAL dan muncul sebagai baris
 * `invoice_items` tersendiri — bukan nyampur ke `other_fee`/materai, dan
 * bukan baris bernominal nol saat fitur ini tidak dipakai (pemanggil lama).
 *
 * `InvoiceItemBuilder::rebuildFor()` sendiri sudah menegakkan invarian
 * SUM(invoice_items.amount) == invoice.subtotal — test ini cuma memastikan
 * jalur baru tidak melanggarnya (tidak throw) dan baris Jual Modem benar2
 * nyangkut di DB dengan nominal yang benar.
 */
class InitialInvoiceModemSaleLineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    private function createCustomerSiapVerifikasi(float $monthlyPrice): Customer
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
            'ppn' => 0,
            'total_monthly_bill' => $monthlyPrice,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        return $customer;
    }

    #[Test]
    public function modem_sale_amount_masuk_subtotal_dan_jadi_baris_jual_modem(): void
    {
        $customer = $this->createCustomerSiapVerifikasi(300000);
        $service = $customer->customerService;
        $user = User::factory()->create();

        $invoiceService = app(InitialInvoiceService::class);
        $billing = $invoiceService->calculate($service, '2026-06-16', [], 150000.0);

        $invoice = $invoiceService->issue($customer, $service, $billing, '2026-06-16', $user->id, 150000.0);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'subcategory_name_snapshot' => 'Jual Modem',
            'amount' => 150000.0,
        ]);

        // Komponen modem harus benar-benar ikut subtotal, bukan cuma nempel
        // di baris invoice_items tanpa dihitung ke total.
        $this->assertEqualsWithDelta(150000.0, $billing['modem_sale_amount'], 0.01);
        $this->assertEqualsWithDelta($billing['subtotal'], (float) $invoice->subtotal, 0.01);
    }

    #[Test]
    public function modem_sale_amount_default_nol_tidak_menambah_baris(): void
    {
        $customer = $this->createCustomerSiapVerifikasi(300000);
        $service = $customer->customerService;
        $user = User::factory()->create();

        $invoiceService = app(InitialInvoiceService::class);
        $billing = $invoiceService->calculate($service, '2026-06-16');

        $invoice = $invoiceService->issue($customer, $service, $billing, '2026-06-16', $user->id);

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'subcategory_name_snapshot' => 'Jual Modem',
        ]);
    }

    /**
     * Koreksi (jalur BD/kategori Bisnis) — `withInstallationFee()` dulu
     * cuma menjumlah LIMA suku ke `subtotal` (tanpa `modem_sale_amount`),
     * jadi kategori Bisnis yang pilih "Beli dari Gudang" kehilangan nominal
     * modem dari `subtotal` begitu BD menyuntik Biaya Instalasi — padahal
     * SN-nya sudah kejual (ownership CUSTOMER, custody kepotong). Sekarang
     * `withInstallationFee()` WAJIB mengikutkan `modem_sale_amount` yang
     * sudah ada di `$billing` (dititipkan `calculate()` lewat
     * `finalVerify()`), PERSIS cara `calculate()` menjumlahkannya sendiri.
     */
    #[Test]
    public function with_installation_fee_tetap_mengikutkan_modem_sale_amount_ke_subtotal(): void
    {
        $customer = $this->createCustomerSiapVerifikasi(300000);
        $service = $customer->customerService;

        $invoiceService = app(InitialInvoiceService::class);
        $billing = $invoiceService->calculate($service, '2026-06-16', [], 150000.0);

        $billingAfterBd = $invoiceService->withInstallationFee($billing, 300000.0);

        $this->assertEqualsWithDelta(150000.0, $billingAfterBd['modem_sale_amount'], 0.01);
        $expectedSubtotal = $billing['prorate_amount'] + 300000.0 + 150000.0;
        $this->assertEqualsWithDelta($expectedSubtotal, $billingAfterBd['subtotal'], 0.01);
    }
}
