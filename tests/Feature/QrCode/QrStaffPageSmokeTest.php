<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\CustomerQrTokenService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * QrScanDispatchTest & CustomerQrTokenServiceTest tidak pernah benar-benar
 * me-render halaman staf (`CustomerQrController`) lewat HTTP — cuma level
 * Service/dispatcher publik. File ini menutup celah itu: lihat/terbitkan/
 * cetak/cabut lewat request sungguhan, termasuk render QR SVG asli
 * (`CustomerQrCodeRenderer` + `endroid/qr-code`), bukan cuma dipanggil
 * langsung di PHP.
 */
class QrStaffPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'smoke-secret']);

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMK-A', 'pop_code' => 'SMA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Smoke', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'customer_code' => 'RQ777001']);

        $role = Role::where('code', 'admin')->firstOrFail();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $this->admin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    #[Test]
    public function halaman_show_render_normal_saat_belum_ada_token(): void
    {
        $response = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));

        $response->assertOk();
        $response->assertSee('belum punya token QR aktif');
    }

    /**
     * Regresi — tombol "QR Pelanggan" di Detail Pelanggan (customers.show)
     * SEMPAT ketinggalan: route/controller/view sudah ada tapi gak ada
     * jalan masuk dari halaman yang staf beneran buka (user lapor lewat
     * uji coba manual, bukan test). Test ini menutup celah supaya kalau
     * tombolnya kecabut lagi nanti, ketahuan di sini duluan.
     */
    #[Test]
    public function tombol_qr_pelanggan_muncul_di_halaman_detail_pelanggan(): void
    {
        $response = $this->actingAs($this->admin)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertSee(route('customers.qr.show', $this->customer), false);
    }

    /**
     * Koreksi 2026-08-26 — QR + PIN diterbitkan BARENG dalam satu aksi
     * (§6.5.3 "bukan aksi admin lepas"), respons JSON (dikonsumsi modal
     * Alpine di show.blade.php, koreksi kedua 2026-08-26 dari halaman/tab
     * terpisah).
     */
    #[Test]
    public function terbitkan_menerbitkan_qr_dan_pin_sekaligus_lalu_lihat_lalu_cetak_semua_render_normal(): void
    {
        $issueResponse = $this->actingAs($this->admin)->post(route('customers.qr.issue', $this->customer));
        $issueResponse->assertOk();
        $issueResponse->assertJsonPath('customer.full_name', $this->customer->full_name);

        $token = $this->customer->fresh()->activeQrToken;
        $this->assertNotNull($token);
        $this->assertNotNull($token->pin_hash);

        $showResponse = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));
        $showResponse->assertOk();

        // Render SVG QR SUNGGUHAN lewat CustomerQrCodeRenderer — bukan cuma
        // dipanggil di PHP tanpa lewat HTTP.
        $printResponse = $this->actingAs($this->admin)->get(route('customers.qr.print', $this->customer));
        $printResponse->assertOk();
        $printResponse->assertSee($this->customer->customer_code);
        // Digabung 2026-08-26 (keputusan user) — Login ID Portal ikut di
        // stiker reprintable. PIN JUGA ikut sekarang (koreksi kedua
        // 2026-08-26, perintah eksplisit user) — pin_hash reversible,
        // revealPin() bisa dibuka ulang kapan pun (lihat CustomerQrTokenService).
        $printResponse->assertSee($this->customer->fresh()->portal_login_id);
        $printResponse->assertSee(app(CustomerQrTokenService::class)->revealPin($token));
    }

    /**
     * Klik "Terbitkan QR + PIN" LAGI setelah PIN sudah ada TIDAK BOLEH
     * menimpanya diam-diam — koreksi atas kesalahan logic sebelumnya
     * (user report 2026-08-26): PIN yang sudah dipegang pelanggan gak
     * boleh mati gara-gara admin klik gak sengaja. Reset PIN wajib lewat
     * tombol/endpoint terpisah (reissuePin()).
     */
    #[Test]
    public function klik_terbitkan_lagi_setelah_pin_ada_tidak_menimpa_pin_lama(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $service->issuePin($token);
        $hashSebelum = $token->fresh()->pin_hash;

        $response = $this->actingAs($this->admin)->post(route('customers.qr.issue', $this->customer));

        $response->assertRedirect(route('customers.qr.show', $this->customer));
        $this->assertSame($hashSebelum, $token->fresh()->pin_hash);
    }

    /**
     * §6.5.5 mensyaratkan admin verifikasi identitas pelanggan SEBELUM
     * reset PIN — field `verification_note` yang dulu memaksa ini dicabut
     * (permintaan user), TAPI itu bukan alasan pengingatnya hilang tanpa
     * pengganti. Regresi: kalau teks ini kecabut lagi nanti tanpa
     * pengganti apa pun, ketahuan di sini.
     */
    #[Test]
    public function halaman_show_mengingatkan_verifikasi_identitas_sebelum_reset_pin(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $service->issuePin($token);

        $response = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));

        $response->assertOk();
        $response->assertSee('Verifikasi identitas pelanggan dulu sebelum Reset PIN');
    }

    #[Test]
    public function cabut_lalu_lihat_render_normal_dan_riwayat_muncul(): void
    {
        $service = app(CustomerQrTokenService::class);
        $service->issue($this->customer);

        $revokeResponse = $this->actingAs($this->admin)->post(route('customers.qr.revoke', $this->customer), ['reason' => 'test cabut']);
        $revokeResponse->assertRedirect(route('customers.qr.show', $this->customer));

        $showResponse = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));
        $showResponse->assertOk();
        $showResponse->assertSee('Riwayat Token Dicabut');
    }

    /**
     * Fase 2 (§6.5.3) — respons terbitkan PIN SENGAJA JSON (bukan redirect
     * PRG biasa, bukan pula halaman/tab terpisah lagi — koreksi kedua
     * 2026-08-26 jadi modal in-page), jadi harus dites lewat HTTP sungguhan
     * buat memastikan payload-nya beneran berisi PIN + header no-store,
     * bukan cuma dipercaya dari baca kode. Jalur ini: token sudah ada TAPI
     * belum pernah punya PIN.
     */
    #[Test]
    public function terbitkan_merender_pin_json_dengan_header_no_store_saat_token_belum_punya_pin(): void
    {
        $service = app(CustomerQrTokenService::class);
        $service->issue($this->customer);

        $response = $this->actingAs($this->admin)->post(route('customers.qr.issue', $this->customer));

        $response->assertOk();
        // Symfony ResponseHeaderBag menormalkan Cache-Control (urutan
        // direktif + nambah "private") — cek substring, bukan string
        // persis, biar gak rapuh ke detail normalisasi framework.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $token = $this->customer->fresh()->activeQrToken;
        $this->assertNotNull($token->pin_hash);

        // PIN di payload JSON harus COCOK dengan hash yang baru disimpan —
        // bukti ini bukan PIN basi/kebetulan tampil.
        $this->assertSame($response->json('pin'), Crypt::decryptString($token->pin_hash));

        // Login ID (§6.6.2) ikut di payload — pelanggan butuh ini buat
        // klaim akun portal belakangan.
        $response->assertJsonPath('customer.portal_login_id', $this->customer->portal_login_id);

        // Riwayat PIN ikut di payload yang sama — dipakai bagian "Status
        // PIN" di show.blade.php update tanpa request kedua.
        $this->assertCount(1, $response->json('history'));
        $this->assertSame('Diterbitkan', $response->json('history.0.action'));
    }

    /**
     * Reset PIN (§6.5.5) — koreksi 2026-08-26 (2x): field `verification_note`
     * wajib DICABUT atas masukan user (dialog isi-alasan dianggap gak perlu),
     * gerbangnya sekarang murni modal pratinjau sisi klien. Baris DB tetap
     * berubah (hash lama mati), dan jejak siapa/kapan tetap masuk AuditLog
     * lewat Service — cuma TANPA kolom catatan bebas lagi.
     */
    #[Test]
    public function reset_pin_tanpa_field_tambahan_langsung_mencabut_pin_lama_dan_tercatat_di_riwayat(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $service->issuePin($token);
        $hashSebelum = $token->fresh()->pin_hash;

        $response = $this->actingAs($this->admin)->post(route('customers.qr.pin.reissue', $this->customer), []);
        $response->assertOk();

        $token->refresh();
        $this->assertNotSame($hashSebelum, $token->pin_hash);
        $this->assertSame($response->json('pin'), Crypt::decryptString($token->pin_hash));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'QR Pelanggan',
            'action' => 'pin_reissued',
            'auditable_id' => $token->id,
        ]);
        $this->assertSame(2, AuditLog::where('auditable_id', $token->id)
            ->where('auditable_type', CustomerQrToken::class)
            ->count()); // pin_issued (setup) + pin_reissued

        // Riwayat 2 baris (terbit pertama + reset ini), terbaru duluan.
        $history = $response->json('history');
        $this->assertCount(2, $history);
        $this->assertSame('Direset', $history[0]['action']);
        $this->assertSame('Diterbitkan', $history[1]['action']);
    }

    #[Test]
    public function reset_pin_tanpa_token_aktif_404(): void
    {
        $response = $this->actingAs($this->admin)->post(route('customers.qr.pin.reissue', $this->customer), []);

        $response->assertNotFound();
    }

    /**
     * `pinHistory()` dipanggil juga di GET show() (bukan cuma respons JSON
     * issue/reset) — halaman yang dimuat ulang biasa (F5) tetap nunjukin
     * riwayat yang sama, bukan cuma hidup selama satu sesi Alpine.
     */
    #[Test]
    public function riwayat_pin_tetap_muncul_setelah_reload_halaman_biasa(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $service->issuePin($token, $this->admin);

        $this->assertDatabaseHas('audit_logs', ['action' => 'pin_issued', 'user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));
        $response->assertOk();
        $response->assertSee($this->admin->name);
    }

    /**
     * Perintah eksplisit user 2026-08-26 (membalik keputusan sebelumnya):
     * `/qr/cetak` HARUS bisa nunjukin PIN kapan pun dibuka ulang, bukan
     * cuma sekali di modal. Dua GET terpisah, PIN-nya harus sama persis —
     * bukti beneran reversible (revealPin()), bukan kebetulan tersisa di
     * state request pertama.
     */
    #[Test]
    public function halaman_cetak_menampilkan_pin_yang_sama_di_dua_kali_buka(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $plainPin = $service->issuePin($token);

        $first = $this->actingAs($this->admin)->get(route('customers.qr.print', $this->customer));
        $second = $this->actingAs($this->admin)->get(route('customers.qr.print', $this->customer));

        $first->assertOk();
        $second->assertOk();
        $first->assertSee($plainPin);
        $second->assertSee($plainPin);
    }

    /**
     * Baris `pin_hash` yang dibuat SEBELUM revisi ke enkripsi reversible
     * (masih format bcrypt) tetap harus render halaman cetak tanpa error —
     * revealPin() balikin null buat baris begitu (lihat docblock service),
     * bukan exception yang bikin 500.
     */
    #[Test]
    public function halaman_cetak_tidak_error_saat_pin_hash_masih_format_bcrypt_lama(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $token->update(['pin_hash' => bcrypt('123456')]);

        $response = $this->actingAs($this->admin)->get(route('customers.qr.print', $this->customer));

        $response->assertOk();
        $response->assertSee('PIN belum tersedia untuk dicetak ulang');
    }
}
