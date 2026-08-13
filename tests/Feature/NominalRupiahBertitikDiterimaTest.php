<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\ScopeType;
use App\Models\CollectorDeposit;
use App\Models\Customer;
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
 * Kasir mengetik nominal seperti yang dia baca di layar & kwitansi: `150.000`.
 *
 * Sebelum normalisasi server, titik itu dibaca sebagai desimal Inggris —
 * `numeric` LOLOS, pembayaran tersimpan **150 rupiah**, dan invoice tetap
 * "belum lunas" tanpa satu pun pesan error. Kegagalan diam yang paling mahal:
 * uangnya sudah diterima di dunia nyata.
 *
 * Test ini menjaga ketiga jalur uang masuk sekaligus, karena masing-masing
 * punya validasi sendiri dan mudah menyimpang satu sama lain.
 */
class NominalRupiahBertitikDiterimaTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->buatPop();
    }

    public function test_jalur_tagihan_menerima_titik_ribuan(): void
    {
        $this->loginAsAdmin();
        $invoice = $this->buatInvoice('C-RPH-1', 150000);

        $this->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => '150.000',
        ])->assertRedirect();

        $payment = Payment::query()->firstOrFail();
        $this->assertEquals(150000.0, (float) $payment->amount);
        $this->assertEquals(0.0, (float) $invoice->fresh()->remaining_amount);
    }

    public function test_jalur_tagihan_menerima_desimal_koma(): void
    {
        $this->loginAsAdmin();
        $invoice = $this->buatInvoice('C-RPH-2', 150000);

        $this->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => '75.000,50',
        ])->assertRedirect();

        $this->assertEquals(75000.50, (float) Payment::query()->firstOrFail()->amount);
    }

    public function test_batch_kolektor_menerima_titik_ribuan(): void
    {
        $kolektor = $this->buatUser('kolektor');
        $invoice = $this->buatInvoice('C-RPH-3', 200000, $kolektor);

        $this->actingAs($kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'rph-batch-1',
            'rows' => [[
                'invoice_id' => $invoice->id,
                'amount' => '200.000',
                'payment_method' => 'cash',
                'collected_date' => now()->toDateString(),
            ]],
        ])->assertOk();

        $this->assertEquals(200000.0, (float) Payment::query()->firstOrFail()->amount);
    }

    public function test_verifikasi_setoran_menerima_titik_ribuan(): void
    {
        $kolektor = $this->buatUser('kolektor');
        $admin = $this->buatUser('pop_admin');
        $invoice = $this->buatInvoice('C-RPH-4', 1250000, $kolektor);

        $this->actingAs($kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'rph-batch-2',
            'rows' => [[
                'invoice_id' => $invoice->id,
                'amount' => 1250000,
                'payment_method' => 'cash',
                'collected_date' => now()->toDateString(),
            ]],
        ])->assertOk();

        $this->actingAs($kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => '1.250.000',
        ])->assertRedirect();

        $deposit->refresh();
        // Kalau titiknya dibaca desimal, `declared` jadi 1,25 → setoran ditandai
        // selisih 1.249.998,75 dan kolektor ditagih uang yang sudah dia setor.
        $this->assertSame(DepositStatus::TERVERIFIKASI, $deposit->status);
        $this->assertEquals(0.0, (float) $deposit->difference);
    }

    public function test_ketikan_tak_dikenali_tetap_ditolak(): void
    {
        $this->loginAsAdmin();
        $invoice = $this->buatInvoice('C-RPH-5', 150000);

        // "12.34.56" bukan format ribuan maupun desimal yang sah. Menebaknya
        // lebih berbahaya daripada menolaknya — ini uang.
        $this->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => '12.34.56',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_master_paket_menerima_titik_ribuan(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.paket.store'), [
            'package_code' => 'PKT-RPH',
            'name' => 'Paket Rupiah 20M',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Home',
            'bandwidth_label' => '20 Mbps',
            'is_active' => 1,
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 10,
            'monthly_price' => '138.000',
            'installation_fee' => '250.000',
            // Persen — TIDAK dimasking, dikirim apa adanya.
            'discount_default' => 0,
            'ppn' => 11,
            'contract_period_months' => 12,
        ])->assertSessionHasNoErrors();

        $paket = InternetPackage::where('package_code', 'PKT-RPH')->firstOrFail();
        // Salah baca di sini menular ke SETIAP tagihan turunan paket ini,
        // bukan cuma ke satu transaksi.
        $this->assertEquals(138000.0, (float) $paket->monthly_price);
        $this->assertEquals(250000.0, (float) $paket->installation_fee);
        $this->assertEquals(11.0, (float) $paket->ppn);
    }

    public function test_diskon_pelanggan_menerima_titik_ribuan(): void
    {
        $this->loginAsAdmin();
        $invoice = $this->buatInvoice('C-RPH-6', 150000);
        $customer = $invoice->customer;

        $this->put(route('customers.update', $customer->id), [
            'full_name' => $customer->full_name,
            'primary_phone' => $customer->primary_phone,
            'registration_date' => $customer->registration_date->toDateString(),
            'pop_id' => $customer->pop_id,
            'status' => $customer->status,
            'discount_amount' => '10.000',
            'tax_percent' => 11,
            'other_fee' => '5.000',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(10000.0, (float) $customer->fresh()->discount_amount);
        // Persen tidak ikut dinormalkan — nilainya harus tetap 11, bukan 11000.
        $this->assertEquals(11.0, (float) $customer->fresh()->tax_percent);
    }

    public function test_tagihan_manual_menerima_titik_ribuan(): void
    {
        $this->loginAsAdmin();
        $invoice = $this->buatInvoice('C-RPH-7', 150000);

        $this->post(route('customers.invoices.manual', $invoice->customer_id), [
            'billing_period' => now()->addMonth()->format('Y-m'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'invoice_type' => 'reaktivasi',
            'prorate_amount' => '50.000',
            'extra_cable_fee' => '25.000',
        ])->assertSessionHasNoErrors();

        $manual = Invoice::where('customer_id', $invoice->customer_id)
            ->where('id', '!=', $invoice->id)
            ->firstOrFail();

        // Dicek per komponen, bukan cuma totalnya: total juga memuat harga
        // langganan, jadi salah baca satu komponen bisa tersamar di angka akhir.
        $this->assertEquals(50000.0, (float) $manual->prorate_amount);
        $this->assertEquals(25000.0, (float) $manual->extra_cable_fee);
    }

    private function buatPop(): Pop
    {
        return Pop::create([
            'code' => 'POP-RPH',
            'pop_code' => 'RPH',
            'registration_prefix' => 'CR',
            'cid_prefix' => 'DR',
            'name' => 'POP Rupiah',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function buatUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $this->pop->id,
        ]);
        $user->pops()->syncWithoutDetaching([$this->pop->id]);

        return $user->fresh();
    }

    private function buatInvoice(string $code, float $total, ?User $kolektor = null): Invoice
    {
        $customer = Customer::create([
            'collector_id' => $kolektor?->id,
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081299900011',
            'registration_date' => now()->toDateString(),
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Rupiah No. 1',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $total,
            'activation_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
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
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
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
