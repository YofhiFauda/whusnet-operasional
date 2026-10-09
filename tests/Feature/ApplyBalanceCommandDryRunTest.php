<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADHOC-92 §4.3 — `billing:apply-balance` untuk saldo yang masuk SETELAH
 * tagihan bulan itu sudah terbit (kasus pinggiran), dan buat audit
 * `--dry-run` sebelum go-live (§4.5).
 */
class ApplyBalanceCommandDryRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        [$customer, $invoice] = $this->createCustomerWithOpenInvoice();

        app(CustomerBalanceService::class)->creditWithoutPayment(
            $customer, 150000, $invoice->pop_id, 'Test dry-run'
        );

        $this->artisan('billing:apply-balance', ['--dry-run' => true])->assertSuccessful();

        $invoice->refresh();
        $this->assertSame('belum_dibayar', $invoice->invoice_status->value);
        $this->assertFalse(Payment::where('invoice_id', $invoice->id)->exists());
        $this->assertSame(150000.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_tanpa_dry_run_benar_benar_melunasi_tagihan_dari_saldo(): void
    {
        [$customer, $invoice] = $this->createCustomerWithOpenInvoice();

        app(CustomerBalanceService::class)->creditWithoutPayment(
            $customer, 150000, $invoice->pop_id, 'Test apply'
        );

        $this->artisan('billing:apply-balance')->assertSuccessful();

        $invoice->refresh();
        $this->assertSame('lunas', $invoice->invoice_status->value);
        $this->assertSame(0.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_customer_filter_membatasi_ke_satu_pelanggan(): void
    {
        [$customerA, $invoiceA] = $this->createCustomerWithOpenInvoice('C-APB-A');
        [$customerB, $invoiceB] = $this->createCustomerWithOpenInvoice('C-APB-B');

        app(CustomerBalanceService::class)->creditWithoutPayment($customerA, 150000, $invoiceA->pop_id, 'A');
        app(CustomerBalanceService::class)->creditWithoutPayment($customerB, 150000, $invoiceB->pop_id, 'B');

        $this->artisan('billing:apply-balance', ['--customer' => $customerA->id])->assertSuccessful();

        $invoiceA->refresh();
        $invoiceB->refresh();
        $this->assertSame('lunas', $invoiceA->invoice_status->value);
        $this->assertSame('belum_dibayar', $invoiceB->invoice_status->value);
    }

    /**
     * @return array{0: Customer, 1: Invoice}
     */
    protected function createCustomerWithOpenInvoice(string $code = 'C-APB-0001'): array
    {
        $pop = Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => substr($code, -4),
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Customer '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. '.$code,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        return [$customer, $invoice];
    }
}
