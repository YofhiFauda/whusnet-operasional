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
use Tests\TestCase;

/**
 * PaymentController::reject() — jalur resmi void/reject payment yang salah
 * input, dengan invoice ikut terkoreksi otomatis di transaksi yang sama.
 * Sebelum ini, `Payment.php` sudah mengantisipasi `payment_status → DITOLAK`
 * (audit action 'cancel') tapi tak ada yang mengembalikan
 * invoice.paid_amount/remaining_amount/invoice_status — jebakan laten
 * (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-6, §A-7 #7).
 */
class PaymentRejectRecalculatesInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    private function createAdminWithRejectPermission(Pop $pop): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($pop->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop->id,
        ]);

        return $user;
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createInvoiceWithPayment(Pop $pop, float $paymentAmount, float $totalAmount = 150000): array
    {
        $customer = Customer::create([
            'customer_code' => 'C-REJ-'.random_int(1000, 9999),
            'full_name' => 'Pelanggan Reject Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Reject Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Reject Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $totalAmount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-REJ-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paymentAmount,
            'remaining_amount' => $totalAmount - $paymentAmount,
            'invoice_status' => $paymentAmount >= $totalAmount ? 'lunas' : 'sebagian',
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-REJ-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => $paymentAmount,
            'received_by' => $this->createAdminWithRejectPermission($pop)->id,
            'payment_status' => 'valid',
        ]);

        return [$invoice, $payment];
    }

    public function test_rejecting_full_payment_reverts_invoice_to_belum_dibayar(): void
    {
        $pop = $this->createPop('REJ1');
        [$invoice, $payment] = $this->createInvoiceWithPayment($pop, 150000);
        $admin = $this->createAdminWithRejectPermission($pop);

        $response = $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Salah input, dobel dengan pembayaran lain.',
        ]);

        $response->assertRedirect(route('payments.show', $payment->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'ditolak',
            'reject_reason' => 'Salah input, dobel dengan pembayaran lain.',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    public function test_rejecting_one_of_two_payments_leaves_invoice_sebagian_with_correct_remainder(): void
    {
        $pop = $this->createPop('REJ2');
        [$invoice, $payment1] = $this->createInvoiceWithPayment($pop, 50000);

        $payment2 = Payment::create([
            'payment_number' => 'PAY-REJ-9001',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-14',
            'payment_method' => 'transfer',
            'amount' => 50000,
            'received_by' => $this->createAdminWithRejectPermission($pop)->id,
            'payment_status' => 'valid',
        ]);

        // Invoice belum sinkron dgn kedua payment (dibuat manual di atas) —
        // samakan dulu biar starting state realistis.
        $invoice->recalculateFromPayments();

        $admin = $this->createAdminWithRejectPermission($pop);

        $response = $this->actingAs($admin)->post(route('payments.reject', $payment1->id), [
            'reject_reason' => 'Nominal salah catat.',
        ]);

        $response->assertRedirect(route('payments.show', $payment1->id));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
            'invoice_status' => 'sebagian',
        ]);

        // Payment kedua yang tidak direject tetap valid, tak ikut berubah.
        $this->assertDatabaseHas('payments', [
            'id' => $payment2->id,
            'payment_status' => 'valid',
        ]);
    }

    public function test_reject_writes_cancel_audit_log(): void
    {
        $pop = $this->createPop('REJ3');
        [, $payment] = $this->createInvoiceWithPayment($pop, 150000);
        $admin = $this->createAdminWithRejectPermission($pop);

        $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Dibatalkan untuk audit log test.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'action' => 'cancel',
        ]);
    }

    public function test_reject_requires_a_reason(): void
    {
        $pop = $this->createPop('REJ4');
        [, $payment] = $this->createInvoiceWithPayment($pop, 150000);
        $admin = $this->createAdminWithRejectPermission($pop);

        $response = $this->actingAs($admin)->post(route('payments.reject', $payment->id), []);

        $response->assertSessionHasErrors('reject_reason');
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'valid',
        ]);
    }

    public function test_cannot_reject_an_already_rejected_payment(): void
    {
        $pop = $this->createPop('REJ5');
        [, $payment] = $this->createInvoiceWithPayment($pop, 150000);
        $admin = $this->createAdminWithRejectPermission($pop);

        $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Pertama kali ditolak.',
        ]);

        $response = $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Coba ditolak lagi.',
        ]);

        $response->assertSessionHasErrors('reject_reason');
    }

    public function test_user_without_reject_permission_cannot_reject_payment(): void
    {
        $pop = $this->createPop('REJ6');
        [, $payment] = $this->createInvoiceWithPayment($pop, 150000);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $response = $this->actingAs($teknisi)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Tidak berwenang.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'valid',
        ]);
    }

    public function test_admin_cannot_reject_payment_outside_pop_scope(): void
    {
        $popA = $this->createPop('REJ7A');
        $popB = $this->createPop('REJ7B');

        [, $paymentB] = $this->createInvoiceWithPayment($popB, 150000);
        $adminA = $this->createAdminWithRejectPermission($popA);

        $response = $this->actingAs($adminA)->post(route('payments.reject', $paymentB->id), [
            'reject_reason' => 'Coba tembus scope POP lain.',
        ]);

        $response->assertForbidden();
    }
}
