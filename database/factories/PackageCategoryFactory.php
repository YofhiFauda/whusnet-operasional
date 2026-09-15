<?php

namespace Database\Factories;

use App\Models\PackageCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageCategory>
 */
class PackageCategoryFactory extends Factory
{
    protected $model = PackageCategory::class;

    public function definition(): array
    {
        return [
            'name' => 'Kategori '.fake()->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => 0,
            'installation_fee_approval_role_id' => null,
        ];
    }
}
