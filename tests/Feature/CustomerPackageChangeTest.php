<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerBalanceMutation;
use App\Models\CustomerPackageChange;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use App\Services\CustomerBalanceService;
use Carbon\Carbon;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ganti paket internet pelanggan aktif — upgrade/downgrade dengan prorate
 * (ADHOC-68, docs/plan/billing/upgrade-downgrade/analisa-upgrade-downgrade-paket.md).
 *
 * `customers.detail.packages.change` sengaja permission TERPISAH dari
 * `customers.detail.packages.update` (dipakai form edit pelanggan umum) —
 * lihat App\Enums\ActionCode::CHANGE.
 */
class CustomerPackageChangeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $paketLama;

    private InternetPackage $paketBaru;

    protected function setUp(): void
    {
        parent::setUp();

        // Tanggal tetap di tengah bulan 30 hari supaya pembagian hari prorate
        // deterministik lintas jalan test (September 2026 = 30 hari).
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        Village::create(['district_id' => $district->id, 'name' => 'Babadan']);

        $this->pop = Pop::create([
            'name' => 'POP Babadan',
            'type' => 'cabang',
            'code' => 'BBD-PKT',
            'cid_prefix' => 'BBD-PKT',
            'registration_prefix' => 'REG-BBD-PKT',
        ]);

        $this->paketLama = InternetPackage::create([
            'name' => 'Paket A 10 Mbps',
            'package_code' => 'PKT-A-10',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => 150000,
        ]);

        $this->paketBaru = InternetPackage::create([
            'name' => 'Paket B 20 Mbps',
            'package_code' => 'PKT-B-20',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '20 Mbps',
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 20,
            'monthly_price' => 250000,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeActiveCustomerWithService(float $discount = 0, float $ppn = 0, float $otherFee = 0): Customer
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'full_name' => 'Budi Santoso',
            'primary_phone' => '081234567890',
            'registration_date' => now()->subMonths(2)->toDateString(),
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->paketLama->id,
            'status' => 'active',
            'customer_code' => "REG-BBD-PKT-{$seq}",
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->paketLama->id,
            'package_name_snapshot' => $this->paketLama->name,
            'download_speed_snapshot' => '10 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => $this->paketLama->monthly_price,
            'discount' => $discount,
            'ppn' => $ppn,
            'other_fee' => $otherFee,
            'total_monthly_bill' => (150000 - $discount) * (1 + $ppn / 100) + $otherFee,
            'activation_date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->subMonth()->toDateString(),
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer->fresh();
    }

    private function makeCurrentPeriodInvoice(Customer $customer, float $totalAmount): Invoice
    {
        static $seq = 0;
        $seq++;

        return Invoice::create([
            'invoice_number' => "INV-TEST-{$seq}",
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->paketLama->id,
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->day(10)->toDateString(),
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    private function payInFull(Invoice $invoice): void
    {
        Payment::create([
            'payment_number' => 'PAY-TEST-'.uniqid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'amount' => $invoice->total_amount,
            'payment_status' => PaymentStatus::VALID->value,
        ]);

        $invoice->recalculateFromPayments();
    }

    public function test_admin_bisa_ganti_paket_pelanggan_aktif(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService(discount: 5000, ppn: 11, otherFee: 2000);

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $response->assertSessionHas('success');

        $service = $customer->customerService()->first();
        $this->assertSame($this->paketBaru->id, $service->internet_package_id);
        $this->assertSame('Paket B 20 Mbps', $service->package_name_snapshot);
        $this->assertSame('20.00 Mbps', $service->download_speed_snapshot);
        $this->assertEqualsWithDelta(250000.0, (float) $service->monthly_price, 0.01);

        // Diskon, PPN, biaya lain (kolom other_fee) TIDAK ikut berubah — cuma
        // harga & identitas paket.
        $this->assertEqualsWithDelta(5000.0, (float) $service->discount, 0.01);
        $this->assertEqualsWithDelta(11.0, (float) $service->ppn, 0.01);
        $this->assertEqualsWithDelta(2000.0, (float) $service->other_fee, 0.01);

        // total_monthly_bill TIDAK ikutkan other_fee (konsisten fix 2026-09-14
        // di CustomerController::store() — other_fee cuma sekali di Tagihan
        // Awal, bukan komponen tagihan bulanan berulang).
        $expectedTotal = (250000 - 5000) * 1.11;
        $this->assertEqualsWithDelta($expectedTotal, (float) $service->total_monthly_bill, 0.01);

        // customers.internet_package_id (FK legacy) ikut disinkron.
        $this->assertSame($this->paketBaru->id, $customer->fresh()->internet_package_id);
    }

    public function test_ganti_paket_ke_paket_yang_sama_ditolak(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketLama->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($this->paketLama->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_role_tanpa_permission_change_ditolak_403(): void
    {
        // Role custom yang cuma punya .view + .update (edit form umum),
        // TANPA .change — RBAC dinamis, dua permission independen.
        $role = Role::create(['name' => 'Helpdesk Tanpa Ganti Paket', 'code' => 'helpdesk-no-change-'.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('code', [
            'customers.view',
            'customers.detail.view',
            'customers.detail.packages.view',
            'customers.detail.packages.update',
        ])->get());

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($user);

        $customer = $this->makeActiveCustomerWithService();

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($this->paketLama->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_tidak_ada_invoice_periode_berjalan_ganti_paket_tanpa_prorate(): void
    {
        // Belum ada invoice BULANAN periode ini (mis. belum lewat tanggal
        // generate) — efeknya murni ke periode depan, sama seperti dulu.
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $this->assertSame(0, CustomerPackageChange::count());
        $this->assertSame($this->paketBaru->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_upgrade_invoice_belum_dibayar_total_diupdate_jumlah_dua_prorate(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        $invoice = $this->makeCurrentPeriodInvoice($customer, 150000);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        // 2026-09-15: hari lama Sep1-14 (14 hari), hari baru Sep15-30 (16 hari), 30 hari total.
        $expectedSubtotal = round((150000 / 30) * 14 + (250000 / 30) * 16, 2);

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedSubtotal, (float) $invoice->total_amount, 1.0);
        $this->assertEqualsWithDelta($expectedSubtotal, (float) $invoice->remaining_amount, 1.0);
        $this->assertSame('belum_dibayar', $invoice->invoice_status->value);
        $this->assertSame($this->paketBaru->id, $invoice->internet_package_id);

        $change = CustomerPackageChange::first();
        $this->assertNotNull($change);
        $this->assertSame($invoice->id, $change->resulting_invoice_id);
        $this->assertNull($change->deposit_mutation_id);

        // invoice_items ikut diperbarui — jumlah baris harus persis sama
        // dengan subtotal baru, bukan baris lama nominal 150000.
        $items = $invoice->items()->get();
        $this->assertGreaterThan(0, $items->count());
        $this->assertEqualsWithDelta($expectedSubtotal, (float) $items->sum('amount'), 1.0);
    }

    public function test_other_fee_customer_service_tidak_ikut_ditagih_saat_ganti_paket(): void
    {
        // Regresi bug: customer_services.other_fee SENGAJA tidak jadi
        // komponen tagihan bulanan berulang (fix 2026-09-14) — jangan ikut
        // ditambahkan ke total invoice ATAU total_monthly_bill saat ganti paket.
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService(otherFee: 42000);
        $invoice = $this->makeCurrentPeriodInvoice($customer, 150000);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $expectedSubtotal = round((150000 / 30) * 14 + (250000 / 30) * 16, 2);

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedSubtotal, (float) $invoice->total_amount, 1.0);

        $service = $customer->fresh()->customerService;
        $this->assertEqualsWithDelta(42000.0, (float) $service->other_fee, 0.01);
        $this->assertEqualsWithDelta(250000.0, (float) $service->total_monthly_bill, 0.01);
    }

    public function test_upgrade_invoice_lunas_muncul_sisa_tagih_selisih(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        $invoice = $this->makeCurrentPeriodInvoice($customer, 150000);
        $this->payInFull($invoice);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $expectedTotal = round((150000 / 30) * 14 + (250000 / 30) * 16, 2);

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedTotal, (float) $invoice->total_amount, 1.0);
        $this->assertEqualsWithDelta($expectedTotal - 150000, (float) $invoice->remaining_amount, 1.0);
        $this->assertSame('sebagian', $invoice->invoice_status->value);
    }

    public function test_downgrade_invoice_belum_dibayar_total_lebih_kecil(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        // Mulai dari paket mahal, downgrade ke yang murah.
        $customer->customerService->update([
            'internet_package_id' => $this->paketBaru->id,
            'monthly_price' => $this->paketBaru->monthly_price,
        ]);
        $invoice = $this->makeCurrentPeriodInvoice($customer, 250000);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketLama->id,
        ]);

        $expectedTotal = round((250000 / 30) * 14 + (150000 / 30) * 16, 2);

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedTotal, (float) $invoice->total_amount, 1.0);
        $this->assertLessThan(250000, (float) $invoice->total_amount);
    }

    public function test_downgrade_invoice_lunas_menghasilkan_deposit_saldo_pelanggan(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        $customer->customerService->update([
            'internet_package_id' => $this->paketBaru->id,
            'monthly_price' => $this->paketBaru->monthly_price,
        ]);
        $invoice = $this->makeCurrentPeriodInvoice($customer, 250000);
        $this->payInFull($invoice);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketLama->id,
        ]);

        $expectedTotal = round((250000 / 30) * 14 + (150000 / 30) * 16, 2);
        $expectedDeposit = round(250000 - $expectedTotal, 2);

        $invoice->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $invoice->remaining_amount, 0.01);
        $this->assertSame('lunas', $invoice->invoice_status->value);

        $mutation = CustomerBalanceMutation::where('customer_id', $customer->id)->first();
        $this->assertNotNull($mutation);
        $this->assertSame('credit', $mutation->type->value);
        $this->assertNull($mutation->payment_id);
        $this->assertEqualsWithDelta($expectedDeposit, (float) $mutation->amount, 1.0);

        $balance = app(CustomerBalanceService::class)->balance($customer);
        $this->assertEqualsWithDelta($expectedDeposit, $balance, 1.0);

        $change = CustomerPackageChange::first();
        $this->assertSame($mutation->id, $change->deposit_mutation_id);
    }

    public function test_upgrade_diblok_kalau_ada_tunggakan_periode_sebelumnya(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        Invoice::create([
            'invoice_number' => 'INV-TUNGGAKAN-1',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->paketLama->id,
            'billing_period' => now()->subMonth()->format('Y-m'),
            'issue_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'due_date' => now()->subMonth()->startOfMonth()->day(10)->toDateString(),
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($this->paketLama->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_downgrade_tetap_jalan_meski_ada_tunggakan_periode_sebelumnya(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        $customer->customerService->update([
            'internet_package_id' => $this->paketBaru->id,
            'monthly_price' => $this->paketBaru->monthly_price,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-TUNGGAKAN-2',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->paketBaru->id,
            'billing_period' => now()->subMonth()->format('Y-m'),
            'issue_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'due_date' => now()->subMonth()->startOfMonth()->day(10)->toDateString(),
            'subtotal' => 250000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 250000,
            'paid_amount' => 0,
            'remaining_amount' => 250000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketLama->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame($this->paketLama->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_ganti_paket_dua_kali_dalam_periode_yang_sama_tiga_segmen(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        $invoice = $this->makeCurrentPeriodInvoice($customer, 150000);

        $paketC = InternetPackage::create([
            'name' => 'Paket C 30 Mbps',
            'package_code' => 'PKT-C-30',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '30 Mbps',
            'download_speed_mbps' => 30,
            'upload_speed_mbps' => 30,
            'monthly_price' => 350000,
        ]);

        // Segmen 1: Sep1-14 paket lama (150k). Ganti ke paket B di hari ke-15.
        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        // Segmen 2: Sep15-19 paket B (250k). Ganti ke paket C di hari ke-20.
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $paketC->id,
        ]);

        // Segmen 3: Sep20-30 paket C (350k), 11 hari.
        $expectedSubtotal = round(
            (150000 / 30) * 14 + (250000 / 30) * 5 + (350000 / 30) * 11,
            2
        );

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedSubtotal, (float) $invoice->total_amount, 1.0);
        $this->assertSame(2, CustomerPackageChange::count());
        $this->assertSame($paketC->id, $invoice->internet_package_id);
    }

    public function test_ganti_paket_di_hari_pertama_periode_nol_hari_paket_lama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 08:00:00'));

        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();
        $invoice = $this->makeCurrentPeriodInvoice($customer, 150000);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        // Seluruh 30 hari periode ditagih dengan harga paket baru — tidak ada
        // pembagian nol/negatif yang meledak.
        $expectedSubtotal = round((250000 / 30) * 30, 2);

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedSubtotal, (float) $invoice->total_amount, 1.0);

        $change = CustomerPackageChange::first();
        $this->assertSame(0, $change->days_old_used);
        $this->assertSame(30, $change->days_new_used);
    }

    public function test_tagihan_awal_masih_berjalan_hari_dalam_periode_ikut_panjang_prorate_awal(): void
    {
        // Aktivasi tanggal 10 September — hari aktivasi digratiskan (konvensi
        // legacy), jadi jendela billing invoice AWAL cuma Sep11-30 (20 hari),
        // BUKAN 30 hari kalender penuh.
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-AWAL-1',
            'invoice_type' => 'awal',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->paketLama->id,
            'billing_period' => '2026-09',
            'issue_date' => '2026-09-10',
            'due_date' => '2026-09-10',
            'subtotal' => 160000,
            'discount' => 0,
            'ppn' => 0,
            'extra_installation_fee' => 50000,
            'other_fee' => 10000,
            'total_amount' => 160000,
            'paid_amount' => 0,
            'remaining_amount' => 160000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $change = CustomerPackageChange::first();
        $this->assertSame(20, $change->days_in_period);

        // Effective date test-now (Sep15): lama Sep11-14 (4 hari), baru Sep15-30 (16 hari).
        $expectedProratePaket = round((150000 / 20) * 4 + (250000 / 20) * 16, 2);
        // Biaya sekali-bayar (instalasi + materai) TETAP utuh, cuma porsi
        // paket internet yang diprorate ulang.
        $expectedTotal = $expectedProratePaket + 50000 + 10000;

        $invoice->refresh();
        $this->assertEqualsWithDelta($expectedTotal, (float) $invoice->total_amount, 1.0);

        // invoice_items ikut diperbarui: baris prorata + baris instalasi +
        // baris materai, jumlahnya persis subtotal baru.
        $items = $invoice->items()->get();
        $this->assertEqualsWithDelta($expectedTotal, (float) $items->sum('amount'), 1.0);
        $this->assertGreaterThanOrEqual(2, $items->count());
    }
}
