<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\TrackingType;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Konversi Pack↔pcs — dipakai bersama di 3 titik scope (Receive/Transfer/
 * Issue, lihat docs/superpowers/specs/2026-11-01-pack-pcs-conversion-design.md).
 * File ini ditambah bertahap per task (Task 2 = Receive), jangan dipecah per
 * Service — satu file biar kasus "item tanpa pack_size tetap jalan normal"
 * gak diulang-ulang di tiap Service.
 */
class WarehousePackConversionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function receive_barang_berpack_size_dikonversi_ke_pcs(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        // Item::factory() belum ada di repo ini — item master selalu dibuat
        // langsung lewat Item::create() di test lain, ikuti pola itu.
        $item = Item::create([
            'code' => 'PACK-TEST-01',
            'name' => 'Barang Berpack Uji',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);

        $response = $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 1000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'type' => InventoryTransactionType::RECEIVE->value,
            'qty' => 100,
            'entered_pack_qty' => 2,
        ]);

        $transaction = InventoryTransaction::where('item_id', $item->id)->first();
        $this->get(route('warehouse.receive.show', $transaction->reference_number))
            ->assertSee('2 Pack (100 pcs)');
    }

    #[Test]
    public function receive_barang_tanpa_pack_size_tetap_pcs_langsung(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $item = Item::create([
            'code' => 'PACK-TEST-02',
            'name' => 'Barang Tanpa Pack Uji',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => null,
        ]);

        $response = $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 73, 'unit_price' => 1000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'qty' => 73,
            'entered_pack_qty' => null,
        ]);
    }
}
