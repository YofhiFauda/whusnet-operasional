<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\CashDeposit;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\Payment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Dashboard Owner Fase 1 (docs/plan/analisa-dashboard-owner-statistik.md
 * §6 Fase 1): Net Customer Growth, Collection Rate, Posisi Kas & Arus Kas.
 */
class DashboardFinancialCashGrowthTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('DSH1');
    }

    /**
     * Inti kenapa Net Growth HARUS baca `customer_status_logs`, bukan
     * `customers.created_at`: pelanggan ini didaftarkan lama sebelum periode
     * filter, tapi baru transisi ke `active` DI DALAM periode ini. Kalau
     * dashboard salah pakai `created_at`, pelanggan ini tak akan pernah
     * terhitung "aktif baru" — datanya sudah lama, padahal statusnya baru.
     */
    public function test_net_customer_growth_pakai_customer_status_logs_bukan_created_at(): void
    {
        $customerLamaBaruAktif = Customer::create([
            'full_name' => 'Pelanggan Growth',
            'customer_code' => 'C-DSH1-000001',
            'primary_phone' => '081200000001',
            'gender' => 'Laki-laki',
            'pop_id' => $this->pop->id,
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'registration_date' => '2020-01-01',
        ]);

        CustomerStatusLog::create([
            'customer_id' => $customerLamaBaruAktif->id,
            'from_status' => 'verification_admin',
            'to_status' => 'active',
            'changed_by' => $this->admin->id,
        ]);

        Customer::create([
            'full_name' => 'Pelanggan Putus',
            'customer_code' => 'C-DSH1-000002',
            'primary_phone' => '081200000002',
            'gender' => 'Laki-laki',
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'data_completeness_status' => 'siap_billing',
            'registration_date' => '2020-01-01',
            'terminated_at' => now(),
        ]);

        $stats = $this->actingAs($this->owner())->get('/')->viewData('stats');

        $this->assertSame(1, $stats['new_active_customers']);
        $this->assertSame(1, $stats['terminated_customers']);
        $this->assertSame(0, $stats['net_customer_growth']);
    }

    public function test_collection_rate_dari_realisasi_kas_dibagi_omzet_tagihan(): void
    {
        $invoice = $this->createInvoice($this->pop, 'DSH1-CR', 100000);

        Payment::create([
            'payment_number' => 'PAY-DSH1-CR',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $this->pop->id,
            // Samakan dengan billing_period invoice bawaan trait ('2026-06') —
            // periodPaymentQuery difilter dari payment_date, bukan billing_period.
            'payment_date' => '2026-06-05',
            'payment_method' => 'cash',
            'amount' => 60000,
            'received_by' => $this->admin->id,
            'payment_status' => PaymentStatus::VALID->value,
        ]);

        // Invoice bawaan trait selalu terbit periode '2026-06' — filter
        // harus eksplisit ke situ, jangan andalkan periode default (bulan
        // berjalan) yang bisa lewat dari bulan ini.
        $stats = $this->actingAs($this->owner())
            ->get('/?period_from=2026-06&period_to=2026-06')
            ->viewData('stats');

        $this->assertSame(60.0, $stats['collection_rate']);
    }

    /**
     * Guard pembagi nol: belum ada tagihan terbit periode ini bukan berarti
     * penagihan 0% — harus `null` (view merender "-"), bukan divide-by-zero.
     */
    public function test_collection_rate_null_saat_belum_ada_tagihan_terbit(): void
    {
        $stats = $this->actingAs($this->owner())
            ->get('/?period_from=2019-01&period_to=2019-01')
            ->viewData('stats');

        $this->assertNull($stats['collection_rate']);
    }

    public function test_owner_melihat_posisi_kas_dan_setoran_pending(): void
    {
        // Kas kasir POP: pembayaran manual yang belum ikut sesi setoran apa pun.
        $this->payAtOffice('DSH1-A', 50000);

        // Uang di tangan kolektor: sudah ditagih, belum disetor ke admin.
        $this->collect('DSH1-B', 30000);

        // Setoran menunggu verifikasi Owner.
        CashDeposit::create([
            'deposit_number' => 'TKAS-DSH1-0001',
            'depositor_id' => $this->admin->id,
            'pop_id' => $this->pop->id,
            'status' => 'menunggu_verifikasi',
            'declared_amount' => 70000,
        ]);

        // Selisih menggantung yang menuntut keputusan Owner.
        CashDeposit::create([
            'deposit_number' => 'TKAS-DSH1-0002',
            'depositor_id' => $this->admin->id,
            'pop_id' => $this->pop->id,
            'status' => 'selisih_kurang',
            'declared_amount' => 20000,
            'difference' => -5000,
        ]);

        $response = $this->actingAs($this->owner())->get('/');
        $stats = $response->viewData('stats');

        $this->assertSame(50000.0, $stats['cash_at_office_amount']);
        $this->assertSame(30000.0, $stats['cash_with_collector_amount']);
        $this->assertSame(1, $stats['cash_deposit_pending_count']);
        $this->assertSame(70000.0, $stats['cash_deposit_pending_amount']);
        $this->assertSame(1, $stats['cash_deposit_open_difference_count']);
        $response->assertSee('Posisi Keuangan & Arus Kas', false);
        $response->assertSee('1 selisih admin');
    }

    /**
     * `cash_deposit.view` = pandangan pemeriksa lintas-admin (Owner/Atasan).
     * Admin penyetor sendiri TIDAK boleh melihat blok kas di dashboard —
     * sama seperti dia tak boleh membuka `/cash-deposits` (lihat
     * `OwnerCashBalanceTest::test_halaman_penerimaan_tertutup_untuk_admin_penyetor`).
     */
    public function test_admin_tanpa_cash_deposit_view_tidak_melihat_blok_kas(): void
    {
        $response = $this->actingAs($this->admin->fresh())->get('/');

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertArrayNotHasKey('cash_at_office_amount', $stats);
        $response->assertDontSee('Posisi Keuangan & Arus Kas');
    }
}
