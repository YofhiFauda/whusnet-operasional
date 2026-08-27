<?php

namespace Tests\Feature\QrCode;

use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Models\Pop;
use App\Services\CustomerQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §10 Fase 1 — "Test wajib
 * fase ini". Cakupan file ini murni level Service (signature/issue/revoke/
 * invariant); alur HTTP dispatcher ada di QrScanDispatchTest.
 */
class CustomerQrTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Secret tetap & dikenal di setiap test — bukan bergantung ke .env
        // asli, pola sama seperti InteractsWithPortalAuth::PORTAL_CLIENT_SECRET.
        config(['qr.secret' => 'test-qr-hmac-secret-fase-1']);
    }

    private function makePop(string $suffix = 'A'): Pop
    {
        return Pop::create([
            'code' => "QRT-{$suffix}", 'pop_code' => "QT{$suffix}", 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => "POP QR Test {$suffix}", 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    #[Test]
    public function signature_deterministik_dan_berubah_kalau_bahannya_berubah(): void
    {
        $service = app(CustomerQrTokenService::class);

        $base = $service->signature(1, 'RQ000631', 'TOKENABC');

        $this->assertSame($base, $service->signature(1, 'RQ000631', 'TOKENABC'), 'harus deterministik');
        $this->assertNotSame($base, $service->signature(2, 'RQ000631', 'TOKENABC'), 'pop_id beda harus beda signature');
        $this->assertNotSame($base, $service->signature(1, 'RQ000999', 'TOKENABC'), 'customer_code beda harus beda signature');
        $this->assertNotSame($base, $service->signature(1, 'RQ000631', 'TOKENXYZ'), 'token beda harus beda signature');
    }

    #[Test]
    public function dua_pelanggan_beda_pop_dengan_customer_code_identik_menghasilkan_signature_berbeda(): void
    {
        // Kasus nyata Winda/Endah RQ000042 (§2.1) — customer_code SENGAJA
        // sama, pop_id-nya yang membedakan.
        $service = app(CustomerQrTokenService::class);

        $sigPopA = $service->signature(1, 'RQ000042', 'SAMATOKEN12345678901234AB');
        $sigPopB = $service->signature(2, 'RQ000042', 'SAMATOKEN12345678901234AB');

        $this->assertNotSame($sigPopA, $sigPopB);
    }

    #[Test]
    public function issue_menerbitkan_token_baru_dan_idempoten(): void
    {
        $pop = $this->makePop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ001001']);
        $service = app(CustomerQrTokenService::class);

        $first = $service->issue($customer);
        $second = $service->issue($customer);

        $this->assertSame($first->id, $second->id, 'penerbitan berulang harus mengembalikan token lama, bukan token kedua');
        $this->assertSame(1, CustomerQrToken::where('customer_id', $customer->id)->count());
        $this->assertSame($pop->id, $first->signed_pop_id);
        $this->assertSame('RQ001001', $first->signed_customer_code);
    }

    #[Test]
    public function issue_menolak_pelanggan_tanpa_customer_code_atau_pop_id(): void
    {
        $customer = Customer::factory()->create(['customer_code' => '', 'pop_id' => null]);
        $service = app(CustomerQrTokenService::class);

        $this->expectException(RuntimeException::class);

        $service->issue($customer);
    }

    #[Test]
    public function revoke_idempoten_tidak_menimpa_alasan_lama(): void
    {
        $pop = $this->makePop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ001002']);
        $service = app(CustomerQrTokenService::class);

        $token = $service->issue($customer);
        $service->revoke($token, 'alasan pertama');
        $service->revoke($token->fresh(), 'alasan kedua — harus diabaikan');

        $this->assertSame('alasan pertama', $token->fresh()->revoke_reason);
    }

    #[Test]
    public function invariant_satu_token_aktif_ditegakkan_dari_jalur_non_http(): void
    {
        // Bypass Service sepenuhnya — simulasi artisan/tinker/import yang
        // langsung create() ke model. Observer::creating() harus tetap
        // menahan ini (CLAUDE.md § Observer: invariant wajib jalan dari
        // SEMUA jalur masuk).
        $pop = $this->makePop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ001003']);

        CustomerQrToken::create([
            'customer_id' => $customer->id,
            'token' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA',
            'signed_pop_id' => $pop->id,
            'signed_customer_code' => 'RQ001003',
            'issued_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        CustomerQrToken::create([
            'customer_id' => $customer->id,
            'token' => 'BBBBBBBBBBBBBBBBBBBBBBBBBB',
            'signed_pop_id' => $pop->id,
            'signed_customer_code' => 'RQ001003',
            'issued_at' => now(),
        ]);
    }

    /**
     * Dukungan secret lama (rotasi §7.5) DICABUT 2026-08-27 (perintah
     * eksplisit user) — cuma satu secret aktif, gak ada masa transisi lagi.
     * Rotasi `QR_HMAC_SECRET` = SEMUA QR lama wajib cetak ulang, bukan
     * ditolerir sementara. Test ini dulu namanya
     * `verify_menerima_signature_dari_secret_lama_selama_masa_rotasi` dan
     * assert SEBALIKNYA (`assertTrue`) — dibalik jadi regresi yang
     * membuktikan perilaku baru, bukan dihapus diam-diam.
     */
    #[Test]
    public function verify_menolak_signature_dari_secret_lama_setelah_secret_dirotasi(): void
    {
        $pop = $this->makePop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ001004']);
        $service = app(CustomerQrTokenService::class);

        config(['qr.secret' => 'secret-lama']);
        $token = $service->issue($customer);
        $oldSignature = $service->signature($pop->id, 'RQ001004', $token->token);

        config(['qr.secret' => 'secret-baru']);

        $this->assertFalse($service->verify($token->token, $oldSignature, $token));
    }
}
