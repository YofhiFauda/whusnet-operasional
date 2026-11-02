<?php

namespace Tests\Feature\Warehouse;

use App\Services\InventoryReceiveService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Form "Cek & cocokkan stok" — satu form untuk opname qty, SN, dan roll.
 * URL simpan WAJIB dirender server (CLAUDE.md: target aksi dari route());
 * action default tidak boleh kosong, kalau kosong form POST ke halamannya
 * sendiri dan gagal diam-diam (bug ADHOC-20).
 */
class WarehouseCheckFormTest extends StockBoardTestCase
{
    #[Test]
    public function form_cek_stok_merender_url_simpan_dari_server(): void
    {
        $html = $this->actingAs($this->owner)->get(route('warehouse.adjustments.check.create'))
            ->assertOk()
            ->getContent();

        $qtyUrl = e(route('warehouse.adjustments.opname.store'));
        $unitUrl = e(route('warehouse.adjustments.unit-opname.store'));

        $this->assertStringContainsString('action="'.$qtyUrl.'"', $html, 'action default harus terisi URL opname qty');
        $this->assertStringContainsString('data-url-qty="'.$qtyUrl.'"', $html);
        $this->assertStringContainsString('data-url-unit="'.$unitUrl.'"', $html);
        $this->assertStringContainsString('data-resolve-url="'.e(route('warehouse.adjustments.resolve')).'"', $html);
    }

    #[Test]
    public function form_cek_stok_terisi_dari_query_pop_dan_item(): void
    {
        $konektor = $this->buatItem('CHK-KON', 'Konektor Cek', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $konektor, 12, 1000, $this->owner);

        $this->actingAs($this->owner)
            ->get(route('warehouse.adjustments.check.create', ['pop_id' => $this->pusat->id, 'item_id' => $konektor->id]))
            ->assertOk()
            ->assertSee('value="CHK-KON"', false)
            ->assertSee('data-prefill-pop="'.$this->pusat->id.'"', false);
    }

    #[Test]
    public function prefill_gudang_di_luar_scope_diabaikan(): void
    {
        $user = $this->userDenganScope([$this->cabangA->id]);
        $konektor = $this->buatItem('CHK-KON', 'Konektor Cek', 'quantity');

        $this->actingAs($user)
            ->get(route('warehouse.adjustments.check.create', ['pop_id' => $this->pusat->id, 'item_id' => $konektor->id]))
            ->assertOk()
            ->assertDontSee('value="CHK-KON"', false)
            ->assertSee('data-prefill-pop=""', false);
    }

    #[Test]
    public function form_cek_stok_hanya_menampilkan_gudang_dalam_scope(): void
    {
        $user = $this->userDenganScope([$this->cabangA->id]);

        $this->actingAs($user)->get(route('warehouse.adjustments.check.create'))
            ->assertOk()
            ->assertSee($this->cabangA->name)
            ->assertDontSee($this->cabangB->name)
            ->assertDontSee($this->pusat->name);
    }
}
