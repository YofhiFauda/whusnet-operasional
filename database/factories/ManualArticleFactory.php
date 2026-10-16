<?php

namespace Database\Factories;

use App\Models\ManualArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualArticle>
 */
class ManualArticleFactory extends Factory
{
    protected $model = ManualArticle::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(4),
            'category' => 'Umum',
            'summary' => fake()->sentence(),
            'body_markdown' => '## Langkah\n\n1. Periksa data.\n2. Simpan hasil.',
            'required_permission' => null,
            'route_names' => [],
        ];
    }
}
