<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /api/customer-portal/ping` (docs/api/api-portal-pelanggan/, Fase 0) —
 * health-check permanen, tanpa token, dipakai membuktikan pondasi API portal:
 * routing di bawah prefix yang benar dan error di bawah
 * /api/customer-portal/* tetap JSON (bukan halaman Blade).
 *
 * Endpoint ini SENGAJA response mentah (`response()->json()`), bukan lewat
 * `App\Http\Resources\ApiResource` — data kosong, gak ada gunanya dibungkus
 * envelope {data, meta}. Envelope+serialisasi desimal dibuktikan terpisah di
 * `ApiResourceTest`.
 */
class CustomerPortalPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_mengembalikan_json_status_ok(): void
    {
        $response = $this->getJson('/api/customer-portal/ping');

        $response->assertOk();
        $response->assertJson(['data' => ['status' => 'ok']]);
    }

    public function test_rute_tidak_dikenal_di_bawah_customer_portal_tetap_json_404(): void
    {
        // withExceptions() di bootstrap/app.php sudah generik untuk semua
        // /api/*, tapi ini kriteria "selesai" Fase 0 yang eksplisit di
        // rencana-implementasi.md — wajib dibuktikan, bukan diasumsikan.
        $response = $this->getJson('/api/customer-portal/rute-tidak-ada');

        $response->assertStatus(404);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }
}
