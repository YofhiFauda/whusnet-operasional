<?php

namespace Database\Factories;

use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pop>
 */
class PopFactory extends Factory
{
    protected $model = Pop::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('POP-###'),
            'pop_code' => $this->faker->unique()->bothify('??'),
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $this->faker->city.' POP',
            'type' => 'cabang',
            'status' => 'active',
        ];
    }
}
