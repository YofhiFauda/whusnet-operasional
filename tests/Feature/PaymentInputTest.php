<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentInputTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_finance_can_record_partial_payment_and_invoice_becomes_sebagian(): void
    {
        $role = Role::where('name', 'Admin')->firstOrFail();
        $finance = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $pop = $this->createPop('POP-PAY-1', 'PAY1', 'POP Payment 1');
        $finance->pops()->attach($pop->id);
        $scope = UserRoleScope::create([
            'user_id' => $finance->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop->id,
        ]);
        $invoice = $this->createInvoice($pop, 'INV-202606-8001');

        $response = $this->actingAs($finance)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'note' => 'Pembayaran sebagian.',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'payment_number' => 'PAY-202606-0001',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $pop->id,
            'payment_method' => 'cash',
            'amount' => 50000,
            'payment_status' => 'valid',
            'received_by' => $finance->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
            'invoice_status' => 'sebagian',
        ]);
    }

    public function test_finance_can_record_full_payment_with_proof_and_invoice_becomes_lunas(): void
    {
        Storage::fake('public');

        $role = Role::where('name', 'Admin')->firstOrFail();
        $finance = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $pop = $this->createPop('POP-PAY-2', 'PAY2', 'POP Payment 2');
        $finance->pops()->attach($pop->id);
        $scope = UserRoleScope::create([
            'user_id' => $finance->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop->id,
        ]);
        $invoice = $this->createInvoice($pop, 'INV-202606-8002');

        $response = $this->actingAs($finance)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'amount' => 150000,
            'proof_file' => UploadedFile::fake()->image('bukti.jpg'),
            'note' => 'Pembayaran penuh.',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::firstOrFail();
        Storage::disk('public')->assertExists($payment->proof_file);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);
    }

    public function test_payment_appears_on_customer_detail(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $pop = $this->createPop('POP-PAY-3', 'PAY3', 'POP Payment 3');
        $invoice = $this->createInvoice($pop, 'INV-202606-8003');

        Payment::create([
            'payment_number' => 'PAY-202606-9001',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'qris',
            'amount' => 150000,
            'received_by' => $owner->id,
            'payment_status' => 'valid',
        ]);

        $response = $this->actingAs($owner)->get(route('customers.show', $invoice->customer_id));

        $response->assertOk();
        $response->assertSee('PAY-202606-9001');
        $response->assertSee('INV-202606-8003');
        $response->assertSee('QRIS');
    }

    public function test_user_without_payment_permission_cannot_record_payment(): void
    {
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $pop = $this->createPop('POP-PAY-4', 'PAY4', 'POP Payment 4');
        $invoice = $this->createInvoice($pop, 'INV-202606-8004');

        $response = $this->actingAs($teknisi)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
        ]);

        $response->assertForbidden();
        $this->assertEquals(0, Payment::count());
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
            'full_name' => 'Customer Payment Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Payment Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Payment Test',
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
