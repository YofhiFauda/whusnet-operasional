<?php

namespace Tests\Feature\QrCode;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Services\CustomerQrTokenService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.1, §10 Fase 2 — level
 * HTTP (`QrBillingController`). Limiter `qr-billing-verify` DILONGGARKAN di
 * setiap test (bukan dihapus) — supaya percobaan berulang di satu test
 * (mis. tes lockout PIN 5x) menguji lockout KOLOM DB-nya sendiri, bukan
 * numpang kena rate limiter yang kebetulan sama angkanya (5).
 */
class QrBillingPageTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-billing']);

        RateLimiter::for('qr-billing-verify', fn () => Limit::perMinute(1000));

        $this->pop = Pop::create([
            'code' => 'BIL-A', 'pop_code' => 'BLA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Billing Test', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'customer_code' => 'RQ004001',
            'primary_phone' => '081234565678',
            'full_name' => 'Budi Santoso',
        ]);
    }

    private function codeFor(): string
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        return "{$token->token}.{$signature}";
    }

    #[Test]
    public function pelanggan_tanpa_pin_dapat_gerbang_4_digit_hp_dan_lolos_dengan_benar(): void
    {
        $code = $this->codeFor();

        $gate = $this->get(route('qr.billing', ['code' => $code]));
        $gate->assertOk();
        $gate->assertSee('4 Digit Terakhir', false);

        $wrong = $this->post(route('qr.billing.verify', ['code' => $code]), ['hp_last4' => '0000']);
        $wrong->assertRedirect(route('qr.billing', ['code' => $code]));
        $this->assertDatabaseHas('qr_scan_logs', ['result' => 'verify_failed']);

        $correct = $this->post(route('qr.billing.verify', ['code' => $code]), ['hp_last4' => '5678']);
        $correct->assertRedirect(route('qr.billing', ['code' => $code]));

        $detail = $this->get(route('qr.billing', ['code' => $code]));
        $detail->assertOk();
        $detail->assertSee('Budi Santoso');
    }

    #[Test]
    public function pelanggan_dengan_pin_wajib_ganti_pin_dulu_sebelum_lihat_tagihan(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $plainPin = $service->issuePin($token);
        $code = $this->codeFor();

        $gate = $this->get(route('qr.billing', ['code' => $code]));
        $gate->assertSee('PIN Anda', false);

        $verify = $this->post(route('qr.billing.verify', ['code' => $code]), ['pin' => $plainPin]);
        $verify->assertRedirect(route('qr.billing.pin.change-form', ['code' => $code]));

        // Belum ganti PIN → tagihan TETAP gak boleh keliatan walau PIN
        // sudah benar sekalipun (pin_must_change belum lunas).
        $stillGated = $this->get(route('qr.billing', ['code' => $code]));
        $stillGated->assertDontSee('Budi Santoso');

        $change = $this->post(route('qr.billing.pin.change-submit', ['code' => $code]), [
            'new_pin' => '481037',
            'new_pin_confirmation' => '481037',
        ]);
        $change->assertRedirect(route('qr.billing', ['code' => $code]));

        $detail = $this->get(route('qr.billing', ['code' => $code]));
        $detail->assertOk();
        $detail->assertSee('Budi Santoso');

        $this->assertFalse($token->fresh()->pin_must_change);
    }

    #[Test]
    public function halaman_ganti_pin_tidak_bisa_dilompati_langsung(): void
    {
        $code = $this->codeFor();

        // Belum pernah verifikasi PIN sama sekali — akses langsung ke
        // halaman ganti-PIN harus dilempar balik ke gerbang.
        $response = $this->get(route('qr.billing.pin.change-form', ['code' => $code]));
        $response->assertRedirect(route('qr.billing', ['code' => $code]));
    }

    #[Test]
    public function lima_gagal_pin_mengunci_lalu_percobaan_keenam_ditolak_meski_pin_benar(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $plainPin = $service->issuePin($token);
        $code = $this->codeFor();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('qr.billing.verify', ['code' => $code]), ['pin' => '999999']);
        }

        $this->assertNotNull($token->fresh()->pin_locked_until);

        $response = $this->post(route('qr.billing.verify', ['code' => $code]), ['pin' => $plainPin]);
        $response->assertRedirect(route('qr.billing', ['code' => $code]));
        $response->assertSessionHas('qr_billing_error');

        // Masih di gerbang, BUKAN lolos ke tagihan — PIN benar pun ditolak
        // selama masih terkunci.
        $stillGated = $this->get(route('qr.billing', ['code' => $code]));
        $stillGated->assertDontSee('Budi Santoso');
    }

    /**
     * §6.5.4/§6.5.5b: "PIN plaintext tidak pernah masuk session, cache,
     * flash, atau log". `$request->validate()` gagal (format salah, BUKAN
     * business-logic verifyPin()) secara default nge-flash SEMUA input ke
     * session `_old_input` — kecuali field-nya didaftarkan di
     * `bootstrap/app.php` `$exceptions->dontFlash()`. Ini regresi buat
     * pengaman itu — hapus baris dontFlash-nya, test ini yang gagal duluan.
     */
    #[Test]
    public function pin_format_salah_tidak_pernah_masuk_old_input_session(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $service->issuePin($token);
        $code = $this->codeFor();

        // "12" bukan 6 digit — gagal di validasi FORMAT ($request->validate()),
        // bukan di verifyPin() business-logic.
        $this->post(route('qr.billing.verify', ['code' => $code]), ['pin' => '12']);

        $this->assertArrayNotHasKey('pin', session('_old_input', []));
    }

    #[Test]
    public function kode_invalid_di_halaman_tagihan_404_bukan_error_lain(): void
    {
        $response = $this->get('/q1/ZZZZZZZZZZZZZZZZZZZZZZZZZZ.ZZZZZZZZZZ/tagihan');

        $response->assertNotFound();
    }

    #[Test]
    public function tagihan_pelanggan_muncul_di_halaman_detail_setelah_verifikasi(): void
    {
        $package = InternetPackage::create([
            'package_code' => 'PKT-'.random_int(1000, 9999),
            'name' => 'Paket Test 20 Mbps',
            'category' => 'rumahan',
            'package_group' => 'reguler',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
        ]);

        $service = CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => now(),
            'due_date' => now()->addDays(10),
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-TEST-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-08',
            'issue_date' => now(),
            'due_date' => now()->addDays(10),
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $code = $this->codeFor();
        $this->post(route('qr.billing.verify', ['code' => $code]), ['hp_last4' => '5678']);

        $detail = $this->get(route('qr.billing', ['code' => $code]));
        $detail->assertOk();
        $detail->assertSee('INV-TEST-0001');
        $detail->assertSee('2026-08');
    }
}
