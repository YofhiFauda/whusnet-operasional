<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentEditUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $owner;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->owner = User::where('email', 'owner@whusnet.net')->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-PAY-EDIT',
            'pop_code' => 'PPE',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Payment Edit',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->bankAccount = BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'PT Whusnet Network',
            'label' => 'BCA Pusat',
            'is_active' => true,
        ]);
    }

    public function test_payment_index_renders_bank_label_for_transfer_method(): void
    {
        $invoice = $this->createInvoice('Test Transfer Customer', 'INV-202609-8001');
        $payment = Payment::create([
            'payment_number' => 'PAY-202609-8001',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-09-25',
            'payment_method' => 'transfer',
            'bank_account_id' => $this->bankAccount->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'sender_name' => 'Budi Pengirim',
            'amount' => 150000,
            'received_by' => $this->owner->id,
            'payment_status' => PaymentStatus::VALID->value,
            'note' => 'Pembayaran via transfer BCA',
        ]);

        $response = $this->actingAs($this->owner)->get(route('payments.index'));

        $response->assertOk();
        $response->assertSee('BCA Pusat');
    }

    public function test_payment_index_renders_badge_lunas_dari_saldo(): void
    {
        $invoice = $this->createInvoice('Test Saldo Customer', 'INV-202609-8002');
        $payment = Payment::create([
            'payment_number' => 'PAY-202609-8002',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-09-25',
            'payment_method' => 'saldo',
            'amount' => 150000,
            'balance_used_amount' => 150000,
            'received_by' => $this->owner->id,
            'payment_status' => PaymentStatus::VALID->value,
            'note' => 'Auto lunas dari saldo',
        ]);

        $response = $this->actingAs($this->owner)->get(route('payments.index'));

        $response->assertOk();
        $response->assertSee('Lunas dari Saldo');
    }

    public function test_authorized_user_can_update_payment(): void
    {
        $invoice = $this->createInvoice('Test Edit Customer', 'INV-202609-8003');
        $payment = Payment::create([
            'payment_number' => 'PAY-202609-8003',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-09-24',
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => $this->owner->id,
            'payment_status' => PaymentStatus::VALID->value,
            'note' => 'Catatan lama',
        ]);

        $response = $this->actingAs($this->owner)->put(route('payments.update', $payment->id), [
            'payment_date' => '2026-09-25',
            'payment_method' => 'transfer',
            'bank_account_id' => $this->bankAccount->id,
            'sender_name' => 'Budi Santoso',
            'note' => 'Koreksi: Pembayaran ternyata via transfer BCA',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals('2026-09-25', $payment->payment_date->format('Y-m-d'));
        $this->assertEquals('transfer', $payment->payment_method);
        $this->assertEquals('BCA', $payment->bank_name);
        $this->assertEquals('1234567890', $payment->account_number);
        $this->assertEquals('Budi Santoso', $payment->sender_name);
        $this->assertEquals('Koreksi: Pembayaran ternyata via transfer BCA', $payment->note);
    }

    protected function createInvoice(string $customerName, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => $customerName,
            'primary_phone' => '081234567899',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Payment Edit Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Payment Edit Test',
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
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-09',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);
    }
}
