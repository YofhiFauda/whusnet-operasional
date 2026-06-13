<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_payment_create_update_and_cancel_are_recorded_in_audit_log(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $pop = $this->createPop('POP-PAY-AUDIT', 'PPA', 'POP Payment Audit');
        $invoice = $this->createInvoice($pop, 'Audit Payment Customer', 'INV-202606-8801');

        $this->actingAs($owner);

        $payment = Payment::create([
            'payment_number' => 'PAY-202606-8801',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 75000,
            'received_by' => $owner->id,
            'payment_status' => 'pending',
            'note' => 'Pembayaran awal.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'module' => 'Pembayaran',
            'action' => 'create',
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
        ]);

        $createLog = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', 'create')
            ->firstOrFail();

        $this->assertNull($createLog->old_values);
        $this->assertSame('PAY-202606-8801', $createLog->new_values['payment_number']);
        $this->assertSame('pending', $createLog->new_values['payment_status']);
        $this->assertNotNull($createLog->created_at);

        $payment->update([
            'payment_method' => 'transfer',
            'note' => 'Pembayaran dipindah ke transfer.',
        ]);

        $updateLog = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', 'update')
            ->firstOrFail();

        $this->assertSame('cash', $updateLog->old_values['payment_method']);
        $this->assertSame('transfer', $updateLog->new_values['payment_method']);
        $this->assertSame('Pembayaran awal.', $updateLog->old_values['note']);
        $this->assertSame('Pembayaran dipindah ke transfer.', $updateLog->new_values['note']);

        $payment->update([
            'payment_status' => 'ditolak',
        ]);

        $cancelLog = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', 'cancel')
            ->firstOrFail();

        $this->assertSame('pending', $cancelLog->old_values['payment_status']);
        $this->assertSame('ditolak', $cancelLog->new_values['payment_status']);
    }

    public function test_owner_and_admin_pusat_can_see_payment_audit_log_on_payment_detail(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $adminPusatRole = Role::where('name', 'Admin Pusat')->firstOrFail();
        $adminPusat = User::factory()->create([
            'role_id' => $adminPusatRole->id,
            'status' => 'active',
        ]);

        $pop = $this->createPop('POP-PAY-AUDIT-VIEW', 'PPV', 'POP Payment Audit View');
        $invoice = $this->createInvoice($pop, 'Audit View Payment Customer', 'INV-202606-8802');

        $this->actingAs($owner);

        $payment = Payment::create([
            'payment_number' => 'PAY-202606-8802',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'qris',
            'amount' => 150000,
            'received_by' => $owner->id,
            'payment_status' => 'valid',
            'note' => 'Pembayaran untuk audit view.',
        ]);

        $ownerResponse = $this->actingAs($owner)->get(route('payments.show', $payment->id));
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('Riwayat Audit Pembayaran');
        $ownerResponse->assertSee('Create');
        $ownerResponse->assertSee($owner->name);

        $adminPusatResponse = $this->actingAs($adminPusat)->get(route('payments.show', $payment->id));
        $adminPusatResponse->assertOk();
        $adminPusatResponse->assertSee('Riwayat Audit Pembayaran');
        $adminPusatResponse->assertSee('PAY-202606-8802');
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

    protected function createInvoice(Pop $pop, string $customerName, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => $customerName,
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Payment Audit Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Payment Audit Test',
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
