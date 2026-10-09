<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminCashBalanceService;
use App\Services\CollectorBalanceService;
use App\Services\CollectorDepositService;
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADHOC-92 (G4) — laporan kas admin/kolektor tidak boleh menghitung porsi
 * `balance_used_amount` sebagai uang fisik. Payment yang SEBAGIAN dibayar
 * dari Saldo Pelanggan (`use_balance_amount`) hanya menyumbang porsi
 * TUNAI-nya ke kewajiban setor.
 *
 * Koreksi susulan 2026-09-24 (ditemukan saat diskusi manual testing): arah
 * sebaliknya juga harus benar — `overpay_amount` (kelebihan tunai FISIK,
 * mis. pelanggan bayar 3 bulan sekaligus di satu invoice BULANAN) WAJIB
 * ikut kewajiban setor, karena uangnya beneran ada di tangan admin/kolektor
 * meski disimpan di kolom terpisah dari `amount`. `Payment::physicalAmount()`
 * = `amount − balance_used_amount + overpay_amount`.
 */
class CashReportsExcludeSaldoPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected Customer $customer;

    protected CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = Pop::create([
            'code' => 'POP-CASH-1',
            'pop_code' => 'CSH1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Cash Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'C-CSH-0001',
            'full_name' => 'Customer Cash Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Cash Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => 'Jl. Cash Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $this->service = CustomerService::where('customer_id', $this->customer->id)->firstOrFail();

        app(CustomerBalanceService::class)->creditWithoutPayment(
            $this->customer, 200000, $this->pop->id, 'Setup saldo test'
        );
    }

    public function test_kas_admin_tidak_menghitung_porsi_saldo_sebagai_tunai(): void
    {
        $admin = $this->loginAsAdmin();
        $invoice = $this->makeInvoice('2026-06');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
            'use_balance_amount' => 100000,
        ])->assertRedirect();

        // Tagihan 150k = 50k tunai + 100k saldo. Kewajiban setor admin cuma 50k.
        $this->assertSame(50000.0, app(AdminCashBalanceService::class)->tunaiBelumDisetor($admin));
    }

    public function test_kas_kolektor_tidak_menghitung_porsi_saldo_sebagai_tunai(): void
    {
        $kolektorRole = Role::where('name', 'kolektor')->orWhere('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['status' => 'active', 'role_id' => $kolektorRole->id]);

        $admin = $this->loginAsAdmin();
        $invoice = $this->makeInvoice('2026-06');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
            'amount' => 50000,
            'use_balance_amount' => 100000,
        ])->assertRedirect();

        $this->assertSame(50000.0, app(CollectorBalanceService::class)->balance($kolektor));
    }

    public function test_kas_admin_ikut_menghitung_overpay_tunai_sebagai_kewajiban_setor(): void
    {
        $admin = $this->loginAsAdmin();
        // Pelanggan bayar 3x tagihan bulanan (450k) sekaligus di SATU invoice
        // BULANAN 150k — overpay 300k tercatat `kelebihan_bayar` (bukan
        // `bayar_di_muka`, itu cuma buat invoice AWAL), tapi tetap uang
        // tunai FISIK yang admin pegang dan wajib disetor.
        $invoice = $this->makeInvoice('2026-06');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 450000,
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_balance_mutations', [
            'customer_id' => $this->customer->id,
            'source' => 'kelebihan_bayar',
            'amount' => '300000.00',
        ]);

        // 150k applied ke invoice + 300k overpay = 450k tunai fisik seluruhnya
        // wajib disetor, BUKAN 150k saja.
        $this->assertSame(450000.0, app(AdminCashBalanceService::class)->tunaiBelumDisetor($admin));
    }

    public function test_kas_kolektor_ikut_menghitung_overpay_tunai_sebagai_kewajiban_setor(): void
    {
        $kolektorRole = Role::where('name', 'kolektor')->orWhere('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['status' => 'active', 'role_id' => $kolektorRole->id]);

        $admin = $this->loginAsAdmin();
        $invoice = $this->makeInvoice('2026-06');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
            'amount' => 450000,
        ])->assertRedirect();

        $this->assertSame(450000.0, app(CollectorBalanceService::class)->balance($kolektor));

        // CollectorDeposit::computedAmount() (pembanding declared_amount saat
        // verifikasi setoran) juga harus ikut overpay — kalau tidak, admin
        // yang menghitung uang fisik 450k akan dituduh sistem "Lebih Setor"
        // 300k padahal itu overpay yang sah, bukan kelebihan setor.
        $deposit = app(CollectorDepositService::class)->submit($kolektor, actor: $admin);
        $this->assertSame(450000.0, $deposit->computedAmount());
    }

    protected function makeInvoice(string $billingPeriod): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-'.$this->customer->customer_code.'-'.$billingPeriod,
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
}
