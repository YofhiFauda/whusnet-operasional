<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentNumberSequence;
use App\Models\Pop;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment::generatePaymentNumber() — pengganti generator MAX+1 lama
 * (`PAY-{Ym}-%04d`) yang jebol di 9.999 pembayaran/bulan (docs/plan/analisa-
 * billing-tagihan-pembayaran-kolektor.md §A-7 #5, §C-2(b)). Lebar digit
 * wajib naik otomatis begitu lewat 9999, dan generator berurutan tidak boleh
 * menghasilkan nomor kembar dalam satu periode.
 */
class PaymentNumberSequenceWidthExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_number_in_a_period_keeps_four_digit_padding(): void
    {
        $number = Payment::generatePaymentNumber('2026-06-13');

        $this->assertSame('PAY-202606-0001', $number);
    }

    public function test_sequential_generation_never_collides_within_same_period(): void
    {
        $numbers = [];

        for ($i = 0; $i < 50; $i++) {
            $numbers[] = Payment::generatePaymentNumber('2026-06-13');
        }

        $this->assertCount(50, array_unique($numbers));
        $this->assertSame('PAY-202606-0050', end($numbers));
    }

    public function test_width_expands_automatically_past_9999(): void
    {
        // Simulasikan periode yang sudah mencapai batas lama tanpa perlu
        // benar-benar generate 9999 baris satu-satu.
        PaymentNumberSequence::create([
            'period_code' => '202607',
            'current_number' => 9999,
        ]);

        $tenThousandth = Payment::generatePaymentNumber('2026-07-05');

        // Bukan lagi 4 digit (yang akan jebol jadi "10000" tak muat/salah
        // format) — generator wajib melebarkan sendiri jadi 5 digit.
        $this->assertSame('PAY-202607-10000', $tenThousandth);

        $next = Payment::generatePaymentNumber('2026-07-05');
        $this->assertSame('PAY-202607-10001', $next);
    }

    public function test_generator_syncs_with_existing_max_payment_number_for_legacy_safety(): void
    {
        $this->seed(InternetPackageSeeder::class);
        $package = InternetPackage::query()->firstOrFail();

        $pop = Pop::create([
            'code' => 'POP-SEQ',
            'pop_code' => 'SEQ',
            'registration_prefix' => 'CS',
            'cid_prefix' => 'DS',
            'name' => 'POP Sequence Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-SEQ-001',
            'full_name' => 'Pelanggan Sequence Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-08-01',
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

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 100000,
            'activation_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-SEQ-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-08',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => 'belum_dibayar',
        ]);

        // Data lama/import bisa punya payment_number di luar sequence ini
        // (mis. hasil import langsung ke tabel payments tanpa lewat
        // generator). Sequence wajib sinkron ke MAX existing, bukan mulai
        // dari 0 lagi dan bertabrakan. withoutEvents() supaya PaymentObserver
        // tidak ikut campur — yang diuji di sini murni logika sinkronisasi
        // generator, bukan guard dobel-submit.
        Payment::withoutEvents(function () use ($invoice) {
            Payment::create([
                'payment_number' => 'PAY-202608-0025',
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'pop_id' => $invoice->pop_id,
                'payment_date' => '2026-08-01',
                'payment_method' => 'cash',
                'amount' => 100000,
                'payment_status' => 'valid',
            ]);
        });

        $next = Payment::generatePaymentNumber('2026-08-10');

        $this->assertSame('PAY-202608-0026', $next);
    }
}
