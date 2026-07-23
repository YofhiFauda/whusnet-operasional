<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name,
            'customer_code' => 'REG'.$this->faker->unique()->numberBetween(100000, 999999),
            'phone' => $this->faker->phoneNumber,
            'status' => 'aktif',
            'pop_id' => Pop::factory(),
            'registration_date' => now(),
        ];
    }
}
