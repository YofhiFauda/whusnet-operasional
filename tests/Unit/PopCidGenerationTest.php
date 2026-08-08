<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\Distribution;
use App\Models\District;
use App\Models\Pop;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopCidGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_complex_cid_format()
    {
        $branch = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C', 'type' => 'cabang']);
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C1', 'type' => 'mini_pop', 'parent_id' => $branch->id]);

        $city = City::create(['name' => 'PONOROGO']);
        $district = District::create(['city_id' => $city->id, 'name' => 'PONOROGO']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'MANGKUJAYAN']);

        $dist = Distribution::create([
            'pop_id' => $pop->id,
            'code' => 'X1A',
            'name' => 'Dist Jetis 1',
        ]);

        // customer_code menggunakan format baru: {cid_prefix}00{registration_prefix}{######}
        $customer = Customer::create([
            'pop_id' => $pop->id,
            'customer_code' => 'C00RQ000001',
            'full_name' => 'DYAH PURBA',
            'primary_phone' => '08123456789',
            'registration_date' => now(),
            'village_id' => $village->id,
            'status' => 'registered',
        ]);

        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'olt_number' => '1',
            'olt_port' => '1/1/1',
        ]);

        $cid = $pop->generateComplexCid($customer, $dist);

        // CID format: {cid_prefix}{mini_pop_or_olt}{dist_code}{request_id}
        // = C + 1 + X1A + RQ000001
        $this->assertEquals('C1X1ARQ000001', $cid);

        $pppoe = $pop->generatePppoeUsername($customer, $dist);
        // PPPOE Username format: {CID}_{DESA}_{NAMA}
        $this->assertEquals('C1X1ARQ000001_MANGKUJAYAN_DYAHPURBA', $pppoe);
    }

    public function test_generates_default_zero_segments_when_unassigned()
    {
        // Skema 3 (ID_NUMBERING_RULES.md): belum di-assign mini POP maupun
        // distribusi → kedua segmen default "0", bukan "XX"/"1" (bug lama).
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C', 'type' => 'cabang']);

        $city = City::create(['name' => 'PONOROGO']);
        $district = District::create(['city_id' => $city->id, 'name' => 'PONOROGO']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'MANGKUJAYAN']);

        $customer = Customer::create([
            'pop_id' => $pop->id,
            'customer_code' => 'C00RQ000004',
            'full_name' => 'BUDI SANTOSO',
            'primary_phone' => '08123456789',
            'registration_date' => now(),
            'village_id' => $village->id,
            'status' => 'registered',
        ]);

        $cid = $pop->generateComplexCid($customer, null);

        $this->assertEquals('C00RQ000004', $cid);
    }

    public function test_extract_bare_registration_id_from_customer_code()
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'JTS']);

        // Format normal: strip cid_prefix + "00" → bare RQ######
        $this->assertSame('RQ000001', $pop->extractBareRegistrationId('C00RQ000001'));
        $this->assertSame('RQ000474', $pop->extractBareRegistrationId('C00RQ000474'));

        // Format berbeda cid_prefix
        $pop2 = Pop::factory()->create(['cid_prefix' => 'D', 'registration_prefix' => 'RQ', 'pop_code' => 'SMN']);
        $this->assertSame('RQ001296', $pop2->extractBareRegistrationId('D00RQ001296'));
    }
}
