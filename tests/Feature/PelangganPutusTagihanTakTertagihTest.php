<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\CollectorMonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

/**
 * Sisi manual ADHOC-105: gate utang di Langganan Lagi, tombol Kembalikan di
 * List Putus Langganan (satu-satu & borongan) yang tetap bisa dipakai setelah
 * periode hapus buku terkunci, dan laporan bulanan yang tidak bergeser.
 */
class PelangganPutusTagihanTakTertagihTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    private Pop $pop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->seedBase();

        $this->pop = $this->makePop('Cabang A');
        $this->owner = $this->ownerUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function terminatedCustomer(): Customer
    {
        $customer = $this->makeCustomer($this->pop);
        $customer->forceFill(['status' => 'terminated', 'terminated_at' => '2026-09-15 10:00:00'])->save();

        return $customer->refresh();
    }

    private function takTertagih(Customer $customer, string $period = '2026-08', float $total = 150000): Invoice
    {
        $invoice = $this->makeInvoice($this->pop, $period, $total, customer: $customer);
        $invoice->forceFill([
            'invoice_status' => InvoiceStatus::TAK_TERTAGIH->value,
            'written_off_at' => '2026-11-01 00:20:00',
            'written_off_amount' => $total,
            'write_off_reason' => 'Auto write-off: pelanggan putus, grace period habis (ADHOC-105)',
        ])->save();

        return $invoice;
    }

    /** @return array<string, array{0: string}> */
    public static function statusBelumLunas(): array
    {
        return [
            'belum_dibayar' => ['belum_dibayar'],
            'sebagian' => ['sebagian'],
            'tak_tertagih' => ['tak_tertagih'],
        ];
    }

    // ---------------------------------------------------------------- Gate reaktivasi

    #[Test]
    #[DataProvider('statusBelumLunas')]
    public function langganan_lagi_ditolak_selama_ada_tagihan_belum_lunas_di_cabang_alat_belum_diambil(string $status): void
    {
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $invoice->forceFill(['invoice_status' => $status])->save();

        $this->actingAs($this->owner)
            ->post(route('customers.reactivate', $customer))
            ->assertSessionHas('error');

        $this->assertSame('terminated', $customer->refresh()->status);
        $this->assertDatabaseMissing('customer_status_logs', ['customer_id' => $customer->id, 'to_status' => 'active']);
    }

    #[Test]
    #[DataProvider('statusBelumLunas')]
    public function langganan_lagi_ditolak_juga_di_cabang_alat_sudah_diambil(string $status): void
    {
        $customer = $this->terminatedCustomer();
        CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => CustomerDevice::LEGACY_DEVICE_TYPE,
            'device_retrieved_at' => now()->subWeek(),
        ]);
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $invoice->forceFill(['invoice_status' => $status])->save();

        $this->actingAs($this->owner)
            ->post(route('customers.reactivate', $customer))
            ->assertSessionHas('error');

        $this->assertSame('terminated', $customer->refresh()->status);
        // Gate jalan SEBELUM percabangan: flag alat tidak boleh ikut tercabut.
        $this->assertNotNull($customer->customerDevice->device_retrieved_at);
    }

    #[Test]
    public function langganan_lagi_lolos_begitu_semua_tagihan_lunas(): void
    {
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->makePayment($invoice, 150000, '2026-11-10');
        $batal = $this->makeInvoice($this->pop, '2026-07', 90000, customer: $customer);
        $batal->forceFill(['invoice_status' => 'batal'])->save();

        $this->actingAs($this->owner)
            ->post(route('customers.reactivate', $customer))
            ->assertSessionHas('success');

        $this->assertSame('active', $customer->refresh()->status);
    }

    // ---------------------------------------------------------------- Kembalikan (A2)

    #[Test]
    public function kembalikan_periode_terkunci_berhasil_jejak_dipertahankan_dan_bisa_dibayar(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet'])->assertSessionHasNoErrors();

        // Dua bulan kemudian: periode hapus buku (September) sudah lama terkunci.
        Carbon::setTestNow('2026-11-15 10:00:00');

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off.reverse', $invoice), ['redirect_to' => 'customers.terminated'])
            ->assertRedirect(route('customers.terminated'))
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->invoice_status);
        $this->assertNotNull($invoice->written_off_at);
        $this->assertNotNull($invoice->write_off_reversed_at);

        // Sudah kembali ke tab Tagihan → pembayaran diterima lagi.
        $this->actingAs($this->owner)
            ->post(route('invoices.payments.store', $invoice), [
                'amount' => 150000,
                'payment_date' => '2026-11-15',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatus::LUNAS, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function laporan_bulan_hapus_buku_tidak_bergeser_dan_pemulihan_dibukukan_di_bulan_terjadinya(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->actingAs($this->owner)->post(route('invoices.write-off.reverse', $invoice))->assertSessionHasNoErrors();

        $service = app(CollectorMonthlyReportService::class);
        $ambil = fn (string $period) => $service->figures($period, [$this->pop->id])[$this->pop->id]['piutang_lalu'];

        // September: hapus buku memang berlaku di bulan itu, pemulihan Oktober tak menggesernya.
        $sept = $ambil('2026-09');
        $this->assertEquals(150000, $sept['pembuka']);
        $this->assertEquals(150000, $sept['tak_tertagih']);
        $this->assertEquals(0, $sept['tak_tertagih_dipulihkan']);

        // Oktober: pembuka masih dianggap terhapus buku, pemulihannya jadi pengurang bulan ini.
        $okt = $ambil('2026-10');
        $this->assertEquals(0, $okt['pembuka']);
        $this->assertEquals(0, $okt['tak_tertagih']);
        $this->assertEquals(150000, $okt['tak_tertagih_dipulihkan']);

        // November: piutangnya kembali hidup sebagai pembuka.
        $nov = $ambil('2026-11');
        $this->assertEquals(150000, $nov['pembuka']);
        $this->assertEquals(0, $nov['tak_tertagih_dipulihkan']);

        // Rincian sel pemulihan menyebut invoice-nya.
        $rincian = $service->detail('2026-10', $this->pop->id, 'piutang_lalu', 'tak_tertagih_dipulihkan');
        $this->assertCount(1, $rincian);
        $this->assertSame($invoice->invoice_number, $rincian[0]['referensi']);
    }

    #[Test]
    public function kembalikan_di_periode_berjalan_tetap_mengosongkan_jejak_seperti_biasa(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        $this->actingAs($this->owner)->post(route('invoices.write-off.reverse', $invoice))->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertNull($invoice->written_off_at);
        // Penanda "pernah dikembalikan" tetap ada walau riwayat hapus buku dikosongkan.
        $this->assertNotNull($invoice->write_off_reversed_at);
    }

    #[Test]
    public function tagihan_yang_pernah_dikembalikan_tidak_dihapus_buku_lagi_oleh_job_otomatis_periode_terkunci(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        // Oktober: periode hapus buku (September) sudah terkunci, admin Kembalikan.
        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->actingAs($this->owner)->post(route('invoices.write-off.reverse', $invoice));

        // 1 November: masa tenggang pelanggan habis, tapi tagihan ini pernah dikembalikan.
        Carbon::setTestNow('2026-11-01 00:20:00');
        $this->artisan('billing:write-off-terminated')->assertSuccessful();

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function tagihan_yang_pernah_dikembalikan_tidak_dihapus_buku_lagi_oleh_job_otomatis_bulan_yang_sama(): void
    {
        // Job pertama menghapus buku tanggal 1 November; admin Kembalikan di
        // bulan yang sama (periode berjalan, jejak dikosongkan); job Desember
        // tidak boleh menghapus bukunya lagi.
        Carbon::setTestNow('2026-11-01 00:20:00');
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->artisan('billing:write-off-terminated')->assertSuccessful();
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->refresh()->invoice_status);

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->actingAs($this->owner)->post(route('invoices.write-off.reverse', $invoice));
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);

        Carbon::setTestNow('2026-12-01 00:20:00');
        $this->artisan('billing:write-off-terminated')->assertSuccessful();

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function kembalikan_semua_atomik_satu_gagal_semua_batal(): void
    {
        $customer = $this->terminatedCustomer();
        $satu = $this->takTertagih($customer, '2026-08');
        $dua = $this->takTertagih($customer, '2026-09', 250000);

        // Paksa invoice kedua ditolak di tengah transaksi. Penolakan seperti
        // ini praktis tak bisa terjadi lagi lewat data biasa, jadi disimulasikan
        // lewat event model; `$aktif` mematikan pendengar begitu tes selesai.
        $aktif = true;
        Invoice::updating(function (Invoice $invoice) use (&$aktif, $dua) {
            if ($aktif && $invoice->id === $dua->id) {
                throw ValidationException::withMessages(['reason' => 'Ditolak untuk uji atomik.']);
            }
        });

        try {
            $this->actingAs($this->owner)
                ->post(route('customers.write-off.reverse-all', $customer))
                ->assertRedirect(route('customers.terminated'))
                ->assertSessionHas('error', 'Ditolak untuk uji atomik.');
        } finally {
            $aktif = false;
        }

        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $satu->refresh()->invoice_status, 'Invoice pertama harus ikut batal.');
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $dua->refresh()->invoice_status);
    }

    #[Test]
    public function hapus_buku_ulang_setelah_dipulihkan_memulai_siklus_baru(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $customer = $this->terminatedCustomer();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->actingAs($this->owner)->post(route('invoices.write-off.reverse', $invoice));
        $this->assertNotNull($invoice->refresh()->write_off_reversed_at);

        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet lagi'])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->invoice_status);
        $this->assertNull($invoice->write_off_reversed_at, 'Siklus baru tidak boleh membawa jejak pemulihan siklus lama.');
        $this->assertSame('macet lagi', $invoice->write_off_reason);
    }

    // ---------------------------------------------------------------- Kembalikan Semua

    #[Test]
    public function kembalikan_semua_mengembalikan_seluruh_tagihan_tak_tertagih_pelanggan_itu_saja(): void
    {
        $customer = $this->terminatedCustomer();
        $satu = $this->takTertagih($customer, '2026-08');
        $dua = $this->takTertagih($customer, '2026-09', 250000);
        $lain = $this->takTertagih($this->terminatedCustomer(), '2026-08');

        $this->actingAs($this->owner)
            ->post(route('customers.write-off.reverse-all', $customer))
            ->assertRedirect(route('customers.terminated'))
            ->assertSessionHas('success');

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $satu->refresh()->invoice_status);
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $dua->refresh()->invoice_status);
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $lain->refresh()->invoice_status, 'Pelanggan lain tidak boleh ikut.');
    }

    #[Test]
    public function kembalikan_semua_tanpa_tagihan_tak_tertagih_memberi_pesan_error(): void
    {
        $customer = $this->terminatedCustomer();

        $this->actingAs($this->owner)
            ->post(route('customers.write-off.reverse-all', $customer))
            ->assertRedirect(route('customers.terminated'))
            ->assertSessionHas('error');
    }

    #[Test]
    public function kembalikan_semua_butuh_permission_invoices_approve(): void
    {
        $customer = $this->terminatedCustomer();
        $this->takTertagih($customer);
        $helpdesk = User::factory()->create([
            'role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($helpdesk)
            ->post(route('customers.write-off.reverse-all', $customer))
            ->assertForbidden();
    }

    #[Test]
    public function redirect_to_yang_tidak_dikenal_diabaikan_dan_kembali_ke_detail_tagihan(): void
    {
        $customer = $this->terminatedCustomer();
        $invoice = $this->takTertagih($customer);

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off.reverse', $invoice), ['redirect_to' => 'https://evil.example/phish'])
            ->assertRedirect(route('invoices.show', $invoice));
    }

    // ---------------------------------------------------------------- Tampilan

    #[Test]
    public function list_putus_langganan_menampilkan_kolom_tagihan_dengan_tombol_kembalikan_yang_dirender_server(): void
    {
        $customer = $this->terminatedCustomer();
        $satu = $this->takTertagih($customer, '2026-08');
        $this->takTertagih($customer, '2026-09', 250000);

        $response = $this->actingAs($this->owner)->get(route('customers.terminated'));

        $response->assertOk()
            ->assertSee('Tagihan Tak Tertagih')
            ->assertSee('2 tagihan')
            ->assertSee(route('invoices.write-off.reverse', $satu), false)
            ->assertSee(route('customers.write-off.reverse-all', $customer), false)
            ->assertSee('Kembalikan Semua');
    }

    #[Test]
    public function pelanggan_tanpa_tagihan_tak_tertagih_tidak_punya_dialog(): void
    {
        $this->terminatedCustomer();

        $this->actingAs($this->owner)
            ->get(route('customers.terminated'))
            ->assertOk()
            ->assertDontSee('Tagihan Tak Tertagih')
            ->assertDontSee('Kembalikan Semua');
    }

    #[Test]
    public function detail_tagihan_tak_tertagih_periode_terkunci_menampilkan_tombol_batalkan_bukan_pesan_terkunci(): void
    {
        $customer = $this->terminatedCustomer();
        $invoice = $this->takTertagih($customer);
        $invoice->forceFill(['written_off_at' => '2026-09-20 00:20:00'])->save();

        $this->actingAs($this->owner)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Batalkan Hapus Buku')
            ->assertDontSee('Periode sudah tutup buku — tidak bisa dibatalkan');
    }
}
