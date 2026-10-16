<?php

namespace Tests\Feature;

use App\Models\NetworkNode;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkNodeLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_address_keeps_inherited_coordinates_and_region(): void
    {
        $pop = Pop::factory()->create([
            'type' => 'cabang',
            'address' => 'Kantor cabang',
            'village' => 'Desa Satu',
            'district' => 'Kecamatan Dua',
            'city' => 'Kota Tiga',
            'latitude' => '-7.1234567',
            'longitude' => '110.1234567',
        ]);
        $parent = NetworkNode::factory()->create(['anchor_pop_id' => $pop->id, 'address' => 'Tiang utama']);
        $child = NetworkNode::factory()->create(['anchor_pop_id' => null, 'parent_id' => $parent->id, 'address' => 'Rumah nomor 7']);

        $location = $child->effectiveLocation();

        $this->assertSame('Rumah nomor 7', $location['address']);
        $this->assertSame('Desa Satu', $location['village']);
        $this->assertSame('Kecamatan Dua', $location['district']);
        $this->assertSame('Kota Tiga', $location['city']);
        $this->assertEqualsWithDelta(-7.1234567, (float) $location['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(110.1234567, (float) $location['longitude'], 0.0000001);
    }
}
