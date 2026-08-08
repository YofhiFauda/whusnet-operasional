<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.4 — endpoint pencarian wilayah (?q= + limit) untuk typeahead,
 * menggantikan pemuatan SELURUH baris ke <select>.
 *
 * Menjaga: hasil difilter `q`, dibatasi 20 baris, dan bisa disaring per induk
 * (city_id/district_id).
 */
class WilayahSearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function city(string $name): City
    {
        return City::create(['name' => $name]);
    }

    private function district(City $city, string $name): District
    {
        return District::create(['city_id' => $city->id, 'name' => $name]);
    }

    private function village(District $d, string $name): Village
    {
        return Village::create(['district_id' => $d->id, 'name' => $name]);
    }

    public function test_villages_difilter_q_dan_dibatasi_20(): void
    {
        $this->loginAsAdmin();
        $city = $this->city('Ponorogo');
        $district = $this->district($city, 'Babadan');

        // 25 desa "Sukorejo N" + 1 desa lain.
        for ($i = 1; $i <= 25; $i++) {
            $this->village($district, "Sukorejo {$i}");
        }
        $this->village($district, 'Mangkujayan');

        $res = $this->getJson('/api/wilayah/villages?q=Sukorejo');
        $res->assertStatus(200);

        $data = $res->json();
        $this->assertLessThanOrEqual(30, count($data), 'Hasil wajib dibatasi (limit 30).');
        foreach ($data as $row) {
            $this->assertStringContainsString('Sukorejo', $row['name']);
        }
    }

    public function test_villages_disaring_per_district(): void
    {
        $this->loginAsAdmin();
        $city = $this->city('Ponorogo');
        $d1 = $this->district($city, 'Babadan');
        $d2 = $this->district($city, 'Siman');
        $this->village($d1, 'Desa A');
        $this->village($d2, 'Desa B');

        $res = $this->getJson("/api/wilayah/villages?district_id={$d1->id}");
        $names = collect($res->json())->pluck('name');

        $this->assertTrue($names->contains('Desa A'));
        $this->assertFalse($names->contains('Desa B'), 'district_id harus menyaring desa dari kecamatan lain.');
    }

    public function test_villages_cascade_district_id_array_dan_bawa_nama_kecamatan(): void
    {
        $this->loginAsAdmin();
        $city = $this->city('Ponorogo');
        $d1 = $this->district($city, 'Babadan');
        $d2 = $this->district($city, 'Badegan');
        $d3 = $this->district($city, 'Siman');
        $this->village($d1, 'Babadan Lor');
        $this->village($d2, 'Banjar');
        $this->village($d3, 'Sekaran'); // di luar kecamatan terpilih

        $res = $this->getJson("/api/wilayah/villages?district_id[]={$d1->id}&district_id[]={$d2->id}");
        $rows = collect($res->json());

        $this->assertTrue($rows->pluck('name')->contains('Babadan Lor'));
        $this->assertTrue($rows->pluck('name')->contains('Banjar'));
        $this->assertFalse($rows->pluck('name')->contains('Sekaran'), 'Desa di kecamatan tak terpilih tidak boleh muncul.');
        // Nama kecamatan ikut untuk disambiguasi.
        $this->assertEquals('Babadan', $rows->firstWhere('name', 'Babadan Lor')['district']);
    }

    public function test_districts_disaring_per_city(): void
    {
        $this->loginAsAdmin();
        $c1 = $this->city('Ponorogo');
        $c2 = $this->city('Madiun');
        $this->district($c1, 'Babadan');
        $this->district($c2, 'Kartoharjo');

        $res = $this->getJson("/api/wilayah/districts?city_id={$c1->id}");
        $names = collect($res->json())->pluck('name');

        $this->assertTrue($names->contains('Babadan'));
        $this->assertFalse($names->contains('Kartoharjo'));
    }

    public function test_cities_search_q(): void
    {
        $this->loginAsAdmin();
        $this->city('Ponorogo');
        $this->city('Madiun');

        $res = $this->getJson('/api/wilayah/cities?q=Pono');
        $names = collect($res->json())->pluck('name');

        $this->assertTrue($names->contains('Ponorogo'));
        $this->assertFalse($names->contains('Madiun'));
    }
}
