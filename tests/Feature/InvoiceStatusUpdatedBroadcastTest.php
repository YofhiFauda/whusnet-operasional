<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Events\InvoiceStatusUpdated;
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
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * App\Events\InvoiceStatusUpdated — broadcast tunggal dari
 * Invoice::recalculateFromPayments(), dipicu dari SEMUA jalur pembayaran
 * (single, bulk, batch kolektor, reject) — nutup gap "kasir/loket masih
 * full page reload" (docs/plan/analisa-realtime-spa-operasional.md
 * §2.1 no. 2 & 11).
 */
class InvoiceStatusUpdatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
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

    private function createAdmin(Pop $pop): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($pop->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        // RefreshDatabase reset ID auto-increment tiap test, tapi cache
        // permission/scope EffectiveAccessService tidak ikut ke-flush —
        // user baru bisa kebentur cache stale dari user ber-ID sama di test
        // sebelumnya kalau tak dibersihkan (lihat CLAUDE.md § POP Scope).
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    private function createInvoice(Pop $pop, float $totalAmount = 150000): Invoice
    {
        $customer = Customer::create([
            'customer_code' => 'C-ISU-'.random_int(1000, 9999),
            'full_name' => 'Pelanggan Invoice Status Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Invoice Status Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Invoice Status Test',
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

        return Invoice::create([
            'invoice_number' => 'INV-ISU-'.random_int(1000, 9999),
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
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    public function test_payment_store_dispatches_invoice_status_updated_on_pop_channel(): void
    {
        $pop = $this->createPop('ISU1');
        $invoice = $this->createInvoice($pop, 150000);
        $admin = $this->createAdmin($pop);

        Event::fake([InvoiceStatusUpdated::class]);

        $this->actingAs($admin)->postJson(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
        ])->assertOk();

        Event::assertDispatched(
            InvoiceStatusUpdated::class,
            fn ($event) => $event->invoice->id === $invoice->id
                && $event->invoice->pop_id === $pop->id
                && $event->invoice->invoice_status->value === 'lunas'
        );
    }

    public function test_payment_reject_dispatches_invoice_status_updated(): void
    {
        $pop = $this->createPop('ISU2');
        $invoice = $this->createInvoice($pop, 150000);
        $admin = $this->createAdmin($pop);

        $payment = Payment::create([
            'payment_number' => 'PAY-ISU-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => $admin->id,
            'payment_status' => 'valid',
        ]);
        $invoice->recalculateFromPayments();

        Event::fake([InvoiceStatusUpdated::class]);

        $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Salah input.',
        ])->assertRedirect();

        Event::assertDispatched(
            InvoiceStatusUpdated::class,
            fn ($event) => $event->invoice->id === $invoice->id
                && $event->invoice->invoice_status->value === 'belum_dibayar'
        );
    }

    public function test_broadcast_payload_carries_status_paid_and_remaining_amount(): void
    {
        $pop = $this->createPop('ISU3');
        $invoice = $this->createInvoice($pop, 200000);
        $invoice->refresh();

        $event = new InvoiceStatusUpdated($invoice->fresh());

        // Simulasikan pembayaran sebagian lalu cek payload broadcastWith().
        Payment::create([
            'payment_number' => 'PAY-ISU-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 75000,
            'received_by' => $this->createAdmin($pop)->id,
            'payment_status' => 'valid',
        ]);
        $invoice->recalculateFromPayments();

        $payload = (new InvoiceStatusUpdated($invoice->fresh()))->broadcastWith();

        $this->assertSame($invoice->id, $payload['invoice_id']);
        $this->assertSame('sebagian', $payload['invoice_status']);
        $this->assertSame('Sebagian', $payload['invoice_status_label']);
        $this->assertSame(75000.0, $payload['paid_amount']);
        $this->assertSame(125000.0, $payload['remaining_amount']);
    }

    public function test_collector_batch_store_returns_per_invoice_results_and_dispatches_event(): void
    {
        $pop = $this->createPop('ISU4');
        $invoice = $this->createInvoice($pop, 150000);
        $invoice->customer()->update(['collector_id' => null]);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);
        $invoice->customer()->update(['collector_id' => $kolektor->id]);

        $admin = $this->createAdmin($pop);

        Event::fake([InvoiceStatusUpdated::class]);

        $response = $this->actingAs($admin)->postJson(route('collector-batch.store', $kolektor->id), [
            'idempotency_key' => 'test-key-'.random_int(1000, 9999),
            'rows' => [[
                'invoice_id' => $invoice->id,
                'amount' => 150000,
                'payment_method' => 'cash',
                'collected_date' => '2026-06-10',
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('results.0.invoice_id', $invoice->id);
        $response->assertJsonPath('results.0.invoice_status', 'lunas');
        $response->assertJsonPath('results.0.remaining_amount', 0);

        Event::assertDispatched(
            InvoiceStatusUpdated::class,
            fn ($event) => $event->invoice->id === $invoice->id
        );
    }

    /**
     * routes/channels.php `invoices.{popId}` gak bisa dites lewat HTTP
     * POST /broadcasting/auth — phpunit.xml sengaja set BROADCAST_CONNECTION
     * ke `null`, dan Illuminate\Broadcasting\Broadcasters\NullBroadcaster::auth()
     * kosong (selalu 200 tanpa pernah manggil closure channel). Jadi otorisasi
     * dites langsung lewat EffectiveAccessService — persis kondisi yang dicek
     * closure-nya (permission `invoices.view` + `hasAllPopAccess`/`getAllowedPopIds`).
     */
    public function test_channel_grants_access_for_user_with_permission_and_matching_pop_scope(): void
    {
        $pop = $this->createPop('ISU5');
        $admin = $this->createAdmin($pop);
        $access = app(EffectiveAccessService::class);

        $this->assertTrue($admin->hasPermission('invoices.view'));
        $this->assertFalse($access->hasAllPopAccess($admin));
        $this->assertContains($pop->id, $access->getAllowedPopIds($admin));
    }

    public function test_channel_denies_access_for_user_outside_pop_scope(): void
    {
        $popA = $this->createPop('ISU6A');
        $popB = $this->createPop('ISU6B');
        $adminA = $this->createAdmin($popA);
        $access = app(EffectiveAccessService::class);

        $this->assertFalse($access->hasAllPopAccess($adminA));
        $this->assertNotContains($popB->id, $access->getAllowedPopIds($adminA));
    }

    public function test_channel_denies_access_for_user_without_invoices_view_permission(): void
    {
        $pop = $this->createPop('ISU7');

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $teknisi->pops()->attach($pop->id);
        app(EffectiveAccessService::class)->clearCache($teknisi);

        $this->assertFalse($teknisi->hasPermission('invoices.view'));
    }
}
