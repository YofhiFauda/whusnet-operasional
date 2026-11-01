<?php

namespace Tests\Unit;

use App\Helpers\FormatHelper;
use App\Models\InventoryTransaction;
use App\Models\Item;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `qtyWithPack()` murni presentasi — baca `entered_pack_qty` (snapshot audit
 * di ledger, lihat migration `add_entered_pack_qty_to_inventory_transactions_table`)
 * kalau ada, selain itu balik ke tampilan qty biasa. `qty` (pcs) TETAP
 * satu-satunya sumber kebenaran; method ini gak pernah menghitung ulang
 * konversi Pack→pcs dari `item->pack_size`.
 */
class FormatHelperPackQtyTest extends TestCase
{
    #[Test]
    public function qty_with_pack_shows_pack_and_pcs_when_entered_pack_qty_present(): void
    {
        $line = new InventoryTransaction(['qty' => 100, 'entered_pack_qty' => 2]);
        $line->setRelation('item', new Item(['unit' => 'pcs']));

        $this->assertSame('2 Pack (100 pcs)', FormatHelper::qtyWithPack($line));
    }

    #[Test]
    public function qty_with_pack_shows_plain_qty_when_entered_pack_qty_null(): void
    {
        $line = new InventoryTransaction(['qty' => 7.5]);
        $line->setRelation('item', new Item(['unit' => 'meter']));

        $this->assertSame('7,5 meter', FormatHelper::qtyWithPack($line));
    }
}
