<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADHOC-92 — overpay pada invoice AWAL (studi kasus 550k di tagihan 100k)
 * ditandai `source=bayar_di_muka`; overpay di jenis invoice lain tetap
 * `kelebihan_bayar` seperti sebelumnya (ADHOC-38).
 */
class AdvancePaymentOverpayCreditsBalanceWithSourceTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = Pop::create([
            'code' => 'POP-ADV-1',
            'pop_code' => 'ADV1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Advance Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    public function test_overpay_di_invoice_awal_ditandai_bayar_di_muka(): void
    {
        $customer = $this->createCustomer('C-ADV-0001');
        $awal = $this->makeInvoice($customer, 'awal', 100000);

        $admin = $this->loginAsAdmin();
        $this->actingAs($admin)->post(route('invoices.payments.store', $awal->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 550000,
        ])->assertRedirect();

        $payment = Payment::where('invoice_id', $awal->id)->firstOrFail();
        $this->assertSame('450000.00', $payment->overpay_amount);

        $this->assertDatabaseHas('customer_balance_mutations', [
            'customer_id' => $customer->id,
            'type' => 'credit',
            'source' => 'bayar_di_muka',
            'amount' => '450000.00',
            'payment_id' => $payment->id,
        ]);
    }

    public function test_overpay_di_invoice_bulanan_tetap_kelebihan_bayar(): void
    {
        $customer = $this->createCustomer('C-ADV-0002');
        $bulanan = $this->makeInvoice($customer, 'bulanan', 150000);

        $admin = $this->loginAsAdmin();
        $this->actingAs($admin)->post(route('invoices.payments.store', $bulanan->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ])->assertRedirect();

        $payment = Payment::where('invoice_id', $bulanan->id)->firstOrFail();

        $this->assertDatabaseHas('customer_balance_mutations', [
            'customer_id' => $customer->id,
            'type' => 'credit',
            'source' => 'kelebihan_bayar',
            'amount' => '50000.00',
            'payment_id' => $payment->id,
        ]);
    }

    protected function createCustomer(string $code): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Customer Advance Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Advance Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Advance Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
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

        return $customer;
    }

    protected function makeInvoice(Customer $customer, string $type, float $total): Invoice
    {
        $service = CustomerService::where('customer_id', $customer->id)->firstOrFail();

        return Invoice::create([
            'invoice_number' => 'INV-'.$customer->customer_code.'-'.strtoupper($type),
            'invoice_type' => $type,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => 'belum_dibayar',
        ]);
    }
}
