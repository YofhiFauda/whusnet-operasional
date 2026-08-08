<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\User;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * PaymentObserver::rejectBurstDuplicate() — pengganti unique index
 * `payments_invoice_date_amount_unique` yang di-drop karena memblokir
 * cicilan sah (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md
 * §A-7 #6 & #8). Index lama itu SATU-SATUNYA guard dobel-submit di jalur
 * single-payment — guard ini menggantikannya.
 */
class PaymentBurstDuplicateSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InternetPackageSeeder::class);
    }

    private function createInvoice(float $totalAmount = 150000): Invoice
    {
        $package = InternetPackage::query()->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-BURST',
            'pop_code' => 'BURST',
            'registration_prefix' => 'CB',
            'cid_prefix' => 'DB',
            'name' => 'POP Burst Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-BURST-001',
            'full_name' => 'Pelanggan Burst Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Burst Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Burst Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $totalAmount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-BURST-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    private function receiverId(): int
    {
        return User::factory()->create()->id;
    }

    public function test_identical_payment_submitted_twice_within_burst_window_is_rejected(): void
    {
        $invoice = $this->createInvoice();

        Payment::create([
            'payment_number' => 'PAY-BURST-0001',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'received_by' => $this->receiverId(),
            'payment_status' => 'valid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate payment detected: same invoice, amount, and date was just recorded.');

        Payment::create([
            'payment_number' => 'PAY-BURST-0002',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'received_by' => $this->receiverId(),
            'payment_status' => 'valid',
        ]);
    }

    public function test_legitimate_installment_same_day_same_amount_outside_burst_window_is_allowed(): void
    {
        $invoice = $this->createInvoice();

        $morning = Payment::create([
            'payment_number' => 'PAY-BURST-0003',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'received_by' => $this->receiverId(),
            'payment_status' => 'valid',
        ]);

        // Simulasi jarak waktu nyata (di luar jendela burst 300 detik) —
        // pagi vs sore hari yang sama, nominal sama. Ini cicilan sah, bukan
        // dobel-submit, dan tidak boleh ditolak.
        $morning->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        $afternoon = Payment::create([
            'payment_number' => 'PAY-BURST-0004',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'received_by' => $this->receiverId(),
            'payment_status' => 'valid',
        ]);

        $this->assertDatabaseHas('payments', ['id' => $morning->id]);
        $this->assertDatabaseHas('payments', ['id' => $afternoon->id]);
        $this->assertEquals(2, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_different_amount_same_day_is_allowed(): void
    {
        $invoice = $this->createInvoice();

        Payment::create([
            'payment_number' => 'PAY-BURST-0005',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'received_by' => $this->receiverId(),
            'payment_status' => 'valid',
        ]);

        $second = Payment::create([
            'payment_number' => 'PAY-BURST-0006',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 75000,
            'received_by' => $this->receiverId(),
            'payment_status' => 'valid',
        ]);

        $this->assertDatabaseHas('payments', ['id' => $second->id]);
    }
}
