<?php

namespace Tests\Feature;

use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\Payment;
use App\Services\CollectorDepositService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Titik nol pencatatan kas.
 *
 * Masalah yang dijaga: uang yang sudah lama masuk bank di dunia nyata tidak
 * boleh muncul sebagai kewajiban setor di hari pertama modul ini hidup. Yang
 * menutupnya adalah SATU baris sentinel yang menyerap seluruh sumber lama —
 * bukan cutoff tanggal, yang menuntut syarat kedua di tiap query kas.
 *
 * Migrasinya dipanggil ulang di test ini persis seperti di produksi: file
 * migrasi yang sama, method `up()` yang sama. Itu sekaligus membuktikan
 * sifat idempotennya.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §7.
 */
class CashLedgerZeroPointTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('KAS4');
    }

    /** Jalankan ulang migrasi titik nol — persis file yang dipakai produksi. */
    private function jalankanTitikNol(): void
    {
        $migration = require database_path('migrations/2026_08_14_100003_seed_cash_ledger_zero_point.php');
        $migration->up();
    }

    private function sentinel(): CashDeposit
    {
        return CashDeposit::query()
            ->where('status', CashDepositStatus::SALDO_AWAL->value)
            ->firstOrFail();
    }

    public function test_sentinel_titik_nol_dibuat_migrasi_dan_tidak_mengklaim_uang_apa_pun(): void
    {
        $sentinel = $this->sentinel();

        $this->assertSame('SETKAS-0000-0000', $sentinel->deposit_number);
        $this->assertNull($sentinel->depositor_id);
        $this->assertSame(0.0, (float) $sentinel->declared_amount);
        $this->assertStringContainsString('Titik nol', (string) $sentinel->note);
    }

    public function test_uang_lama_diserap_sentinel_dan_tidak_jadi_kewajiban_setor(): void
    {
        // Uang "lama": setoran kolektor yang sudah diverifikasi + pembayaran
        // manual tunai, keduanya lahir sebelum modul kas dipakai.
        $this->collect('ZERO-A', 300000);
        $this->setorDanVerifikasi(300000);
        $this->payAtOffice('ZERO-B', 120000);

        $this->assertSame(420000.0, $this->saldo());

        $this->jalankanTitikNol();

        $this->assertSame(0.0, $this->saldo());

        $sentinelId = $this->sentinel()->id;
        $this->assertSame($sentinelId, CollectorDeposit::query()->firstOrFail()->cash_deposit_id);
        $this->assertSame($sentinelId, Payment::query()->whereNull('collected_by')->firstOrFail()->cash_deposit_id);
    }

    /**
     * Setoran yang uangnya MASIH di tas kolektor saat titik nol dipasang tidak
     * boleh ikut terserap: sesudah diverifikasi nanti ia benar-benar menjadi
     * saldo admin lewat jalur normal.
     */
    public function test_setoran_yang_belum_diverifikasi_tidak_terserap_dan_tetap_masuk_saldo_sesudahnya(): void
    {
        $this->collect('ZERO-C', 250000);
        app(CollectorDepositService::class)->submit($this->kolektor->fresh());

        $this->jalankanTitikNol();

        $setoran = CollectorDeposit::query()->firstOrFail();
        $this->assertNull($setoran->cash_deposit_id);

        app(CollectorDepositService::class)->verify($setoran, $this->admin->fresh(), 250000);

        $this->assertSame(250000.0, $this->saldo());
    }

    /**
     * Uang non-tunai tak pernah masuk saldo tunai, jadi menautkannya cuma akan
     * merusak rekap non-tunai historis.
     */
    public function test_pembayaran_non_tunai_tidak_ikut_diserap(): void
    {
        $transfer = $this->payAtOffice('ZERO-D', 400000, 'transfer');

        $this->jalankanTitikNol();

        $this->assertNull($transfer->fresh()->cash_deposit_id);
    }

    /**
     * Kewajiban kolektor jalur TERPISAH dari kas admin — menyerap setoran
     * berselisih tidak boleh ikut menghapus sisa kewajibannya.
     */
    public function test_kewajiban_kurang_setor_kolektor_tetap_hidup_setelah_terserap(): void
    {
        $this->collect('ZERO-E', 350000);
        $setoran = $this->setorDanVerifikasi(320000, 'Fisik kurang 30rb.');

        $this->jalankanTitikNol();

        $setoran->refresh();
        $this->assertNotNull($setoran->cash_deposit_id);
        $this->assertSame(30000.0, $setoran->outstandingShortfall());
    }

    public function test_migrasi_idempoten_dan_tidak_menggandakan_sentinel(): void
    {
        $this->collect('ZERO-F', 100000);
        $this->setorDanVerifikasi(100000);

        $this->jalankanTitikNol();
        $this->jalankanTitikNol();
        $this->jalankanTitikNol();

        $this->assertSame(1, CashDeposit::query()
            ->where('status', CashDepositStatus::SALDO_AWAL->value)
            ->count());
        $this->assertSame(0.0, $this->saldo());
    }

    /**
     * Sentinel bukan setoran: ia tak boleh pernah muncul sebagai baris di
     * daftar mana pun.
     */
    public function test_sentinel_tidak_pernah_muncul_di_daftar_setoran(): void
    {
        $this->assertSame(0, CashDeposit::query()->realDeposits()->count());

        $this->actingAs($this->owner())
            ->get(route('cash-deposits.index'))
            ->assertOk()
            ->assertDontSee('SETKAS-0000-0000');
    }
}
