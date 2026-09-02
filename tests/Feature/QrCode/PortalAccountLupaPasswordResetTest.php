<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\CustomerPortalToken;
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
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * "Lupa Password" (business-logic.md §"Aktivasi akun") — nemu 2026-08-27
 * lewat pengujian manual kalau jalur ini SEBENARNYA GAK ADA: `reissuePin()`
 * biasa tidak menyentuh `customer_portal_accounts`, jadi akun `active` yang
 * pelanggannya lupa password TERKUNCI PERMANEN (`claim()` menolak keras
 * status `active`). Test ini menutup celah itu —
 * `CustomerQrController::resetPortalAccount()` +
 * `PortalAuthService::resetToPendingClaim()`.
 */
class PortalAccountLupaPasswordResetTest extends TestCase
{
    use InteractsWithPortalAuth;
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'lupa-password-secret']);
        $this->setUpPortalClientSecret();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'LPW-A', 'pop_code' => 'LPA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Lupa Password', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'customer_code' => 'RQ888001']);

        $role = Role::where('code', 'admin')->firstOrFail();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $this->admin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    #[Test]
    public function reset_akun_portal_404_kalau_belum_ada_akun_portal_sama_sekali(): void
    {
        app(CustomerQrTokenService::class)->issue($this->customer);

        $response = $this->actingAs($this->admin)->post(route('customers.qr.portal-account.reset', $this->customer));

        $response->assertNotFound();
    }

    #[Test]
    public function reset_akun_portal_409_kalau_akun_masih_pending_claim(): void
    {
        app(CustomerQrTokenService::class)->issue($this->customer);
        CustomerPortalAccount::create([
            'customer_id' => $this->customer->id,
            'login_id' => $this->customer->portal_login_id,
            'password_hash' => Hash::make('placeholder-belum-diklaim'),
            'status' => 'pending_claim',
        ]);

        $response = $this->actingAs($this->admin)->post(route('customers.qr.portal-account.reset', $this->customer));

        $response->assertStatus(409);
    }

    #[Test]
    public function reset_akun_portal_sukses_menurunkan_status_menerbitkan_pin_baru_dan_mencabut_sesi_lama(): void
    {
        $qrTokens = app(CustomerQrTokenService::class);
        $token = $qrTokens->issue($this->customer);
        $qrTokens->issuePin($token);

        $account = CustomerPortalAccount::create([
            'customer_id' => $this->customer->id,
            'login_id' => $this->customer->portal_login_id,
            'password_hash' => Hash::make(self::PORTAL_TEST_PASSWORD),
            'status' => 'active',
            'claimed_at' => now(),
        ]);

        // Sesi lama yang HARUS dicabut begitu reset sukses.
        $oldTokens = $this->loginAndGetTokens($account->login_id);

        $response = $this->actingAs($this->admin)->post(route('customers.qr.portal-account.reset', $this->customer));

        $response->assertOk();
        $this->assertNotEmpty($response->json('pin'));

        $account->refresh();
        $this->assertSame('pending_claim', $account->status);
        $this->assertFalse(Hash::check(self::PORTAL_TEST_PASSWORD, $account->password_hash));

        // Sesi lama mati — resolveRefreshToken() tetap MENEMUKAN baris token
        // (dipakai buat deteksi reuse/pencurian), tapi revoked_at-nya keisi.
        $dbToken = CustomerPortalToken::resolveRefreshToken($oldTokens['refresh_token']);
        $this->assertNotNull($dbToken);
        $this->assertNotNull($dbToken->revoked_at);

        $meResponse = $this->withHeaders($this->authenticatedHeaders($oldTokens['access_token']))
            ->getJson('/api/customer-portal/me');
        $meResponse->assertStatus(401);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Portal Pelanggan',
            'action' => 'account_reset_to_pending_claim',
            'auditable_type' => CustomerPortalAccount::class,
            'auditable_id' => $account->id,
            'user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function pelanggan_bisa_klaim_ulang_pakai_pin_baru_setelah_reset(): void
    {
        $qrTokens = app(CustomerQrTokenService::class);
        $token = $qrTokens->issue($this->customer);
        $qrTokens->issuePin($token);

        $account = CustomerPortalAccount::create([
            'customer_id' => $this->customer->id,
            'login_id' => $this->customer->portal_login_id,
            'password_hash' => Hash::make(self::PORTAL_TEST_PASSWORD),
            'status' => 'active',
            'claimed_at' => now(),
        ]);

        $resetResponse = $this->actingAs($this->admin)->post(route('customers.qr.portal-account.reset', $this->customer));
        $resetResponse->assertOk();
        $newPin = $resetResponse->json('pin');

        $claimResponse = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/claim', [
                'login_id' => $account->login_id,
                'pin' => $newPin,
                'new_password' => 'Password-Baru-Setelah-Lupa-99',
            ]);

        $claimResponse->assertOk();
        $claimResponse->assertJsonStructure(['access_token', 'refresh_token']);

        $account->refresh();
        $this->assertSame('active', $account->status);
        $this->assertTrue(Hash::check('Password-Baru-Setelah-Lupa-99', $account->password_hash));
    }

    #[Test]
    public function tombol_reset_akun_portal_cuma_muncul_kalau_status_active(): void
    {
        $qrTokens = app(CustomerQrTokenService::class);
        $qrTokens->issue($this->customer);

        CustomerPortalAccount::create([
            'customer_id' => $this->customer->id,
            'login_id' => $this->customer->portal_login_id,
            'password_hash' => Hash::make('placeholder'),
            'status' => 'pending_claim',
        ]);

        $pendingResponse = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));
        $pendingResponse->assertOk();
        $pendingResponse->assertDontSee('Reset Akun Portal (Lupa Password)');

        CustomerPortalAccount::where('customer_id', $this->customer->id)->update(['status' => 'active']);

        $activeResponse = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));
        $activeResponse->assertOk();
        $activeResponse->assertSee('Reset Akun Portal (Lupa Password)');
    }
}
