<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment::generatePaymentNumber() — format `PAY-{invoice_number}` untuk
 * lunas sekali bayar, `PAY-{invoice_number}-{NN}` untuk cicilan (keputusan
 * user 2026-10-02, revisi BUG 13/2026-10-01). `invoice_number`-nya ditempel
 * apa adanya, `{NN}` urutan pembayaran ke berapa pada invoice itu (cicilan
 * ke-N), dihitung dari SEMUA baris `payments` milik invoice tsb (termasuk
 * yang nanti ditolak) — tidak pernah dipakai ulang. `{NN}` cuma muncul kalau
 * ini BUKAN pembayaran pertama, atau pembayaran pertama itu tidak langsung
 * melunasi sisa tagihan.
 *
 * Nama file dipertahankan dari era counter per-bulan sebelumnya
 * (`payment_number_sequences`, sekarang sudah tidak dipakai generator ini
 * sama sekali) — rancangan: `docs/plan/billing/rancangan-prefix-nomor-invoice.md` §8.
 */
class PaymentNumberSequenceWidthExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lunas_sekali_bayar_tanpa_suffix_urutan(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-A', 'TAG-20260613-000001');

        // Dibayar penuh (100000 = remaining_amount) pada pembayaran pertama
        // — bukan cicilan, jadi tanpa `-NN`.
        $this->assertSame('PAY-TAG-20260613-000001', Payment::generatePaymentNumber($invoice, 100000));
    }

    public function test_pembayaran_pertama_cicilan_mendapat_urutan_01(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-B', 'TAG-20260613-000002');

        // Dibayar sebagian (40000 dari 100000) — langsung ketahuan cicilan
        // sejak pembayaran pertama.
        $this->assertSame('PAY-TAG-20260613-000002-01', Payment::generatePaymentNumber($invoice, 40000));
    }

    public function test_pembayaran_kedua_cicilan_tetap_dapat_suffix_walau_melunasi(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-C', 'TAG-20260613-000003');

        $this->recordPayment($invoice, Payment::generatePaymentNumber($invoice, 40000), amount: 40000);

        // Pembayaran kedua melunasi sisa (60000) tapi invoice ini SUDAH
        // ketahuan cicilan (ordinal > 1) — nomor pertama yang sudah dicetak
        // tanpa suffix tidak diubah, tapi baris berikutnya tetap `-NN`.
        $this->assertSame('PAY-TAG-20260613-000003-02', Payment::generatePaymentNumber($invoice, 60000));
    }

    public function test_urutan_tidak_pernah_dipakai_ulang_walau_ada_yang_ditolak(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-D', 'TAG-20260613-000004');

        // Payment pertama DITOLAK, nominal penuh (lunas sekali bayar kalau
        // valid) — tetap tercatat tanpa suffix karena ordinal-nya 1.
        $this->recordPayment($invoice, Payment::generatePaymentNumber($invoice, 100000), status: 'ditolak', amount: 100000);

        // Pembayaran berikutnya ordinal-nya 2 (ikut baris yang ditolak tadi,
        // tidak dipakai ulang) — langsung dapat suffix "-02", bukan "-01".
        $this->assertSame('PAY-TAG-20260613-000004-02', Payment::generatePaymentNumber($invoice, 100000));
    }

    public function test_dua_invoice_berbeda_punya_urutan_independen(): void
    {
        [$customer, $invoiceA] = $this->createCustomerWithInvoice('C-SEQ-E', 'TAG-20260613-000005');

        // withoutEvents: invoiceB sengaja sama customer/type/period/amount
        // dengan invoiceA (satu-satunya hal yang mau diuji di sini ya
        // independensi COUNTER-nya) — tanpa ini, InvoiceObserver menolaknya
        // sebagai duplikat burst, yang bukan hal yang mau diuji.
        $invoiceB = Invoice::withoutEvents(fn () => $this->makeBulananInvoice($customer, 'TAG-20260614-000006'));

        $this->recordPayment($invoiceA, Payment::generatePaymentNumber($invoiceA, 40000), amount: 40000);

        // invoiceB belum pernah dibayar sama sekali — dibayar penuh langsung
        // di pembayaran pertama, TIDAK ikut urutan invoiceA yang sudah
        // ketahuan cicilan.
        $this->assertSame('PAY-TAG-20260614-000006', Payment::generatePaymentNumber($invoiceB, 100000));
    }

    public function test_generator_ikut_baris_payment_lama_hasil_import(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-F', 'TAG-20260613-000007');

        // Data lama/import bisa punya baris payment di invoice ini tanpa
        // lewat generator (payment_number bebas format). Urutan berikutnya
        // tetap wajib lanjut dari JUMLAH baris yang ada, bukan mulai dari 1
        // lagi dan bertabrakan secara konsep (meski payment_number string-nya
        // sendiri tidak pernah bertabrakan — kolom ini bukan unique).
        Payment::withoutEvents(function () use ($invoice) {
            Payment::create([
                'payment_number' => 'PAY-LEGACY-IMPORT-0001',
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'pop_id' => $invoice->pop_id,
                'payment_date' => '2026-06-13',
                'payment_method' => 'cash',
                'amount' => 50000,
                'payment_status' => 'valid',
            ]);
        });

        // Ordinal sudah 2 (baris legacy dihitung) — dapat suffix walau
        // nominalnya melunasi sisa tagihan.
        $this->assertSame('PAY-TAG-20260613-000007-02', Payment::generatePaymentNumber($invoice, 100000));
    }

    public function test_nomor_tidak_dipakai_ulang_walau_baris_payment_dihapus_permanen(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-G', 'TAG-20260613-000008');

        $this->recordPayment($invoice, Payment::generatePaymentNumber($invoice, 40000), amount: 40000);
        $second = $this->recordPayment($invoice, Payment::generatePaymentNumber($invoice, 40000), amount: 40000);

        // Hard delete (lihat CleanupLegacyDuplicateInvoicesCommand) — count()
        // turun ke 1, tapi urutan 02 sudah pernah terbit dan tidak boleh
        // dipakai lagi.
        Payment::withoutEvents(fn () => $second->delete());

        $this->assertSame('PAY-TAG-20260613-000008-03', Payment::generatePaymentNumber($invoice, 40000));
    }

    public function test_counter_diseed_dari_suffix_legacy_walau_jumlah_baris_sudah_berkurang(): void
    {
        [, $invoice] = $this->createCustomerWithInvoice('C-SEQ-H', 'TAG-20260613-000009');

        // Baris legacy `-05` tersisa sendirian (empat sebelumnya sudah terhapus):
        // count() = 1, tapi urutan terakhir yang pernah terbit = 5.
        Payment::withoutEvents(fn () => Payment::create([
            'payment_number' => 'PAY-TAG-20260613-000009-05',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 40000,
            'payment_status' => 'valid',
        ]));

        $this->assertSame('PAY-TAG-20260613-000009-06', Payment::generatePaymentNumber($invoice, 40000));
    }

    /**
     * @return array{0: Customer, 1: Invoice}
     */
    protected function createCustomerWithInvoice(string $customerCode, string $invoiceNumber): array
    {
        $this->seed(InternetPackageSeeder::class);
        $package = InternetPackage::query()->firstOrFail();

        $pop = Pop::create([
            'code' => 'POP-'.$customerCode,
            'pop_code' => substr($customerCode, -4),
            'registration_prefix' => 'CS',
            'cid_prefix' => 'DS',
            'name' => 'POP '.$customerCode,
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => 'Pelanggan Sequence Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Sequence Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Sequence Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 100000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = $this->makeBulananInvoice($customer, $invoiceNumber);

        return [$customer, $invoice];
    }

    protected function makeBulananInvoice(Customer $customer, string $invoiceNumber): Invoice
    {
        $service = CustomerService::where('customer_id', $customer->id)->firstOrFail();

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-13',
            'due_date' => '2026-06-15',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    protected function recordPayment(Invoice $invoice, string $paymentNumber, string $status = 'valid', float $amount = 100000): Payment
    {
        return Payment::withoutEvents(fn () => Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => $amount,
            'payment_status' => $status,
        ]));
    }
}
