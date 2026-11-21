<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\IsolationCaseStatus;
use App\Events\IsolationCaseQueueChanged;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\IsolationCase;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\Village;
use App\Services\IsolationCaseService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Monitoring Isolir — lifecycle kasus isolir
 * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §21), menggantikan
 * `IsolirQueueTest` lama (ADHOC-306, belum pernah commit) total. Fokus:
 * BR-08 (pembayaran ≠ pemulihan — layanan TETAP suspended sampai NOC
 * konfirmasi), BR-10 (kasus tidak hilang saat status layanan berubah).
 */
class IsolirQueueTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected InternetPackage $package;

    protected User $adminUser;

    protected User $customerServiceUser;

    protected User $nocUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);

        $this->pop = Pop::create([
            'name' => 'POP Ponorogo Kota',
            'code' => 'PNO-01',
            'pop_code' => 'PNO',
            'cid_prefix' => 'P',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $this->package = InternetPackage::first();

        $this->adminUser = $this->makeRoleUser('Admin');
        $this->customerServiceUser = $this->makeRoleUser('Customer Service');
        $this->nocUser = $this->makeRoleUser('NOC');
    }

    protected function makeRoleUser(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => 'all_pop',
        ]);

        return $user;
    }

    /**
     * Gerbang keputusan Admin (follow-up 2026-11-19) — `toggleSuspend()` arah
     * isolir sekarang menolak kasus yang belum diputuskan `execute`. Dipanggil
     * di tiap test yang perlu isolir SETELAH kasus sudah ada (dari command
     * atau "Tandai Manual"), supaya tes fokus ke hal yang diuji, bukan ke
     * gerbang ini.
     */
    protected function decideExecute(Customer $customer, ?User $actor = null): void
    {
        $this->actingAs($actor ?? $this->adminUser)
            ->post(route('customers.isolir-queue.decide', $customer->customerService->id), ['decision' => 'execute'])
            ->assertRedirect();
    }

    protected function createCustomer(string $status = 'active'): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_code' => 'RQ'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'cid' => 'P00RQ'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'full_name' => 'Pelanggan Isolir '.$seq,
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Diponegoro No. 10',
            'data_completeness_status' => 'siap_billing',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Diponegoro No. 10',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'service_status' => $status === 'active' ? 'aktif' : 'isolir',
            'billing_status' => 'active',
            'monthly_price' => $this->package->monthly_price,
            'total_monthly_bill' => $this->package->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $customer;
    }

    protected function createOverdueInvoice(Customer $customer, string $periode = '2026-08'): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-PTG-'.$customer->id.'-'.$periode,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);
    }

    protected function payInFull(Invoice $invoice): void
    {
        Payment::create([
            'payment_number' => 'PAY-'.$invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => $invoice->remaining_amount,
            'received_by' => $this->adminUser->id,
            'payment_status' => 'valid',
        ]);

        $invoice->refresh()->recalculateFromPayments();
    }

    #[Test]
    public function command_sync_cases_membuat_kasus_menunggu_isolir_dari_tunggakan_periode_lalu(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');

        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertNotNull($case);
        $this->assertSame(IsolationCaseStatus::WAITING_ISOLATION, $case->status);
        $this->assertSame('overdue_billing', $case->trigger_type->value);

        $this->travelBack();
    }

    #[Test]
    public function command_sync_cases_membuat_kasus_untuk_tunggakan_bulan_berjalan_setelah_lewat_jatuh_tempo(): void
    {
        // Konfirmasi user 2026-11-19: isolir pakai JATUH TEMPO (tgl 10),
        // BUKAN piutang pembukuan (nunggu pergantian bulan) — tagihan
        // periode BERJALAN yang sudah lewat tgl 10 tetap harus masuk
        // antrean isolir, meski secara pembukuan belum dianggap piutang.
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-09'); // periode BERJALAN, due_date 2026-09-10 sudah lewat

        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertNotNull($case);
        $this->assertSame(IsolationCaseStatus::WAITING_ISOLATION, $case->status);

        $this->travelBack();
    }

    #[Test]
    public function command_sync_cases_tidak_membuat_kasus_untuk_tunggakan_bulan_berjalan_sebelum_jatuh_tempo(): void
    {
        $this->travelTo(now()->parse('2026-09-05'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-09'); // due_date 2026-09-10, BELUM lewat

        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $this->assertDatabaseMissing('isolation_cases', ['service_id' => $customer->customerService->id]);

        $this->travelBack();
    }

    #[Test]
    public function command_sync_cases_idempoten_tidak_bikin_kasus_dobel(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');

        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $this->assertSame(1, IsolationCase::where('service_id', $customer->customerService->id)->count());

        $this->travelBack();
    }

    #[Test]
    public function tandai_manual_membuat_kasus_dan_tercatat_pelaku_waktu(): void
    {
        $customer = $this->createCustomer('active');

        $response = $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), [
                'customer_id' => $customer->id,
                'note' => 'Menunggak 2 bulan, sudah diingatkan lewat telepon.',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertNotNull($case);
        $this->assertSame('manual', $case->trigger_type->value);

        $this->assertDatabaseHas('isolation_events', [
            'isolation_case_id' => $case->id,
            'event_type' => 'case_created',
            'actor_id' => $this->customerServiceUser->id,
            'description' => 'Menunggak 2 bulan, sudah diingatkan lewat telepon.',
        ]);
    }

    #[Test]
    public function tandai_manual_pelanggan_non_aktif_ditolak(): void
    {
        $customer = $this->createCustomer('suspended');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('customers.isolir-queue.store'), [
                'customer_id' => $customer->id,
                'note' => 'Coba tandai pelanggan yang sudah isolir.',
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('isolation_cases', ['service_id' => $customer->customerService->id]);
    }

    #[Test]
    public function batalkan_kasus_sebelum_isolir_boleh_oleh_user_lain_dan_tercatat(): void
    {
        $customer = $this->createCustomer('active');
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'Ditandai oleh CS.'])
            ->assertOk();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();

        // NOC (bukan pembuat kasus) membatalkan — boleh, asal tercatat.
        $this->actingAs($this->nocUser)
            ->delete(route('customers.isolir-queue.destroy', $customer->customerService->id))
            ->assertRedirect();

        $case->refresh();
        $this->assertSame(IsolationCaseStatus::CANCELLED, $case->status);
        $this->assertDatabaseHas('isolation_events', [
            'isolation_case_id' => $case->id,
            'event_type' => 'case_cancelled',
            'actor_id' => $this->nocUser->id,
        ]);
    }

    #[Test]
    public function isolir_dari_toggle_suspend_membuat_kasus_bila_belum_ada_dan_mencatat_pelaku(): void
    {
        $customer = $this->createCustomer('active');

        $this->actingAs($this->adminUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir langsung'])
            ->assertOk();

        $customer->refresh();
        $this->assertEquals('suspended', $customer->status);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertNotNull($case);
        $this->assertSame(IsolationCaseStatus::SUSPENDED, $case->status);
        $this->assertNotNull($case->suspended_at);

        $this->assertDatabaseHas('isolation_actions', [
            'isolation_case_id' => $case->id,
            'action_type' => 'suspend',
            'status' => 'succeeded',
            'executed_by' => $this->adminUser->id,
        ]);
    }

    #[Test]
    public function pembayaran_lunas_sebelum_isolir_membatalkan_kasus_layanan_tetap_aktif(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $invoice = $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::WAITING_ISOLATION, $case->status);

        $this->payInFull($invoice);

        $case->refresh();
        $this->assertSame(IsolationCaseStatus::CANCELLED, $case->status);
        $this->assertSame('paid_before_isolation', $case->cancellation_reason);
        $this->assertEquals('active', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function noc_tidak_bisa_isolir_kasus_yang_belum_diputuskan_admin(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        // TANPA decideExecute() — kasus masih default (belum diputuskan).
        $response = $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir']);

        $response->assertStatus(422);
        $this->assertEquals('active', $customer->refresh()->status);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::WAITING_ISOLATION, $case->status);

        $this->travelBack();
    }

    #[Test]
    public function noc_tidak_bisa_isolir_kasus_yang_ditoleransi_admin(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $this->actingAs($this->adminUser)
            ->post(route('customers.isolir-queue.decide', $customer->customerService->id), [
                'decision' => 'tolerate',
                'note' => 'Pelanggan lama, tunggu konfirmasi pelunasan dari kolektor dulu.',
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "Pelanggan {$customer->full_name} masih ditoleransi Admin: Pelanggan lama, tunggu konfirmasi pelunasan dari kolektor dulu."]);
        $this->assertEquals('active', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function noc_bisa_isolir_setelah_admin_memutuskan_eksekusi(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer, $this->customerServiceUser);

        $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $customer->refresh();
        $this->assertEquals('suspended', $customer->status);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::SUSPENDED, $case->status);
        $this->assertDatabaseHas('isolation_events', [
            'isolation_case_id' => $case->id,
            'event_type' => 'admin_marked_execute',
            'actor_id' => $this->customerServiceUser->id,
        ]);

        $this->travelBack();
    }

    #[Test]
    public function keputusan_eksekusi_memindahkan_kasus_ke_tab_siap_isolir(): void
    {
        // Follow-up 2026-11-19 (lanjutan): dua tab TERPISAH — "Menunggu
        // Isolir" (antrean review Admin) vs "Siap Isolir" (antrean eksekusi
        // NOC) — biar NOC tidak pernah pegang kasus yang belum di-acc Admin.
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::WAITING_ISOLATION, $case->status);

        $this->decideExecute($customer);

        $case->refresh();
        $this->assertSame(IsolationCaseStatus::READY_FOR_ISOLATION, $case->status);

        // Request tambahan buat "membuang" flash message redirect
        // `decideExecute()` di atas — kalau tidak, flash itu nebeng muncul di
        // request GET berikutnya dan bikin assertDontSee() di bawah salah
        // deteksi (case_number kelihatan dari toast, bukan dari baris tabel).
        $this->actingAs($this->adminUser)->get(route('customers.isolir-queue.index'));

        // Tab "Menunggu Isolir" tidak lagi menampilkan kasus ini...
        $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['tab' => 'menunggu']))
            ->assertOk()
            ->assertDontSee($case->case_number);

        // ...tapi tab "Siap Isolir" iya.
        $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['tab' => 'siap']))
            ->assertOk()
            ->assertSee($case->case_number);

        $this->travelBack();
    }

    #[Test]
    public function admin_bisa_tarik_kasus_dari_siap_isolir_balik_ke_menunggu_sebelum_noc_eksekusi(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::READY_FOR_ISOLATION, $case->status);

        $this->actingAs($this->adminUser)
            ->post(route('customers.isolir-queue.decide', $customer->customerService->id), [
                'decision' => 'tolerate',
                'note' => 'Ternyata belum, tarik dulu.',
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertSame(IsolationCaseStatus::WAITING_ISOLATION, $case->status);

        // NOC tidak lagi bisa isolir setelah ditarik balik.
        $response = $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir']);
        $response->assertStatus(422);
        $this->assertEquals('active', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function kpi_siap_isolir_terpisah_dari_menunggu_isolir(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customerMenunggu = $this->createCustomer('active');
        $this->createOverdueInvoice($customerMenunggu, '2026-08');

        $customerSiap = $this->createCustomer('active');
        $this->createOverdueInvoice($customerSiap, '2026-08');

        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customerSiap);

        $kpi = app(IsolationCaseService::class)->kpiSummary($this->adminUser);

        $this->assertSame(1, $kpi['waiting_isolation']);
        $this->assertSame(1, $kpi['ready_for_isolation']);

        $this->travelBack();
    }

    #[Test]
    public function isolir_tanpa_kasus_sama_sekali_tidak_kena_gerbang_keputusan(): void
    {
        // Isolir ad-hoc di luar jalur Monitoring Isolir (tidak pernah
        // terdeteksi/ditandai) — tidak ada kasus untuk diputuskan, jadi
        // gerbang tidak berlaku. Perilaku sebelum fitur keputusan Admin ada.
        $customer = $this->createCustomer('active');

        $this->actingAs($this->adminUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir langsung tanpa antrean'])
            ->assertOk();

        $this->assertEquals('suspended', $customer->refresh()->status);
    }

    #[Test]
    public function pembayaran_lunas_setelah_isolir_tida_k_otomatis_aktifkan_layanan(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $invoice = $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $this->payInFull($invoice);

        // BR-08: pembayaran memindahkan kasus ke "menunggu pemulihan", TAPI
        // layanan TETAP suspended sampai NOC mengonfirmasi lewat
        // "Aktifkan Kembali" — ini akar bug yang dikomplain user di desain lama.
        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::RESTORATION_PENDING, $case->status);
        $this->assertEquals('suspended', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function noc_konfirmasi_pemulihan_menyelesaikan_kasus(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $invoice = $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $this->payInFull($invoice);

        $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Aktifkan setelah lunas'])
            ->assertOk();

        $customer->refresh();
        $this->assertEquals('active', $customer->status);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::COMPLETED, $case->status);
        $this->assertNotNull($case->restored_at);

        $this->travelBack();
    }

    #[Test]
    public function pembayaran_tetap_memproses_kasus_walau_invoice_belum_pernah_tersinkron_ke_kasus(): void
    {
        // Reproduksi bug nyata (2026-11-19): kasus dibuat manual (trigger
        // MANUAL, "Tandai Manual") SEBELUM invoice periode ini jadi piutang,
        // jadi tidak sempat tertaut ke kasus lewat `syncInvoices()` — beda
        // dengan skenario lain di file ini yang selalu lewat
        // `isolir:sync-cases` dulu (yang menautkan invoice SAAT kasus
        // dibuat). Tanpa command deteksi harian berjalan ulang, pembayaran
        // invoice ini dulu tidak memicu apa pun sama sekali.
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'ditandai sebelum ada piutang'])
            ->assertOk();

        $invoice = $this->createOverdueInvoice($customer, '2026-08'); // dibuat SETELAH kasus ada
        $this->decideExecute($customer);

        $this->actingAs($this->adminUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $this->payInFull($invoice);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::RESTORATION_PENDING, $case->status);
        $this->assertSame(0.0, (float) $case->current_outstanding_amount);
        $this->assertEquals('suspended', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function pembayaran_lewat_tombol_bayar_halaman_tagihan_memindahkan_kasus_ke_pemulihan(): void
    {
        // Reproduksi EKSAK jalur yang dipakai user manual test: tombol "Bayar"
        // di halaman Invoice/Tagihan (`PaymentController::store()` →
        // `PaymentService::record()` → `Invoice::recalculateFromPayments()`),
        // BUKAN `Payment::create()` langsung seperti helper `payInFull()` di
        // atas — supaya perbaikan bug benar-benar terverifikasi lewat jalur
        // yang sama dengan yang dipakai di produksi.
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $invoice = $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $response = $this->actingAs($this->adminUser)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => (string) $invoice->remaining_amount,
        ]);
        $response->assertRedirect(route('invoices.show', $invoice->id));

        $invoice->refresh();
        $this->assertSame('lunas', $invoice->invoice_status->value);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::RESTORATION_PENDING, $case->status);
        $this->assertSame(0.0, (float) $case->current_outstanding_amount);
        $this->assertEquals('suspended', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function aktifkan_kembali_manual_meski_belum_lunas_tercatat_sebagai_override(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08'); // TIDAK dibayar
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $this->actingAs($this->adminUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Dipulihkan manual oleh atasan'])
            ->assertOk();

        $customer->refresh();
        $this->assertEquals('active', $customer->status);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::COMPLETED, $case->status);

        $this->assertDatabaseHas('isolation_events', [
            'isolation_case_id' => $case->id,
            'event_type' => 'restoration_succeeded',
        ]);
        $event = $case->events()->where('event_type', 'restoration_succeeded')->first();
        $this->assertStringContainsString('belum lunas', (string) $event->description);

        $this->travelBack();
    }

    #[Test]
    public function tagihan_baru_numpuk_saat_restoration_pending_membalik_kasus_ke_suspended(): void
    {
        // Regresi bug (2026-11-20): `restoration_pending` artinya "sudah
        // lunas, nunggu NOC" — tapi billing tetap jalan walau layanan masih
        // suspended, jadi tagihan BARU bisa numpuk & lewat jatuh tempo lagi
        // sebelum NOC sempat konfirmasi. Dulu kasus diam di
        // `restoration_pending` (label "Menunggu Pemulihan") walau
        // `current_outstanding_amount` sudah > 0 lagi — NOC bisa salah
        // aktifkan pelanggan yang sebenarnya nunggak lagi.
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $invoiceLama = $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $this->actingAs($this->nocUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir'])
            ->assertOk();

        $this->payInFull($invoiceLama);

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        $this->assertSame(IsolationCaseStatus::RESTORATION_PENDING, $case->status);

        // Tagihan periode BARU, numpuk & jatuh tempo lagi sementara layanan
        // masih suspended (NOC belum sempat konfirmasi).
        $invoiceBaru = $this->createOverdueInvoice($customer, '2026-09');

        // Pembayaran SEBAGIAN ke tagihan baru ini — cukup buat memicu
        // `InvoiceStatusUpdated` (satu-satunya pemicu `handlePaymentVerified()`),
        // tapi TIDAK melunasi, jadi `current_outstanding_amount` kasus
        // seharusnya balik > 0.
        Payment::create([
            'payment_number' => 'PAY-'.$invoiceBaru->invoice_number,
            'invoice_id' => $invoiceBaru->id,
            'customer_id' => $invoiceBaru->customer_id,
            'pop_id' => $invoiceBaru->pop_id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => 20000,
            'received_by' => $this->adminUser->id,
            'payment_status' => 'valid',
        ]);
        $invoiceBaru->refresh()->recalculateFromPayments();

        $case->refresh();
        $this->assertSame(IsolationCaseStatus::SUSPENDED, $case->status);
        $this->assertGreaterThan(0.0, (float) $case->current_outstanding_amount);
        $this->assertEquals('suspended', $customer->refresh()->status);

        $this->assertDatabaseHas('isolation_events', [
            'isolation_case_id' => $case->id,
            'event_type' => 'restoration_reverted',
        ]);

        $this->travelBack();
    }

    #[Test]
    public function tracking_kasus_gagal_tidak_membatalkan_aksi_isolir_yang_sudah_terjadi(): void
    {
        // Regresi bug (2026-11-20): dulu pencatatan lifecycle kasus
        // (`markIsolationSucceeded()`) ikut SATU transaksi dengan aksi isolir
        // inti — exception di tracking bikin SELURUH aksi (termasuk
        // `Customer::status`) ikut rollback. Sekarang tracking dipanggil DI
        // LUAR transaksi itu: exception di sana cuma dicatat sebagai kasus
        // `isolation_failed`, aksi isolir yang sudah terjadi tetap berdiri.
        $customer = $this->createCustomer('active');

        $this->mock(IsolationCaseService::class, function ($mock) {
            $mock->shouldReceive('findActiveCaseForService')->andReturn(null);
            $mock->shouldReceive('markIsolationSucceeded')->andThrow(new \RuntimeException('simulasi gagal tracking'));
            $mock->shouldReceive('markTrackingFailed')->once();
        });

        $this->actingAs($this->adminUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), ['note' => 'Isolir langsung'])
            ->assertOk();

        // Aksi inti TETAP terjadi walau tracking-nya gagal.
        $this->assertEquals('suspended', $customer->refresh()->status);
    }

    #[Test]
    public function role_di_luar_eksekutor_ditolak(): void
    {
        $salesRole = Role::where('name', 'Sales')->firstOrFail();
        $salesUser = User::factory()->create(['role_id' => $salesRole->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $salesUser->id,
            'role_id' => $salesUser->role_id,
            'scope_type' => 'all_pop',
        ]);

        $customer = $this->createCustomer('active');

        $this->actingAs($salesUser)->get(route('customers.isolir-queue.index'))->assertStatus(403);
        $this->actingAs($salesUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'x'])
            ->assertStatus(403);
    }

    #[Test]
    public function user_tanpa_pop_scope_ditolak_menandai_pelanggan_pop_lain(): void
    {
        $otherPop = Pop::create([
            'name' => 'POP Siman',
            'code' => 'SMN-01',
            'pop_code' => 'SMN',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $restrictedCs = $this->makeRoleUser('Customer Service');
        UserRoleScope::where('user_id', $restrictedCs->id)->delete();
        $scope = UserRoleScope::create([
            'user_id' => $restrictedCs->id,
            'role_id' => $restrictedCs->role_id,
            'scope_type' => 'selected_pop',
        ]);
        $scope->targets()->create(['pop_id' => $otherPop->id]);

        $customer = $this->createCustomer('active'); // di $this->pop, BUKAN $otherPop

        $this->actingAs($restrictedCs)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'x'])
            ->assertStatus(403);
    }

    #[Test]
    public function halaman_menampilkan_kartu_kpi_dan_pencarian_menemukan_kasus(): void
    {
        $customer = $this->createCustomer('active');
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'uji KPI & pencarian'])
            ->assertOk();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();

        $response = $this->actingAs($this->adminUser)->get(route('customers.isolir-queue.index'));
        $response->assertOk();
        $response->assertSee('Menunggu Isolir');
        $response->assertSee($case->case_number);

        // Pencarian nama pelanggan menemukan kasus di tab yang sama.
        $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['q' => $customer->full_name]))
            ->assertOk()
            ->assertSee($case->case_number);

        // Pencarian yang tidak match tidak menampilkan kasus ini.
        $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['q' => 'TidakAdaPelangganIni']))
            ->assertOk()
            ->assertDontSee($case->case_number);
    }

    #[Test]
    public function sort_header_nama_pelanggan_urut_asc_dan_desc(): void
    {
        // Permintaan user 2026-11-20: header tabel bisa ASC/DESC
        // (CustomerIsolirQueueController::applySort()). `createCustomer()`
        // menghasilkan "Pelanggan Isolir 1" lalu "Pelanggan Isolir 2" — cukup
        // buat bedakan urutan tanpa perlu nama custom.
        $customerA = $this->createCustomer('active'); // "Pelanggan Isolir 1"
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customerA->id, 'note' => 'uji sort A'])
            ->assertOk();

        $customerB = $this->createCustomer('active'); // "Pelanggan Isolir 2"
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customerB->id, 'note' => 'uji sort B'])
            ->assertOk();

        $caseA = IsolationCase::where('service_id', $customerA->customerService->id)->first();
        $caseB = IsolationCase::where('service_id', $customerB->customerService->id)->first();

        $asc = $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['sort' => 'customer_name', 'dir' => 'asc']))
            ->assertOk()
            ->getContent();
        $this->assertGreaterThan(strpos($asc, $caseA->case_number), strpos($asc, $caseB->case_number));

        $desc = $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['sort' => 'customer_name', 'dir' => 'desc']))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(strpos($desc, $caseA->case_number), strpos($desc, $caseB->case_number));
    }

    #[Test]
    public function filter_pop_hanya_menampilkan_kasus_pop_tersebut(): void
    {
        $otherPop = Pop::create([
            'name' => 'POP Siman',
            'code' => 'SMN-01',
            'pop_code' => 'SMN',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $customerA = $this->createCustomer('active');
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customerA->id, 'note' => 'kasus POP A'])
            ->assertOk();
        $caseA = IsolationCase::where('service_id', $customerA->customerService->id)->first();

        $customerB = $this->createCustomer('active');
        $customerB->update(['pop_id' => $otherPop->id]);
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customerB->id, 'note' => 'kasus POP B'])
            ->assertOk();
        $caseB = IsolationCase::where('service_id', $customerB->customerService->id)->first();

        $response = $this->actingAs($this->adminUser)
            ->get(route('customers.isolir-queue.index', ['pop_id' => $otherPop->id]));

        $response->assertOk();
        $response->assertSee($caseB->case_number);
        $response->assertDontSee($caseA->case_number);
    }

    #[Test]
    public function tandai_manual_menyiarkan_perubahan_antrean_ke_pop_pelanggan(): void
    {
        Event::fake([IsolationCaseQueueChanged::class]);

        $customer = $this->createCustomer('active');

        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'uji broadcast'])
            ->assertOk();

        Event::assertDispatched(IsolationCaseQueueChanged::class, fn ($event) => $event->popId === $this->pop->id);
    }

    #[Test]
    public function batalkan_kasus_manual_menyiarkan_perubahan_antrean(): void
    {
        $customer = $this->createCustomer('active');
        $this->actingAs($this->customerServiceUser)
            ->postJson(route('customers.isolir-queue.store'), ['customer_id' => $customer->id, 'note' => 'uji broadcast batal'])
            ->assertOk();

        Event::fake([IsolationCaseQueueChanged::class]);

        $this->actingAs($this->nocUser)
            ->delete(route('customers.isolir-queue.destroy', $customer->customerService->id))
            ->assertRedirect();

        Event::assertDispatched(IsolationCaseQueueChanged::class, fn ($event) => $event->popId === $this->pop->id);
    }

    #[Test]
    public function pembayaran_lunas_sebelum_isolir_tida_k_menyiarkan_event_antrean_terpisah(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $invoice = $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        Event::fake([IsolationCaseQueueChanged::class]);

        // Pembatalan otomatis "paid_before_isolation" (actor null) sudah
        // tercakup broadcast InvoiceStatusUpdated bawaan pembayaran — tidak
        // perlu IsolationCaseQueueChanged kedua untuk kejadian yang sama.
        $this->payInFull($invoice);

        Event::assertNotDispatched(IsolationCaseQueueChanged::class);

        $this->travelBack();
    }

    #[Test]
    public function mark_isolation_succeeded_dengan_actor_null_tercatat_actor_type_custom(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $this->decideExecute($customer);

        $customer->update(['status' => 'suspended']);
        $customer->customerService->update(['service_status' => 'isolir']);

        app(IsolationCaseService::class)->markIsolationSucceeded(
            $customer->customerService,
            null,
            'external_web_a'
        );

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();

        $this->assertDatabaseHas('isolation_events', [
            'isolation_case_id' => $case->id,
            'event_type' => 'isolation_succeeded',
            'actor_type' => 'external_web_a',
            'actor_id' => null,
        ]);

        $this->travelBack();
    }
}
