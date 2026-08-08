<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
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
use App\Notifications\AppNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PaymentController::reject() — sebelumnya penolakan pembayaran gak ngasih
 * tau siapa pun yang mencatatnya (docs/plan/analisa-status-implementasi-
 * notifikasi.md §5). Kembaran PaymentRejectRecalculatesInvoiceTest, fokus
 * ke sisi notifikasi.
 */
class PaymentRejectNotificationTest extends TestCase
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

    private function createInvoiceWithPayment(Pop $pop, User $recorder, float $amount = 150000): array
    {
        $customer = Customer::create([
            'customer_code' => 'C-REJN-'.random_int(1000, 9999),
            'full_name' => 'Pelanggan Reject Notif Test',
            'primary_phone' => '081234567891',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Reject Notif Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Reject Notif Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $amount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-REJN-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $amount,
            'paid_amount' => $amount,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-REJN-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => $amount,
            'received_by' => $recorder->id,
            'payment_status' => 'valid',
        ]);

        return [$invoice, $payment];
    }

    public function test_reject_notifies_recorder_with_error_type(): void
    {
        $pop = $this->createPop('REJN1');
        $recorder = $this->createAdminWithRejectPermission($pop);
        [, $payment] = $this->createInvoiceWithPayment($pop, $recorder);
        $admin = $this->createAdminWithRejectPermission($pop);

        Notification::fake();

        $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Salah input, dobel dengan pembayaran lain.',
        ])->assertRedirect();

        Notification::assertSentTo(
            $recorder,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::ERROR
                && str_contains($notification->title, $payment->payment_number)
        );
    }

    public function test_reject_by_recorder_themselves_does_not_self_notify(): void
    {
        $pop = $this->createPop('REJN2');
        $recorder = $this->createAdminWithRejectPermission($pop);
        [, $payment] = $this->createInvoiceWithPayment($pop, $recorder);

        Notification::fake();

        $this->actingAs($recorder)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Ternyata salah catat sendiri.',
        ])->assertRedirect();

        Notification::assertNothingSentTo($recorder);
    }
}
