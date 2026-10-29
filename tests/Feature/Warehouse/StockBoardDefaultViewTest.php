<?php

namespace Tests\Feature\Warehouse;

use PHPUnit\Framework\Attributes\Test;

/**
 * Papan jadi tampilan default Kelola Stok; Per Lot lama pindah ke
 * `?view=rincian` (spec 2026-10-29 §2).
 */
class StockBoardDefaultViewTest extends StockBoardTestCase
{
    private function buka(array $query = [])
    {
        return $this->actingAs($this->owner)->get(route('warehouse.stock.index', $query))->assertOk();
    }

    #[Test]
    public function tanpa_view_merender_papan(): void
    {
        $this->buka()->assertViewHas('viewMode', 'papan');
    }

    #[Test]
    public function view_rincian_merender_per_lot_lama(): void
    {
        $response = $this->buka(['view' => 'rincian']);

        $response->assertViewHas('balances');
        $this->assertNotSame('papan', $response->viewData('viewMode'));
        // Filter lama harus mempertahankan view=rincian supaya tidak mental ke papan.
        $response->assertSee('id="stockFilterForm"', false);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="view" value="rincian">/', $response->getContent());
    }

    #[Test]
    public function reset_filter_rincian_tetap_di_rincian(): void
    {
        $html = $this->buka(['view' => 'rincian', 'search' => 'zzz', 'low_stock_only' => 1])->getContent();

        $this->assertStringContainsString('href="'.route('warehouse.stock.index', ['view' => 'rincian']).'"', $html);
    }

    #[Test]
    public function view_ringkasan_tetap(): void
    {
        $this->buka(['view' => 'ringkasan'])->assertViewHas('viewMode', 'ringkasan')->assertViewHas('summary');
    }

    #[Test]
    public function toggle_tiga_mode_menandai_mode_aktif(): void
    {
        $papan = $this->buka()->getContent();
        $this->assertMatchesRegularExpression('/view=papan[^>]*role="tab" aria-selected="true"/', $papan);

        $rincian = $this->buka(['view' => 'rincian'])->getContent();
        $this->assertMatchesRegularExpression('/view=rincian[^>]*role="tab" aria-selected="true"/', $rincian);

        $ringkasan = $this->buka(['view' => 'ringkasan'])->getContent();
        $this->assertMatchesRegularExpression('/view=ringkasan[^>]*role="tab" aria-selected="true"/', $ringkasan);
    }

    #[Test]
    public function view_tidak_dikenal_jatuh_ke_papan(): void
    {
        $this->buka(['view' => 'ngawur'])->assertViewHas('viewMode', 'papan');
    }
}
