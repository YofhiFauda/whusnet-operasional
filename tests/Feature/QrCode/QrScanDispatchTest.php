<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\QrScanLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CustomerQrTokenService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §5, §10 Fase 1 — "Test
 * wajib fase ini" untuk QrScanController::dispatch() (level HTTP).
 */
class QrScanDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-fase-1']);

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'QRD-A', 'pop_code' => 'QDA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP QR Dispatch', 'type' => 'cabang', 'status' => 'active',
        ]);

        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'customer_code' => 'RQ002001',
        ]);
    }

    private function scopedUser(string $roleCode, Pop $onlyPop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $onlyPop->id]);

        return $user;
    }

    #[Test]
    public function token_tidak_ketemu_mengembalikan_404_dan_log_token_not_found(): void
    {
        $response = $this->get('/q1/ZZZZZZZZZZZZZZZZZZZZZZZZZZ.ZZZZZZZZZZ');

        $response->assertNotFound();
        $this->assertDatabaseHas('qr_scan_logs', ['result' => 'token_not_found']);
    }

    #[Test]
    public function signature_salah_mengembalikan_404_dan_log_bad_signature(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);

        $response = $this->get("/q1/{$token->token}.ZZZZZZZZZZ");

        $response->assertNotFound();
        $this->assertDatabaseHas('qr_scan_logs', [
            'customer_qr_token_id' => $token->id,
            'result' => 'bad_signature',
        ]);
    }

    #[Test]
    public function token_dicabut_mengembalikan_404_dan_log_token_revoked(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);
        $service->revoke($token, 'stiker hilang');

        $response = $this->get("/q1/{$token->token}.{$signature}");

        $response->assertNotFound();
        $this->assertDatabaseHas('qr_scan_logs', [
            'customer_qr_token_id' => $token->id,
            'result' => 'token_revoked',
        ]);
    }

    #[Test]
    public function pop_id_pelanggan_berubah_di_luar_observer_tetap_ditolak_pop_mismatch(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        $popLain = Pop::create([
            'code' => 'QRD-B', 'pop_code' => 'QDB', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP QR Dispatch B', 'type' => 'cabang', 'status' => 'active',
        ]);

        // Update LEWAT QUERY BUILDER, bukan Eloquent — sengaja BYPASS
        // CustomerObserver supaya kasus "pop_id berubah tanpa lewat jalur
        // aplikasi normal" (import langsung, restore backup) tetap
        // ketahuan pop_mismatch di layer resolve(), bukan cuma ditutup
        // Observer (§2.1: "Bukan serangan — kegagalan proses").
        DB::table('customers')->where('id', $this->customer->id)->update(['pop_id' => $popLain->id]);

        $response = $this->get("/q1/{$token->token}.{$signature}");

        $response->assertNotFound();
        $this->assertDatabaseHas('qr_scan_logs', [
            'customer_qr_token_id' => $token->id,
            'result' => 'pop_mismatch',
        ]);
        // Token TIDAK otomatis tercabut di jalur ini — bukti resolve() cek
        // independen dari Observer, bukan cuma menemukan token yang sudah
        // dicabut duluan.
        $this->assertNull($token->fresh()->revoked_at);
    }

    #[Test]
    public function semua_kegagalan_mengembalikan_body_404_identik(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);
        $service->revoke($token, 'test');

        $notFound = $this->get('/q1/ZZZZZZZZZZZZZZZZZZZZZZZZZZ.ZZZZZZZZZZ');
        $badSignature = $this->get("/q1/{$token->token}.ZZZZZZZZZZ");
        $revoked = $this->get("/q1/{$token->token}.{$signature}");

        $notFound->assertNotFound();
        $badSignature->assertNotFound();
        $revoked->assertNotFound();
        $this->assertSame($notFound->getContent(), $badSignature->getContent());
        $this->assertSame($notFound->getContent(), $revoked->getContent());
    }

    #[Test]
    public function format_kode_tidak_valid_ditolak_di_level_route_tanpa_query_db(): void
    {
        // Regex route '[A-Z2-7]{26}\.[A-Z2-7]{10}' — 'x' huruf kecil dan '0'/'1'
        // bukan alfabet base32 kita, jadi ini gagal MATCHING ROUTE (404 bawaan
        // Laravel), bukan masuk QrScanController sama sekali.
        $response = $this->get('/q1/not-a-valid-qr-code-at-all');

        $response->assertNotFound();
        $this->assertDatabaseCount('qr_scan_logs', 0);
    }

    #[Test]
    public function pelanggan_di_luar_pop_scope_staf_ditolak_403_dan_log_out_of_scope(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        $popLain = Pop::create([
            'code' => 'QRD-C', 'pop_code' => 'QDC', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP QR Dispatch C', 'type' => 'cabang', 'status' => 'active',
        ]);
        $staff = $this->scopedUser('helpdesk', $popLain);

        $response = $this->actingAs($staff)->get("/q1/{$token->token}.{$signature}");

        $response->assertForbidden();
        $this->assertDatabaseHas('qr_scan_logs', [
            'customer_qr_token_id' => $token->id,
            'result' => 'out_of_scope',
        ]);
    }

    #[Test]
    public function staf_dengan_permission_tickets_create_diarahkan_ke_form_tiket_terprefill(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        $staff = $this->scopedUser('helpdesk', $this->pop);
        $code = "{$token->token}.{$signature}";

        $dispatchResponse = $this->actingAs($staff)->get("/q1/{$code}");
        $dispatchResponse->assertRedirect(route('qr.ticket.create', ['code' => $code]));

        $ticketHopResponse = $this->actingAs($staff)->get(route('qr.ticket.create', ['code' => $code]));
        $ticketHopResponse->assertRedirect(route('tickets.create', ['customer_id' => $this->customer->id]));

        $formResponse = $this->actingAs($staff)->get(route('tickets.create', ['customer_id' => $this->customer->id]));
        $formResponse->assertOk();
        $formResponse->assertViewHas('prefillCustomer', fn ($payload) => $payload['id'] === $this->customer->id);

        $this->assertDatabaseHas('qr_scan_logs', [
            'customer_qr_token_id' => $token->id,
            'purpose' => 'ticketing',
            'result' => 'success',
        ]);
    }

    #[Test]
    public function tamu_belum_login_diarahkan_ke_halaman_tagihan_bukan_404(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);
        $code = "{$token->token}.{$signature}";

        $dispatchResponse = $this->get("/q1/{$code}");
        $dispatchResponse->assertRedirect(route('qr.billing', ['code' => $code]));

        $billingResponse = $this->get(route('qr.billing', ['code' => $code]));
        $billingResponse->assertOk();

        $this->assertDatabaseHas('qr_scan_logs', [
            'customer_qr_token_id' => $token->id,
            'purpose' => 'payment',
            'result' => 'success',
        ]);
    }

    #[Test]
    public function scan_sukses_menaikkan_scan_count_dan_last_scanned_at(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        $this->assertSame(0, $token->fresh()->scan_count);

        $this->get("/q1/{$token->token}.{$signature}");

        $fresh = $token->fresh();
        $this->assertSame(1, $fresh->scan_count);
        $this->assertNotNull($fresh->last_scanned_at);
        $this->assertSame(1, QrScanLog::where('result', 'success')->count());
    }
}
