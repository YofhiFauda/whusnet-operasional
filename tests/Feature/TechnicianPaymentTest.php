<?php

namespace Tests\Feature;

use App\Enums\CollectorRole;
use App\Enums\ScopeType;
use App\Models\CollectorVisit;
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
use App\Services\CollectorBalanceService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Teknisi mencatat pembayaran pelanggan di lapangan — ADHOC-122,
 * docs/plan/kolektor/rancangan-pembayaran-teknisi.md.
 *
 * Yang dikunci: pelanggan TIDAK perlu di-assign ke teknisi (beda dari
 * kolektor), tapi tetap dibatasi POP scope; sumber tercatat `teknisi`;
 * buku kunjungan kolektor tidak tersentuh; saldo teknisi bisa disetor
 * lewat jalur setoran yang sama; "belum setor" hanya peringatan.
 */
class TechnicianPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->createPop('TPY1');
        $this->teknisi = $this->createUserWithRole('teknisi', $this->pop);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createUserWithRole(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    /**
     * Pelanggan TANPA collector_id — sengaja tidak di-assign ke siapa pun.
     */
    private function createUnpaidInvoice(Pop $pop, string $code, float $total = 150000): Invoice
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => null,
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
            'pop_id' => $pop->id,
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

    private function payload(Invoice $invoice, string $key, float $amount = 150000, string $collectedDate = '2026-06-20'): array
    {
        return [
            'idempotency_key' => $key,
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => $amount, 'payment_method' => 'cash', 'collected_date' => $collectedDate],
            ],
        ];
    }

    #[Test]
    public function teknisi_records_payment_for_customer_not_assigned_to_anyone_and_is_tagged_teknisi(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-OK');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-001'))
            ->assertOk()
            ->assertJson(['success' => true, 'processed' => 1]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame($this->teknisi->id, (int) $payment->collected_by);
        $this->assertSame($this->teknisi->id, (int) $payment->received_by);
        $this->assertSame(CollectorRole::TEKNISI->value, $payment->collected_by_role);
        $this->assertStringStartsWith('Batch teknisi:', (string) $payment->note);

        $this->assertSame('lunas', $invoice->fresh()->invoice_status->value);
    }

    #[Test]
    public function teknisi_payment_does_not_write_collector_visit_log(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-VISIT');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-visit'))
            ->assertOk();

        $this->assertSame(0, CollectorVisit::query()->count());
    }

    #[Test]
    public function teknisi_cannot_record_payment_for_customer_outside_pop_scope(): void
    {
        $popLain = $this->createPop('TPY2');
        $invoice = $this->createUnpaidInvoice($popLain, 'TPY-LUAR');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-luar'))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('belum_dibayar', $invoice->fresh()->invoice_status->value);
    }

    #[Test]
    public function kolektor_cannot_use_technician_payment_endpoint(): void
    {
        $kolektor = $this->createUserWithRole('kolektor', $this->pop);
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-KOL');

        $this->actingAs($kolektor)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-kol'))
            ->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function search_returns_only_customers_with_outstanding_invoices_in_pop_scope(): void
    {
        $this->createUnpaidInvoice($this->pop, 'TPY-CARI-A');
        $this->createUnpaidInvoice($this->createPop('TPY3'), 'TPY-CARI-B');

        $response = $this->actingAs($this->teknisi)
            ->getJson(route('technician-payments.search', ['q' => 'TPY-CARI']))
            ->assertOk();

        $this->assertSame(['Pelanggan TPY-CARI-A'], collect($response->json('customers'))->pluck('full_name')->all());
    }

    #[Test]
    public function teknisi_balance_is_settled_through_collector_deposit(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-SETOR');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-setor'))
            ->assertOk();

        $balance = app(CollectorBalanceService::class);
        $this->assertEquals(150000, $balance->balance($this->teknisi));

        $this->actingAs($this->teknisi)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-dep-001'])
            ->assertRedirect();

        $this->assertEquals(0, $balance->balance($this->teknisi));
        $this->assertDatabaseHas('collector_deposits', [
            'collector_id' => $this->teknisi->id,
            'status' => 'menunggu_verifikasi',
        ]);
    }

    #[Test]
    public function unsettled_money_from_previous_day_is_flagged_overdue_as_warning_only(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-TELAT');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-telat', 150000, '2026-10-04'))
            ->assertOk();

        $status = app(CollectorBalanceService::class)->technicianSettlementStatus();

        $this->assertCount(1, $status);
        $this->assertSame($this->teknisi->id, $status->first()['user']->id);
        $this->assertTrue($status->first()['overdue']);
        // Peringatan saja: pembayaran tetap tercatat dan tidak diblokir.
        $this->assertSame(1, Payment::query()->count());
    }

    #[Test]
    public function money_collected_today_is_not_overdue_before_close_time(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-HARI-INI');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-hari', 150000, '2026-10-05'))
            ->assertOk();

        $status = app(CollectorBalanceService::class)->technicianSettlementStatus();

        $this->assertCount(1, $status);
        $this->assertFalse($status->first()['overdue']);
    }

    #[Test]
    public function technician_payment_page_renders_for_teknisi_and_is_forbidden_for_kolektor(): void
    {
        $this->actingAs($this->teknisi)
            ->get(route('technician-payments.index'))
            ->assertOk()
            ->assertSee(route('technician-payments.store'), false);

        $kolektor = $this->createUserWithRole('kolektor', $this->pop);

        $this->actingAs($kolektor)
            ->get(route('technician-payments.index'))
            ->assertForbidden();
    }

    #[Test]
    public function technician_deposit_returns_to_technician_page_not_worklist(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-BALIK');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-balik'))
            ->assertOk();

        $this->actingAs($this->teknisi)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-dep-balik'])
            ->assertRedirect(route('technician-payments.index'));
    }

    #[Test]
    public function report_source_filter_separates_teknisi_from_kolektor(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-RPT');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-rpt'))
            ->assertOk();

        $admin = $this->loginAsAdmin();

        $teknisiOnly = $this->actingAs($admin)
            ->get(route('reports.collector-payments.index', ['source' => 'teknisi', 'start_date' => '2026-06-01', 'end_date' => now()->toDateString()]))
            ->assertOk();
        $this->assertSame(1, $teknisiOnly->viewData('count'));

        $kolektorOnly = $this->actingAs($admin)
            ->get(route('reports.collector-payments.index', ['source' => 'kolektor', 'start_date' => '2026-06-01', 'end_date' => now()->toDateString()]))
            ->assertOk();
        $this->assertSame(0, $kolektorOnly->viewData('count'));
    }

    #[Test]
    public function notify_command_warns_only_overdue_technicians(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-10-05 10:00:00');

        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-NOTIF');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-pay-notif', 150000, '2026-10-04'))
            ->assertOk();

        $this->artisan('technicians:notify-unsettled')->assertSuccessful();

        Notification::assertSentTo($this->teknisi, AppNotification::class);
        // Peringatan saja: saldo tidak berubah.
        $this->assertEquals(150000, app(CollectorBalanceService::class)->balance($this->teknisi));
    }
}
