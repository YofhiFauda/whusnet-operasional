<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CollectorBatchController — Tab Kolektor, inti fitur Fase 2
 * (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-5B, §E2.5).
 */
class CollectorBatchPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $admin;

    protected User $kolektor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-CBP1',
            'pop_code' => 'CBP1',
            'registration_prefix' => 'CP',
            'cid_prefix' => 'DP',
            'name' => 'POP Collector Batch Test',
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

    public function test_batch_pays_multiple_invoices_across_different_customers(): void
    {
        $invoice1 = $this->createUnpaidInvoice('C-CBP-A1', $this->kolektor->id, 100000);
        $invoice2 = $this->createUnpaidInvoice('C-CBP-A2', $this->kolektor->id, 200000);

        $response = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), [
            'idempotency_key' => 'batch-test-001',
            'rows' => [
                ['invoice_id' => $invoice1->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
                ['invoice_id' => $invoice2->id, 'amount' => 200000, 'payment_method' => 'transfer', 'collected_date' => '2026-06-13'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'processed' => 2]);

        $this->assertDatabaseHas('invoices', ['id' => $invoice1->id, 'invoice_status' => 'lunas']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice2->id, 'invoice_status' => 'lunas']);

        $payment1 = Payment::where('invoice_id', $invoice1->id)->firstOrFail();
        $this->assertEquals($this->kolektor->id, $payment1->collected_by);
        $this->assertEquals('2026-06-13', $payment1->collected_date->format('Y-m-d'));
        $this->assertNotNull($payment1->payment_batch_id);

        $this->assertDatabaseHas('payment_batches', [
            'idempotency_key' => 'batch-test-001',
            'collector_id' => $this->kolektor->id,
            'submitted_by' => $this->admin->id,
        ]);
    }

    public function test_partial_payment_in_batch_leaves_invoice_sebagian(): void
    {
        $invoice = $this->createUnpaidInvoice('C-CBP-B1', $this->kolektor->id, 150000);

        $response = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), [
            'idempotency_key' => 'batch-test-002',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_status' => 'sebagian',
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
        ]);
    }

    public function test_one_bad_row_rejects_entire_batch_with_reasons(): void
    {
        $goodInvoice = $this->createUnpaidInvoice('C-CBP-C1', $this->kolektor->id, 100000);
        $badInvoice = $this->createUnpaidInvoice('C-CBP-C2', $this->kolektor->id, 100000);

        $response = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), [
            'idempotency_key' => 'batch-test-003',
            'rows' => [
                ['invoice_id' => $goodInvoice->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
                // Nominal melebihi sisa tagihan — baris ini harus gagal.
                ['invoice_id' => $badInvoice->id, 'amount' => 999999, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonStructure(['failures' => [['invoice_id', 'reason']]]);

        // ALL-OR-NOTHING: baris yang "baik" pun TIDAK boleh tersimpan.
        $this->assertDatabaseHas('invoices', ['id' => $goodInvoice->id, 'invoice_status' => 'belum_dibayar']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_batches', 0);
    }

    public function test_batch_cannot_include_invoice_belonging_to_different_collector(): void
    {
        $otherKolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $otherKolektor = User::factory()->create(['role_id' => $otherKolektorRole->id, 'status' => 'active']);

        $invoiceOfOther = $this->createUnpaidInvoice('C-CBP-D1', $otherKolektor->id, 100000);

        $response = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), [
            'idempotency_key' => 'batch-test-004',
            'rows' => [
                ['invoice_id' => $invoiceOfOther->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_resubmitting_same_idempotency_key_does_not_create_duplicate_payments(): void
    {
        $invoice = $this->createUnpaidInvoice('C-CBP-E1', $this->kolektor->id, 100000);

        $payload = [
            'idempotency_key' => 'batch-test-005',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ];

        $first = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), $payload);
        $first->assertOk();

        $second = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), $payload);
        $second->assertOk();
        $second->assertJson(['already_processed' => true]);

        $this->assertEquals(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertEquals(1, PaymentBatch::where('idempotency_key', 'batch-test-005')->count());
    }

    public function test_batch_target_must_actually_be_a_kolektor(): void
    {
        $notAKolektor = User::factory()->create([
            'role_id' => Role::where('name', 'Teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('collectors.show', $notAKolektor->id));
        $response->assertNotFound();
    }

    public function test_batch_excludes_invoices_outside_admin_pop_scope(): void
    {
        $otherPop = Pop::create([
            'code' => 'POP-CBP-OTHER',
            'pop_code' => 'CBPO',
            'registration_prefix' => 'CO',
            'cid_prefix' => 'DO',
            'name' => 'POP Lain',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Invoice pelanggan di POP lain, tapi ditagih kolektor yang sama
        // (kolektor bisa lintas-POP kalau scope-nya luas — di sini admin-nya
        // yang scope-nya sempit, jadi harus tak kelihatan).
        $this->pop = $otherPop;
        $invoiceOtherPop = $this->createUnpaidInvoice('C-CBP-F1', $this->kolektor->id, 100000);

        $response = $this->actingAs($this->admin)->postJson(route('collector-batch.store', $this->kolektor->id), [
            'idempotency_key' => 'batch-test-006',
            'rows' => [
                ['invoice_id' => $invoiceOtherPop->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-13'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }
}
