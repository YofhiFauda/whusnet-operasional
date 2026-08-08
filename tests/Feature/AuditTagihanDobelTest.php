<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jaring deteksi tagihan langganan dobel (BILLING-B0d).
 *
 * Command ini ada untuk kasus yang lolos dari dua lapis pencegahan — jalur
 * insert yang belum terpikir, atau data yang masuk sebelum guard dipasang.
 * Karena itu fixture di sini sengaja memakai `Invoice::withoutEvents()` untuk
 * menanam dobel: lewat jalur normal, InvoiceObserver sudah menolaknya.
 */
class AuditTagihanDobelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InternetPackageSeeder::class);
    }

    private function createPelanggan(string $nama = 'Rahmat Hidayat'): Customer
    {
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::factory()->create([
            'full_name' => $nama,
            'status' => 'active',
            'internet_package_id' => $package->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 110000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 110000,
            'activation_date' => '2026-05-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer->fresh();
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function tanamInvoice(Customer $customer, string $type, string $period, array $override = []): Invoice
    {
        $service = $customer->customerService;

        // withoutEvents: mensimulasikan baris yang sudah terlanjur ada sebelum
        // guard lintas-jenis dipasang. Lewat jalur normal ini akan ditolak.
        return Invoice::withoutEvents(fn () => Invoice::create(array_merge([
            'invoice_number' => 'INV-'.strtoupper(uniqid()),
            'invoice_type' => $type,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => $period.'-01',
            'due_date' => $period.'-10',
            'subtotal' => 110000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 110000,
            'paid_amount' => 0,
            'remaining_amount' => 110000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ], $override)));
    }

    public function test_database_bersih_melaporkan_tidak_ada_temuan(): void
    {
        $customer = $this->createPelanggan();
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07');

        $this->artisan('billing:audit-duplicate-invoices')
            ->expectsOutputToContain('Tidak ada temuan.')
            ->assertExitCode(0);
    }

    public function test_dobel_lintas_jenis_terdeteksi_dan_ditandai_perlu_cek(): void
    {
        $customer = $this->createPelanggan('Sri Wahyuni');
        $this->tanamInvoice($customer, InvoiceType::AWAL->value, '2026-07');
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07');

        $this->artisan('billing:audit-duplicate-invoices')
            ->expectsOutputToContain('PERLU CEK')
            ->expectsOutputToContain('Total grup dobel : 1')
            ->expectsOutputToContain('perlu dicek    : 1')
            ->assertExitCode(0);
    }

    public function test_grup_legacy_dipisahkan_dari_temuan_baru(): void
    {
        $customer = $this->createPelanggan();
        $this->tanamInvoice($customer, InvoiceType::AWAL->value, '2026-07', ['old_invoice_id' => 'IDBIAYA001-AWAL']);
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07', ['old_invoice_id' => 'IDBIAYA001-BULANAN']);

        $this->artisan('billing:audit-duplicate-invoices')
            ->expectsOutputToContain('warisan legacy : 1')
            ->expectsOutputToContain('perlu dicek    : 0')
            ->assertExitCode(0);
    }

    public function test_tagihan_batal_dan_reaktivasi_tidak_dihitung_dobel(): void
    {
        $customer = $this->createPelanggan();

        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07');
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07', [
            'invoice_status' => InvoiceStatus::BATAL->value,
        ]);
        $this->tanamInvoice($customer, InvoiceType::REAKTIVASI->value, '2026-07');

        $this->artisan('billing:audit-duplicate-invoices')
            ->expectsOutputToContain('Tidak ada temuan.')
            ->assertExitCode(0);
    }

    public function test_filter_periode(): void
    {
        $customer = $this->createPelanggan();
        $this->tanamInvoice($customer, InvoiceType::AWAL->value, '2026-07');
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07');

        $this->artisan('billing:audit-duplicate-invoices', ['--period' => '2026-08'])
            ->expectsOutputToContain('Tidak ada temuan.')
            ->assertExitCode(0);

        $this->artisan('billing:audit-duplicate-invoices', ['--period' => '2026-07'])
            ->expectsOutputToContain('Total grup dobel : 1')
            ->assertExitCode(0);
    }

    public function test_format_periode_salah_ditolak(): void
    {
        $this->artisan('billing:audit-duplicate-invoices', ['--period' => 'Juli 2026'])
            ->expectsOutputToContain('Format --period harus YYYY-MM')
            ->assertExitCode(2);
    }

    public function test_strict_keluar_dengan_exit_code_gagal_untuk_monitoring(): void
    {
        $customer = $this->createPelanggan();
        $this->tanamInvoice($customer, InvoiceType::AWAL->value, '2026-07');
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07');

        $this->artisan('billing:audit-duplicate-invoices', ['--strict' => true])
            ->assertExitCode(1);

        // Tanpa temuan, --strict tetap sukses.
        Invoice::query()->delete();

        $this->artisan('billing:audit-duplicate-invoices', ['--strict' => true])
            ->assertExitCode(0);
    }

    public function test_memperingatkan_kalau_tagihan_dobel_sudah_dibayar(): void
    {
        $customer = $this->createPelanggan();
        $this->tanamInvoice($customer, InvoiceType::AWAL->value, '2026-07');
        $this->tanamInvoice($customer, InvoiceType::BULANAN->value, '2026-07', [
            'paid_amount' => 110000,
            'remaining_amount' => 0,
            'invoice_status' => InvoiceStatus::LUNAS->value,
        ]);

        $this->artisan('billing:audit-duplicate-invoices')
            ->expectsOutputToContain('Sebagian tagihan dobel sudah dibayar')
            ->assertExitCode(0);
    }
}
