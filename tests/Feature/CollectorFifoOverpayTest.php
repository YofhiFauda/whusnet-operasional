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
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADHOC-84 §2.5/§4.4 — FIFO Kolektor: isian awal per-invoice tetap dikirim
 * client (JS `cbApplyFifo()`, murni kemudahan), tapi jaminan yang WAJIB
 * dipegang backend adalah split otomatis amount/overpay per baris + kredit
 * saldo — sama persis studi kasus dokumen rancangan
 * `docs/plan/billing/analisa-skema-alokasi-pembayaran-dan-saldo.md` §2.5.
 */
class CollectorFifoOverpayTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected Customer $customer;

    protected CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Tanggal bayar di tes ini di bulan Oktober; tutup buku otomatis
        // (ADHOC-96) mengunci bulan lewat, jadi waktu dibekukan di sini.
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-FIFO1',
            'pop_code' => 'FIFO1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP FIFO1',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $role = Role::where('code', 'kolektor')->firstOrFail();
        $this->kolektor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $this->kolektor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $this->customer = Customer::create([
            'customer_code' => 'C-FIFO-01',
            'full_name' => 'Pelanggan FIFO',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-05-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. FIFO Test',
            'collector_id' => $this->kolektor->id,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => 'Jl. FIFO Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $this->service = CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    private function unpaidInvoice(string $number, string $billingPeriod): Invoice
    {
        return Invoice::create([
            'invoice_number' => $number,
            'invoice_type' => 'bulanan',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $this->service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $billingPeriod,
            'issue_date' => $billingPeriod.'-01',
            'due_date' => $billingPeriod.'-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    public function test_200k_across_two_invoices_settles_oldest_and_leaves_second_partial(): void
    {
        $sept = $this->unpaidInvoice('INV-FIFO-SEP', '2026-09');
        $okt = $this->unpaidInvoice('INV-FIFO-OKT', '2026-10');

        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'fifo-200k',
            'rows' => [
                ['invoice_id' => $sept->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-10-05'],
                ['invoice_id' => $okt->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-10-05'],
            ],
        ]);

        $response->assertOk();

        $sept->refresh();
        $okt->refresh();
        $this->assertSame('lunas', $sept->invoice_status->value);
        $this->assertSame('sebagian', $okt->invoice_status->value);
        $this->assertSame('100000.00', $okt->remaining_amount);
        $this->assertSame(0.0, app(CustomerBalanceService::class)->balance($this->customer));
    }

    public function test_500k_across_two_invoices_settles_both_and_credits_remainder_as_balance(): void
    {
        $sept = $this->unpaidInvoice('INV-FIFO-SEP2', '2026-09');
        $okt = $this->unpaidInvoice('INV-FIFO-OKT2', '2026-10');

        // Kolektor mengetik total 500k tersebar FIFO: Sept dapat pas 150k,
        // sisanya (350k) dikirim di baris Okt — server yang memisah
        // 150k penutup + 200k overpay, bukan klien.
        $response = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'fifo-500k',
            'rows' => [
                ['invoice_id' => $sept->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-10-05'],
                ['invoice_id' => $okt->id, 'amount' => 350000, 'payment_method' => 'cash', 'collected_date' => '2026-10-05'],
            ],
        ]);

        $response->assertOk();

        $sept->refresh();
        $okt->refresh();
        $this->assertSame('lunas', $sept->invoice_status->value);
        $this->assertSame('lunas', $okt->invoice_status->value);

        $oktPayment = Payment::where('invoice_id', $okt->id)->firstOrFail();
        $this->assertSame('150000.00', $oktPayment->amount);
        $this->assertSame('200000.00', $oktPayment->overpay_amount);

        $this->assertSame(200000.0, app(CustomerBalanceService::class)->balance($this->customer));
    }

    public function test_batch_stays_atomic_and_idempotent_with_overpay_row(): void
    {
        $sept = $this->unpaidInvoice('INV-FIFO-SEP3', '2026-09');

        $payload = [
            'idempotency_key' => 'fifo-idem-1',
            'rows' => [
                ['invoice_id' => $sept->id, 'amount' => 200000, 'payment_method' => 'cash', 'collected_date' => '2026-10-05'],
            ],
        ];

        $first = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), $payload);
        $first->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(50000.0, app(CustomerBalanceService::class)->balance($this->customer));

        // Submit ulang dengan idempotency_key sama — tidak dobel kredit saldo.
        $second = $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), $payload);
        $second->assertOk();
        $second->assertJson(['already_processed' => true]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(50000.0, app(CustomerBalanceService::class)->balance($this->customer));
    }
}
