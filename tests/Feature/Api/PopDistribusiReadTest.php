<?php

namespace Tests\Feature\Api;

use App\Models\Distribution;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint #1 — `GET /api/v1/pop-distribusi`
 * (docs/api/api-pop-distribusi/business-logic.md).
 */
class PopDistribusiReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.pop_distribusi_read_token' => 'read-token-123']);
    }

    private function seedHierarchy(): void
    {
        $cabang = Pop::create([
            'code' => 'JTS', 'pop_code' => 'PNR-JTS', 'name' => 'Jetis',
            'type' => 'cabang', 'status' => 'active', 'cid_prefix' => 'C',
        ]);

        $miniPop = Pop::create([
            'code' => 'JTS-C1', 'pop_code' => 'C1', 'name' => 'Mini POP C1',
            'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $cabang->id,
        ]);

        Distribution::create(['pop_id' => $miniPop->id, 'code' => 'A', 'name' => 'Distribusi A', 'description' => '-']);
        Distribution::create(['pop_id' => $miniPop->id, 'code' => 'B', 'name' => 'Distribusi B', 'description' => '-']);
    }

    public function test_balikin_seluruh_hierarki_cabang_mini_pop_distribusi(): void
    {
        $this->seedHierarchy();

        $response = $this->withToken('read-token-123')->getJson('/api/v1/pop-distribusi');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                [
                    'pop_code' => 'PNR-JTS',
                    'pop_name' => 'Jetis',
                    'mini_pops' => [
                        [
                            'code' => 'C1',
                            'name' => 'Mini POP C1',
                            'distributions' => [
                                ['code' => 'A', 'name' => 'Distribusi A'],
                                ['code' => 'B', 'name' => 'Distribusi B'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_tanpa_token_ditolak_401(): void
    {
        $this->seedHierarchy();

        $response = $this->getJson('/api/v1/pop-distribusi');

        $response->assertStatus(401);
    }

    public function test_token_salah_ditolak_401(): void
    {
        $this->seedHierarchy();

        $response = $this->withToken('token-ngasal')->getJson('/api/v1/pop-distribusi');

        $response->assertStatus(401);
    }

    public function test_token_tulis_tidak_bisa_dipakai_baca(): void
    {
        config(['webhooks.network_assignment_write_token' => 'write-token-456']);
        $this->seedHierarchy();

        $response = $this->withToken('write-token-456')->getJson('/api/v1/pop-distribusi');

        $response->assertStatus(401);
    }
}
