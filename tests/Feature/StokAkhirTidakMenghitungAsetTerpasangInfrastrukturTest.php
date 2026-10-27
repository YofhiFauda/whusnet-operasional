<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Services\WarehouseStockAsOfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StokAkhirTidakMenghitungAsetTerpasangInfrastrukturTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registrasi_aset_lama_langsung_terpasang_bukan_stok_gudang(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $category = ItemCategory::create(['code' => 'AUDIT-REGISTER', 'name' => 'Audit Register', 'default_unit' => 'pcs']);
        $item = Item::create([
            'code' => 'AUDIT-REGISTER',
            'name' => 'Audit Register',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'serialized',
        ]);
        $serial = InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'AUDIT-REGISTER-SN']);
        InventoryTransaction::create([
            'type' => InventoryTransactionType::REGISTER_INFRA,
            'item_id' => $item->id,
            'serial_id' => $serial->id,
            'qty' => 1,
            'to_pop_id' => $pop->id,
        ]);

        $balance = app(WarehouseStockAsOfService::class)->balanceAsOf($pop, $item, now()->addMinute());
        $this->assertEquals(0, $balance['lama']['qty'] + $balance['baru']['qty']);
    }

    #[Test]
    public function stok_akhir_tiga_tipe_barang_tidak_menghitung_deploy(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $category = ItemCategory::create(['code' => 'AUDIT-STOCK', 'name' => 'Audit Stock', 'default_unit' => 'pcs']);

        foreach (['serialized', 'quantity', 'roll'] as $trackingType) {
            $item = Item::create([
                'code' => 'AUDIT-'.$trackingType,
                'name' => 'Audit '.$trackingType,
                'item_category_id' => $category->id,
                'unit' => 'pcs',
                'tracking_type' => $trackingType,
            ]);
            $unitIds = [];

            if ($trackingType === 'serialized') {
                $unitIds['serial_id'] = InventorySerial::create([
                    'item_id' => $item->id,
                    'serial_number' => 'AUDIT-SN-001',
                ])->id;
            } elseif ($trackingType === 'roll') {
                $unitIds['roll_id'] = InventoryRoll::create([
                    'item_id' => $item->id,
                    'roll_code' => 'AUDIT-ROLL-001',
                    'length_total' => 10,
                    'length_remaining' => 0,
                ])->id;
            }

            InventoryTransaction::create($unitIds + [
                'type' => InventoryTransactionType::RECEIVE,
                'item_id' => $item->id,
                'qty' => 10,
                'to_pop_id' => $pop->id,
            ]);
            InventoryTransaction::create($unitIds + [
                'type' => InventoryTransactionType::ISSUE,
                'item_id' => $item->id,
                'qty' => 10,
                'from_pop_id' => $pop->id,
            ]);
            InventoryTransaction::create($unitIds + [
                'type' => InventoryTransactionType::DEPLOY,
                'item_id' => $item->id,
                'qty' => 10,
                'to_pop_id' => $pop->id,
            ]);

            $balance = app(WarehouseStockAsOfService::class)->balanceAsOf($pop, $item, now()->addMinute());
            $this->assertEquals(0, $balance['lama']['qty'] + $balance['baru']['qty'], $trackingType);
        }
    }
}
