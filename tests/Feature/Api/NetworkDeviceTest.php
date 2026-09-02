<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\WebhookOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Endpoint #3 — `POST /api/v1/installations/network-device`
 * (docs/api/api-pop-distribusi/business-logic.md, keputusan.md §19). Pisahan
 * dari network-assignment — kredensial PPPoE & detail titik sambung OLT.
 */
class NetworkDeviceTest extends TestCase
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

    /**
     * Pelanggan dengan assignment Mini POP/Distribusi yang SUDAH ada —
     * prasyarat network-device (endpoint ini gak pernah mengisi mini_pop_id/
     * distribution_id sendiri).
     */
    private function seedAssignedCustomer(Pop $cabang, array $overrides = []): Customer
    {
        $miniPop = $this->seedMiniPop($cabang);
        $distribution = $this->seedDistribution($miniPop);

        return $this->seedCustomer($cabang, array_merge([
            'mini_pop_id' => $miniPop->id,
            'distribution_id' => $distribution->id,
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

    private function postDevice(array $body): TestResponse
    {
        return $this->withToken('write-token-456')->postJson('/api/v1/installations/network-device', $body);
    }

    // ── Validasi dasar ───────────────────────────────────────────────────

    public function test_tanpa_token_ditolak_401(): void
    {
        $response = $this->postJson('/api/v1/installations/network-device', ['idempotency_key' => 'x']);

        $response->assertStatus(401);
    }

    public function test_idempotency_key_tidak_dikenal_404(): void
    {
        $response = $this->postDevice([
            'idempotency_key' => 'installation:9999:activation:1',
            'perangkat' => ['pppoe_username' => 'user-a'],
        ]);

        $response->assertStatus(404);
    }

    public function test_perangkat_kosong_ditolak_422(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:1:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:1:activation:1',
            'perangkat' => ['pppoe_username' => null],
        ]);

        $response->assertStatus(422);
    }

    public function test_perangkat_tidak_dikirim_ditolak_422(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:2:activation:1');

        $response = $this->postDevice(['idempotency_key' => 'installation:2:activation:1']);

        $response->assertStatus(422);
    }

    public function test_tanpa_assignment_tersimpan_ditolak_422(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedCustomer($cabang, ['mini_pop_id' => null, 'distribution_id' => null]);
        $this->seedOutbox($customer, 'installation:3:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:3:activation:1',
            'perangkat' => ['pppoe_username' => 'user-a'],
        ]);

        $response->assertStatus(422);
    }

    // ── Upsert perangkat: dua tabel ──────────────────────────────────────

    public function test_perangkat_upsert_ke_customer_devices(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:4:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:4:activation:1',
            'perangkat' => [
                'pppoe_username' => 'C1X4ARQ000631',
                'pppoe_password' => 'rahasia123',
            ],
        ]);

        $response->assertOk();
        $device = CustomerDevice::where('customer_id', $customer->id)->first();
        $this->assertSame('C1X4ARQ000631', $device->pppoe_username);
    }

    public function test_field_olt_vlan_masuk_customer_technical_details_bukan_customer_devices(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:5:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:5:activation:1',
            'perangkat' => [
                'olt_number' => 'OLT-03',
                'olt_slot' => '1',
                'olt_port' => '8',
                'vlan' => '301',
            ],
        ]);

        $response->assertOk();
        $tech = CustomerTechnicalDetail::where('customer_id', $customer->id)->first();
        $this->assertSame('OLT-03', $tech->olt_number);
        $this->assertSame('301', $tech->vlan);
        $this->assertDatabaseCount('customer_devices', 0);
    }

    public function test_perangkat_parsial_tidak_menghapus_field_lain_yang_sudah_tersimpan(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        // mac_address diisi teknisi manual, BUKAN field yang endpoint ini
        // tulis — jadi field ini harus tetap utuh apa pun yang dikirim API.
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'mac_address' => 'AA:BB:CC:DD:EE:FF']);
        $this->seedOutbox($customer, 'installation:6:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:6:activation:1',
            'perangkat' => ['pppoe_username' => 'user-baru'],
        ]);

        $response->assertOk();
        $device = CustomerDevice::where('customer_id', $customer->id)->first();
        $this->assertSame('user-baru', $device->pppoe_username);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $device->mac_address);
    }

    public function test_perangkat_menimpa_nilai_yang_diisi_teknisi(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'pppoe_username' => 'user-teknisi']);
        $this->seedOutbox($customer, 'installation:7:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:7:activation:1',
            'perangkat' => ['pppoe_username' => 'user-website-b'],
        ]);

        $response->assertOk();
        $this->assertSame('user-website-b', CustomerDevice::where('customer_id', $customer->id)->first()->pppoe_username);
    }

    // ── Bentuk respons ───────────────────────────────────────────────────

    /**
     * Respons endpoint #3 HARUS beda dari endpoint #2 — kalau cuma balikin
     * mini_pop_code/distribution_code (state assignment lama) tanpa
     * mengonfirmasi field perangkat yang barusan ditulis, Website B gak
     * punya bukti balik apa yang beneran kesimpen di request INI.
     */
    public function test_response_menyertakan_perangkat_yang_baru_disimpan(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:20:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:20:activation:1',
            'perangkat' => [
                'pppoe_username' => 'user-baru',
                'pppoe_password' => 'rahasia123',
                'olt_number' => '3',
                'olt_slot' => '1',
                'olt_port' => '8',
                'vlan' => '301',
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'perangkat' => [
                'pppoe_username' => 'user-baru',
                'olt_number' => '3',
                'olt_slot' => '1',
                'olt_port' => '8',
                'vlan' => '301',
            ],
        ]);
        $response->assertJsonMissingPath('perangkat.pppoe_password');
        $response->assertJsonMissingPath('perangkat.device_type');
    }

    public function test_response_perangkat_hanya_field_yang_dikirim(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:21:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:21:activation:1',
            'perangkat' => ['olt_number' => 'OLT-09'],
        ]);

        $response->assertOk();
        $response->assertJson(['perangkat' => ['olt_number' => 'OLT-09']]);
        $response->assertJsonMissingPath('perangkat.pppoe_username');
    }

    // ── Keamanan kredensial ──────────────────────────────────────────────

    public function test_pppoe_password_tidak_pernah_masuk_audit_log_mentah(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:8:activation:1');

        $this->postDevice([
            'idempotency_key' => 'installation:8:activation:1',
            'perangkat' => ['pppoe_password' => 'rahasia-banget'],
        ]);

        $log = AuditLog::where('action', 'network_device_update')->first();
        $this->assertNotNull($log);
        $this->assertSame('[diubah]', $log->new_values['pppoe_password']);
        $this->assertStringNotContainsString('rahasia-banget', json_encode($log->new_values));

        // Baris audit OTOMATIS dari trait CustomerDevice pun tidak boleh bocor.
        $allLogsJson = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('rahasia-banget', $allLogsJson);
    }

    public function test_pppoe_password_tidak_dikembalikan_di_response(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:9:activation:1');

        $response = $this->postDevice([
            'idempotency_key' => 'installation:9:activation:1',
            'perangkat' => ['pppoe_password' => 'rahasia-banget'],
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('rahasia-banget', $response->getContent());
    }

    public function test_audit_log_user_id_null_dan_sumber_ditandai(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:10:activation:1');

        $this->postDevice([
            'idempotency_key' => 'installation:10:activation:1',
            'perangkat' => ['pppoe_username' => 'user-a'],
        ]);

        $log = AuditLog::where('action', 'network_device_update')->first();
        $this->assertNull($log->user_id);
        $this->assertSame('API — Website B integration', $log->user_agent);
    }

    // ── Idempotency & dedup ──────────────────────────────────────────────

    public function test_retry_key_dan_body_identik_tidak_menulis_audit_log_dobel(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:11:activation:1');

        $body = [
            'idempotency_key' => 'installation:11:activation:1',
            'perangkat' => ['pppoe_username' => 'user-a'],
        ];

        $first = $this->postDevice($body);
        $second = $this->postDevice($body);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'network_device_update')->count());
    }

    /**
     * Skenario lintas endpoint: idempotency_key yang sama dipakai dulu di
     * network-assignment, lalu di network-device (dua endpoint terpisah,
     * satu key). Keduanya harus diproses sebagai kejadian yang sah, gak ada
     * yang salah ke-block sebagai duplikat — inilah alasan dedup di-scope
     * ke key+hash, bukan key doang (business-logic.md §"Idempotency").
     */
    public function test_key_sama_dipakai_lintas_endpoint_diproses_sebagai_kejadian_terpisah(): void
    {
        $cabang = $this->seedCabang();
        $miniPop = $this->seedMiniPop($cabang);
        $this->seedDistribution($miniPop);
        $customer = $this->seedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:12:activation:1');

        $assignResponse = $this->withToken('write-token-456')->postJson('/api/v1/installations/network-assignment', [
            'idempotency_key' => 'installation:12:activation:1',
            'mini_pop_code' => 'C1',
            'distribution_code' => 'A',
        ]);
        $deviceResponse = $this->postDevice([
            'idempotency_key' => 'installation:12:activation:1',
            'perangkat' => ['pppoe_username' => 'user-susulan'],
        ]);

        $assignResponse->assertOk();
        $deviceResponse->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'network_assignment')->count());
        $this->assertSame(1, AuditLog::where('action', 'network_device_update')->count());
        $this->assertSame('user-susulan', CustomerDevice::where('customer_id', $customer->id)->first()->pppoe_username);
    }

    // ── Rate limit ───────────────────────────────────────────────────────

    public function test_rate_limit_20_per_menit(): void
    {
        $cabang = $this->seedCabang();
        $customer = $this->seedAssignedCustomer($cabang);
        $this->seedOutbox($customer, 'installation:13:activation:1');

        for ($i = 0; $i < 20; $i++) {
            $this->postDevice(['idempotency_key' => 'nonexistent-'.$i]);
        }

        $response = $this->postDevice([
            'idempotency_key' => 'installation:13:activation:1',
            'perangkat' => ['pppoe_username' => 'user-a'],
        ]);

        $response->assertStatus(429);
    }
}
