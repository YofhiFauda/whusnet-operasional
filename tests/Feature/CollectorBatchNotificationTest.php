<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
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
 * CollectorBatchController::store() — sebelumnya sukses setoran gak notif
 * siapa pun (docs/plan/analisa-status-implementasi-notifikasi.md §8.3,
 * gap "Finance Pusat" — sistem ini gak punya role itu, penerima yang dipilih
 * adalah pop_admin, satu-satunya role yang pegang payments.validate/reject).
 */
class CollectorBatchNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $admin;

    protected User $popAdmin2;

    protected User $kolektor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-CBN1',
            'pop_code' => 'CBN1',
            'registration_prefix' => 'CN',
            'cid_prefix' => 'DN',
            'name' => 'POP Collector Notif Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $adminRole = Role::where('name', 'POP Admin')->firstOrFail();

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);
        $this->admin->pops()->attach($this->pop->id);
        $scope = UserRoleScope::create([
            'user_id' => $this->admin->id,
            'role_id' => $adminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        // pop_admin KEDUA di POP yang sama — buat mastiin notif nyampe ke
        // SEMUA pop_admin di POP itu, gak cuma yang submit batch.
        $this->popAdmin2 = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);
        $scope2 = UserRoleScope::create([
            'user_id' => $this->popAdmin2->id,
            'role_id' => $adminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope2->id, 'pop_id' => $this->pop->id]);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $this->kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);
    }

    private function createUnpaidInvoice(string $code, ?int $collectorId, float $total = 150000): Invoice
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collectorId,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $total,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    public function test_successful_batch_notifies_all_pop_admins_in_pop(): void
    {
        $invoice1 = $this->createUnpaidInvoice('C-CBN-A1', $this->kolektor->id, 100000);
        $invoice2 = $this->createUnpaidInvoice('C-CBN-A2', $this->kolektor->id, 200000);

        Notification::fake();

        $response = $this->actingAs($this->admin)->postJson(route('payment-batches.store', $this->kolektor->id), [
            'idempotency_key' => 'notif-batch-001',
            'rows' => [
                ['invoice_id' => $invoice1->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
                ['invoice_id' => $invoice2->id, 'amount' => 200000, 'payment_method' => 'transfer', 'collected_date' => '2026-06-13'],
            ],
        ]);

        $response->assertOk();

        foreach ([$this->admin, $this->popAdmin2] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                AppNotification::class,
                fn ($notification) => $notification->type === NotificationType::INFO
                    && str_contains($notification->title, $this->kolektor->name)
                    && str_contains($notification->message, '2 pembayaran')
                    && str_contains($notification->message, 'Rp300.000')
            );
        }
    }

    public function test_rejected_batch_does_not_notify_anyone(): void
    {
        $goodInvoice = $this->createUnpaidInvoice('C-CBN-B1', $this->kolektor->id, 100000);

        Notification::fake();

        $this->actingAs($this->admin)->postJson(route('payment-batches.store', $this->kolektor->id), [
            'idempotency_key' => 'notif-batch-002',
            'rows' => [
                ['invoice_id' => $goodInvoice->id, 'amount' => 999999, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ])->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_idempotent_resubmit_does_not_renotify(): void
    {
        $invoice = $this->createUnpaidInvoice('C-CBN-C1', $this->kolektor->id, 100000);
        $payload = [
            'idempotency_key' => 'notif-batch-003',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ];

        $this->actingAs($this->admin)->postJson(route('payment-batches.store', $this->kolektor->id), $payload)->assertOk();

        Notification::fake();

        $this->actingAs($this->admin)->postJson(route('payment-batches.store', $this->kolektor->id), $payload)
            ->assertOk()
            ->assertJson(['already_processed' => true]);

        Notification::assertNothingSent();
    }
}
