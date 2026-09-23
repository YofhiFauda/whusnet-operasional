<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\CollectorPaymentReportService;
use Database\Seeders\CollectorPaymentReportDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo Laporan Bayar Kolektor harus menghasilkan angka yang sama
 * dengan tabel manual "Bayar Wifi Cash" di docs/plan/billing.
 */
class CollectorPaymentReportDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function report(): array
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();

        return app(CollectorPaymentReportService::class)->build($owner, null, '2026-09-01', '2026-09-30', null);
    }

    #[Test]
    public function angka_laporan_sama_dengan_tabel_manual(): void
    {
        $this->seed(CollectorPaymentReportDemoSeeder::class);

        $report = $this->report();

        $this->assertSame(6, $report['groups']->count());
        $this->assertSame(46, $report['count']);
        // Kas Terkumpul dari tabel manual.
        $this->assertEquals(7685000, $report['total']);

        // Total Sub yang tercetak di spreadsheet.
        $subtotals = $report['groups']->pluck('subtotal')->map(fn ($v) => (int) $v)->all();
        $this->assertContains(2035000, $subtotals);
        $this->assertContains(1651000, $subtotals);
        $this->assertContains(1656000, $subtotals);
        $this->assertContains(110000, $subtotals);
    }

    #[Test]
    public function seeder_bisa_diulang_tanpa_menggandakan_data(): void
    {
        $this->seed(CollectorPaymentReportDemoSeeder::class);
        $this->seed(CollectorPaymentReportDemoSeeder::class);

        $this->assertSame(46, Payment::query()->where('payment_number', 'like', 'DEMO-PAY-%')->count());
        $this->assertEquals(7685000, $this->report()['total']);
        $this->assertSame(1, User::where('email', CollectorPaymentReportDemoSeeder::KOLEKTOR_EMAIL)->count());
    }

    #[Test]
    public function ditolak_di_production(): void
    {
        $this->app['env'] = 'production';

        // Dipanggil langsung: `db:seed` sendiri sudah minta konfirmasi di production.
        $this->expectException(RuntimeException::class);
        app(CollectorPaymentReportDemoSeeder::class)->run();
    }
}
