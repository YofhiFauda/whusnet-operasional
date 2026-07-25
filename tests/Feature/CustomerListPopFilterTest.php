<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.4b — filter POP (Cabang) + Mini POP multi-pilih (dropdown, cascade).
 *
 * Menjaga: render komponen, filter pop_id[] (cabang → customers.pop_id) &
 * mini_pop_id[] (Mini POP → customers.mini_pop_id) via whereIn, endpoint
 * /api/pop/mini cascade dari cabang, dan scope forUser di endpoint.
 */
class CustomerListPopFilterTest extends TestCase
{
    use RefreshDatabase;

    private function cabang(string $code): Pop
    {
        return Pop::create([
            'code' => $code, 'pop_code' => $code, 'registration_prefix' => 'RQ',
            'cid_prefix' => $code, 'name' => "Cabang {$code}", 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function mini(Pop $cabang, string $code): Pop
    {
        return Pop::create([
            'code' => $code, 'pop_code' => $code, 'name' => "Mini {$code}",
            'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $cabang->id,
        ]);
    }

    public function test_filter_cabang_dan_mini_pop_where_in(): void
    {
        $this->loginAsAdmin();
        $cA = $this->cabang('AAA');
        $cB = $this->cabang('BBB');
        $mA1 = $this->mini($cA, 'AAA1');
        $mA2 = $this->mini($cA, 'AAA2');

        Customer::factory()->create(['full_name' => 'Di Cabang A', 'status' => 'active', 'pop_id' => $cA->id, 'mini_pop_id' => $mA1->id]);
        Customer::factory()->create(['full_name' => 'Di Cabang A Mini2', 'status' => 'active', 'pop_id' => $cA->id, 'mini_pop_id' => $mA2->id]);
        Customer::factory()->create(['full_name' => 'Di Cabang B', 'status' => 'active', 'pop_id' => $cB->id]);

        // Render komponen tanpa error.
        $this->get('/customers')->assertStatus(200);

        // Filter Cabang A.
        $res = $this->get("/customers?pop_id[]={$cA->id}&status=");
        $res->assertSee('Di Cabang A');
        $res->assertDontSee('Di Cabang B');

        // Filter Mini POP A1 saja.
        $res = $this->get("/customers?mini_pop_id[]={$mA1->id}&status=");
        $res->assertSee('Di Cabang A');
        $res->assertDontSee('Di Cabang A Mini2');
    }

    public function test_endpoint_mini_cascade_dari_cabang(): void
    {
        $this->loginAsAdmin();
        $cA = $this->cabang('AAA');
        $cB = $this->cabang('BBB');
        $this->mini($cA, 'AAA1');
        $this->mini($cB, 'BBB1');

        $res = $this->getJson("/api/pop/mini?pop_id[]={$cA->id}");
        $names = collect($res->json())->pluck('name');

        $this->assertTrue($names->contains('Mini AAA1'));
        $this->assertFalse($names->contains('Mini BBB1'), 'Mini dari cabang tak terpilih tidak boleh muncul.');
    }

    public function test_endpoint_mini_kosong_tanpa_cabang(): void
    {
        $this->loginAsAdmin();
        $cA = $this->cabang('AAA');
        $this->mini($cA, 'AAA1');

        $this->getJson('/api/pop/mini')->assertExactJson([]);
    }
}
