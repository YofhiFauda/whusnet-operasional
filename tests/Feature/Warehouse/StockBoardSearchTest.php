<?php

namespace Tests\Feature\Warehouse;

use App\Enums\SerialStatus;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Pop;
use App\Services\InventoryReceiveService;
use App\Services\WarehouseStockBoardService;
use App\Support\BoardFilters;
use App\Support\LikeSearch;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pencarian papan Kelola Stok: nama/kode barang UNION SN/kode roll, label
 * `Cocok:`, batas 50 pasangan, dan `emptyState()`.
 */
class StockBoardSearchTest extends StockBoardTestCase
{
    private function filters(string $search = '', ?int $popId = null, bool $lowOnly = false): BoardFilters
    {
        return new BoardFilters($popId, null, null, $search, $lowOnly);
    }

    /**
     * @param  array<int, int>  $popIds
     */
    private function build(array $popIds, ?BoardFilters $f = null): Collection
    {
        return app(WarehouseStockBoardService::class)->build(collect($popIds), $f ?? $this->filters());
    }

    /** @return Collection<int, array<string, mixed>> */
    private function semuaRow(Collection $groups): Collection
    {
        return $groups->flatMap(fn ($g) => $g['categories'])->flatMap(fn ($c) => $c['rows'])->values();
    }

