<?php

namespace Tests\Feature\QrCode;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\CustomerQrToken;
use App\Models\Pop;
use App\Services\CustomerWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §7.2, §10 Fase 2 —
 * penerbitan token+PIN OTOMATIS saat pelanggan masuk WAITING_INSTALLATION.
 */
class CustomerWorkflowQrAutoIssueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-workflow']);

        // CustomerWorkflowService::transition() nulis AuditLog dgn
        // Auth::id() ?? 1 — user id 1 gak otomatis ada di RefreshDatabase
        // segar, jadi WAJIB login beneran biar FK audit_logs.user_id valid.
        $this->loginAsAdmin();
    }

    private function registeredCustomer(): Customer
    {
        $pop = Pop::create([
            'code' => 'WFA', 'pop_code' => 'WFA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Workflow QR', 'type' => 'cabang', 'status' => 'active',
        ]);

        return Customer::factory()->create([
            'pop_id' => $pop->id,
            'customer_code' => 'RQ005001',
            'status' => 'registered',
        ]);
    }

    #[Test]
    public function masuk_waiting_installation_menerbitkan_token_dan_pin_otomatis(): void
    {
        $customer = $this->registeredCustomer();

        app(CustomerWorkflowService::class)->transition($customer, 'waiting_installation');

        $token = CustomerQrToken::where('customer_id', $customer->id)->whereNull('revoked_at')->first();
        $this->assertNotNull($token);
        $this->assertNotNull($token->pin_hash);
        $this->assertTrue($token->pin_must_change);

        // Kartu yang bakal dicetak dari titik ini punya login_id + PIN —
        // akun customer_portal_accounts (pending_claim) WAJIB udah ada di
        // sini juga, bukan cuma lahir lewat command backfill manual
        // (gejala nyata 2026-08-27: /auth/claim gagal 401 generik kalau
        // baris ini belum ada — lihat PortalAuthService::ensureAccountExists()).
        $account = CustomerPortalAccount::where('customer_id', $customer->id)->first();
        $this->assertNotNull($account);
        $this->assertSame('pending_claim', $account->status);
        $this->assertSame($customer->portal_login_id, $account->login_id);
    }

    #[Test]
    public function transisi_berulang_tidak_menerbitkan_token_atau_pin_kedua(): void
    {
        $customer = $this->registeredCustomer();
        $workflow = app(CustomerWorkflowService::class);

        $workflow->transition($customer, 'waiting_installation');
        $firstToken = CustomerQrToken::where('customer_id', $customer->id)->whereNull('revoked_at')->firstOrFail();
        $firstPinHash = $firstToken->pin_hash;

        // Instalasi diulang (WorkflowTransition.php:37-40) — mundur ke
        // installation_in_progress lalu WAITING_INSTALLATION lagi.
        $workflow->transition($customer, 'installation_in_progress');
        $workflow->transition($customer, 'waiting_installation');

        $this->assertSame(1, CustomerQrToken::where('customer_id', $customer->id)->count());
        $secondToken = CustomerQrToken::where('customer_id', $customer->id)->whereNull('revoked_at')->firstOrFail();
        $this->assertSame($firstToken->id, $secondToken->id);
        $this->assertSame($firstToken->token, $secondToken->token);
        // PIN TIDAK diterbitkan ulang — kartu lama masih berlaku.
        $this->assertSame($firstPinHash, $secondToken->pin_hash);

        $this->assertSame(1, CustomerPortalAccount::where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function akun_portal_yang_sudah_active_tidak_ditimpa_saat_transisi_berulang(): void
    {
        $customer = $this->registeredCustomer();
        $workflow = app(CustomerWorkflowService::class);

        $workflow->transition($customer, 'waiting_installation');

        // Simulasikan pelanggan sudah klaim akunnya sebelum instalasi diulang.
        $account = CustomerPortalAccount::where('customer_id', $customer->id)->firstOrFail();
        $account->update(['status' => 'active', 'password_hash' => 'sudah-diklaim']);

        $workflow->transition($customer, 'installation_in_progress');
        $workflow->transition($customer, 'waiting_installation');

        $account->refresh();
        $this->assertSame('active', $account->status);
        // password_hash pakai cast 'hashed' — cek isinya TETAP hash dari
        // 'sudah-diklaim', bukan ketimpa placeholder baru dari ensureAccountExists().
        $this->assertTrue(Hash::check('sudah-diklaim', $account->password_hash));
    }
}
