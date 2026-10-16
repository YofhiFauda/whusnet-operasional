<?php

namespace Tests\Feature;

use App\Enums\CollectorRole;
use App\Enums\ScopeType;
use App\Models\CollectorDeposit;
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
 * Teknisi mencatat pembayaran pelanggan di lapangan â€” ADHOC-122,
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
     * Pelanggan TANPA collector_id â€” sengaja tidak di-assign ke siapa pun.
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
    public function technician_worksheet_shows_unassigned_invoice_with_shared_payment_form(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-ADMIN-FORM');
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->get(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id,
                'invoice_search' => 'TPY-ADMIN-FORM',
            ]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('cb-row-checkbox')
            ->assertSee(route('payment-batches.technicians.store', $this->teknisi->id), false);
    }

    #[Test]
    public function admin_cannot_record_for_technician_outside_technician_pop_scope(): void
    {
        $invoice = $this->createUnpaidInvoice($this->createPop('TPY6'), 'TPY-ADMIN-OUTSIDE');
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->postJson(route('payment-batches.technicians.store', $this->teknisi->id), $this->payload($invoice, 'admin-tech-outside'))
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function admin_can_record_payment_for_technician_without_customer_assignment(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-ADMIN-PAY');
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->postJson(route('payment-batches.technicians.store', $this->teknisi->id), $this->payload($invoice, 'admin-tech-pay'))
            ->assertOk()
            ->assertJsonPath('processed', 1);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'collected_by' => $this->teknisi->id,
            'collected_by_role' => 'teknisi',
            'received_by' => $admin->id,
        ]);
    }

    #[Test]
    public function technician_payment_notifies_pop_admin_with_technician_worksheet_link(): void
    {
        Notification::fake();
        $admin = $this->createUserWithRole('pop_admin', $this->pop);
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-NOTIFY-ADMIN');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-notify-admin'))
            ->assertOk();

        Notification::assertSentTo($admin, AppNotification::class, function (AppNotification $notification): bool {
            return str_contains($notification->title, 'Teknisi')
                && $notification->actionUrl === route('collector-worksheet.technicians.show', $this->teknisi->id);
        });
    }

    #[Test]
    public function technician_payment_page_uses_collector_payment_table_for_unassigned_customer_in_pop(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-PARITY');
        $this->createUnpaidInvoice($this->createPop('TPY5'), 'TPY-PARITY-OTHER');

        $this->actingAs($this->teknisi)
            ->get(route('technician-payments.index', ['search' => 'TPY-PARITY']))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('cb-row-checkbox')
            ->assertSee('CB_RELOAD_ON_SUCCESS = true')
            ->assertSee('cb-method')
            ->assertSee('cb-bank')
            ->assertSee('cb-sender')
            ->assertDontSee('INV-TPY-PARITY-OTHER');
    }

    #[Test]
    public function worksheet_groups_collectors_and_technicians_in_one_staff_list_with_tabs(): void
    {
        $collector = $this->createUserWithRole('kolektor', $this->pop);
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk()
            ->assertSee('Daftar Petugas Pembayaran')
            ->assertSee('Kolektor (1)')
            ->assertSee('Teknisi (1)')
            ->assertSee(route('collector-worksheet.show', $collector->id), false)
            ->assertSee(route('collector-worksheet.technicians.show', $this->teknisi->id), false)
            ->assertDontSee('Batas setor:');
    }

    #[Test]
    public function worksheet_lists_scoped_technician_before_first_payment(): void
    {
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk()
            ->assertSee('/collector-worksheet/technicians/'.$this->teknisi->id, false);
    }

    #[Test]
    public function technician_payment_page_renders_for_teknisi_and_is_forbidden_for_kolektor(): void
    {
        $this->actingAs($this->teknisi)
            ->get(route('technician-payments.index'))
            ->assertOk()
            ->assertSee('Cari pelanggan atau tagihan dalam POP Anda');

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
    public function technician_can_review_only_own_unsettled_payments_before_deposit(): void
    {
        $ownInvoice = $this->createUnpaidInvoice($this->pop, 'TPY-OWN-LIST');
        $otherInvoice = $this->createUnpaidInvoice($this->pop, 'TPY-OTHER-LIST');
        $otherTechnician = $this->createUserWithRole('teknisi', $this->pop);

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($ownInvoice, 'tek-own-list'))
            ->assertOk();
        $this->actingAs($otherTechnician)
            ->postJson(route('technician-payments.store'), $this->payload($otherInvoice, 'tek-other-list'))
            ->assertOk();

        $ownPayment = Payment::where('collected_by', $this->teknisi->id)->firstOrFail();
        $otherPayment = Payment::where('collected_by', $otherTechnician->id)->firstOrFail();

        $this->actingAs($this->teknisi)
            ->get(route('technician-payments.index', ['tab' => 'bayar']))
            ->assertOk()
            ->assertSee('Sudah Bayar (1)')
            ->assertSee($ownPayment->payment_number)
            ->assertSee('Pelanggan TPY-OWN-LIST')
            ->assertDontSee($otherPayment->payment_number)
            ->assertDontSee('Pelanggan TPY-OTHER-LIST');
    }

    #[Test]
    public function technician_can_review_own_deposit_amount_and_status(): void
    {
        $ownInvoice = $this->createUnpaidInvoice($this->pop, 'TPY-OWN-DEPOSIT');
        $otherInvoice = $this->createUnpaidInvoice($this->pop, 'TPY-OTHER-DEPOSIT');
        $otherTechnician = $this->createUserWithRole('teknisi', $this->pop);

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($ownInvoice, 'tek-own-deposit'))
            ->assertOk();
        $this->actingAs($this->teknisi)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-own-deposit-submit'])
            ->assertRedirect();

        $this->actingAs($otherTechnician)
            ->postJson(route('technician-payments.store'), $this->payload($otherInvoice, 'tek-other-deposit'))
            ->assertOk();
        $this->actingAs($otherTechnician)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-other-deposit-submit'])
            ->assertRedirect();

        $ownDeposit = CollectorDeposit::where('collector_id', $this->teknisi->id)->firstOrFail();
        $otherDeposit = CollectorDeposit::where('collector_id', $otherTechnician->id)->firstOrFail();
        $ownPayment = Payment::where('collected_by', $this->teknisi->id)->firstOrFail();

        $response = $this->actingAs($this->teknisi)
            ->get(route('technician-payments.index', ['tab' => 'setoran']))
            ->assertOk()
            ->assertSee($ownDeposit->deposit_number)
            ->assertSee('Rp 150.000')
            ->assertSee('Menunggu Verifikasi Admin')
            ->assertSee($ownPayment->payment_number)
            ->assertSee('Pelanggan TPY-OWN-DEPOSIT');

        $this->assertSame([$ownDeposit->id], $response->viewData('deposits')->pluck('id')->all());
        $this->assertNotSame($ownDeposit->id, $otherDeposit->id);
    }

    #[Test]
    public function worksheet_admin_keeps_technician_visible_after_full_deposit(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-WORKSHEET');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-worksheet'))
            ->assertOk();
        $this->actingAs($this->teknisi)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-worksheet-deposit'])
            ->assertRedirect();

        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk()
            ->assertSee('/collector-worksheet/technicians/'.$this->teknisi->id, false);
    }

    #[Test]
    public function technician_worksheet_has_four_distinct_payment_tabs(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-FOUR-TABS');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-four-tabs'))
            ->assertOk();
        $payment = Payment::query()->firstOrFail();
        $admin = $this->loginAsAdmin();
        $url = fn (string $tab) => route('collector-worksheet.technicians.show', [
            'technician' => $this->teknisi->id,
            'tab' => $tab,
        ]);

        $this->actingAs($admin)->get(route('collector-worksheet.technicians.show', $this->teknisi->id))
            ->assertOk()
            ->assertSee('Setor Seluruh Saldo')
            ->assertSee(route('collector-worksheet.technicians.deposit', $this->teknisi->id), false);

        $this->actingAs($admin)->get($url('pembayaran'))
            ->assertOk()
            ->assertSee('Pembayaran')
            ->assertSee('Sudah Dibayar')
            ->assertSee('Setor')
            ->assertSee('Riwayat Pembayaran')
            ->assertDontSee($payment->payment_number);

        $this->actingAs($admin)->get($url('sudah_bayar'))
            ->assertOk()
            ->assertSee($payment->payment_number);

        $this->actingAs($admin)->get($url('riwayat_pembayaran'))
            ->assertOk()
            ->assertSee($payment->payment_number)
            ->assertSee('Belum disetor');
    }

    #[Test]
    public function admin_can_setor_for_technician_from_worksheet(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-ADMIN-SETOR');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-admin-setor'))
            ->assertOk();

        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->post(route('collector-worksheet.technicians.deposit', $this->teknisi->id), [
                'idempotency_key' => 'admin-tek-setor',
            ])
            ->assertRedirect(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id, 'tab' => 'setor',
            ]));

        $this->assertDatabaseHas('collector_deposits', [
            'collector_id' => $this->teknisi->id,
            'status' => 'menunggu_verifikasi',
        ]);

        $deposit = CollectorDeposit::where('collector_id', $this->teknisi->id)->firstOrFail();
        $this->actingAs($admin)
            ->get(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id, 'tab' => 'setor',
            ]))
            ->assertOk()
            ->assertSee($deposit->deposit_number)
            ->assertSee('Belum ada saldo yang dapat disetor.');
    }

    #[Test]
    public function admin_outside_technician_pop_cannot_setor_for_technician(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-OUTSIDE-SETOR');
        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-outside-setor'))
            ->assertOk();

        $otherAdmin = $this->createUserWithRole('pop_admin', $this->createPop('TPY7'));

        $this->actingAs($otherAdmin)
            ->post(route('collector-worksheet.technicians.deposit', $this->teknisi->id), [
                'idempotency_key' => 'outside-tek-setor',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('collector_deposits', 0);
        $this->assertEquals(150000, app(CollectorBalanceService::class)->balance($this->teknisi));
    }

    #[Test]
    public function worksheet_admin_can_inspect_technician_payment_and_deposit_details(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-ADMIN-DETAIL');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-admin-detail'))
            ->assertOk();
        $payment = Payment::where('collected_by', $this->teknisi->id)->firstOrFail();

        $this->actingAs($this->teknisi)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-admin-detail-deposit'])
            ->assertRedirect();
        $deposit = CollectorDeposit::where('collector_id', $this->teknisi->id)->firstOrFail();

        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->get(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id, 'tab' => 'setor',
            ]))
            ->assertOk()
            ->assertSee($payment->payment_number)
            ->assertSee($deposit->deposit_number)
            ->assertSee('Pelanggan TPY-ADMIN-DETAIL')
            ->assertSee('Rp 150.000');

        $this->actingAs($admin)
            ->get(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id, 'tab' => 'riwayat_pembayaran',
            ]))
            ->assertOk()
            ->assertSee($payment->payment_number)
            ->assertSee($deposit->deposit_number);
    }

    #[Test]
    public function worksheet_admin_cannot_open_technician_cash_outside_pop_scope(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-SCOPE-DETAIL');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-scope-detail'))
            ->assertOk();

        $otherPop = $this->createPop('TPY4');
        $otherAdmin = $this->createUserWithRole('pop_admin', $otherPop);

        $this->actingAs($otherAdmin)
            ->get(route('collector-worksheet.technicians.show', $this->teknisi->id))
            ->assertForbidden();
    }

    #[Test]
    public function technician_deposit_verification_returns_admin_to_technician_worksheet(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'TPY-VERIFY-DETAIL');

        $this->actingAs($this->teknisi)
            ->postJson(route('technician-payments.store'), $this->payload($invoice, 'tek-verify-detail'))
            ->assertOk();
        $this->actingAs($this->teknisi)
            ->post(route('collector-worklist.deposit'), ['idempotency_key' => 'tek-verify-detail-deposit'])
            ->assertRedirect();

        $deposit = CollectorDeposit::where('collector_id', $this->teknisi->id)->firstOrFail();
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)
            ->post(route('collector-deposits.verify', $deposit->id), ['declared_amount' => '150000'])
            ->assertRedirect(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id, 'tab' => 'setor',
            ]));

        $this->assertSame('terverifikasi', $deposit->fresh()->status->value);

        $this->actingAs($admin)
            ->get(route('collector-worksheet.technicians.show', [
                'technician' => $this->teknisi->id, 'tab' => 'riwayat_pembayaran',
            ]))
            ->assertOk()
            ->assertSee($deposit->deposit_number)
            ->assertSee('Terverifikasi');
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
