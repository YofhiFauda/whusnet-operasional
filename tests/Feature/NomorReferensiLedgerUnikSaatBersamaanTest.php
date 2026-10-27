<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Services\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NomorReferensiLedgerUnikSaatBersamaanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function nomor_ledger_berurutan_unik_dan_melanjutkan_nomor_lama(): void
    {
        $category = ItemCategory::create(['code' => 'AUDIT-NUMBER', 'name' => 'Audit Number', 'default_unit' => 'pcs']);
        $item = Item::create([
            'code' => 'AUDIT-NUMBER',
            'name' => 'Audit Number',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
        ]);
        InventoryTransaction::create([
            'type' => InventoryTransactionType::ISSUE,
            'reference_number' => 'ISS-'.date('Ymd').'-000123',
            'item_id' => $item->id,
            'qty' => 1,
        ]);

        $numbers = [];
        for ($i = 0; $i < 20; $i++) {
            $numbers[] = app(NumberSequenceService::class)->ledgerNumber('ISS');
        }

        $this->assertSame('ISS-'.date('Ymd').'-000124', $numbers[0]);
        $this->assertSame('ISS-'.date('Ymd').'-000143', $numbers[19]);
        $this->assertCount(20, array_unique($numbers));
    }
}
