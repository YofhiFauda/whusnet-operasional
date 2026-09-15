<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Dashboard Owner Fase 5 (gap analisa 2026-09-10): risiko "kurang setor"
 * SATU TINGKAT DI BAWAH admin — kolektor ke admin, bukan admin ke Owner
 * (yang sudah ada di `DashboardFinancialCashGrowthTest`).
 */
class DashboardCollectorShortfallTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('DSH5');
    }

    /**
     * `DepositStatus::SELISIH` SENGAJA bukan status terminal (uang masih
     * jadi kewajiban kolektor) — nominalnya pakai `outstandingShortfall()`,
     * bukan `difference` mentah, karena sisa kewajiban bisa berkurang lewat
     * setoran susulan (`settled_amount`), meski di sini belum ada susulan.
     */
    public function test_kurang_setor_kolektor_dihitung_dari_outstanding_shortfall(): void
    {
        $this->collect('DSH5-A', 100000);
        // Fisik cuma 80rb dari yang seharusnya 100rb — kurang setor 20rb.
        $this->setorDanVerifikasi(80000, 'Fisik kurang 20rb saat cross check.');

        $stats = $this->actingAs($this->owner())->get('/')->viewData('stats');

        $this->assertSame(1, $stats['collector_shortfall_count']);
        $this->assertSame(20000.0, $stats['collector_shortfall_amount']);
        $this->actingAs($this->owner())->get('/')
            ->assertSee('1 kurang setor kolektor')
            ->assertSee('Kurang Setor Kolektor');
    }

    /**
     * Setoran yang fisiknya PAS (tanpa selisih) bukan risiko — jangan ikut
     * kehitung sama sekali.
     */
    public function test_setoran_pas_tidak_dianggap_kurang_setor(): void
    {
        $this->collect('DSH5-B', 50000);
        $this->setorDanVerifikasi(50000);

        $stats = $this->actingAs($this->owner())->get('/')->viewData('stats');

        $this->assertSame(0, $stats['collector_shortfall_count']);
        $this->assertSame(0.0, $stats['collector_shortfall_amount']);
    }
}
