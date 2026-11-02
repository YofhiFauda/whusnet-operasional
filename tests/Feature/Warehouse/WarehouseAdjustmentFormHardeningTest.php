<?php

namespace Tests\Feature\Warehouse;

use App\Models\InventoryBalance;
use App\Services\InventoryReceiveService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pengerasan form Koreksi Stok dari temuan review: parameter query yang
 * dirakit manual (bukan dari link server) tidak boleh menjatuhkan 500, dan
 * pesan error session tidak boleh dobel (toast global + kotak inline).
 */
class WarehouseAdjustmentFormHardeningTest extends StockBoardTestCase
{
    #[Test]
    public function query_item_id_berbentuk_array_tidak_crash(): void
    {
        $this->actingAs($this->owner)
            ->get(route('warehouse.adjustments.check.create', ['pop_id' => $this->pusat->id, 'item_id' => [1, 2]]))
            ->assertOk();
    }

    #[Test]
    public function query_lot_no_berbentuk_array_tidak_crash(): void
    {
        $this->actingAs($this->owner)
            ->get(route('warehouse.adjustments.check.create', ['pop_id' => $this->pusat->id, 'lot_no' => ['x']]))
            ->assertOk();
    }

    #[Test]
    public function pesan_error_session_tidak_dirender_dobel_di_form_cek_dan_masalah(): void
    {
        foreach (['warehouse.adjustments.check.create', 'warehouse.adjustments.problem.create'] as $route) {
            $html = $this->actingAs($this->owner)->withSession(['error' => 'Qty penyesuaian tidak boleh nol.'])
                ->get(route($route))->getContent();

            // Toast global (resources/views/components/toast.blade.php) sudah
            // merender session('error') — kotak inline di atas form jadi
            // duplikat pesan yang sama di layar yang sama.
            $this->assertSame(1, substr_count($html, 'Qty penyesuaian tidak boleh nol.'), $route);
        }
    }

    #[Test]
    public function tiga_tingkat_harga_diberi_label_berurutan(): void
    {
        $konektor = $this->buatItem('HARD-KON', 'Konektor Hardening', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $konektor, 10, 1000, $this->owner);
        InventoryBalance::create(['pop_id' => $this->pusat->id, 'item_id' => $konektor->id, 'lot_no' => 'LOT-X', 'qty' => 5]);
        InventoryBalance::create(['pop_id' => $this->pusat->id, 'item_id' => $konektor->id, 'lot_no' => 'LOT-Y', 'qty' => 5]);

        $html = $this->actingAs($this->owner)
            ->get(route('warehouse.adjustments.check.create', ['pop_id' => $this->pusat->id, 'item_id' => $konektor->id]))
            ->getContent();

        $this->assertStringContainsString("'Lot ke-' + (index + 1)", $html);
    }
}
