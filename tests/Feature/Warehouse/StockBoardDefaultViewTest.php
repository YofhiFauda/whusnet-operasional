<?php

namespace Tests\Feature\Warehouse;

use PHPUnit\Framework\Attributes\Test;

class StockBoardDefaultViewTest extends StockBoardTestCase
{
    #[Test]
    public function tanpa_view_merender_papan(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.stock.index'))
            ->assertOk()->assertViewHas('viewMode', 'papan');
    }

    #[Test]
    public function matriks_tetap_tersedia_dari_toolbar_papan(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.stock.index', ['view' => 'matrix']))
            ->assertOk()->assertViewHas('viewMode', 'matrix');

        $response->assertSee('Matriks');
    }

    #[Test]
    public function tautan_tampilan_lama_dialihkan_ke_papan_dengan_filter_yang_masih_relevan(): void
    {
        foreach (['rincian', 'ringkasan'] as $legacyMode) {
            $this->actingAs($this->owner)
                ->get(route('warehouse.stock.index', [
                    'view' => $legacyMode,
                    'search' => 'modem',
                    'tracking_type' => 'serialized',
                    'sort' => 'qty',
                ]).'&pop_id=')
                ->assertRedirect(route('warehouse.stock.index', [
                    'view' => 'papan',
                    'search' => 'modem',
                    'pop_id' => '',
                    'tracking' => 'serialized',
                ]));
        }
    }

    #[Test]
    public function papan_tidak_memuat_tab_dan_modal_lama(): void
    {
        $html = $this->actingAs($this->owner)->get(route('warehouse.stock.index'))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('view=rincian', $html);
        $this->assertStringNotContainsString('view=ringkasan', $html);
        $this->assertStringNotContainsString('function serialListModal(', $html);
        $this->assertStringNotContainsString('function rollListModal(', $html);
    }

    #[Test]
    public function view_tidak_dikenal_jatuh_ke_papan(): void
    {
        $this->actingAs($this->owner)
            ->get(route('warehouse.stock.index', ['view' => 'ngawur']))
            ->assertOk()->assertViewHas('viewMode', 'papan');
    }
}
