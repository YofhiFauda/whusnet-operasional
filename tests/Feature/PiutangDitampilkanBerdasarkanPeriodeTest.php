<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi pemasangan aturan piutang di layar: `due_date` tanggal 10 cuma
 * label, piutang = tagihan belum lunas dari periode SEBELUM bulan berjalan.
 * InvoicePiutangBatasBulanTest membuktikan modelnya; test ini membuktikan
 * dashboard, list pelanggan, dan hub pelanggan benar-benar memakainya —
 * bukan lagi `due_date <= hari ini`.
 */
class PiutangDitampilkanBerdasarkanPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    private Invoice $tagihanBulanIni;

    private Invoice $tagihanBulanLalu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // 25 Sep: tagihan periode 2026-09 sudah lewat tanggal 10 tapi bulan
        // belum ganti → BUKAN piutang. Periode 2026-08 → piutang.
        $this->travelTo(now()->parse('2026-09-25 09:00:00'));

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $package = InternetPackage::query()->firstOrFail();
        $this->customer = Customer::factory()->create([
            'internet_package_id' => $package->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 100000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $buat = fn (string $periode, string $nomor, int $sisa) => Invoice::create([
            'invoice_number' => $nomor,
            'invoice_type' => 'bulanan',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => $sisa,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $sisa,
            'paid_amount' => 0,
            'remaining_amount' => $sisa,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);

        $this->tagihanBulanLalu = $buat('2026-08', 'INV-PTG-AGT', 70000);
        $this->tagihanBulanIni = $buat('2026-09', 'INV-PTG-SEP', 100000);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_dashboard_hanya_menghitung_tagihan_periode_lalu_sebagai_piutang(): void
    {
        $response = $this->actingAs($this->owner)->get('/');

        $this->assertSame(1, $response->viewData('stats')['due_invoices_count']);

        $ids = $response->viewData('dueInvoices')->pluck('id')->all();
        $this->assertSame([$this->tagihanBulanLalu->id], $ids);
    }

    public function test_dashboard_tidak_menganggap_tagihan_bulan_berjalan_piutang_walau_lewat_tanggal_10(): void
    {
        $this->tagihanBulanLalu->update(['invoice_status' => InvoiceStatus::LUNAS->value, 'remaining_amount' => 0]);

        $response = $this->actingAs($this->owner)->get('/');

        $this->assertSame(0, $response->viewData('stats')['due_invoices_count']);
        $this->assertCount(0, $response->viewData('dueInvoices'));
    }

    public function test_tagihan_bulan_berjalan_jadi_piutang_begitu_bulan_berganti(): void
    {
        $this->travelTo(now()->parse('2026-10-01 00:30:00'));

        $ids = $this->actingAs($this->owner)->get('/')->viewData('dueInvoices')->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$this->tagihanBulanLalu->id, $this->tagihanBulanIni->id])->sort()->values()->all(),
            $ids
        );
    }

    public function test_list_pelanggan_menghitung_piutang_bukan_lewat_due_date(): void
    {
        $response = $this->actingAs($this->owner)->get(route('customers.index'));

        $this->assertSame(1, $response->viewData('overdueCount'));
    }

    public function test_kartu_total_tunggakan_dashboard_hanya_menjumlah_piutang(): void
    {
        $stats = $this->actingAs($this->owner)->get('/')->viewData('stats');

        $this->assertEquals(70000, $stats['total_unpaid_amount']);
    }

    public function test_ringkasan_nunggak_halaman_tagihan_hanya_menjumlah_piutang(): void
    {
        $response = $this->actingAs($this->owner)->get(route('invoices.index'));

        $this->assertEquals(70000, $response->viewData('unpaidBulananTotal'));
        $this->assertEquals(0, $response->viewData('unpaidAwalTotal'));
    }

    public function test_laporan_tagihan_total_tunggakan_dan_toggle_hanya_piutang(): void
    {
        $response = $this->actingAs($this->owner)->get('/reports/invoices?show_tunggakan=1');

        $this->assertEquals(70000, $response->viewData('totalTunggakanSum'));
        $response->assertSee('INV-PTG-AGT');
        $response->assertDontSee('INV-PTG-SEP');

        // Tanpa toggle, kedua tagihan tampil, tapi kartu tunggakan tetap piutang saja.
        $tanpaToggle = $this->actingAs($this->owner)->get('/reports/invoices');

        $this->assertEquals(70000, $tanpaToggle->viewData('totalTunggakanSum'));
        $tanpaToggle->assertSee('INV-PTG-SEP');
    }

    public function test_hub_pelanggan_total_piutang_tidak_ikut_tagihan_bulan_berjalan(): void
    {
        $json = $this->actingAs($this->owner)
            ->getJson(route('customers.payment-info', $this->customer))
            ->assertOk()
            ->json();

        $this->assertEquals(70000, $json['total_piutang']);
    }
}
