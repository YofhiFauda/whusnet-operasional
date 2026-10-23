<?php

namespace Database\Factories;

use App\Models\CustomerCableCrossing;
use App\Models\CustomerCableCrossingPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCableCrossingPoint>
 */
class CustomerCableCrossingPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_cable_crossing_id' => CustomerCableCrossing::factory(),
            'urutan' => 1,
            'label' => null,
            'latitude' => fake()->latitude(-8.0, -7.5),
            'longitude' => fake()->longitude(111.5, 112.0),
        ];
    }
}
