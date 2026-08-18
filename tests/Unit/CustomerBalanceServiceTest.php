<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Logic murni CustomerBalanceService: balance() = SUM(credit)-SUM(debit),
 * ledger ditulis benar, reverseCreditForPayment() idempotent.
 */
class CustomerBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerBalanceService $service;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->service = new CustomerBalanceService;
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    private function makeCustomer(): Customer
    {
        return Customer::factory()->create(['internet_package_id' => $this->package->id]);
    }

    private function makePayment(Customer $customer, float $amount = 100000): Payment
    {
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // Periode berbeda tiap panggilan — kalau tidak, invoice kedua untuk
        // pelanggan yang sama (customer/type/period/amount identik) kena
        // guard InvoiceObserver::rejectBurstDuplicate().
        static $sequence = 0;
        $sequence++;

        $invoice = Invoice::create([
            'invoice_number' => 'INV-UNIT-'.uniqid(),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => sprintf('2026-%02d', ($sequence % 12) + 1),
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

        return Payment::create([
            'payment_number' => 'PAY-UNIT-'.uniqid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => $amount,
            'payment_status' => 'valid',
        ]);
    }

    public function test_balance_is_zero_without_any_mutation(): void
    {
        $customer = $this->makeCustomer();

        $this->assertSame(0.0, $this->service->balance($customer));
    }

    public function test_credit_raises_balance(): void
    {
        $customer = $this->makeCustomer();
        $payment = $this->makePayment($customer);

        $mutation = $this->service->credit($customer, 25000, $payment);

        $this->assertSame('credit', $mutation->type->value);
        $this->assertSame($payment->id, $mutation->payment_id);
        $this->assertSame($customer->pop_id, $mutation->pop_id);
        $this->assertSame(25000.0, $this->service->balance($customer));
    }

    public function test_debit_lowers_balance(): void
    {
        $customer = $this->makeCustomer();
        $creditPayment = $this->makePayment($customer);
        $this->service->credit($customer, 40000, $creditPayment);

        $debitPayment = $this->makePayment($customer);
        $this->service->debit($customer, 15000, $debitPayment);

        $this->assertSame(25000.0, $this->service->balance($customer));
    }

    public function test_debit_more_than_available_throws_and_does_not_write_ledger(): void
    {
        $customer = $this->makeCustomer();
        $creditPayment = $this->makePayment($customer);
        $this->service->credit($customer, 10000, $creditPayment);

        $debitPayment = $this->makePayment($customer);

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service->debit($customer, 50000, $debitPayment);
        } finally {
            // Saldo tetap 10.000 — tak ada baris debit yatim yang ditulis
            // sebelum exception dilempar.
            $this->assertSame(10000.0, $this->service->balance($customer));
        }
    }

    public function test_reverse_credit_for_payment_is_idempotent(): void
    {
        $customer = $this->makeCustomer();
        $payment = $this->makePayment($customer);
        $this->service->credit($customer, 30000, $payment);

        $this->service->reverseCreditForPayment($payment);
        $this->assertSame(0.0, $this->service->balance($customer));

        // Panggilan kedua tidak membalik dua kali (tak jadi -30.000).
        $this->service->reverseCreditForPayment($payment);
        $this->assertSame(0.0, $this->service->balance($customer));
    }

    public function test_reverse_credit_for_payment_without_credit_is_a_no_op(): void
    {
        $customer = $this->makeCustomer();
        $payment = $this->makePayment($customer);

        $this->service->reverseCreditForPayment($payment);

        $this->assertSame(0.0, $this->service->balance($customer));
    }
}
