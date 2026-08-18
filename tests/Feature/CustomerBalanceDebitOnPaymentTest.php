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
use Tests\TestCase;

/**
 * Pemakaian saldo pelanggan (`use_balance_amount`) pada pembayaran baru:
 * saldo berkurang, invoice ter-update benar, dan saldo tak cukup ditolak
 * bersih (tanpa payment yatim) — PaymentService::record().
 */
class CustomerBalanceDebitOnPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    /** Pelanggan lebih bayar Rp50.000 di invoice pertama, lalu pakai sebagian di invoice kedua. */
    private function giveCustomerBalance(Customer $customer, Pop $pop, float $amount): void
    {
        $sourceInvoice = $this->createInvoice($pop, $customer, 'INV-BAL-SOURCE-'.$customer->id, '2026-05');

        $this->actingAs($this->loginAsAdmin())->post(route('invoices.payments.store', $sourceInvoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000 + $amount,
        ]);
    }

    public function test_paying_with_partial_customer_balance_reduces_balance_and_updates_invoice(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-BAL-1', 'BAL1', 'POP Balance Test');
        $customer = $this->makeCustomer($pop, 'C-BAL-0001');

        $this->giveCustomerBalance($customer, $pop, 50000);
        $this->assertSame(50000.0, app(CustomerBalanceService::class)->balance($customer));

        $invoice = $this->createInvoice($pop, $customer, 'INV-BAL-0001');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-14',
            'payment_method' => 'cash',
            'amount' => 100000,
            'use_balance_amount' => 50000,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $invoice->refresh();
        $this->assertSame('150000.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining_amount);
        $this->assertSame('lunas', $invoice->invoice_status->value);

        $this->assertSame(0.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_full_page_payment_form_shows_balance_checklist_only_when_customer_has_balance(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-BAL-3', 'BAL3', 'POP Balance Test 3');
        $customer = $this->makeCustomer($pop, 'C-BAL-0003');

        $invoiceNoBalance = $this->createInvoice($pop, $customer, 'INV-BAL-0003');
        $this->actingAs($admin)
            ->get(route('invoices.payments.create', $invoiceNoBalance->id))
            ->assertOk()
            ->assertDontSee('Saldo Pelanggan Tersedia');

        $this->giveCustomerBalance($customer, $pop, 30000);

        $invoiceWithBalance = $this->createInvoice($pop, $customer, 'INV-BAL-0004', '2026-07');
        $this->actingAs($admin)
            ->get(route('invoices.payments.create', $invoiceWithBalance->id))
            ->assertOk()
            ->assertSee('Saldo Pelanggan Tersedia')
            ->assertSee('use_balance_amount', false);
    }

    public function test_using_more_balance_than_available_is_rejected_without_creating_payment(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-BAL-2', 'BAL2', 'POP Balance Test 2');
        $customer = $this->makeCustomer($pop, 'C-BAL-0002');

        $this->giveCustomerBalance($customer, $pop, 20000);
        $this->assertSame(20000.0, app(CustomerBalanceService::class)->balance($customer));

        $invoice = $this->createInvoice($pop, $customer, 'INV-BAL-0002');
        $paymentCountBefore = Payment::count();

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-14',
            'payment_method' => 'cash',
            'amount' => 50000,
            'use_balance_amount' => 999999,
        ]);

        $response->assertSessionHasErrors('use_balance_amount');
        $this->assertSame($paymentCountBefore, Payment::count());
        $this->assertSame(20000.0, app(CustomerBalanceService::class)->balance($customer));

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
    }

    protected function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function makeCustomer(Pop $pop, string $customerCode): Customer
    {
        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => 'Customer Balance Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Balance Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Balance Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        return $customer;
    }

    protected function createInvoice(Pop $pop, Customer $customer, string $invoiceNumber, string $billingPeriod = '2026-06'): Invoice
    {
        $service = CustomerService::create([
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

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $billingPeriod,
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
    }
}
