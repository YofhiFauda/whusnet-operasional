<?php

namespace Database\Factories;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkNode>
 */
class NetworkNodeFactory extends Factory
{
    protected $model = NetworkNode::class;

    /**
     * Default: node puncak (root) ditambatkan langsung ke Cabang baru.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'anchor_pop_id' => Pop::factory()->create(['type' => 'cabang'])->id,
            'node_type_id' => NetworkNodeType::factory(),
            'code' => $this->faker->unique()->bothify('NODE-####'),
            'name' => ucfirst($this->faker->words(2, true)),
            'capacity' => null,
            'status' => 'active',
        ];
    }

    /**
     * Node di dalam node induk lain — bukan ditambatkan ke POP. Dipakai
     * membangun rantai ODC → ODP → splitter di test.
     */
    public function child(NetworkNode $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent->id,
            'anchor_pop_id' => null,
        ]);
    }
}
