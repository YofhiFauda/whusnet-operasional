<?php

namespace Tests\Feature\Warehouse;

use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Pop;
use App\Services\InventoryReceiveService;
use App\Services\WarehouseStockBoardService;
use App\Support\BoardFilters;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

/**
 * `WarehouseStockBoardService::matrix()` + tab Matriks: barang (dikelompokkan
 * per kategori) × gudang, sel = tersedia. Angka WAJIB sama dengan tabel
 * (`build()`), karena matriks hanya memivot hasil build().
 */
class StockBoardMatrixTest extends StockBoardTestCase
{
    private function filters(?int $popId = null, ?int $categoryId = null, ?string $tracking = null, string $search = '', bool $lowOnly = false): BoardFilters
    {
        return new BoardFilters($popId, $categoryId, $tracking, $search, $lowOnly);
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $popIds
     * @return array{pops: Collection, categories: Collection}
     */
    private function matrix($popIds, ?BoardFilters $f = null): array
    {
        return app(WarehouseStockBoardService::class)->matrix(collect($popIds), $f ?? $this->filters());
    }

    private function stokQuantity(Pop $pop, Item $item, float $qty, ?float $min = null): void
    {
        InventoryBalance::create(['pop_id' => $pop->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => $qty, 'minimum_stock' => $min]);
    }

    private function sn(Pop $pop, Item $item, string $serial): void
    {
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => $serial, 'status' => SerialStatus::AVAILABLE->value, 'current_pop_id' => $pop->id]);
    }

    /** @return Collection<int, array<string, mixed>> semua baris matriks lintas kategori */
    private function barisMatriks(array $matrix): Collection
    {
        return $matrix['categories']->flatMap(fn ($c) => $c['rows']);
    }

    private function semuaPop(): array
    {
        return [$this->pusat->id, $this->cabangA->id, $this->cabangB->id];
    }

    /** Fixture 3 gudang × 3 tracking dengan sebagian sel kosong. */
    private function fixtureTigaTracking(): array
    {
        $qty = $this->buatItem('MX-Q', 'Konektor Matriks', 'quantity', 'pcs', null, 'kabel_dropcore');
        $sn = $this->buatItem('MX-S', 'Modem Matriks', 'serialized', 'unit', null, 'kabel_dropcore');
        $roll = $this->buatItem('MX-R', 'Kabel Matriks', 'roll', 'meter', 1000, 'kabel_dropcore');

        $this->stokQuantity($this->pusat, $qty, 40, 10);
        $this->stokQuantity($this->cabangA, $qty, 3, 10);
        $this->sn($this->pusat, $sn, 'MXS-1');
        $this->sn($this->pusat, $sn, 'MXS-2');
        $this->sn($this->cabangB, $sn, 'MXS-3');
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $roll, 2, null, 1500, $this->owner);

        return [$qty, $sn, $roll];
    }

    #[Test]
    public function sel_matriks_sama_dengan_angka_tabel(): void
    {
        $this->fixtureTigaTracking();

        $groups = app(WarehouseStockBoardService::class)->build(collect($this->semuaPop()), $this->filters());
        $matrix = $this->matrix($this->semuaPop());

        $this->assertCount(3, $this->barisMatriks($matrix));

        foreach ($this->barisMatriks($matrix) as $baris) {
            foreach ($groups as $group) {
                $rowTabel = $group['categories']->flatMap(fn ($c) => $c['rows'])
                    ->first(fn ($r) => $r['item']->id === $baris['item']->id);
                $sel = $baris['cells'][$group['pop']->id];

                $this->assertSame($rowTabel === null ? 0.0 : $rowTabel['qty'], $sel['qty'], "qty {$baris['item']->code} @ {$group['pop']->name}");
                $this->assertSame($rowTabel === null ? false : $rowTabel['is_low'], $sel['is_low']);
                if ($rowTabel === null) {
                    $this->assertNull($sel['row']);
                } else {
                    $this->assertSame($rowTabel['qty'], $sel['row']['qty']);
                    $this->assertSame($baris['item']->id, $sel['row']['item']->id);
                }
            }
        }
    }

    #[Test]
    public function sel_nol_dan_menipis_ditandai_teks(): void
    {
        [$qty] = $this->fixtureTigaTracking();

        $matrix = $this->matrix($this->semuaPop());
        $baris = $this->barisMatriks($matrix)->first(fn ($r) => $r['item']->id === $qty->id);

        $this->assertTrue($baris['cells'][$this->cabangA->id]['is_low'], '3 < min 10');
        $this->assertFalse($baris['cells'][$this->pusat->id]['is_low']);
        $this->assertSame(0.0, $baris['cells'][$this->cabangB->id]['qty']);
        $this->assertNull($baris['cells'][$this->cabangB->id]['row']);

        $html = $this->actingAs($this->owner)
            ->get(route('warehouse.stock.index', ['view' => 'matrix']))
            ->assertOk()->getContent();
        $fragmen = substr($html, strpos($html, '<!-- matriks:awal -->'), strpos($html, '<!-- matriks:akhir -->') - strpos($html, '<!-- matriks:awal -->'));
        $teks = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($fragmen)));

        $this->assertStringContainsString('⚠ menipis', $teks, 'menipis ditulis sebagai teks, bukan hanya warna');
        $this->assertStringContainsString('⚠ kosong', $teks, 'sel nol ditulis sebagai teks, bukan hanya warna');
        $this->assertStringNotContainsStringIgnoringCase('lot', $teks, 'istilah Lot tidak boleh tampil');
    }

    #[Test]
    public function kolom_pusat_dulu_lalu_cabang(): void
    {
        $this->fixtureTigaTracking();

        // Urutan input sengaja dibalik: urutan kolom ditentukan service.
        $matrix = $this->matrix(array_reverse($this->semuaPop()));

        $this->assertSame(
            [$this->pusat->id, $this->cabangA->id, $this->cabangB->id],
            $matrix['pops']->pluck('id')->all()
        );
        foreach ($this->barisMatriks($matrix) as $baris) {
            $this->assertEqualsCanonicalizing($this->semuaPop(), array_keys($baris['cells']), 'setiap gudang punya kunci sel');
        }
    }

    #[Test]
    public function filter_dan_pencarian_yang_sama_berlaku(): void
    {
        [$qty, $sn, $roll] = $this->fixtureTigaTracking();

        $kode = fn (array $m) => $this->barisMatriks($m)->pluck('item.code')->sort()->values()->all();

        $this->assertSame(['MX-S'], $kode($this->matrix($this->semuaPop(), $this->filters(tracking: 'serialized'))));
        $this->assertSame(['MX-Q'], $kode($this->matrix($this->semuaPop(), $this->filters(search: 'Konektor'))));
        $this->assertSame(['MX-Q'], $kode($this->matrix($this->semuaPop(), $this->filters(lowOnly: true))));

        // Cocok hanya lewat SN: barang tampil walau nama tidak cocok.
        $this->assertSame(['MX-S'], $kode($this->matrix($this->semuaPop(), $this->filters(search: 'MXS-3'))));

        // Kategori lain → tidak ada baris.
        $lain = $this->buatItem('MX-L', 'Alat Lain', 'quantity', 'pcs', null, null);
        $this->stokQuantity($this->pusat, $lain, 5);
        $hasil = $this->matrix($this->semuaPop(), $this->filters(categoryId: $lain->item_category_id ?? 999999));
        $this->assertSame([], $kode($hasil));

        // Filter menipis tidak boleh berbohong soal sel gudang lain: pusat 40 aman,
        // tetapi tetap tampil 40 (bukan 0) pada baris barang yang lolos.
        $baris = $this->barisMatriks($this->matrix($this->semuaPop(), $this->filters(lowOnly: true)))->first();
        $this->assertSame(40.0, $baris['cells'][$this->pusat->id]['qty']);
    }

    #[Test]
    public function matriks_satu_gudang_tetap_valid(): void
    {
        $this->fixtureTigaTracking();

        $matrix = $this->matrix([$this->pusat->id]);

        $this->assertCount(1, $matrix['pops']);
        $this->assertCount(3, $this->barisMatriks($matrix));
        foreach ($this->barisMatriks($matrix) as $baris) {
            $this->assertSame([$this->pusat->id], array_keys($baris['cells']));
        }
    }

    #[Test]
    public function matriks_luar_scope_tidak_memuat_kolom_gudang_lain(): void
    {
        $this->fixtureTigaTracking();

        $matrix = $this->matrix([$this->cabangA->id]);
        $this->assertSame([$this->cabangA->id], $matrix['pops']->pluck('id')->all());

        // popId di luar scope: menyempit ke kosong, bukan membuka gudang itu.
        $kosong = $this->matrix([$this->cabangA->id], $this->filters(popId: $this->pusat->id));
        $this->assertCount(0, $kosong['pops']);
        $this->assertCount(0, $kosong['categories']);

        $user = $this->userDenganScope([$this->cabangA->id]);
        $html = $this->actingAs($user)->get(route('warehouse.stock.index', ['view' => 'matrix']))->assertOk()->getContent();
        $awal = strpos($html, '<!-- matriks:awal -->');
        $fragmen = substr($html, $awal, strpos($html, '<!-- matriks:akhir -->') - $awal);
        $this->assertStringNotContainsString($this->pusat->name, $fragmen);
        $this->assertStringNotContainsString('Cabang Papan B', $fragmen);
        $this->assertStringContainsString('Cabang Papan A', $fragmen);
    }

    #[Test]
    public function halaman_matriks_dirender_dengan_view_matrix(): void
    {
        [$qty, $sn] = $this->fixtureTigaTracking();

        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.stock.index', ['view' => 'matrix', 'tracking' => 'quantity']))
            ->assertOk()
            ->assertViewHas('viewMode', 'matrix');

        $html = $response->getContent();
        $this->assertStringContainsString('<!-- matriks:awal -->', $html);
        $this->assertStringContainsString('Konektor Matriks', $html);
        $this->assertStringNotContainsString('Modem Matriks', $html, 'filter tracking berlaku di matriks');

        // Sel dengan stok membawa payload drawer + container data-board-row;
        // sel kosong (Cabang B) tidak membawa payload.
        $this->assertStringContainsString('data-stock-detail=', $html);
        $this->assertStringContainsString('data-board-row', $html);
        $this->assertSame(2, substr_count($html, 'data-stock-detail='), 'hanya sel Pusat dan Cabang A yang bisa dibuka');

        // Sub-toggle Tabel | Matriks membawa filter.
        $this->assertStringContainsString('view=papan', $html);
        $this->assertMatchesRegularExpression('/view=matrix[^"]*tracking=quantity|tracking=quantity[^"]*view=matrix/', html_entity_decode($html));
    }

    #[Test]
    public function matriks_tanpa_stok_menampilkan_keadaan_kosong(): void
    {
        $this->actingAs($this->owner)
            ->get(route('warehouse.stock.index', ['view' => 'matrix']))
            ->assertOk()
            ->assertSee('Belum ada stok tersedia di gudang dalam cakupan Anda');
    }
}
