<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ScopeType;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerBalanceMutation;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorDepositService;
use App\Services\CustomerBalanceService;
use App\Services\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edit Pembayaran PENUH (ADHOC-108) — nominal & saldo pelanggan ikut bisa
 * dikoreksi, bukan cuma tanggal/metode. Rancangan lengkap:
 * docs/plan/billing/rancangan-edit-pembayaran-penuh.md.
 *
 * Tanggal SELALU relatif ke `now()` — payment yang boleh diedit dibatasi
 * ketat ke bulan berjalan (K3), jadi tanggal hard-code akan gagal begitu
 * bulan berganti (pelajaran dari PaymentEditUpdateTest lama, lihat §F9
 * dokumen rancangan).
 */
class PaymentEditFullRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $owner;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->owner = User::where('email', 'owner@whusnet.net')->firstOrFail();

        $this->pop = $this->createPop('PEFR');

        $this->bankAccount = BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '9988776655',
            'account_holder_name' => 'PT Whusnet Network',
            'label' => 'BCA Pusat',
            'is_active' => true,
        ]);
    }

    // ── Nominal & status invoice ────────────────────────────────

    public function test_nominal_diturunkan_membuat_invoice_jadi_sebagian(): void
    {
        $invoice = $this->createInvoice('C-N1', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);

        $this->putUpdate($payment, ['amount' => 100000])->assertRedirect(route('payments.show', $payment->id));

        $payment->refresh();
        $invoice->refresh();
        $this->assertEquals(100000, (float) $payment->amount);
        $this->assertEquals(InvoiceStatus::SEBAGIAN, $invoice->invoice_status);
        $this->assertEquals(50000, (float) $invoice->remaining_amount);
    }

    public function test_nominal_dinaikkan_sampai_pas_membuat_invoice_lunas(): void
    {
        $invoice = $this->createInvoice('C-N2', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 100000]);
        $this->assertEquals(InvoiceStatus::SEBAGIAN, $invoice->fresh()->invoice_status);

        $this->putUpdate($payment, ['amount' => 150000])->assertRedirect(route('payments.show', $payment->id));

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::LUNAS, $invoice->invoice_status);
        $this->assertEquals(0, (float) $invoice->remaining_amount);
    }

    // ── Ledger saldo — F2, risiko terbesar rancangan ────────────

    public function test_nominal_dinaikkan_melebihi_sisa_mengkredit_saldo_pelanggan(): void
    {
        $invoice = $this->createInvoice('C-O1', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);
        $customer = $invoice->customer;
        $this->assertEquals(0.0, app(CustomerBalanceService::class)->balance($customer));

        $this->putUpdate($payment, ['amount' => 200000])->assertRedirect();

        $payment->refresh();
        $this->assertEquals(150000, (float) $payment->amount);
        $this->assertEquals(50000, (float) $payment->overpay_amount);
        $this->assertEquals(50000.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_overpay_yang_dikoreksi_turun_membalik_kredit_dan_boleh_negatif(): void
    {
        $invoice = $this->createInvoice('C-O2', 150000);
        // Bayar 200rb sekaligus — overpay 50rb otomatis kredit saldo.
        $payment = $this->recordPayment($invoice, ['amount' => 200000]);
        $customer = $invoice->customer;
        $this->assertEquals(50000.0, app(CustomerBalanceService::class)->balance($customer));

        // Pelanggan sudah TERLANJUR memakai saldo 50rb itu di payment lain
        // sebelum koreksi ini — saldo boleh jatuh negatif waktu dibalik,
        // sama filosofinya dengan reverseCreditForPayment().
        CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => 'debit',
            'source' => 'pakai_manual',
            'amount' => 50000,
            'payment_id' => null,
            'pop_id' => $this->pop->id,
            'created_by' => $this->owner->id,
            'note' => 'Dipakai di payment lain (simulasi)',
        ]);
        $this->assertEquals(0.0, app(CustomerBalanceService::class)->balance($customer));

        // Koreksi nominal pas — overpay hilang, kredit lama harus dibalik.
        $this->putUpdate($payment, ['amount' => 150000])->assertRedirect();

        $payment->refresh();
        $this->assertNull($payment->overpay_amount);
        $this->assertEquals(-50000.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_edit_dua_kali_berturut_turut_tidak_bentrok_unique_index(): void
    {
        $invoice = $this->createInvoice('C-D1', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);
        $customer = $invoice->customer;

        $this->putUpdate($payment, ['amount' => 200000])->assertRedirect();
        $this->assertEquals(50000.0, app(CustomerBalanceService::class)->balance($customer));

        // Edit KEDUA pada payment yang SAMA — dulu (sebelum kolom `revision`)
        // ini bentrok UniqueConstraintViolationException di ledger.
        $this->putUpdate($payment, ['amount' => 220000])->assertRedirect();

        $payment->refresh();
        $this->assertEquals(70000, (float) $payment->overpay_amount);
        $this->assertEquals(70000.0, app(CustomerBalanceService::class)->balance($customer));

        $revisions = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('source', 'koreksi')
            ->orderBy('revision')
            ->pluck('revision')
            ->all();
        $this->assertSame([1, 2], $revisions);
    }

    public function test_saldo_dipakai_diubah_menyesuaikan_ledger_dan_menolak_saldo_tak_cukup(): void
    {
        $invoice = $this->createInvoice('C-B1', 200000);
        $customer = $invoice->customer;

        // Titip saldo 100rb via payment lain (invoice AWAL longgar — pakai
        // creditWithoutPayment supaya tak butuh payment sungguhan).
        app(CustomerBalanceService::class)->creditWithoutPayment($customer, 100000, $this->pop->id, 'Saldo awal simulasi');

        $payment = $this->recordPayment($invoice, ['amount' => 200000, 'use_balance_amount' => 0]);
        $this->assertEquals(100000.0, app(CustomerBalanceService::class)->balance($customer));

        // Pakai saldo 100rb, kurangi tunai jadi 100rb — total tetap 200rb.
        $this->putUpdate($payment, ['amount' => 100000, 'use_balance_amount' => 100000])->assertRedirect();

        $payment->refresh();
        $this->assertEquals(100000, (float) $payment->balance_used_amount);
        $this->assertEquals(0.0, app(CustomerBalanceService::class)->balance($customer));

        // Minta saldo lebih dari yang tersedia (sudah 0) — ditolak, TAK ADA
        // perubahan tersimpan.
        $response = $this->putUpdate($payment, ['amount' => 50000, 'use_balance_amount' => 150000]);
        $response->assertSessionHasErrors('use_balance_amount');

        $payment->refresh();
        $this->assertEquals(100000, (float) $payment->balance_used_amount, 'Payment tidak boleh berubah saat validasi saldo gagal.');
    }

    // ── Guard K3 — hanya bulan berjalan ──────────────────────────

    public function test_payment_bulan_lalu_tidak_bisa_diedit(): void
    {
        $invoice = $this->createInvoice('C-K3A', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);
        // Paksa mundur ke bulan lalu — lolos guard `record()` (yang jalan
        // saat now() masih bulan ini) tapi harus ditolak Edit.
        $payment->forceFill(['payment_date' => now()->subMonthNoOverflow()->startOfMonth()])->saveQuietly();

        $response = $this->actingAs($this->owner)->get(route('payments.edit', $payment->id));
        $response->assertRedirect(route('payments.show', $payment->id));
        $response->assertSessionHasErrors('payment');

        $response = $this->putUpdate($payment, ['amount' => 100000]);
        $response->assertRedirect(route('payments.show', $payment->id));
        $response->assertSessionHasErrors('payment');
        $this->assertEquals(150000, (float) $payment->fresh()->amount);
    }

    public function test_tanggal_baru_di_luar_bulan_berjalan_ditolak(): void
    {
        $invoice = $this->createInvoice('C-K3B', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);

        $response = $this->putUpdate($payment, [
            'amount' => 150000,
            'payment_date' => now()->subMonthNoOverflow()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('payment_date');
        $this->assertEquals(now()->format('Y-m-d'), $payment->fresh()->payment_date->format('Y-m-d'));
    }

    // ── Guard: payment ditolak, invoice batal ───────────────────

    public function test_payment_yang_sudah_dikembalikan_tidak_bisa_diedit(): void
    {
        $invoice = $this->createInvoice('C-R1', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);
        $payment->update(['payment_status' => PaymentStatus::DITOLAK->value]);

        $response = $this->putUpdate($payment, ['amount' => 100000]);
        $response->assertSessionHasErrors('payment');
    }

    public function test_invoice_batal_memblokir_edit_pembayarannya(): void
    {
        $invoice = $this->createInvoice('C-BT1', 150000);
        $payment = $this->recordPayment($invoice, ['amount' => 100000]);
        $invoice->update(['invoice_status' => InvoiceStatus::BATAL->value]);

        $response = $this->putUpdate($payment, ['amount' => 120000]);
        $response->assertSessionHasErrors('payment');
        $this->assertEquals(100000, (float) $payment->fresh()->amount);
    }

    // ── K5 — payment metode Saldo tidak boleh diedit ────────────

    public function test_payment_metode_saldo_tidak_bisa_diedit(): void
    {
        $invoice = $this->createInvoice('C-S1', 150000);
        $payment = Payment::create([
            'payment_number' => 'PAY-SALDO-'.$invoice->id,
            'idempotency_key' => 'auto-saldo:'.$invoice->id.':1',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'saldo',
            'amount' => 150000,
            'balance_used_amount' => 150000,
            'payment_status' => PaymentStatus::VALID->value,
        ]);

        $this->actingAs($this->owner)->get(route('payments.edit', $payment->id))
            ->assertRedirect(route('payments.show', $payment->id))
            ->assertSessionHasErrors('payment');

        $this->putUpdate($payment, ['amount' => 100000])->assertSessionHasErrors('payment');
        $this->assertEquals(150000, (float) $payment->fresh()->amount);
    }

    // ── K4 — payment dalam setoran ───────────────────────────────

    public function test_setoran_terverifikasi_memblokir_edit(): void
    {
        [$kolektor, $admin] = $this->createKolektorDanAdmin();
        $invoice = $this->createInvoice('C-DV1', 150000, $kolektor);
        $payment = $this->recordPayment($invoice, [
            'amount' => 150000,
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
        ]);

        $deposit = app(CollectorDepositService::class)->submit($kolektor);
        app(CollectorDepositService::class)->verify($deposit, $admin, 150000.0);
        $this->assertEquals(DepositStatus::TERVERIFIKASI, $deposit->fresh()->status);

        $response = $this->putUpdate($payment, ['amount' => 100000, 'payment_method' => 'kolektor', 'collected_by' => $kolektor->id]);
        $response->assertSessionHasErrors('payment');
        $this->assertEquals(150000, (float) $payment->fresh()->amount);
    }

    public function test_setoran_menunggu_verifikasi_boleh_diedit_nominalnya_dan_total_setoran_ikut_berubah(): void
    {
        [$kolektor, $admin] = $this->createKolektorDanAdmin();
        $invoice = $this->createInvoice('C-DV2', 150000, $kolektor);
        $payment = $this->recordPayment($invoice, [
            'amount' => 150000,
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
        ]);

        $deposit = app(CollectorDepositService::class)->submit($kolektor);
        $this->assertEquals(150000.0, $deposit->fresh()->computedAmount());

        $this->putUpdate($payment, ['amount' => 100000, 'payment_method' => 'kolektor', 'collected_by' => $kolektor->id])
            ->assertRedirect();

        $this->assertEquals(100000.0, $deposit->fresh()->computedAmount());
    }

    public function test_payment_dalam_setoran_tidak_bisa_ganti_metode_atau_kolektor(): void
    {
        [$kolektor, $admin] = $this->createKolektorDanAdmin();
        $kolektorLain = User::factory()->create([
            'role_id' => Role::where('code', 'kolektor')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $invoice = $this->createInvoice('C-DV3', 150000, $kolektor);
        $payment = $this->recordPayment($invoice, [
            'amount' => 150000,
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
        ]);

        app(CollectorDepositService::class)->submit($kolektor);

        $response = $this->putUpdate($payment, ['amount' => 150000, 'payment_method' => 'cash', 'collected_by' => null]);
        $response->assertSessionHasErrors('payment_method');

        $response = $this->putUpdate($payment, ['amount' => 150000, 'payment_method' => 'kolektor', 'collected_by' => $kolektorLain->id]);
        $response->assertSessionHasErrors('collected_by');

        $payment->refresh();
        $this->assertEquals('kolektor', $payment->payment_method);
        $this->assertEquals($kolektor->id, $payment->collected_by);
    }

    // ── Rekening bank ─────────────────────────────────────────────

    public function test_rekening_nonaktif_ditolak_kecuali_dipertahankan_apa_adanya(): void
    {
        $invoice = $this->createInvoice('C-BA1', 150000);
        $payment = $this->recordPayment($invoice, [
            'amount' => 150000,
            'payment_method' => 'transfer',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $this->bankAccount->update(['is_active' => false]);

        // Tetap pakai rekening yang SAMA (kini nonaktif) — snapshot lama
        // dipertahankan, harus LOLOS.
        $this->putUpdate($payment, ['amount' => 150000, 'payment_method' => 'transfer', 'bank_account_id' => $this->bankAccount->id])
            ->assertRedirect(route('payments.show', $payment->id));

        // Rekening nonaktif lain (baru dipilih) — harus DITOLAK.
        $rekeningLain = BankAccount::create([
            'bank_name' => 'Mandiri', 'account_number' => '111222333',
            'account_holder_name' => 'PT Whusnet Network', 'label' => 'Mandiri Nonaktif', 'is_active' => false,
        ]);

        $response = $this->putUpdate($payment, ['amount' => 150000, 'payment_method' => 'transfer', 'bank_account_id' => $rekeningLain->id]);
        $response->assertSessionHasErrors('bank_account_id');
    }

    // ── Format Rupiah ────────────────────────────────────────────

    public function test_nominal_format_titik_ribuan_dibaca_benar(): void
    {
        $invoice = $this->createInvoice('C-RP1', 200000);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);

        $this->putUpdate($payment, ['amount' => '150.000'])->assertRedirect();

        $this->assertEquals(150000, (float) $payment->fresh()->amount);
    }

    // ── Scope POP & permission ───────────────────────────────────

    public function test_pop_admin_di_luar_scope_tidak_bisa_membuka_atau_mengedit(): void
    {
        $popLain = $this->createPop('PEFR2');
        $invoice = $this->createInvoice('C-SC1', 150000, null, $popLain);
        $payment = $this->recordPayment($invoice, ['amount' => 150000]);

        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $popAdmin->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $this->actingAs($popAdmin)->get(route('payments.edit', $payment->id))->assertForbidden();
        $this->actingAs($popAdmin)->put(route('payments.update', $payment->id), ['amount' => 100000])->assertForbidden();
    }

    public function test_default_hanya_owner_dan_admin_yang_punya_payments_update(): void
    {
        $rolesTanpaAkses = ['pop_admin', 'helpdesk', 'noc', 'fop', 'teknisi', 'sales', 'kolektor', 'atasan'];

        foreach ($rolesTanpaAkses as $code) {
            $role = Role::where('code', $code)->first();
            if (! $role) {
                continue;
            }

            $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
            $this->assertFalse(
                $user->hasPermission('payments.update'),
                "Role {$code} SEHARUSNYA belum punya payments.update (K6) — cek RolePermissionSeeder."
            );
        }

        $this->assertTrue($this->owner->hasPermission('payments.update'));

        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);
        $this->assertTrue($admin->hasPermission('payments.update'));
    }

    // ── Helper ────────────────────────────────────────────────────

    private function putUpdate(Payment $payment, array $overrides = [])
    {
        $defaults = [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => $payment->payment_method,
            'note' => null,
        ];

        return $this->actingAs($this->owner)->put(route('payments.update', $payment->id), array_merge($defaults, $overrides));
    }

    private function recordPayment(Invoice $invoice, array $overrides = []): Payment
    {
        $defaults = [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'bank_account_id' => null,
            'sender_name' => null,
            'collected_by' => null,
            'use_balance_amount' => 0,
            'idempotency_key' => null,
            'amount' => 150000,
            'note' => null,
        ];

        return app(PaymentService::class)->record($invoice, array_merge($defaults, $overrides), null);
    }

    private function createKolektorDanAdmin(): array
    {
        $kolektor = User::factory()->create([
            'role_id' => Role::where('code', 'kolektor')->firstOrFail()->id,
            'status' => 'active',
        ]);
        $adminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);
        $admin->pops()->attach($this->pop->id);

        return [$kolektor, $admin];
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

    private function createInvoice(string $code, float $total, ?User $collector = null, ?Pop $pop = null): Invoice
    {
        $pop ??= $this->pop;

        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => now()->format('Y-m-d'),
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collector?->id,
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
            'activation_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
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
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->startOfMonth()->format('Y-m-d'),
            'due_date' => now()->startOfMonth()->addDays(14)->format('Y-m-d'),
            'subtotal' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => 'belum_dibayar',
        ]);
    }
}
