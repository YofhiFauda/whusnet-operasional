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
 * Gejala yang dijaga: `billing_period` dan `due_date` dulu diinput terpisah dari
 * tanggal aktivasi. Admin bisa mengirim periode Juni untuk prorata Juli —
 * invoice tercetak dengan periode berbeda dari bulan yang benar-benar ditagih,
 * sementara bulan yang dilewati GenerateMonthlyInvoicesCommand mengikuti
 * `activation_date` (= `issue_date`), bukan periode yang tertulis.
 *
 * Sekaligus mengunci materai: masuk subtotal tagihan awal lewat
 * `invoices.other_fee`, tidak pernah ikut tagihan bulanan.
 */
class TagihanAwalPeriodeIkutTanggalAktivasiTest extends TestCase
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

    private function createCustomerSiapVerifikasi(float $monthlyPrice = 110000, float $ppnRate = 0): Customer
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
            'customer_code' => 'D00C000031',
            'full_name' => 'Rahmat Hidayat',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234500031',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 31',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 31',
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
            'total_monthly_bill' => $monthlyPrice,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        return $customer;
    }

    public function test_periode_dan_jatuh_tempo_diturunkan_dari_tanggal_aktivasi(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        $this->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-07-21',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('2026-07', $invoice->billing_period);
        $this->assertSame('2026-07-21', $invoice->due_date->format('Y-m-d'));
        $this->assertSame('2026-07-21', $invoice->issue_date->format('Y-m-d'));
    }

    public function test_periode_kiriman_klien_diabaikan(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        // Periode & tempo palsu — dulu langsung masuk invoice apa adanya.
        $this->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-07-21',
            'billing_period' => '2026-06',
            'due_date' => '2026-12-31',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('2026-07', $invoice->billing_period, 'Periode harus mengikuti tanggal aktivasi, bukan kiriman klien.');
        $this->assertSame('2026-07-21', $invoice->due_date->format('Y-m-d'));
    }

    public function test_form_tidak_lagi_mewajibkan_periode_dan_jatuh_tempo(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        $response = $this->get(route('customers.verification.admin', $customer->id));

        $response->assertOk();
        $response->assertDontSee('name="billing_period"', false);
        $response->assertDontSee('name="due_date"', false);
        $response->assertSee('name="other_fee"', false);
    }

    public function test_form_tidak_lagi_punya_input_nominal(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        $response = $this->get(route('customers.verification.admin', $customer->id));

        $response->assertOk();

        // Nominal turunan dihitung server; input readonly-nya cuma bikin admin
        // ragu memverifikasi aritmatika yang bukan urusannya.
        foreach (['subtotal', 'prorate_amount', 'discount', 'ppn', 'total_amount'] as $field) {
            $response->assertDontSee('name="'.$field.'"', false);
        }

        // Diganti kwitansi.
        $response->assertSee('kwitansi_total', false);
        $response->assertSee('Tagihan Pertama', false);
        $response->assertSee('Dibayar saat aktivasi.', false);
    }

    public function test_baris_ppn_tidak_dirender_kalau_rate_nol(): void
    {
        $this->loginAsAdmin();

        // Semua paket saat ini PPN sudah termasuk harga (rate 0).
        $customer = $this->createCustomerSiapVerifikasi(110000, 0);

        $this->get(route('customers.verification.admin', $customer->id))
            ->assertOk()
            ->assertDontSee('id="kwitansi_ppn"', false);
    }

    public function test_baris_ppn_dirender_kalau_rate_lebih_dari_nol(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi(110000, 11);

        $this->get(route('customers.verification.admin', $customer->id))
            ->assertOk()
            ->assertSee('id="kwitansi_ppn"', false)
            ->assertSee('PPN 11%', false);
    }

    public function test_aktivasi_tetap_berhasil_tanpa_field_nominal(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        // Persis seperti yang dikirim form kwitansi: cuma tanggal + biaya.
        $this->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-07-21',
            'extra_installation_fee' => 100000,
            'other_fee' => 0,
            'extra_cable_fee' => 0,
            'extra_pole_fee' => 0,
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertEquals(135484, (float) $invoice->total_amount);
        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_materai_masuk_subtotal_tagihan_awal(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        $this->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-07-21',
            'extra_installation_fee' => 125000,
            'other_fee' => 10000,
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        // 11/31 × 110.000 = 39.032 (angka kanonik) + 125.000 + 10.000
        $this->assertEquals(35484, (float) $invoice->prorate_amount);
        $this->assertEquals(10000, (float) $invoice->other_fee);
        $this->assertEquals(170484, (float) $invoice->subtotal);
        $this->assertEquals(170484, (float) $invoice->total_amount);
        $this->assertEquals(170484, (float) $invoice->remaining_amount);
    }

    public function test_materai_negatif_ditolak(): void
    {
        $this->loginAsAdmin();

        $customer = $this->createCustomerSiapVerifikasi();

        $response = $this->from('/verifications/queue')->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-07-21',
            'other_fee' => -10000,
        ]);

        $response->assertSessionHasErrors('other_fee');
        $this->assertDatabaseCount('invoices', 0);
    }
}
