<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\WebhookOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Endpoint #2 — `POST /api/v1/installations/network-assignment`
 * (docs/api/api-pop-distribusi/business-logic.md). Sejak keputusan.md §19,
 * endpoint ini HANYA menangani Mini POP/Distribusi — kredensial perangkat
 * ada di endpoint terpisah, lihat NetworkDeviceTest.
 */
class NetworkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.network_assignment_write_token' => 'write-token-456']);
    }

    private function seedCabang(): Pop
    {
        return Pop::create([
            'code' => 'JTS', 'pop_code' => 'PNR-JTS', 'name' => 'Jetis',
            'type' => 'cabang', 'status' => 'active', 'cid_prefix' => 'C',
        ]);
    }

    private function seedMiniPop(Pop $cabang, string $popCode = 'C1'): Pop
    {
        return Pop::create([
            'code' => 'JTS-'.$popCode, 'pop_code' => $popCode, 'name' => 'Mini POP '.$popCode,
            'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $cabang->id,
        ]);
    }

    private function seedDistribution(Pop $miniPop, string $code = 'A'): Distribution
    {
        return Distribution::create(['pop_id' => $miniPop->id, 'code' => $code, 'name' => 'Distribusi '.$code, 'description' => '-']);
    }

    private function seedCustomer(Pop $cabang, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'customer_code' => 'RQ000631',
            'full_name' => 'Pelanggan Uji',
            'primary_phone' => '081234500001',
            'registration_date' => '2026-08-01',
            'status' => 'installation_in_progress',
            'pop_id' => $cabang->id,
            'address' => 'Jl. Uji No. 1',
        ], $overrides));
    }

    private function seedOutbox(Customer $customer, string $key): WebhookOutbox
    {
        return WebhookOutbox::create([
            'destination' => 'website_b',
            'event' => 'installation.activated',
            'event_id' => (string) Str::uuid(),
            'idempotency_key' => $key,
            'customer_id' => $customer->id,
            'payload' => [],
            'status' => 'delivered',
        ]);
    }

    private function postAssignment(array $body): TestResponse
    {
        return $this->withToken('write-token-456')->postJson('/api/v1/installations/network-assignment', $body);
    }

    // ── Resolusi pelanggan & validasi dasar ─────────────────────────────

    public function test_tanpa_token_ditolak_401(): void
    {
        $response = $this->postJson('/api/v1/installations/network-assignment', ['idempotency_key' => 'x']);

        $response->assertStatus(401);
    }

    public function test_idempotency_key_tidak_dikenal_404(): void
    {
        $response = $this->postAssignment([
            'idempotency_key' => 'installation:9999:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertStatus(404);
    }

    public function test_field_wajib_tidak_dikirim_ditolak_422(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:1:activation:1');

        $response = $this->postAssignment(['idempotency_key' => 'installation:1:activation:1']);

        $response->assertStatus(422);
        // Bukan assertDatabaseCount('audit_logs', 0) — migrasi RBAC repo ini
        // sendiri sudah menulis baris audit_logs (Permission::create() pakai
        // RecordsAuditLogs) sebelum test manapun jalan. Yang wajib
        // dipastikan: tidak ada baris BARU dari aksi endpoint ini.
        $this->assertSame(0, AuditLog::where('action', 'network_assignment')->count());
    }

    public function test_mini_pop_code_tanpa_distribution_code_ditolak_422(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedCustomer($cabang, ['mini_pop_id' => null, 'distribution_id' => null]);
        $this->seedOutbox($customer, 'installation:2:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:2:activation:1',
            'mini_pop_code' => 'C1',
        ]);

        $response->assertStatus(422);
        $this->assertNull($customer->fresh()->mini_pop_id);
    }

    public function test_mini_pop_bukan_anak_cabang_pop_pelanggan_422(): void
    {
        $cabang = $this->seedCabang();
        $cabangLain = Pop::create(['code' => 'X', 'pop_code' => 'X', 'name' => 'Cabang Lain', 'type' => 'cabang', 'status' => 'active', 'cid_prefix' => 'X']);
        $this->seedMiniPop($cabangLain, 'X1');
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:3:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:3:activation:1',
            'mini_pop_code' => 'X1',
            'distribution_code' => 'A',
        ]);

        $response->assertStatus(422);
    }

    public function test_distribution_bukan_anak_mini_pop_yang_dipilih_422(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $miniPopLain = $this->seedMiniPop($cabang, 'C2');
        $this->seedDistribution($miniPopLain, 'A');
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:4:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:4:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertStatus(422);
    }

    public function test_status_blocked_ditolak_422(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang, ['status' => 'waiting_installation']);
        $this->seedOutbox($customer, 'installation:5:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:5:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertStatus(422);
        $this->assertNull($customer->fresh()->mini_pop_id);
    }

    // ── Assignment sukses & regenerasi CID ──────────────────────────────

    public function test_assignment_sukses_menyimpan_mini_pop_dan_distribusi(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:6:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:6:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertOk();
        $fresh = $customer->fresh();
        $this->assertSame($miniPop->id, $fresh->mini_pop_id);
    }

    public function test_response_menyertakan_mini_pop_code_dan_distribution_code(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:23:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:23:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertOk();
        $response->assertJson([
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);
    }

    public function test_key_cid_tidak_ada_di_response_saat_belum_active(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang, ['status' => 'installation_in_progress', 'cid' => null]);
        $this->seedOutbox($customer, 'installation:24:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:24:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertOk();
        // Key `cid` dihilangkan total dari respons — bukan `null` — supaya
        // gak ambigu (Website B gak salah tafsir "gagal"/"belum diisi").
        $this->assertArrayNotHasKey('cid', $response->json());
        $this->assertNull($customer->fresh()->cid);
    }

    public function test_cid_diregenerate_kalau_pelanggan_active(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang, ['status' => 'active', 'cid' => 'C00RQ000631']);
        $this->seedOutbox($customer, 'installation:7:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:7:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertOk();
        $fresh = $customer->fresh();
        $this->assertNotSame('C00RQ000631', $fresh->cid);
        $this->assertSame($fresh->cid, $response->json('cid'));
        $this->assertArrayHasKey('cid', $response->json());
    }

    public function test_cid_tidak_disentuh_kalau_belum_active(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang, ['status' => 'installation_in_progress', 'cid' => null]);
        $this->seedOutbox($customer, 'installation:8:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:8:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertOk();
        $this->assertNull($customer->fresh()->cid);
    }

    public function test_audit_log_user_id_null_dan_sumber_ditandai(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:16:activation:1');

        $this->postAssignment([
            'idempotency_key' => 'installation:16:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $log = AuditLog::where('action', 'network_assignment')->first();
        $this->assertNull($log->user_id);
        $this->assertSame('API — Website B integration', $log->user_agent);
    }

    // ── Idempotency & dedup ──────────────────────────────────────────────

    public function test_customer_id_di_body_diabaikan(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang);
        $lain = $this->seedCustomer($cabang, ['customer_code' => 'RQ000999']);
        $this->seedOutbox($customer, 'installation:19:activation:1');

        $response = $this->postAssignment([
            'idempotency_key' => 'installation:19:activation:1',
            'customer_id' => $lain->id,
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);

        $response->assertOk();
        $this->assertSame($miniPop->id, $customer->fresh()->mini_pop_id);
        $this->assertNull($lain->fresh()->mini_pop_id);
    }

    public function test_retry_key_dan_body_identik_tidak_menulis_audit_log_dobel(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:20:activation:1');

        $body = [
            'idempotency_key' => 'installation:20:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ];

        $first = $this->postAssignment($body);
        $second = $this->postAssignment($body);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('cid'), $second->json('cid'));
        $this->assertSame(1, AuditLog::where('action', 'network_assignment')->count());
    }

    // ── Rate limit ───────────────────────────────────────────────────────

    public function test_rate_limit_20_per_menit(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedCustomer($cabang, ['mini_pop_id' => null, 'distribution_id' => null]);
        $this->seedOutbox($customer, 'installation:22:activation:1');

        for ($i = 0; $i < 20; $i++) {
            $this->postAssignment(['idempotency_key' => 'nonexistent-'.$i]);
        }

        $response = $this->postAssignment(['idempotency_key' => 'installation:22:activation:1']);

        $response->assertStatus(429);
    }
}
