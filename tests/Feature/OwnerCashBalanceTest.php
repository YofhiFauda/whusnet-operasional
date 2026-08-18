<?php

namespace Tests\Feature;

use App\Models\CashDeposit;
use App\Services\CashDepositService;
use App\Services\OwnerCashBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Ujung rantai uang: pelanggan → kolektor → admin → **owner/bank**.
 *
 * Yang dijaga di sini adalah hal yang dua kali terlewat di modul ini: uang
 * tidak boleh berhenti dicatat saat berpindah tangan. Saldo admin turun karena
 * uangnya diserahkan — dan seseorang harus menerimanya.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §11.
 */
class OwnerCashBalanceTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('KAS5');
    }

    private function ownerBalance(): OwnerCashBalanceService
    {
        return app(OwnerCashBalanceService::class);
    }

    /** Admin mengumpulkan uang lalu menyetorkannya. */
    private function setorkanKas(float $nominal, string $kode, string $channel = 'tunai_brankas'): CashDeposit
    {
        $this->payAtOffice($kode, $nominal, 'cash', null, null, $this->kolektor);

        return app(CashDepositService::class)->submit($this->admin->fresh(), [
            'channel' => $channel,
            'bank_name' => $channel === 'transfer_bank' ? 'BCA' : null,
            'reference_no' => $channel === 'transfer_bank' ? 'TRX-001' : null,
        ]);
    }

    // ================= PERPINDAHAN SALDO =================

    /**
     * Inti koreksi §11: sesudah Owner memeriksa, uang benar-benar PINDAH —
     * hilang dari admin, muncul di Owner. Sebelumnya ia hilang dari admin lalu
     * tidak muncul di mana pun.
     */
    public function test_saldo_pindah_dari_admin_ke_owner_sesudah_diperiksa(): void
    {
        $kas = $this->setorkanKas(300000, 'OWN-A');

        // Sudah lepas dari admin sejak disetorkan.
        $this->assertSame(0.0, $this->saldo());
        // Tapi belum jadi kas Owner — belum dihitung.
        $this->assertSame(0.0, $this->ownerBalance()->saldoBrankas($this->owner()));
        $this->assertSame(300000.0, $this->ownerBalance()->dalamPerjalanan($this->owner()));

        app(CashDepositService::class)->verify($kas, $this->owner(), 300000);

        $this->assertSame(300000.0, $this->ownerBalance()->saldoBrankas($this->owner()));
        $this->assertSame(0.0, $this->ownerBalance()->dalamPerjalanan($this->owner()));
        $this->assertSame(0.0, $this->saldo());
    }

    /**
     * Yang masuk brankas adalah uang yang DIHITUNG Owner, bukan yang diklaim
     * admin. Kurang setor otomatis terpantul tanpa penyesuaian tambahan.
     */
    public function test_brankas_mengikuti_uang_yang_dihitung_bukan_yang_diklaim(): void
    {
        $kas = $this->setorkanKas(300000, 'OWN-B');

        app(CashDepositService::class)->verify($kas, $this->owner(), 280000, 'Fisik kurang 20rb.');

        $this->assertSame(280000.0, $this->ownerBalance()->saldoBrankas($this->owner()));
    }

    /**
     * Kelebihan dikembalikan fisik saat itu juga — tak pernah mengendap di
     * brankas, sama seperti aturan di dua tingkat sebelumnya.
     */
    public function test_kelebihan_setor_tidak_mengendap_di_brankas(): void
    {
        $kas = $this->setorkanKas(300000, 'OWN-C');

        app(CashDepositService::class)->verify($kas, $this->owner(), 330000, 'Lebih 30rb, dikembalikan.');

        $this->assertSame(300000.0, $this->ownerBalance()->saldoBrankas($this->owner()));
    }

    /**
     * Transfer bank tak pernah lewat tangan Owner. Menjumlahkannya ke brankas
     * melahirkan "uang tunai" yang mustahil dihitung ulang di meja.
     */
    public function test_setoran_transfer_masuk_bank_bukan_brankas(): void
    {
        $kas = $this->setorkanKas(500000, 'OWN-D', 'transfer_bank');

        app(CashDepositService::class)->verify($kas, $this->owner(), 500000);

        $this->assertSame(0.0, $this->ownerBalance()->saldoBrankas($this->owner()));

        $bank = $this->ownerBalance()->masukBank($this->owner());
        $this->assertSame(500000.0, $bank['total']);
        $this->assertSame(500000.0, $bank['per_bank']['BCA']);
    }

    /**
     * Kas melekat ke orang yang MEMERIKSA, bukan ke role — cermin aturan yang
     * sama di tingkat admin.
     */
    public function test_kas_melekat_ke_pemeriksa_bukan_ke_pemeriksa_lain(): void
    {
        $atasan = $this->createUser('atasan', $this->pop);
        $kas = $this->setorkanKas(150000, 'OWN-E');

        app(CashDepositService::class)->verify($kas, $atasan, 150000);

        $this->assertSame(150000.0, $this->ownerBalance()->saldoBrankas($atasan));
        $this->assertSame(0.0, $this->ownerBalance()->saldoBrankas($this->owner()));
    }

    // ================= AKSES HALAMAN =================

    /**
     * `/cash-deposits` adalah worksheet PENERIMA. Admin penyetor tak boleh
     * membukanya sama sekali — halaman itu memuat posisi kas admin lain dan
     * rincian sumber sampai nama pelanggan.
     */
    public function test_halaman_penerimaan_tertutup_untuk_admin_penyetor(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cash-deposits.index'))
            ->assertForbidden();
    }

    /**
     * Inti permintaan Owner: uangnya dari kolektor mana, pelanggan siapa,
     * berapa. Seluruhnya turunan dari relasi setoran — tak satu pun angka di
     * halaman ini disimpan.
     */
    public function test_halaman_penerima_menampilkan_rincian_sumber_sampai_pelanggan(): void
    {
        // Uang dari kolektor: pelanggan menagih lewat jalur resmi.
        $this->collect('OWN-SRC', 175000);
        $this->setorDanVerifikasi(175000);
        // Uang dari loket.
        $this->payAtOffice('OWN-MAN', 25000, 'cash', null, null, $this->kolektor);

        app(CashDepositService::class)->submit($this->admin->fresh(), ['channel' => 'tunai_brankas']);

        $response = $this->actingAs($this->owner())
            ->get(route('cash-deposits.index'))
            ->assertOk();

        $response->assertSee('Setoran Masuk');
        $response->assertSee($this->admin->name);            // penyetornya
        $response->assertSee($this->kolektor->name);          // dari kolektor mana
        $response->assertSee('Pelanggan OWN-SRC');            // pelanggan siapa
        $response->assertSee('Pelanggan OWN-MAN');
        $response->assertSee('Rp 200.000');                   // totalnya berapa
    }

    /**
     * Halaman ini menampilkan setoran SELURUH admin dalam scope — bukan cuma
     * milik pembacanya. Itulah gunanya: satu tempat memeriksa semua uang masuk.
     */
    public function test_penerima_melihat_setoran_dari_semua_admin_dalam_scope(): void
    {
        $adminLain = $this->createUser('pop_admin', $this->pop);
        $adminLain->update(['name' => 'Admin Cabang Dua']);
        $this->payAtOffice('OWN-H', 60000, 'cash', $adminLain, null, $this->kolektor);
        app(CashDepositService::class)->submit($adminLain->fresh(), ['channel' => 'tunai_brankas']);

        $this->setorkanKas(40000, 'OWN-I');

        $this->actingAs($this->owner())
            ->get(route('cash-deposits.index'))
            ->assertOk()
            ->assertSee('Admin Cabang Dua')
            ->assertSee($this->admin->name);
    }

    public function test_penerima_melihat_kartu_kas_dirinya_sendiri(): void
    {
        $kas = $this->setorkanKas(220000, 'OWN-F');
        app(CashDepositService::class)->verify($kas, $this->owner(), 220000);

        $this->actingAs($this->owner())
            ->get(route('cash-deposits.index'))
            ->assertOk()
            ->assertSee('Kas Diterima')
            ->assertSee('Brankas (Tunai)')
            ->assertSee('Rp 220.000');
    }

    /**
     * Angka "dalam perjalanan" ikut POP scope pembacanya — atasan cabang lain
     * tak boleh melihat uang yang sedang dikirim di wilayah yang bukan
     * jangkauannya.
     */
    public function test_dalam_perjalanan_dibatasi_pop_scope_pembaca(): void
    {
        $this->setorkanKas(400000, 'OWN-G');

        $popLain = $this->createPop('KAS6');
        $atasanLain = $this->createUser('pop_admin', $popLain);

        $this->assertSame(400000.0, $this->ownerBalance()->dalamPerjalanan($this->owner()));
        $this->assertSame(0.0, $this->ownerBalance()->dalamPerjalanan($atasanLain));
    }

    /**
     * Saldo Owner turunan penuh: sentinel titik nol tak pernah ikut terhitung
     * meski `verified_by`-nya kosong dan statusnya di luar daftar.
     */
    public function test_sentinel_titik_nol_tidak_pernah_masuk_kas_owner(): void
    {
        $this->assertSame(0.0, $this->ownerBalance()->saldoBrankas($this->owner()));
        $this->assertSame(0.0, $this->ownerBalance()->dalamPerjalanan($this->owner()));
    }
}
