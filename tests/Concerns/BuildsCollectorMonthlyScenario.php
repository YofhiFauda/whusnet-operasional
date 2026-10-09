<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Data bantu Laporan Bulanan Admin Collector (ADHOC-90): POP, pelanggan,
 * invoice, dan payment dengan tanggal yang bisa dikendalikan test.
 */
trait BuildsCollectorMonthlyScenario
{
    private int $seq = 0;

    protected function seedBase(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function ownerUser(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    protected function makePop(string $name, string $type = 'cabang', ?Pop $parent = null): Pop
    {
        $n = ++$this->seq;

        return Pop::create([
            'code' => "POP-{$n}",
            'pop_code' => "P{$n}",
            'registration_prefix' => 'R'.$n,
            'cid_prefix' => 'C'.$n,
            'name' => $name,
            'type' => $type,
            'parent_id' => $parent?->id,
            'status' => 'active',
        ]);
    }

    protected function makeCustomer(Pop $pop): Customer
    {
        $n = ++$this->seq;

        return Customer::create([
            'customer_code' => 'CUST'.$n,
            'full_name' => 'Pelanggan '.$n,
            'primary_phone' => '0812345'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'registration_date' => '2026-01-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => InternetPackage::query()->firstOrFail()->id,
            'address' => 'Jl. Test Laporan Bulanan',
        ]);
    }

    protected function makeInvoice(Pop $pop, string $period, float $total, string $type = 'bulanan', float $discount = 0, ?Customer $customer = null): Invoice
    {
        $n = ++$this->seq;
        $customer ??= $this->makeCustomer($pop);

        // customer_service_id NOT NULL — satu layanan dipakai bersama semua
        // invoice pelanggan yang sama.
        $service = CustomerService::where('customer_id', $customer->id)->first()
            ?? CustomerService::create([
                'customer_id' => $customer->id,
                'internet_package_id' => $customer->internet_package_id,
                'package_name_snapshot' => 'Paket Test 20 Mbps',
                'download_speed_snapshot' => '20 Mbps',
                'upload_speed_snapshot' => '10 Mbps',
                'monthly_price' => 100000,
                'discount' => 0,
                'ppn' => 0,
                'total_monthly_bill' => 100000,
                'activation_date' => '2026-01-01',
                'due_date' => '2026-01-10',
                'service_status' => 'aktif',
                'billing_status' => 'active',
            ]);

        return Invoice::create([
            'invoice_number' => 'INV-T-'.$n,
            'invoice_type' => $type,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $customer->internet_package_id,
            'billing_period' => $period,
            'issue_date' => $period.'-01',
            'due_date' => $period.'-10',
            'subtotal' => $total + $discount,
            'discount' => $discount,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    /**
     * @param  ?User  $collector  null = dibayar langsung ke admin (kantor)
     */
    protected function makePayment(
        Invoice $invoice,
        float $amount,
        string $date,
        ?User $collector = null,
        float $overpay = 0,
        ?int $batchId = null,
        string $method = 'cash',
        ?string $note = 'Pembayaran test.',
        ?string $collectedDate = null,
        string $status = 'valid',
    ): Payment {
        $n = ++$this->seq;

        $payment = Payment::create([
            'payment_number' => 'PAY-T-'.$n,
            'invoice_id' => $invoice->id,
            'payment_batch_id' => $batchId,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => $date,
            'collected_date' => $collectedDate,
            'payment_method' => $method,
            'amount' => $amount,
            'overpay_amount' => $overpay,
            'received_by' => User::where('email', 'owner@whusnet.net')->value('id'),
            'collected_by' => $collector?->id,
            'payment_status' => $status,
            'note' => $note,
        ]);

        $invoice->refresh()->recalculateFromPayments();

        return $payment;
    }
}
