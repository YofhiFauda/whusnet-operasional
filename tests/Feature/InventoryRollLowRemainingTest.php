<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use Database\Seeders\ItemCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `InventoryRoll::isLowRemaining()`/`scopeLowRemaining()` — flag "Sisa
 * Kecil" (docs/plan/warehouse/analisa-gap-roll-kabel.md §8). Cuma berarti
 * kalau `item.minimum_length` diisi, roll masih idle/aktif (bukan
 * DEPLETED/DAMAGED/LOST/SCRAPPED/QUARANTINE/TRANSFERRED), dan sisa > 0.
 */
class InventoryRollLowRemainingTest extends TestCase
{
    use RefreshDatabase;

    private Item $itemWithThreshold;

    private Item $itemWithoutThreshold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ItemCategorySeeder::class);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->itemWithThreshold = Item::create(['code' => 'ILR-ROLL', 'name' => 'Kabel FO Low Remaining', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000, 'minimum_length' => 50]);
        $this->itemWithoutThreshold = Item::create(['code' => 'ILR-ROLL-2', 'name' => 'Kabel FO No Threshold', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
    }

    private function makeRoll(Item $item, float $lengthRemaining, RollStatus $status, string $code): InventoryRoll
    {
        return InventoryRoll::create([
            'item_id' => $item->id,
            'roll_code' => $code,
            'length_total' => 1000,
            'length_remaining' => $lengthRemaining,
            'status' => $status,
        ]);
    }

    #[Test]
    public function sisa_di_bawah_ambang_status_available_di_flag(): void
    {
        $roll = $this->makeRoll($this->itemWithThreshold, 30, RollStatus::AVAILABLE, 'ILR-1');

        $this->assertTrue($roll->isLowRemaining());
        $this->assertTrue(InventoryRoll::query()->lowRemaining()->where('inventory_rolls.id', $roll->id)->exists());
    }

    #[Test]
    public function sisa_di_atas_ambang_tidak_di_flag(): void
    {
        $roll = $this->makeRoll($this->itemWithThreshold, 200, RollStatus::AVAILABLE, 'ILR-2');

        $this->assertFalse($roll->isLowRemaining());
        $this->assertFalse(InventoryRoll::query()->lowRemaining()->where('inventory_rolls.id', $roll->id)->exists());
    }

    #[Test]
    public function tanpa_minimum_length_di_item_tidak_pernah_di_flag(): void
    {
        $roll = $this->makeRoll($this->itemWithoutThreshold, 5, RollStatus::AVAILABLE, 'ILR-3');

        $this->assertFalse($roll->isLowRemaining());
    }

    #[Test]
    public function roll_depleted_tidak_di_flag_walau_sisa_0(): void
    {
        $roll = $this->makeRoll($this->itemWithThreshold, 0, RollStatus::DEPLETED, 'ILR-4');

        $this->assertFalse($roll->isLowRemaining());
    }

    #[Test]
    public function roll_scrapped_dengan_sisa_kecil_tidak_di_flag(): void
    {
        $roll = $this->makeRoll($this->itemWithThreshold, 20, RollStatus::SCRAPPED, 'ILR-5');

        $this->assertFalse($roll->isLowRemaining());
    }

    #[Test]
    public function roll_issued_sisa_kecil_tetap_di_flag(): void
    {
        $roll = $this->makeRoll($this->itemWithThreshold, 15, RollStatus::ISSUED, 'ILR-6');

        $this->assertTrue($roll->isLowRemaining());
    }
}
