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
 * ADHOC-92 (G5) — payment yang MEMAKAI saldo pelanggan (manual atau
 * auto-pay) harus mengembalikan saldonya kalau ditolak. Sebelum ini,
 * `PaymentController::reject()` cuma membalik kredit SUMBER overpay,
 * tidak pernah membalik pemakaian.
 */
class PaymentRejectRefundsBalanceUsedTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected Customer $customer;

    protected CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = Pop::create([
            'code' => 'POP-RJ-1',
            'pop_code' => 'RJ1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Reject Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'C-RJ-0001',
            'full_name' => 'Customer Reject Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Reject Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => 'Jl. Reject Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        CustomerService::create([
            'customer_id' => $this->customer->id,
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

        $this->service = CustomerService::where('customer_id', $this->customer->id)->firstOrFail();
    }

    public function test_reject_payment_yang_pakai_saldo_manual_mengembalikan_saldo(): void
    {
        $balances = app(CustomerBalanceService::class);

        // Beri saldo 200k lewat kredit non-payment (setara hasil overpay lama).
        $balances->creditWithoutPayment($this->customer, 200000, $this->pop->id, 'Setup saldo test');
        $this->assertSame(200000.0, $balances->balance($this->customer));

        $invoice = $this->makeInvoice('2026-06', 150000);

        // `amount` divalidasi min:1 (G6 — pembayaran saldo-murni lewat form
        // mustahil, di luar scope ADHOC-92) — jadi tunai kecil + saldo yang
        // menutup sisanya.
        $admin = $this->loginAsAdmin();
        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 1000,
            'use_balance_amount' => 149000,
        ])->assertRedirect();

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('149000.00', $payment->balance_used_amount);
        $this->assertSame(51000.0, $balances->balance($this->customer));

        $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Salah pilih tagihan.',
        ])->assertRedirect();

        $this->assertSame(200000.0, $balances->balance($this->customer));

        $invoice->refresh();
        $this->assertSame('belum_dibayar', $invoice->invoice_status->value);
    }

    protected function makeInvoice(string $billingPeriod, float $total): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-'.$this->customer->customer_code.'-'.$billingPeriod,
            'invoice_type' => 'bulanan',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $this->service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $billingPeriod,
            'issue_date' => $billingPeriod.'-01',
            'due_date' => $billingPeriod.'-15',
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
