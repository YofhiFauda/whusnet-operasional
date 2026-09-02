<?php

namespace Tests\Feature;

use App\Enums\CashDepositStatus;
use App\Enums\PaymentStatus;
use App\Models\CashDeposit;
use App\Services\AdminCashBalanceService;
use App\Services\CashDepositService;
use App\Services\CollectorDepositService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Saldo Kas Admin — rumus §2 beserta SELURUH pengecualiannya.
 *
 * Yang diuji bukan cuma "angkanya jalan", tapi bahwa uang tidak bisa muncul
 * dua kali dan tidak bisa dihitung sebelum benar-benar berpindah tangan:
 * setoran yang masih di tas kolektor, pembayaran yang ditolak, uang non-tunai
 * yang tak pernah lewat tangan admin, dan kelebihan setor yang sudah
 * dikembalikan fisik.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §2.
 */
class AdminCashBalanceTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('KAS1');
    }

    // ================= RUMUS DASAR =================

    public function test_setoran_kolektor_yang_diverifikasi_masuk_saldo_admin_yang_memverifikasi(): void
    {
        $this->assertSame(0.0, $this->saldo());

        $this->collect('KAS-A', 100000);
        $this->collect('KAS-B', 250000);
        $this->setorDanVerifikasi(350000);

        $this->assertSame(350000.0, $this->saldo());
    }

    public function test_pembayaran_manual_tunai_di_kantor_ikut_saldo(): void
    {
        $this->payAtOffice('KAS-MAN', 75000);

        $this->assertSame(75000.0, $this->saldo());
    }

    /**
     * Inilah keluhan yang melahirkan modul ini: sebelum ada saldo kas, uang
     * berhenti di verifikasi setoran dan tak pernah jadi tanggung jawab siapa
     * pun. Saldo harus melekat ke ORANG yang menghitung uangnya, bukan ke POP.
     */
    public function test_saldo_melekat_ke_admin_yang_memverifikasi_bukan_admin_lain(): void
    {
        $adminLain = $this->createUser('pop_admin', $this->pop);

        $this->collect('KAS-C', 120000);
        $this->setorDanVerifikasi(120000);

        $this->assertSame(120000.0, $this->saldo());
        $this->assertSame(0.0, app(AdminCashBalanceService::class)->tunaiBelumDisetor($adminLain));
    }

    // ================= PENGECUALIAN =================

    public function test_setoran_yang_belum_diverifikasi_tidak_pernah_masuk_saldo_admin(): void
    {
        $this->collect('KAS-D', 200000);
        app(CollectorDepositService::class)->submit($this->kolektor->fresh());

        // Uangnya masih di tas kolektor — admin belum memegang apa pun.
        $this->assertSame(0.0, $this->saldo());
    }

    public function test_pembayaran_ditolak_tidak_masuk_saldo(): void
    {
        $payment = $this->payAtOffice('KAS-REJ', 90000);
        $payment->update(['payment_status' => PaymentStatus::DITOLAK->value]);

        $this->assertSame(0.0, $this->saldo());
    }

    public function test_pembayaran_non_tunai_tidak_masuk_saldo_tunai_tapi_muncul_di_rekap(): void
    {
        $this->payAtOffice('KAS-TF', 500000, 'transfer');
        $this->payAtOffice('KAS-QR', 250000, 'qris');

        $this->assertSame(0.0, $this->saldo());

        $rekap = app(AdminCashBalanceService::class)->nonTunaiRekap($this->admin->fresh());
        $this->assertSame(750000.0, $rekap['total']);
        $this->assertSame(500000.0, $rekap['per_metode']['transfer']);
        $this->assertSame(250000.0, $rekap['per_metode']['qris']);
    }

    /**
     * Lebih setor dikembalikan FISIK ke kolektor saat itu juga, jadi tak pernah
     * jadi kas kantor. Kalau ikut terhitung, admin diminta menyetorkan uang
     * yang sudah dia kembalikan.
     */
    public function test_kelebihan_setor_yang_dikembalikan_tidak_menambah_saldo_admin(): void
    {
        $this->collect('KAS-E', 100000);
        $this->setorDanVerifikasi(130000, 'Kolektor menyerahkan lebih 30rb, dikembalikan tunai.');

        $this->assertSame(100000.0, $this->saldo());
    }

    /**
     * Kurang setor: yang mendarat di kantor cuma uang fisik yang benar-benar
     * dihitung — sisanya urusan kolektor dengan kantor, bukan kas admin.
     */
    public function test_kurang_setor_hanya_menambah_uang_fisik_yang_benar_benar_diterima(): void
    {
        $this->collect('KAS-F', 350000);
        $this->setorDanVerifikasi(320000, 'Fisik kurang 30rb, kolektor menggantinya besok.');

        $this->assertSame(320000.0, $this->saldo());
    }

    /**
     * Pembayaran yang ditagih kolektor masuk kas lewat setoran kolektornya.
     * Kalau jalur manual ikut menghitungnya, uang yang sama muncul dua kali.
     */
    public function test_pembayaran_kolektor_tidak_terhitung_dua_kali_lewat_jalur_manual(): void
    {
        $this->collect('KAS-G', 150000);
        $this->setorDanVerifikasi(150000);

        // Pembayaran kolektor punya `collected_by` terisi; jalur manual
        // mensyaratkan `collected_by IS NULL` DAN `collector_deposit_id IS NULL`.
        $this->assertSame(150000.0, $this->saldo());
        $this->assertSame(0, app(AdminCashBalanceService::class)
            ->unsettledManualPaymentsQuery($this->admin->fresh())->count());
    }

    // ================= SESUDAH DISETOR =================

    public function test_saldo_kembali_nol_sesudah_admin_menyetorkan_kas(): void
    {
        $this->collect('KAS-H', 200000);
        $this->setorDanVerifikasi(200000);
        $this->payAtOffice('KAS-I', 50000);

        $this->assertSame(250000.0, $this->saldo());

        $this->actingAs($this->admin)
            ->post(route('cash-deposits.store'), ['channel' => 'tunai_brankas'])
            ->assertRedirect(route('cash-deposits.index'));

        $this->assertSame(0.0, $this->saldo());

        $kas = CashDeposit::query()->realDeposits()->firstOrFail();
        $this->assertSame(CashDepositStatus::MENUNGGU_VERIFIKASI, $kas->status);
        $this->assertSame(250000.0, $kas->computedAmount());
        $this->assertSame(1, $kas->collectorDeposits()->count());
        $this->assertSame(1, $kas->manualPayments()->count());
    }

    // ================= LETAK AKSI SETOR (§9) =================

    /**
     * Aksi setor hidup di Worksheet Admin — halaman kerja admin, tempat uang
     * kolektor berpindah tangan saat setoran diverifikasi. Sesudah menyetor,
     * admin harus mendarat kembali di sana, bukan terlempar ke halaman arsip.
     */
    public function test_setor_dari_worksheet_kembali_ke_worksheet(): void
    {
        $this->payAtOffice('POS-A', 100000);

        $this->actingAs($this->admin)
            ->post(route('cash-deposits.store'), ['channel' => 'tunai_brankas', 'redirect_to' => 'worksheet'])
            ->assertRedirect(route('collector-worksheet.index'));
    }

    public function test_setor_tanpa_penanda_tetap_ke_halaman_setoran_kas(): void
    {
        $this->payAtOffice('POS-B', 100000);

        $this->actingAs($this->admin)
            ->post(route('cash-deposits.store'), ['channel' => 'tunai_brankas'])
            ->assertRedirect(route('cash-deposits.index'));
    }

    /**
     * `redirect_to` adalah PENANDA, bukan URL. Nilai asing ditolak validasi —
     * URL redirect yang datang mentah dari klien adalah open-redirect, dan
     * halaman ini dipakai orang yang sedang memegang uang.
     */
    public function test_penanda_redirect_asing_ditolak(): void
    {
        $this->payAtOffice('POS-C', 100000);

        $this->actingAs($this->admin)
            ->post(route('cash-deposits.store'), [
                'channel' => 'tunai_brankas',
                'redirect_to' => 'https://contoh-jahat.example/phising',
            ])
            ->assertSessionHasErrors('redirect_to');

        // Uangnya tak boleh ikut tersetor saat penandanya ditolak.
        $this->assertSame(100000.0, $this->saldo());
    }

    public function test_tombol_setor_muncul_di_worksheet_admin_saat_ada_saldo(): void
    {
        $this->payAtOffice('POS-D', 100000);

        $this->actingAs($this->admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk()
            ->assertSee('Setorkan Kas')
            ->assertSee('1 sumber uang menunggu disetorkan');
    }

    /**
     * Saldo nol: tombol hilang, TAPI alasannya dikatakan. Tanpa kalimat itu,
     * admin yang berwenang tak bisa membedakan "belum ada uangnya" dari "hak
     * setor saya dicabut" — dan yang kedua bikin orang mengejar admin RBAC
     * untuk masalah yang bukan RBAC.
     */
    public function test_saldo_kosong_menjelaskan_dirinya_alih_alih_tombol_yang_hilang(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk();

        $response->assertDontSee('Setorkan Kas');
        $response->assertSee('Belum ada uang tunai yang perlu disetorkan.');
    }

    /**
     * Sebaliknya, user TANPA `cash_deposit.create` tidak melihat tombol maupun
     * penjelasannya — halaman kas bukan urusannya sama sekali.
     */
    public function test_user_tanpa_hak_setor_tidak_melihat_ajakan_menyetor(): void
    {
        $teknisi = $this->createUser('teknisi', $this->pop);

        $this->actingAs($teknisi)
            ->get(route('collector-worksheet.index'))
            ->assertForbidden();
    }

    /**
     * Halaman Setoran Kas berperan ARSIP & PEMERIKSAAN. Satu aksi, satu pintu —
     * dua jalur UI untuk aksi yang sama adalah cara tercepat keduanya menyimpang.
     */
    public function test_halaman_setoran_kas_tidak_lagi_memuat_form_setor(): void
    {
        $this->payAtOffice('POS-E', 100000);

        $this->actingAs($this->owner())
            ->get(route('cash-deposits.index'))
            ->assertOk()
            ->assertDontSee('Setorkan Seluruh Saldo');
    }

    // ================= DUA TINGKAT RINCIAN (§10) =================

    /**
     * Halaman `/cash-deposits` adalah pandangan PEMERIKSA — posisi kas admin
     * mana pun dalam scope, antrean pemeriksaan, rincian sampai tingkat
     * pelanggan. Admin yang cuma menyetor tak berkepentingan membacanya.
     */
    public function test_admin_penyetor_tidak_bisa_membuka_halaman_pemeriksa(): void
    {
        $this->assertFalse($this->admin->hasPermission('cash_deposit.view'));

        $this->actingAs($this->admin)
            ->get(route('cash-deposits.index'))
            ->assertForbidden();
    }

    /**
     * Gantinya: riwayat setorannya sendiri tersaji di Worksheet Admin, TANPA
     * nama pelanggan maupun nama kolektor — pertanyaan penyetor cuma "setoran
     * saya sudah diperiksa belum".
     */
    public function test_riwayat_penyetor_tampil_di_worksheet_tanpa_data_pelanggan(): void
    {
        $this->collect('RINC-A', 150000);
        $this->setorDanVerifikasi(150000);
        // Pelanggannya diberi kolektor supaya tidak muncul di panel "belum
        // di-assign" halaman ini — yang diuji di sini adalah isi RIWAYAT,
        // bukan panel lain yang kebetulan menyebut nama yang sama.
        $this->payAtOffice('RINC-B', 50000, 'cash', null, null, $this->kolektor);

        app(CashDepositService::class)->submit($this->admin->fresh(), ['channel' => 'tunai_brankas']);

        $response = $this->actingAs($this->admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk();

        $response->assertSee('Riwayat Setoran Kas Anda');
        $response->assertSee('SETKAS-'.now()->year.'-0001');
        $response->assertSee('Rp 200.000');
        // Nama pelanggan tak boleh bocor ke pandangan penyetor.
        $response->assertDontSee('Pelanggan RINC-A');
        $response->assertDontSee('Pelanggan RINC-B');
    }

    public function test_riwayat_penyetor_hanya_memuat_setoran_sendiri(): void
    {
        $adminLain = $this->createUser('pop_admin', $this->pop);
        $this->payAtOffice('RINC-C', 80000, 'cash', $adminLain);
        app(CashDepositService::class)->submit($adminLain->fresh(), ['channel' => 'tunai_brankas']);

        $this->actingAs($this->admin)
            ->get(route('collector-worksheet.index'))
            ->assertOk()
            ->assertDontSee('SETKAS-'.now()->year.'-0001');
    }

    /**
     * Penyetor tetap harus bisa mengambil kembali berkas yang dia unggah
     * sendiri — tapi rute itu tidak boleh berubah jadi jalan membaca bukti
     * setoran admin lain (nomor rekening tujuan yang bukan urusannya).
     */
    public function test_penyetor_hanya_bisa_mengunduh_bukti_setorannya_sendiri(): void
    {
        $adminLain = $this->createUser('pop_admin', $this->pop);
        $this->payAtOffice('RINC-D', 90000, 'cash', $adminLain);
        $milikOrangLain = app(CashDepositService::class)->submit($adminLain->fresh(), ['channel' => 'tunai_brankas']);
        $milikOrangLain->update(['proof_path' => 'cash-deposits/'.$adminLain->id.'/bukti.pdf']);

        $this->actingAs($this->admin)
            ->get(route('cash-deposits.download', $milikOrangLain->id))
            ->assertForbidden();
    }

    public function test_setor_ditolak_kalau_saldo_kosong(): void
    {
        $this->actingAs($this->admin)
            ->post(route('cash-deposits.store'), ['channel' => 'tunai_brankas'])
            ->assertRedirect()
            ->assertSessionHasErrors('cash_deposit');

        $this->assertSame(0, CashDeposit::query()->realDeposits()->count());
    }

    public function test_transfer_wajib_menyertakan_bank_dan_nomor_referensi(): void
    {
        $this->payAtOffice('KAS-J', 100000);

        $this->actingAs($this->admin)
            ->post(route('cash-deposits.store'), ['channel' => 'transfer_bank'])
            ->assertRedirect()
            ->assertSessionHasErrors('cash_deposit');

        // Saldo tak boleh ikut hangus saat setoran gagal.
        $this->assertSame(100000.0, $this->saldo());
    }
}
