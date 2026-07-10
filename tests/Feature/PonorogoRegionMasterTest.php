<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\Village;
use Database\Seeders\PonorogoRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PonorogoRegionMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_ponorogo_region_master_seed_has_expected_totals(): void
    {
        $this->seed(PonorogoRegionSeeder::class);

        $this->assertSame(1, City::query()->where('name', 'Ponorogo')->count());
        $this->assertSame(21, District::query()->count());
        $this->assertSame(307, Village::query()->count());
    }

    public function test_region_master_endpoint_returns_nested_region_data(): void
    {
        $this->seed(PonorogoRegionSeeder::class);
        $this->loginAsAdmin();

        $response = $this->getJson('/master/wilayah?search=Nologaten');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ponorogo')
            ->assertJsonPath('data.0.districts.0.name', 'Ponorogo')
            ->assertJsonPath('data.0.districts.0.villages.0.name', 'Nologaten')
            ->assertJsonPath('data.0.districts.0.villages.0.postal_code', '63411');
    }
}
