<?php

namespace Tests\Feature;

use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CashDepositService;
use App\Services\EffectiveAccessService;
use App\Services\RoleManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Pemeriksaan Setoran Kas: dua arah selisih, catatan wajib, pemeriksa ≠
 * penyetor, gerbang POP scope, dan penutupan selisih oleh Owner.
 *
 * Yang dijaga di sini adalah hal yang bikin cross check punya arti: kalau orang
 * yang sama boleh menyetor sekaligus memeriksa, seluruh modul ini cuma tanda
 * tangan di atas kertas sendiri.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.4, §5.
 */
class CashDepositVerificationTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('KAS2');
        $this->owner = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    /** Admin mengumpulkan saldo lalu menyetorkannya. */
    private function setorkanKas(float $nominal, string $kode): CashDeposit
    {
        $this->payAtOffice($kode, $nominal);

        return app(CashDepositService::class)->submit(
            $this->admin->fresh(),
            ['channel' => 'tunai_brankas'],
        );
    }

    // ================= HASIL PEMERIKSAAN =================

    public function test_nominal_cocok_menutup_setoran_sebagai_terverifikasi(): void
    {
        $kas = $this->setorkanKas(200000, 'VER-A');

        $this->actingAs($this->owner)
            ->post(route('cash-deposits.verify', $kas->id), ['declared_amount' => '200.000'])
            ->assertRedirect();

        $kas->refresh();
        $this->assertSame(CashDepositStatus::TERVERIFIKASI, $kas->status);
        $this->assertSame(0.0, (float) $kas->difference);
        $this->assertSame($this->owner->id, $kas->verified_by);
    }

    public function test_uang_kurang_ditandai_selisih_kurang(): void
    {
        $kas = $this->setorkanKas(200000, 'VER-B');

        $this->actingAs($this->owner)->post(route('cash-deposits.verify', $kas->id), [
            'declared_amount' => '180.000',
            'note' => 'Fisik kurang 20rb, admin mengakui salah hitung.',
        ])->assertRedirect();

        $kas->refresh();
        $this->assertSame(CashDepositStatus::SELISIH_KURANG, $kas->status);
        $this->assertSame(-20000.0, (float) $kas->difference);
    }

    /**
     * Lebih setor di kas admin BUKAN terminal — uangnya tetap di brankas dan
     * asalnya belum jelas, jadi ia harus tetap muncul sampai Owner menutupnya.
     */
    public function test_uang_lebih_ditandai_selisih_lebih_dan_tetap_terbuka(): void
    {
        $kas = $this->setorkanKas(200000, 'VER-C');

        $this->actingAs($this->owner)->post(route('cash-deposits.verify', $kas->id), [
            'declared_amount' => '230.000',
            'note' => 'Ada 30rb yang belum jelas asalnya.',
        ])->assertRedirect();

        $kas->refresh();
        $this->assertSame(CashDepositStatus::SELISIH_LEBIH, $kas->status);
        $this->assertTrue($kas->status->isOpenDifference());
    }

    public function test_selisih_tanpa_catatan_ditolak(): void
    {
        $kas = $this->setorkanKas(200000, 'VER-D');

        $this->actingAs($this->owner)
            ->post(route('cash-deposits.verify', $kas->id), ['declared_amount' => '150.000'])
            ->assertRedirect()
            ->assertSessionHasErrors('cash_deposit');

        $this->assertSame(CashDepositStatus::MENUNGGU_VERIFIKASI, $kas->fresh()->status);
    }

    public function test_setoran_yang_sudah_diperiksa_tidak_bisa_diperiksa_ulang(): void
    {
        $kas = $this->setorkanKas(100000, 'VER-E');

        app(CashDepositService::class)->verify($kas, $this->owner, 100000);

        $this->expectException(RuntimeException::class);
        app(CashDepositService::class)->verify($kas->fresh(), $this->owner, 90000, 'coba lagi');
    }

    // ================= GUARD =================

    public function test_admin_tidak_bisa_memeriksa_setorannya_sendiri(): void
    {
        $kas = $this->setorkanKas(100000, 'VER-F');

        $this->expectExceptionMessage('memeriksa setoran kas Anda sendiri');
        app(CashDepositService::class)->verify($kas, $this->admin->fresh(), 100000);
    }

    /**
     * Uang lintas POP: pemeriksa yang tak bisa melihat SELURUH sumber tak boleh
     * menutup setorannya. Kalau cukup mengecek satu kolom `pop_id`, atasan
     * cabang lain bisa menutup setoran yang isinya sebagian di luar wilayahnya.
     */
    public function test_pemeriksa_wajib_bisa_melihat_seluruh_sumber(): void
    {
        $popLain = $this->createPop('KAS3');
        $this->payAtOffice('VER-G1', 100000);
        $this->payAtOffice('VER-G2', 50000, 'cash', null, $popLain);

        $kas = app(CashDepositService::class)->submit($this->admin->fresh(), ['channel' => 'tunai_brankas']);

        // pop_admin yang cuma membawahi POP pertama.
        $atasanSempit = $this->createUser('pop_admin', $this->pop);

        $this->expectExceptionMessage('di luar scope Anda');
        app(CashDepositService::class)->verify($kas, $atasanSempit, 150000);
    }

    public function test_sentinel_titik_nol_tidak_bisa_diverifikasi_maupun_dihapus_buku(): void
    {
        $sentinel = CashDeposit::query()
            ->where('status', CashDepositStatus::SALDO_AWAL->value)
            ->firstOrFail();

        try {
            app(CashDepositService::class)->verify($sentinel, $this->owner, 0);
            $this->fail('Sentinel titik nol seharusnya menolak verifikasi.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('titik nol', $e->getMessage());
        }

        $this->expectExceptionMessage('titik nol');
        app(CashDepositService::class)->writeOff($sentinel, $this->owner, 'iseng');
    }

    // ================= PENUTUPAN SELISIH =================

    public function test_owner_menutup_selisih_kas(): void
    {
        $kas = $this->setorkanKas(200000, 'VER-H');
        app(CashDepositService::class)->verify($kas, $this->owner, 170000, 'Kurang 30rb.');

        $this->actingAs($this->owner)->post(route('cash-deposits.write-off', $kas->id), [
            'write_off_reason' => 'Kekurangan ditanggung kantor, admin sudah diperingatkan.',
        ])->assertRedirect();

        $kas->refresh();
        $this->assertSame(CashDepositStatus::DIHAPUS_BUKU, $kas->status);
        $this->assertSame($this->owner->id, $kas->written_off_by);
    }

    public function test_setoran_tanpa_selisih_tidak_bisa_dihapus_buku(): void
    {
        $kas = $this->setorkanKas(120000, 'VER-I');
        app(CashDepositService::class)->verify($kas, $this->owner, 120000);

        $this->expectExceptionMessage('yang berselisih');
        app(CashDepositService::class)->writeOff($kas->fresh(), $this->owner, 'tidak ada selisih');
    }

    /**
     * Audiens pemeriksa & daftar pemegang kas diturunkan dari PERMISSION, bukan
     * dari daftar role yang ditulis di kode. Role baru yang diberi kewenangan
     * lewat Role Matrix harus langsung ikut terhitung — kalau tidak, orangnya
     * berwenang tapi tak pernah dikabari, dan kewenangan yang tak terlihat sama
     * saja dengan tak ada.
     */
    public function test_audiens_pemeriksa_ikut_role_baru_dari_role_matrix(): void
    {
        $roleBaru = Role::create([
            'code' => 'supervisor_kas',
            'name' => 'Supervisor Kas',
            'is_system' => false,
        ]);
        $roleBaru->permissions()->attach(
            Permission::where('code', 'cash_deposit.validate')->firstOrFail()->id
        );

        $supervisor = User::factory()->create(['role_id' => $roleBaru->id, 'status' => 'active']);

        $audiens = app(EffectiveAccessService::class)
            ->usersWithPermission('cash_deposit.validate')
            ->pluck('id');

        $this->assertTrue($audiens->contains($supervisor->id));
        // Owner lolos lewat wildcard `*` yang tak pernah punya baris di
        // role_permissions — gampang terlewat kalau query cuma melihat pivot.
        $this->assertTrue($audiens->contains($this->owner->id));
        // pop_admin cuma menyetor, tidak memeriksa.
        $this->assertFalse($audiens->contains($this->admin->id));
    }

    /**
     * Auto-grant `view` di Role Matrix dikecualikan untuk fitur ini.
     *
     * Pada hampir semua fitur, mencentang aksi anak wajar ikut memberi hak
     * membuka halamannya. Di sini tidak: `cash_deposit.view` adalah pandangan
     * PEMERIKSA (kas admin mana pun, rincian sampai nama pelanggan), sementara
     * yang dicentang cuma hak MENYETOR. Tanpa pengecualian ini, satu centang
     * "Setor" diam-diam membatalkan pemisahan dua tingkat rincian (§10).
     */
    public function test_mencentang_hak_setor_tidak_ikut_memberi_pandangan_pemeriksa(): void
    {
        $role = Role::create([
            'code' => 'kasir_cabang',
            'name' => 'Kasir Cabang',
            'is_system' => false,
        ]);

        app(RoleManagementService::class)->syncPermissions($role, [
            Permission::where('code', 'cash_deposit.create')->firstOrFail()->id,
        ]);

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertContains('cash_deposit.create', $kodeTerpasang);
        $this->assertNotContains('cash_deposit.view', $kodeTerpasang);
    }

    public function test_admin_biasa_tidak_boleh_mengakses_endpoint_pemeriksaan(): void
    {
        $kas = $this->setorkanKas(100000, 'VER-J');

        // pop_admin punya cash_deposit.view & create, TIDAK punya validate.
        $this->actingAs($this->admin)
            ->post(route('cash-deposits.verify', $kas->id), ['declared_amount' => '100.000'])
            ->assertForbidden();
    }
}
