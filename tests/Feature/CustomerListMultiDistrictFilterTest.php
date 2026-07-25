<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.4 — filter kecamatan multi-pilih (combobox chips) → whereIn.
 *
 * Menjaga: halaman render tanpa $districts (combobox typeahead), dan filter
 * district_id[] menyaring beberapa kecamatan sekaligus.
 */
class CustomerListMultiDistrictFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_multi_district_pakai_where_in(): void
    {
        $this->loginAsAdmin();
        $pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
        $city = City::create(['name' => 'Ponorogo']);
        $dA = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $dB = District::create(['city_id' => $city->id, 'name' => 'Siman']);
        $dC = District::create(['city_id' => $city->id, 'name' => 'Jetis']);

        $mk = fn (string $name, District $d) => Customer::factory()->create([
            'full_name' => $name, 'status' => 'active', 'pop_id' => $pop->id, 'district_id' => $d->id,
        ]);
        $mk('Warga Babadan', $dA);
        $mk('Warga Siman', $dB);
        $mk('Warga Jetis', $dC);

        // Halaman render tanpa $districts (combobox).
        $this->get('/customers')->assertStatus(200);

        // Filter DUA kecamatan sekaligus.
        $res = $this->get("/customers?district_id[]={$dA->id}&district_id[]={$dB->id}&status=");
        $res->assertSee('Warga Babadan');
        $res->assertSee('Warga Siman');
        $res->assertDontSee('Warga Jetis');
    }

    public function test_filter_multi_village_pakai_where_in(): void
    {
        $this->loginAsAdmin();
        $pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
        $city = City::create(['name' => 'Ponorogo']);
        $d = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $vA = Village::create(['district_id' => $d->id, 'name' => 'Desa A']);
        $vB = Village::create(['district_id' => $d->id, 'name' => 'Desa B']);
        $vC = Village::create(['district_id' => $d->id, 'name' => 'Desa C']);

        $mk = fn (string $n, Village $v) => Customer::factory()->create([
            'full_name' => $n, 'status' => 'active', 'pop_id' => $pop->id,
            'district_id' => $d->id, 'village_id' => $v->id,
        ]);
        $mk('Warga A', $vA);
        $mk('Warga B', $vB);
        $mk('Warga C', $vC);

        $res = $this->get("/customers?village_id[]={$vA->id}&village_id[]={$vB->id}&status=");
        $res->assertSee('Warga A');
        $res->assertSee('Warga B');
        $res->assertDontSee('Warga C');
    }

    public function test_kompatibel_district_id_tunggal(): void
    {
        $this->loginAsAdmin();
        $pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
        $city = City::create(['name' => 'Ponorogo']);
        $dA = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $dB = District::create(['city_id' => $city->id, 'name' => 'Siman']);
        Customer::factory()->create(['full_name' => 'Warga Babadan', 'status' => 'active', 'pop_id' => $pop->id, 'district_id' => $dA->id]);
        Customer::factory()->create(['full_name' => 'Warga Siman', 'status' => 'active', 'pop_id' => $pop->id, 'district_id' => $dB->id]);

        // district_id tunggal (bukan array) tetap jalan.
        $res = $this->get("/customers?district_id={$dA->id}&status=");
        $res->assertSee('Warga Babadan');
        $res->assertDontSee('Warga Siman');
    }
}
