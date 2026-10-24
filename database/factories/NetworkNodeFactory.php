<?php

namespace Database\Factories;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureSlotKind;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

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

    /**
     * Slot di bawah perangkat: OLT chassis -> `olt_slot`, Switch Mini POP ->
     * `switch_slot` (dibaca dari kode tipe perangkat).
     */
    public function slot(NetworkNode $device, int $number): static
    {
        $kind = InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code)
            ?? throw new InvalidArgumentException('Perangkat induk Slot harus OLT chassis atau Switch Mini POP.');

        // TODO Task 3: samakan dengan InfrastructurePositionService::derivedCode().
        return $this->position($device, $kind->typeCode(), $number, "{$device->code}-S{$number}", "Slot {$number}");
    }

    public function pon(NetworkNode $slot, int $number): static
    {
        // TODO Task 3: samakan dengan InfrastructurePositionService::derivedCode().
        return $this->position($slot, InfrastructurePortKind::PON->typeCode(), $number, "{$slot->code}-P{$number}", "PON {$number}");
    }

    public function switchPort(NetworkNode $slot, int $number): static
    {
        return $this->position($slot, InfrastructurePortKind::SWITCH->typeCode(), $number, "{$slot->code}-P{$number}", "Port {$number}");
    }

    private function position(NetworkNode $parent, string $typeCode, int $number, string $code, string $name): static
    {
        $typeId = NetworkNodeType::firstOrCreate(
            ['code' => $typeCode],
            ['name' => $name, 'is_active' => true],
        )->id;

        return $this->state(fn (array $attributes): array => [
            'node_type_id' => $typeId,
            'parent_id' => $parent->id,
            'anchor_pop_id' => null,
            'number' => $number,
            'code' => $code,
            'name' => $name,
        ]);
    }
}
