<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BILLING-B0b — membetulkan `activation_date` peninggalan bug BILLING-B0.
 *
 * Kolom itu dulu diisi `registration_date` (tanggal DAFTAR) dan tidak pernah
 * ditimpa saat aktivasi, sehingga menentukan bulan yang salah untuk dilewati
 * tagihan bulanan.
 *
 * Urutan sumber yang dikunci di sini adalah keputusan bisnis 2026-07-21:
 * invoice AWAL → catatan pemasangan → jangan menebak.
 */
class BackfillActivationDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InternetPackageSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $serviceOverride
     */
    private function createPelanggan(string $activationDate = '2026-06-01', array $serviceOverride = []): Customer
    {
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::factory()->create([
            'status' => 'active',
            'customer_status' => 'aktif',
            'internet_package_id' => $package->id,
            'registration_date' => '2026-06-01',
        ]);

        CustomerService::create(array_merge([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 110000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 110000,
            // Warisan pendaftaran: tanggal DAFTAR, bukan tanggal menyala.
            'activation_date' => $activationDate,
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ], $serviceOverride));

        return $customer->fresh();
    }

    private function buatInvoiceAwal(Customer $customer, string $issueDate): Invoice
    {
        $service = $customer->customerService;

        return Invoice::create([
            'invoice_number' => 'INV-'.strtoupper(uniqid()),
            'invoice_type' => InvoiceType::AWAL->value,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => substr($issueDate, 0, 7),
            'issue_date' => $issueDate,
            'due_date' => $issueDate,
            'subtotal' => 35484,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 35484,
            'paid_amount' => 0,
            'remaining_amount' => 35484,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $customer = $this->createPelanggan();
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date')
            ->expectsOutputToContain('mode daftar saja')
            ->expectsOutputToContain('Akan diubah     : 1')
            ->assertExitCode(0);

        $this->assertSame(
            '2026-06-01',
            $customer->fresh()->customerService->activation_date->format('Y-m-d'),
            'Tanpa --force tidak boleh ada yang berubah.'
        );
    }

    public function test_force_menulis_tanggal_dari_invoice_awal(): void
    {
        $customer = $this->createPelanggan();
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date', ['--force' => true])
            ->expectsOutputToContain('MODE TULIS')
            ->assertExitCode(0);

        $this->assertSame('2026-07-21', $customer->fresh()->customerService->activation_date->format('Y-m-d'));
    }

    public function test_setiap_perubahan_masuk_audit_log(): void
    {
        $customer = $this->createPelanggan();
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date', ['--force' => true])->assertExitCode(0);

        $log = AuditLog::where('action', 'backfill_activation_date')->firstOrFail();

        $this->assertSame(CustomerService::class, $log->auditable_type);
        $this->assertSame('2026-06-01', $log->old_values['activation_date']);
        $this->assertSame('2026-07-21', $log->new_values['activation_date']);
        $this->assertSame('invoice AWAL', $log->new_values['sumber']);
    }

    public function test_jatuh_ke_catatan_pemasangan_kalau_tidak_ada_invoice_awal(): void
    {
        $customer = $this->createPelanggan();

        CustomerInstallation::create([
            'customer_id' => $customer->id,
            'installation_status' => 'completed',
            'finished_date' => '2026-07-19',
        ]);

        $this->artisan('billing:backfill-activation-date', ['--force' => true])
            ->expectsOutputToContain('pemasangan')
            ->assertExitCode(0);

        $this->assertSame('2026-07-19', $customer->fresh()->customerService->activation_date->format('Y-m-d'));
    }

    public function test_invoice_awal_menang_atas_catatan_pemasangan(): void
    {
        $customer = $this->createPelanggan();
        $this->buatInvoiceAwal($customer, '2026-07-21');

        CustomerInstallation::create([
            'customer_id' => $customer->id,
            'installation_status' => 'completed',
            'finished_date' => '2026-07-19',
        ]);

        $this->artisan('billing:backfill-activation-date', ['--force' => true])->assertExitCode(0);

        $this->assertSame('2026-07-21', $customer->fresh()->customerService->activation_date->format('Y-m-d'));
    }

    public function test_tanpa_sumber_tidak_ditebak_tapi_dilaporkan(): void
    {
        $customer = $this->createPelanggan();

        $this->artisan('billing:backfill-activation-date', ['--force' => true])
            ->expectsOutputToContain('REVIEW MANUAL')
            ->expectsOutputToContain('Perlu manual    : 1')
            ->assertExitCode(0);

        $this->assertSame(
            '2026-06-01',
            $customer->fresh()->customerService->activation_date->format('Y-m-d'),
            'Tanpa sumber, tanggal tidak boleh ditebak.'
        );
    }

    public function test_baris_legacy_tidak_disentuh(): void
    {
        $customer = $this->createPelanggan('2026-06-01', ['old_request_id' => 'RQ000247']);
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date', ['--force' => true])
            ->expectsOutputToContain('Tidak ada baris yang perlu dibetulkan')
            ->assertExitCode(0);

        // activation_date legacy berasal dari `finished_at` sistem lama —
        // memang sudah tanggal aktivasi, bukan placeholder pendaftaran.
        $this->assertSame('2026-06-01', $customer->fresh()->customerService->activation_date->format('Y-m-d'));
    }

    public function test_baris_yang_sudah_benar_dilewati(): void
    {
        $customer = $this->createPelanggan('2026-07-21');
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date', ['--force' => true])
            ->expectsOutputToContain('Sudah benar: 1')
            ->assertExitCode(0);

        $this->assertSame(0, AuditLog::where('action', 'backfill_activation_date')->count());
    }

    public function test_pelanggan_non_aktif_tidak_diproses(): void
    {
        $customer = $this->createPelanggan();
        $customer->update(['status' => 'terminated']);
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date', ['--force' => true])
            ->expectsOutputToContain('Tidak ada baris yang perlu dibetulkan')
            ->assertExitCode(0);

        $this->assertSame('2026-06-01', $customer->fresh()->customerService->activation_date->format('Y-m-d'));
    }

    public function test_limit_tidak_valid_ditolak(): void
    {
        $this->artisan('billing:backfill-activation-date', ['--limit' => 'banyak'])
            ->expectsOutputToContain('--limit harus bilangan bulat positif')
            ->assertExitCode(2);
    }

    public function test_setelah_backfill_bulan_aktivasi_tidak_dapat_tagihan_bulanan(): void
    {
        $customer = $this->createPelanggan();
        $this->buatInvoiceAwal($customer, '2026-07-21');

        $this->artisan('billing:backfill-activation-date', ['--force' => true])->assertExitCode(0);

        $this->travelTo('2026-07-25 07:00:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $this->assertSame(1, Invoice::where('customer_id', $customer->id)->count());
    }
}
