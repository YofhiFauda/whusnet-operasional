<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\VisitResult;
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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visit Log (Fase 3) — catatan kunjungan yang TIDAK menghasilkan uang.
 *
 * Lubang yang ditutup fitur ini (§12): kolektor menerima uang lalu tidak
 * melaporkannya sama sekali. Setoran tak bisa menangkapnya — tak ada baris,
 * tak ada selisih, semua tampak beres. Yang dijaga di sini:
 *
 *   1. kunjungan gagal WAJIB bisa & harus dicatat, supaya "tidak ada baris"
 *      tidak lagi ambigu antara "belum didatangi" dan "uangnya raib";
 *   2. hasil `bayar` TIDAK boleh diketik manual — kalau boleh, kolektor bisa
 *      memalsukan aktivitas tanpa uang masuk;
 *   3. satu kunjungan = satu baris per pintu per hari, supaya laporan aging
 *      tidak bohong ke arah sebaliknya.
 */
class CollectorVisitLogTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->createPop('VIS1');
        $this->kolektor = $this->createUser('kolektor', $this->pop);
        $this->admin = $this->createUser('pop_admin', $this->pop);
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

    private function createUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        $user->pops()->attach($pop->id);

        return $user;
    }

    private function createCustomerWithInvoice(Pop $pop, string $code, ?int $collectorId, float $total = 150000): Customer
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

        Invoice::create([
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

        return $customer;
    }

    // ================= INPUT MANUAL =================

    public function test_kolektor_logs_unproductive_visit(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-A', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
            'note' => 'Rumah kosong.',
        ])->assertRedirect(route('collector-worklist.index'));

        $visit = CollectorVisit::query()->firstOrFail();
        $this->assertSame(VisitResult::TIDAK_ADA_ORANG, $visit->result);
        $this->assertSame($this->kolektor->id, (int) $visit->collector_id);
        $this->assertSame($this->pop->id, (int) $visit->pop_id);
        $this->assertNull($visit->payment_id);
    }

    public function test_janji_bayar_requires_promised_date(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-B', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::JANJI_BAYAR->value,
        ])->assertSessionHasErrors('visit');

        $this->assertDatabaseCount('collector_visits', 0);
    }

    public function test_promised_date_is_dropped_for_non_promise_results(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-C', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::MENOLAK->value,
            'promised_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect();

        // Tanggal janji pada hasil "menolak" tak bermakna — kalau disimpan,
        // laporan "janji jatuh tempo" memungut baris yang bukan janji.
        $this->assertNull(CollectorVisit::query()->firstOrFail()->promised_date);
    }

    /**
     * Inti kontrolnya. Kalau `bayar` boleh diketik, kolektor yang mengantongi
     * uang tinggal mencatat "bayar" tanpa payment — dan tabel ini berubah dari
     * alat pengungkap jadi alat penutup.
     */
    public function test_bayar_cannot_be_logged_manually(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-D', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::BAYAR->value,
        ])->assertSessionHasErrors('result');

        $this->assertDatabaseCount('collector_visits', 0);
    }

    public function test_kolektor_cannot_log_visit_for_someone_elses_customer(): void
    {
        $lain = $this->createUser('kolektor', $this->pop);
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-E', $lain->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
        ])->assertSessionHasErrors('visit');

        $this->assertDatabaseCount('collector_visits', 0);
    }

    public function test_kolektor_cannot_log_visit_outside_own_pop_scope(): void
    {
        $popLuar = $this->createPop('VIS2');
        $customer = $this->createCustomerWithInvoice($popLuar, 'C-VIS-F', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
        ])->assertSessionHasErrors('visit');

        $this->assertDatabaseCount('collector_visits', 0);
    }

    public function test_future_visit_date_is_rejected(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-G', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
            'visited_at' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('visited_at');
    }

    // ================= TURUNAN PEMBAYARAN =================

    public function test_payment_automatically_records_a_paid_visit(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-H', $this->kolektor->id);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'vis-pay-1',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
            ],
        ])->assertOk();

        $visit = CollectorVisit::query()->firstOrFail();
        $this->assertSame(VisitResult::BAYAR, $visit->result);
        $this->assertNotNull($visit->payment_id);
        $this->assertSame($customer->id, (int) $visit->customer_id);
    }

    /**
     * Satu pelanggan melunasi 3 tagihan sekaligus = SATU kunjungan, bukan tiga.
     * Kalau tiap payment bikin baris sendiri, "total kunjungan" di laporan
     * aging membengkak dan pola aslinya tertutup.
     */
    public function test_paying_several_invoices_of_one_customer_creates_a_single_visit(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-I', $this->kolektor->id);
        $service = CustomerService::where('customer_id', $customer->id)->firstOrFail();

        foreach (['07', '08'] as $i => $month) {
            Invoice::create([
                'invoice_number' => 'INV-C-VIS-I-'.$month,
                'invoice_type' => 'bulanan',
                'customer_id' => $customer->id,
                'pop_id' => $this->pop->id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $this->package->id,
                'billing_period' => '2026-'.$month,
                'issue_date' => '2026-'.$month.'-01',
                'due_date' => '2026-'.$month.'-15',
                'subtotal' => 150000,
                'discount' => 0,
                'ppn' => 0,
                'total_amount' => 150000,
                'paid_amount' => 0,
                'remaining_amount' => 150000,
                'invoice_status' => 'belum_dibayar',
            ]);
        }

        $rows = Invoice::where('customer_id', $customer->id)->get()
            ->map(fn ($inv) => [
                'invoice_id' => $inv->id,
                'amount' => 150000,
                'payment_method' => 'cash',
                'collected_date' => now()->toDateString(),
            ])->all();

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'vis-pay-2',
            'rows' => $rows,
        ])->assertOk();

        $this->assertSame(3, Payment::count());
        $this->assertSame(1, CollectorVisit::where('customer_id', $customer->id)->count());
    }

    /**
     * Kolektor mencatat "tidak ada orang" pagi hari, lalu balik lagi sore dan
     * berhasil menagih. Yang berlaku adalah hasil akhir kunjungan hari itu.
     */
    public function test_paying_later_the_same_day_overwrites_the_unproductive_visit(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-J', $this->kolektor->id);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
        ]);

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'vis-pay-3',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
            ],
        ])->assertOk();

        $this->assertSame(1, CollectorVisit::where('customer_id', $customer->id)->count());
        $this->assertSame(VisitResult::BAYAR, CollectorVisit::query()->firstOrFail()->result);
    }

    /**
     * Regresi (review Fase 1–3 temuan #3): larangan "bayar tidak bisa diinput
     * manual" dulu cuma separuh. Kolektor memang tak bisa MEMBUAT-nya, tapi
     * bisa MENGHAPUS-nya: tagih pagi (tercatat `bayar`), lalu kirim "tidak ada
     * orang" sore hari untuk pelanggan yang sama → baris ditimpa dan
     * `payment_id` tertinggal, jadi riwayat menampilkan
     * "Tidak Ada Orang • PAY-000123" dan aging menghitungnya kunjungan gagal.
     *
     * Justru jejak itulah yang paling ingin dihapus orang yang mengantongi uang.
     */
    public function test_manual_visit_cannot_overwrite_a_paid_visit(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-LOCK', $this->kolektor->id);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'vis-lock-1',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
            ],
        ])->assertOk();

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
            'note' => 'Coba hapus jejak.',
        ])->assertSessionHasErrors('visit');

        $visit = CollectorVisit::query()->firstOrFail();
        $this->assertSame(VisitResult::BAYAR, $visit->result);
        $this->assertNotNull($visit->payment_id);
        $this->assertNull($visit->note);
    }

    /**
     * Arah sebaliknya: catatan manual pagi ("rumah kosong") tak boleh
     * tertinggal menempel di baris yang sore harinya menjadi `bayar`.
     */
    public function test_paid_visit_clears_stale_manual_note(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-NOTE', $this->kolektor->id);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
            'note' => 'Rumah kosong pagi tadi.',
        ])->assertRedirect();

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'vis-lock-2',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
            ],
        ])->assertOk();

        $visit = CollectorVisit::query()->firstOrFail();
        $this->assertSame(VisitResult::BAYAR, $visit->result);
        $this->assertNull($visit->note);
        $this->assertNotNull($visit->payment_id);
    }

    /**
     * Kunjungan gagal SUSULAN untuk hari yang berbeda tetap boleh — yang
     * dikunci hanya menimpa hari yang sudah berakhir dengan pembayaran.
     */
    public function test_unproductive_visit_on_another_day_is_still_allowed(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-OTHERDAY', $this->kolektor->id);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'vis-lock-3',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
            ],
        ])->assertOk();

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
            'visited_at' => now()->subDay()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, CollectorVisit::where('customer_id', $customer->id)->count());
    }

    // ================= LAPORAN AGING =================

    public function test_aging_report_surfaces_repeatedly_unproductive_customers(): void
    {
        $sering = $this->createCustomerWithInvoice($this->pop, 'C-VIS-SERING', $this->kolektor->id);
        $jarang = $this->createCustomerWithInvoice($this->pop, 'C-VIS-JARANG', $this->kolektor->id);

        foreach ([3, 2, 1] as $daysAgo) {
            $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
                'customer_id' => $sering->id,
                'result' => VisitResult::TIDAK_ADA_ORANG->value,
                'visited_at' => now()->subDays($daysAgo)->toDateString(),
            ]);
        }

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $jarang->id,
            'result' => VisitResult::MENOLAK->value,
        ]);

        $response = $this->actingAs($this->admin)->get(route('collector-worksheet.show', [
            'collector' => $this->kolektor->id,
            'tab' => 'kunjungan',
        ]));

        $response->assertOk();
        $response->assertSee('Pelanggan C-VIS-SERING');
        $response->assertSee('Aging Pelanggan Tertunggak');
        // Yang paling sering gagal harus di atas — itu inti laporannya.
        $response->assertSeeInOrder(['Pelanggan C-VIS-SERING', 'Pelanggan C-VIS-JARANG']);
    }

    /**
     * Sejak perbaikan review #1, admin luar POP ditolak DI GERBANG (403), bukan
     * dibiarkan masuk lalu datanya disaring. Lebih benar: halaman ini
     * menyajikan angka total, dan total yang diam-diam disaring berbohong.
     */
    public function test_admin_from_other_pop_cannot_open_visit_tab_at_all(): void
    {
        $customer = $this->createCustomerWithInvoice($this->pop, 'C-VIS-K', $this->kolektor->id);

        $this->actingAs($this->kolektor)->post(route('collector-worklist.visits.store'), [
            'customer_id' => $customer->id,
            'result' => VisitResult::TIDAK_ADA_ORANG->value,
            'note' => 'Catatan rahasia cabang lain.',
        ]);

        $popLuar = $this->createPop('VIS3');
        $adminLuar = $this->createUser('pop_admin', $popLuar);

        // Buang flash message milik request kolektor tadi ("Kunjungan ke
        // Pelanggan … tercatat"). Session dibawa antar-request dalam satu
        // test, jadi tanpa ini nama pelanggan muncul lewat notifikasi sukses —
        // bukan lewat data — dan assertion di bawah salah menuduh.
        $this->flushSession();

        $response = $this->actingAs($adminLuar)->get(route('collector-worksheet.show', [
            'collector' => $this->kolektor->id,
            'tab' => 'kunjungan',
        ]));

        $response->assertForbidden();
    }

    public function test_kolektor_role_has_visit_permission_but_not_worksheet(): void
    {
        $this->assertTrue($this->kolektor->hasPermission('kolektor.visit'));
        $this->assertFalse($this->kolektor->hasPermission('collector_worksheet.view'));
    }
}
