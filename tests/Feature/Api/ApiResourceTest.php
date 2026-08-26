<?php

namespace Tests\Feature\Api;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `App\Http\Resources\ApiResource` (docs/api/api-portal-pelanggan/, Fase 0) —
 * base class dipakai Resource entitas nyata mulai Fase 2 (Invoice, Payment,
 * Ticket). Feature test (bukan unit) karena envelope {data, meta} baru
 * terbentuk lewat siklus response HTTP penuh (`with()` cuma dipanggil
 * `JsonResource::toResponse()`), bukan sekadar `toArray()`.
 */
class ApiResourceTest extends TestCase
{
    public function test_envelope_data_dan_meta_terbentuk_lewat_response_http(): void
    {
        Route::get('/_test/api-resource-dummy', function () {
            return new class(['nama' => 'Contoh', 'nominal' => 150000]) extends ApiResource
            {
                public function toArray(Request $request): array
                {
                    return [
                        'nama' => $this->resource['nama'],
                        'nominal' => $this->money($this->resource['nominal']),
                    ];
                }
            };
        });

        $response = $this->getJson('/_test/api-resource-dummy');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'nama' => 'Contoh',
                // String desimal, bukan float — inti kontrak nominal API portal.
                'nominal' => '150000.00',
            ],
        ]);
        $this->assertNotEmpty($response->json('meta.generated_at'));
    }
}
