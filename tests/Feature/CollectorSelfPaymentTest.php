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
use App\Services\CollectorPaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kolektor mencatat pembayarannya SENDIRI dari Worklist
 * (`POST /collector-worklist/pay`) — jalur baru kolektor-2.0 §8, §9.
 *
 * Yang dikunci di sini adalah batas-batasnya, bukan cuma happy path:
 *   - kolektornya SELALU auth user (rute tanpa parameter), jadi mustahil
 *     mencatat atas nama kolektor lain;
 *   - cuma invoice pelanggan ber-`collector_id` dirinya;
 *   - cuma dalam POP scope efektifnya;
 *   - `collected_by` terisi dirinya — kalau kosong/salah, laporan setoran
 *     mencatat uang atas nama orang yang tak pernah menagihnya.
 */
class CollectorSelfPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->createPop('CSP1');
        $this->kolektor = $this->createKolektor($this->pop);
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

    private function createKolektor(Pop $pop): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createUnpaidInvoice(Pop $pop, string $code, ?int $collectorId, float $total = 150000): Invoice
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

    public function test_kolektor_records_payment_for_own_customer_and_is_credited_as_collector(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-OWN', $this->kolektor->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-001',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'processed' => 1]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame($this->kolektor->id, (int) $payment->collected_by);
        $this->assertSame($this->kolektor->id, (int) $payment->received_by);
        $this->assertSame('2026-08-05', $payment->collected_date->format('Y-m-d'));

        $invoice->refresh();
        $this->assertSame('lunas', $invoice->invoice_status->value);
    }

    /**
     * Cicilan: nominal boleh di bawah sisa. Saldo kolektor nanti (Fase 2)
     * bertambah sebesar UANG YANG DITERIMA, bukan nilai tagihan — jadi
     * pembayaran sebagian harus tersimpan apa adanya dan sisanya tetap
     * tertagih.
     */
    public function test_kolektor_can_record_partial_payment_and_remainder_stays_billable(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-CICIL', $this->kolektor->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-002',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertOk();

        $invoice->refresh();
        $this->assertSame('sebagian', $invoice->invoice_status->value);
        $this->assertEquals(100000, (float) $invoice->remaining_amount);
    }

    public function test_kolektor_cannot_pay_invoice_belonging_to_another_collector(): void
    {
        $lain = $this->createKolektor($this->pop);
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-LAIN', $lain->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-003',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_kolektor_cannot_pay_invoice_of_customer_without_collector(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-FREE', null);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-004',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * `collector_id` cocok tapi POP-nya di luar scope kolektor (dia dipindah
     * cabang setelah assign). Ditolak — assign lama tidak boleh jadi pintu
     * belakang ke cabang yang bukan wilayahnya lagi.
     */
    public function test_kolektor_cannot_pay_assigned_customer_outside_own_pop_scope(): void
    {
        $popLuar = $this->createPop('CSP2');
        $invoice = $this->createUnpaidInvoice($popLuar, 'C-CSP-OUT', $this->kolektor->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-005',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_kolektor_cannot_pay_more_than_remaining_amount(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-LEBIH', $this->kolektor->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-006',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 200000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_batch_is_all_or_nothing(): void
    {
        $valid = $this->createUnpaidInvoice($this->pop, 'C-CSP-OK', $this->kolektor->id);
        $lain = $this->createKolektor($this->pop);
        $invalid = $this->createUnpaidInvoice($this->pop, 'C-CSP-NG', $lain->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-007',
            'rows' => [
                ['invoice_id' => $valid->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
                ['invoice_id' => $invalid->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_duplicate_idempotency_key_is_not_processed_twice(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-IDEM', $this->kolektor->id);

        $payload = [
            'idempotency_key' => 'self-pay-008',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ];

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), $payload)->assertOk();
        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), $payload)
            ->assertOk()
            ->assertJson(['already_processed' => true]);

        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * Rute kolektor tak menerima id kolektor dari klien. Kalaupun body diisi
     * `collector_id` milik orang lain, yang tercatat tetap auth user —
     * inilah alasan rutenya dibuat tanpa parameter (§9).
     */
    public function test_collector_id_from_request_body_is_ignored(): void
    {
        $lain = $this->createKolektor($this->pop);
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-SPOOF', $this->kolektor->id);

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-009',
            'collector_id' => $lain->id,
            'collected_by' => $lain->id,
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertOk();
        $this->assertSame($this->kolektor->id, (int) Payment::query()->firstOrFail()->collected_by);
    }

    /**
     * Regresi (review Fase 1–3 temuan #2): notifikasi ke pop_admin dulu
     * dipanggil SESUDAH transaksi commit tapi MASIH di dalam try/catch
     * pemanggil. Satu exception dari dispatch (broadcast mati, queue penuh)
     * dijawab `422 Batch ditolak` padahal payment-nya sudah tersimpan
     * permanen — kolektor menekan Bayar lagi, dan pada cicilan pelanggan
     * terkredit dua kali.
     *
     * Batas transaksi dan batas penanganan error harus sejajar: sesudah commit,
     * tak ada apa pun yang boleh mengubah jawaban jadi "gagal".
     */
    public function test_notification_failure_does_not_fail_a_committed_payment(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-NOTIF', $this->kolektor->id);

        $this->app->bind(NotificationDispatcher::class, fn () => new class implements NotificationDispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new \RuntimeException('Broadcast mati.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \RuntimeException('Broadcast mati.');
            }
        });

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-notif',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'processed' => 1]);
        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * Lanjutan #2: seandainya klien tetap mengirim ulang key yang sama setelah
     * melihat kegagalan, server tak boleh menyimpan pembayaran kedua. Ini yang
     * membuat pemakaian ulang key di sisi klien aman.
     */
    public function test_retry_with_the_same_key_never_creates_a_second_payment(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-RETRY', $this->kolektor->id);

        $payload = [
            'idempotency_key' => 'self-pay-retry',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ];

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), $payload)->assertOk();
        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), $payload)->assertOk();

        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(100000, (float) $invoice->fresh()->remaining_amount);
    }

    /**
     * Regresi (review #4): `collected_date` dulu tanpa batas atas. Tanggal masa
     * depan mendarat di `payments.collected_date` (merusak pemotongan
     * pendapatan per periode) dan melahirkan kunjungan bertanggal besok —
     * padahal jalur kunjungan melarang persis itu.
     */
    public function test_future_collected_date_is_rejected(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-FUTURE', $this->kolektor->id);

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-future',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => now()->addDay()->toDateString()],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('collector_visits', 0);
    }

    /**
     * Regresi (review Fase 4 #2): re-validasi di bawah lock dulu cuma
     * memeriksa nominal. `Invoice::recalculateFromPayments()` early-return
     * untuk invoice `batal`, jadi `remaining_amount`-nya tetap utuh dan
     * pemeriksaan nominal saja lolos — pembayaran mendarat di tagihan mati,
     * uangnya masuk saldo kolektor, dan invoice tetap `batal`.
     */
    public function test_payment_is_rejected_when_invoice_is_cancelled_mid_flight(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-BATAL', $this->kolektor->id);

        // Simulasikan admin membatalkan invoice sesudah form kolektor terbuka:
        // validasi fase pertama sudah lewat, transaksi belum jalan.
        $service = app(CollectorPaymentService::class);
        $failures = $service->validateRows($this->kolektor, [
            ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
        ], $this->kolektor);
        $this->assertSame([], $failures);

        $invoice->update(['invoice_status' => 'batal']);

        $this->expectException(\Throwable::class);

        $service->record($this->kolektor, $this->kolektor, 'self-pay-batal', [
            ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
        ]);
    }

    public function test_cancelled_invoice_receives_no_payment_row(): void
    {
        $invoice = $this->createUnpaidInvoice($this->pop, 'C-CSP-BATAL2', $this->kolektor->id);
        $invoice->update(['invoice_status' => 'batal']);

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-batal2',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_non_kolektor_role_cannot_use_self_service_route(): void
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($teknisi)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'self-pay-010',
            'rows' => [
                ['invoice_id' => 1, 'amount' => 1000, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ]);

        $response->assertForbidden();
    }
}