    private function sn(Pop $pop, Item $item, string $serial): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serial,
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $pop->id,
        ]);
    }

    #[Test]
    public function cari_sn_menemukan_barang_dan_gudang_yang_benar_dengan_label_cocok(): void
    {
        $modem = $this->buatItem('SS-MDM', 'Modem Cari', 'serialized');
        $lain = $this->buatItem('SS-LAIN', 'ONT Lain', 'serialized');
        $this->sn($this->pusat, $modem, 'ZXC-0001');
        $this->sn($this->cabangA, $modem, 'ZXC-0002');
        $this->sn($this->cabangA, $modem, 'ZXC-0003');
        $this->sn($this->pusat, $lain, 'QQQ-9999');

        $groups = $this->build([$this->pusat->id, $this->cabangA->id], $this->filters('ZXC-0002'));

        $this->assertSame([$this->cabangA->id], $groups->pluck('pop.id')->all(), 'gudang tanpa kecocokan tidak ikut');
        $row = $this->semuaRow($groups)->sole();
        $this->assertSame($modem->id, $row['item']->id);
        $this->assertSame('Cocok: SN ZXC-0002', $row['match_label']);

        $banyak = $this->semuaRow($this->build([$this->cabangA->id], $this->filters('ZXC')))->sole();
        $this->assertSame('Cocok: SN ZXC-0002 +1 lagi', $banyak['match_label']);
    }

    #[Test]
    public function cari_kode_roll_menemukan_roll_dan_gudangnya(): void
    {
        $fo = $this->buatItem('SS-FO', 'Kabel FO Cari', 'roll', 'meter', 1000);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 1, null, 1500000, $this->owner);
        $roll = InventoryRoll::where('item_id', $fo->id)->firstOrFail();

        $groups = $this->build([$this->pusat->id, $this->cabangA->id], $this->filters($roll->roll_code));

        $this->assertSame([$this->pusat->id], $groups->pluck('pop.id')->all());
        $row = $this->semuaRow($groups)->sole();
        $this->assertSame($fo->id, $row['item']->id);
        $this->assertSame('Cocok: roll '.$roll->roll_code, $row['match_label']);
    }

    #[Test]
    public function sn_gudang_luar_scope_tidak_ditemukan_dan_tidak_bocor(): void
    {
        $modem = $this->buatItem('SS-OUT', 'Modem Luar Scope', 'serialized');
        $this->sn($this->cabangB, $modem, 'RAHASIA-777');
        // Gudang dalam scope punya stok lain agar scope tidak kosong.
        $lain = $this->buatItem('SS-IN', 'Barang Dalam Scope', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $lain, 3, 1000, $this->owner);

        $f = $this->filters('RAHASIA-777');
        $groups = $this->build([$this->cabangA->id], $f);

        $this->assertCount(0, $this->semuaRow($groups));
        $this->assertSame([], $groups->pluck('pop.id')->all());
        $this->assertSame('no_match', app(WarehouseStockBoardService::class)->emptyState(collect([$this->cabangA->id]), $groups, $f));
    }

    #[Test]
    public function kata_kunci_wildcard_dan_panjang_tidak_melebar(): void
    {
        $a = $this->buatItem('SS-W1', 'Barang Satu', 'quantity');
        $b = $this->buatItem('SS-W2', 'Barang Dua', 'quantity');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $a, 3, 1000, $this->owner);
        $receive->receiveQuantity($this->pusat, $b, 3, 1000, $this->owner);

        foreach (['%', '_', str_repeat('A', 500), '%%%', '\\'] as $mentah) {
            $hasil = $this->build([$this->pusat->id], $this->filters($mentah));
            $bersih = $this->build([$this->pusat->id], $this->filters(LikeSearch::sanitize($mentah)));

            $this->assertEquals($bersih->pluck('pop.id')->all(), $hasil->pluck('pop.id')->all(), "pilihan gudang identik untuk {$mentah}");
            $this->assertSame(
                $this->semuaRow($bersih)->pluck('item.id')->all(),
                $this->semuaRow($hasil)->pluck('item.id')->all(),
                'hasil identik dengan keyword yang sudah disanitasi'
            );
        }

        $this->assertCount(0, $this->semuaRow($this->build([$this->pusat->id], $this->filters(str_repeat('A', 500)))));
        // Underscore dibuang (jadi 'SSW'), bukan wildcard satu karakter: 'SS_W' tidak boleh cocok 'SS-W1'.
        $this->assertCount(0, $this->semuaRow($this->build([$this->pusat->id], $this->filters('SS_W'))));
    }

    #[Test]
    public function pencarian_nama_dan_kode_memfilter_baris(): void
    {
        $a = $this->buatItem('SS-ALFA', 'Konektor Alfa', 'quantity');
        $b = $this->buatItem('SS-BETA', 'Splitter Beta', 'quantity');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $a, 3, 1000, $this->owner);
        $receive->receiveQuantity($this->pusat, $b, 3, 1000, $this->owner);

        $byNama = $this->semuaRow($this->build([$this->pusat->id], $this->filters('splitter')));
        $byKode = $this->semuaRow($this->build([$this->pusat->id], $this->filters('SS-ALFA')));

        $this->assertSame([$b->id], $byNama->pluck('item.id')->all());
        $this->assertNull($byNama->first()['match_label'], 'cocok nama/kode tidak diberi label SN');
        $this->assertSame([$a->id], $byKode->pluck('item.id')->all());
    }

    #[Test]
    public function nama_cocok_dan_sn_cocok_digabung_sebagai_union(): void
    {
        $byNama = $this->buatItem('SS-U1', 'Router Zeta', 'quantity');
        $bySn = $this->buatItem('SS-U2', 'Modem Tanpa Kata Kunci', 'serialized');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $byNama, 3, 1000, $this->owner);
        $this->sn($this->pusat, $bySn, 'ZETA-SN-1');

        $rows = $this->semuaRow($this->build([$this->pusat->id], $this->filters('zeta')));

        $this->assertEqualsCanonicalizing([$byNama->id, $bySn->id], $rows->pluck('item.id')->all());
        $this->assertSame('Cocok: SN ZETA-SN-1', $rows->firstWhere('item.id', $bySn->id)['match_label']);
    }

    #[Test]
    public function hasil_pencarian_sn_dibatasi_50_pasangan(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $item = $this->buatItem('SS-C'.$i, 'Barang Cap '.$i, 'serialized');
            $this->sn($this->pusat, $item, 'CAPSN-'.$i);
        }

        $groups = $this->build([$this->pusat->id], $this->filters('CAPSN'));

        $this->assertCount(50, $this->semuaRow($groups));
        $this->assertTrue($groups->every(fn ($g) => $g['search_truncated'] === true));
        $this->assertNull(app(WarehouseStockBoardService::class)->emptyState(collect([$this->pusat->id]), $groups, $this->filters('CAPSN')));

        $sedikit = $this->build([$this->pusat->id], $this->filters('CAPSN-1'));
        $this->assertFalse($sedikit->first()['search_truncated'], 'di bawah batas: tidak terpotong');

        $tanpaCari = $this->build([$this->pusat->id]);
        $this->assertFalse($tanpaCari->first()['search_truncated']);
    }

    #[Test]
    public function empty_state_membedakan_no_access_no_match_no_stock(): void
    {
        $svc = app(WarehouseStockBoardService::class);
        $item = $this->buatItem('SS-ES', 'Barang Empty State', 'quantity');

        $kosong = collect([$this->pusat->id]);
        $this->assertSame('no_access', $svc->emptyState(collect(), collect(), $this->filters()));
        $this->assertSame('no_stock', $svc->emptyState($kosong, $this->build([$this->pusat->id]), $this->filters()));
        $this->assertSame('no_match', $svc->emptyState($kosong, collect(), $this->filters('apa saja')));

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 3, 1000, $this->owner);
        $this->assertNull($svc->emptyState($kosong, $this->build([$this->pusat->id]), $this->filters()));

        $f = $this->filters('tidak-ada-barang-ini');
        $this->assertSame('no_match', $svc->emptyState($kosong, $this->build([$this->pusat->id], $f), $f));
    }

    #[Test]
    public function gudang_tanpa_stok_tampil_sebagai_band_kosong_hanya_tanpa_filter(): void
    {
        $item = $this->buatItem('SS-BAND', 'Barang Band Cari', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 3, 1000, $this->owner);
        $scope = [$this->pusat->id, $this->cabangA->id];

        $tanpa = $this->build($scope);
        $this->assertSame([$this->pusat->id, $this->cabangA->id], $tanpa->pluck('pop.id')->all());
        $this->assertCount(0, $tanpa->get(1)['categories']);

        $this->assertSame([$this->pusat->id], $this->build($scope, $this->filters('Band'))->pluck('pop.id')->all());
    }
}
