<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAcquisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAcquisition>
 */
class CustomerAcquisitionFactory extends Factory
{
    protected $model = CustomerAcquisition::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ];
    }
}
