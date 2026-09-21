<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: `due_date` tanggal 10 hanya label UI. Tagihan baru jadi piutang
 * setelah pergantian bulan (pembukuan bulan lalu tutup), bukan sesudah
 * tanggal 10 lewat.
 */
class InvoicePiutangBatasBulanTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, string, bool}>
     */
    public static function skenario(): array
    {
        return [
            'lewat tgl 10 tapi masih bulan sama' => ['2026-09-25', '2026-09', 'belum_dibayar', false],
            'akhir bulan periode sama' => ['2026-09-30', '2026-09', 'sebagian', false],
            'ganti bulan belum dibayar' => ['2026-10-01', '2026-09', 'belum_dibayar', true],
            'ganti bulan sebagian' => ['2026-10-01', '2026-09', 'sebagian', true],
            'ganti bulan tapi sudah lunas' => ['2026-10-01', '2026-09', 'lunas', false],
            'ganti bulan tapi batal' => ['2026-10-01', '2026-09', 'batal', false],
            'lintas tahun' => ['2027-01-02', '2026-12', 'belum_dibayar', true],
        ];
    }

    #[Test]
    #[DataProvider('skenario')]
    public function piutang_ditentukan_periode_bukan_due_date(string $hariIni, string $periode, string $status, bool $harapan): void
    {
        $this->travelTo(now()->parse($hariIni));

        $invoice = new Invoice([
            'billing_period' => $periode,
            'due_date' => $periode.'-10',
            'invoice_status' => $status,
        ]);

        $this->assertSame($harapan, $invoice->isPiutang());
    }

    #[Test]
    public function scope_piutang_konsisten_dengan_is_piutang(): void
    {
        $this->seed(DatabaseSeeder::class);
        $package = InternetPackage::query()->firstOrFail();
        $customer = Customer::factory()->create(['internet_package_id' => $package->id]);
        $service = CustomerService::create([
            'customer_id' => $customer->id,
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

        $this->travelTo(now()->parse('2026-09-25'));

        $buat = fn (string $periode, InvoiceStatus $status, string $suffix) => Invoice::create([
            'invoice_number' => 'INV-PTG-'.$suffix,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => $status->value,
        ]);

        $berjalan = $buat('2026-09', InvoiceStatus::BELUM_DIBAYAR, 'A');
        $lalu = $buat('2026-08', InvoiceStatus::BELUM_DIBAYAR, 'B');
        $laluLunas = $buat('2026-07', InvoiceStatus::LUNAS, 'C');

        $ids = Invoice::query()->piutang()->pluck('id')->all();

        $this->assertSame([$lalu->id], $ids);
        $this->assertFalse($berjalan->isPiutang());
        $this->assertTrue($lalu->isPiutang());
        $this->assertFalse($laluLunas->isPiutang());
    }
}
