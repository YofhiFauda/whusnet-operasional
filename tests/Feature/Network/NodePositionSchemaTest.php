<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Slot/PON/Port Switch menjadi baris `network_nodes` (ADHOC-213): skema
 * kolom posisi, tipe baru, dan aturan induk.
 */
class NodePositionSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function typeId(string $code): int
    {
        return (int) NetworkNodeType::where('code', $code)->where('is_active', true)->value('id');
    }

    private function node(string $typeCode, ?NetworkNode $parent, array $attrs = []): NetworkNode
    {
        return NetworkNode::factory()->create(array_merge([
            'node_type_id' => $this->typeId($typeCode),
            'parent_id' => $parent?->id,
            'anchor_pop_id' => $parent?->anchor_pop_id,
        ], $attrs));
    }

    #[Test]
    public function tipe_posisi_dan_aturan_induk_ada(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);

        $expected = [
            'olt_slot' => 'olt_chassis',
            'olt_pon' => 'olt_slot',
            'switch_slot' => 'switch_mini_pop',
            'switch_port' => 'switch_slot',
            'odp' => 'olt_pon',
        ];
        foreach ($expected as $child => $parent) {
            $this->assertNotSame(0, $this->typeId($child), "Tipe {$child} harus aktif.");
            $this->assertTrue(
                DB::table('network_node_type_rules')
                    ->where('child_type_id', $this->typeId($child))
                    ->where('parent_type_id', $this->typeId($parent))
                    ->exists(),
                "Aturan {$child} -> {$parent} harus ada."
            );
        }

        $this->assertFalse(
            DB::table('network_node_type_rules')
                ->where('child_type_id', $this->typeId('odp'))
                ->whereIn('anchor_pop_type', ['cabang', 'mini_pop'])
                ->exists(),
            'Aturan anchor ODP ke Cabang/Mini POP sudah dicabut.'
        );
    }

    #[Test]
    public function nomor_unik_per_induk_bukan_global(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $oltA = $this->node('olt_chassis', null, ['code' => 'OLT-A']);
        $oltB = $this->node('olt_chassis', null, ['code' => 'OLT-B']);

        $this->node('olt_slot', $oltA, ['code' => 'OLT-A-S1', 'number' => 1]);
        $this->node('olt_slot', $oltB, ['code' => 'OLT-B-S1', 'number' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->node('olt_slot', $oltA, ['code' => 'OLT-A-S1-DUP', 'number' => 1]);
    }

    #[Test]
    public function kode_panjang_120_karakter_tersimpan(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $kode = str_repeat('X', 120);
        $olt = $this->node('olt_chassis', null, ['code' => $kode]);

        $this->assertSame($kode, $olt->fresh()->code);
    }
}
