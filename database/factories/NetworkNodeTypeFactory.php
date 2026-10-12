<?php

namespace Database\Factories;

use App\Models\NetworkNodeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkNodeType>
 */
class NetworkNodeTypeFactory extends Factory
{
    protected $model = NetworkNodeType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('TYPE-####'),
            'name' => $this->faker->word(),
            'has_ports' => false,
            'monitors_capacity' => false,
            'max_ports' => null,
            'red_zone_threshold' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
