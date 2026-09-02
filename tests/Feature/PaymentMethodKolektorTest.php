<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\CollectorBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metode Bayar "Kolektor" pada Modal Bayar Invoice: `payment_method=kolektor`
 * + `collected_by` terisi dari dropdown. Saldo kolektor tetap DERIVED lewat
 * CollectorBalanceService — tidak ada kolom saldo baru yang perlu disentuh.
 */
class PaymentMethodKolektorTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_payment_with_kolektor_method_fills_collected_by_and_raises_collector_balance(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-KOL-1', 'KOL1', 'POP Kolektor Test');

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $invoice = $this->createInvoice($pop, 'INV-KOL-0001');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
            'amount' => 150000,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($kolektor->id, $payment->collected_by);
        $this->assertSame('kolektor', $payment->payment_method);

        $balance = app(CollectorBalanceService::class)->balance($kolektor);
        $this->assertSame(150000.0, $balance);
    }

    public function test_collected_by_must_belong_to_a_kolektor_role_user(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-KOL-2', 'KOL2', 'POP Kolektor Test 2');

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $bukanKolektor = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $invoice = $this->createInvoice($pop, 'INV-KOL-0002');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'kolektor',
            'collected_by' => $bukanKolektor->id,
            'amount' => 150000,
        ]);

        $response->assertSessionHasErrors('collected_by');
        $this->assertSame(0, Payment::count());
    }

    public function test_kolektor_method_requires_collected_by(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-KOL-3', 'KOL3', 'POP Kolektor Test 3');
        $invoice = $this->createInvoice($pop, 'INV-KOL-0003');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'kolektor',
            'amount' => 150000,
        ]);

        $response->assertSessionHasErrors('collected_by');
        $this->assertSame(0, Payment::count());
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

    protected function createInvoice(Pop $pop, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => 'Customer Kolektor Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Kolektor Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Kolektor Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

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
    }
}
