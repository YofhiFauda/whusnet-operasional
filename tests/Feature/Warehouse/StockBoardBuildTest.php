<?php

namespace Tests\Feature\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use App\Services\WarehouseStockBoardService;
use App\Support\BoardFilters;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

/**
 * `WarehouseStockBoardService::build()` — read-model papan Kelola Stok:
 * Gudang → Kategori → Barang. Angka harus SAMA dengan layar lama (hanya stok
 * tersedia), hanya pengelompokannya yang baru.
 */
class StockBoardBuildTest extends StockBoardTestCase
{
    private function filters(?int $popId = null, ?int $categoryId = null, ?string $tracking = null, string $search = '', bool $lowOnly = false): BoardFilters
    {
        return new BoardFilters($popId, $categoryId, $tracking, $search, $lowOnly);
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $popIds
     */
    private function build($popIds, ?BoardFilters $f = null): Collection
    {
        return app(WarehouseStockBoardService::class)->build(collect($popIds), $f ?? $this->filters());
    }

    /** Row pertama (pop, item) di seluruh kategori sebuah grup gudang. */
    private function row(Collection $groups, int $popId, int $itemId): ?array
    {
        $group = $groups->first(fn ($g) => $g['pop']->id === $popId);

        return $group === null ? null : $group['categories']
            ->flatMap(fn ($c) => $c['rows'])
            ->first(fn ($r) => $r['item']->id === $itemId);
    }

    private function kirimKeCabang(Pop $cabang, Item $item, int $qty): void
    {
        $svc = app(InventoryTransferService::class);
        $transfer = $svc->createTransfer($this->pusat, $cabang, [['item_id' => $item->id, 'qty' => $qty]], $this->owner);
        $svc->receiveTransfer($transfer, [], [$item->id => $qty], $this->owner);
    }

    #[Test]
    public function tersedia_quantity_menjumlah_dua_harga_dan_menandai_price_tiers(): void
    {
        $dua = $this->buatItem('SB-Q2', 'Dropcore Dua Harga', 'quantity', 'meter');
        $satu = $this->buatItem('SB-Q1', 'Konektor Satu Harga', 'quantity');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $dua, 12, 250000, $this->owner);
        $receive->receiveQuantity($this->pusat, $dua, 10, 260000, $this->owner);
        $receive->receiveQuantity($this->pusat, $satu, 7, 5000, $this->owner);

        $groups = $this->build([$this->pusat->id]);

        $rowDua = $this->row($groups, $this->pusat->id, $dua->id);
        $this->assertSame(22.0, $rowDua['qty']);
        $this->assertNull($rowDua['lot_no']);
        $this->assertCount(2, $rowDua['price_tiers']);
        $this->assertSame(['', 12.0, 250000.0], [$rowDua['price_tiers'][0]['lot_no'], $rowDua['price_tiers'][0]['qty'], $rowDua['price_tiers'][0]['price']]);
        $this->assertSame(10.0, $rowDua['price_tiers'][1]['qty']);
        $this->assertSame(260000.0, $rowDua['price_tiers'][1]['price']);
        $this->assertStringStartsWith('SB-Q2-', $rowDua['price_tiers'][1]['lot_no']);

        $rowSatu = $this->row($groups, $this->pusat->id, $satu->id);
        $this->assertSame(7.0, $rowSatu['qty']);
        $this->assertSame('', $rowSatu['lot_no'], 'satu harga: lot_no terisi (sentinel kosong dibawa untuk URL aksi)');
        $this->assertSame([], $rowSatu['price_tiers']);
    }

    #[Test]
    public function quantity_satu_harga_membawa_price_dan_dua_harga_price_null(): void
    {
        $dua = $this->buatItem('SB-P2', 'Dropcore Dua Harga Price', 'quantity', 'meter');
        $satu = $this->buatItem('SB-P1', 'Konektor Satu Harga Price', 'quantity');
        $modem = $this->buatItem('SB-PSN', 'Modem Price', 'serialized', 'unit');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $dua, 12, 250000, $this->owner);
        $receive->receiveQuantity($this->pusat, $dua, 10, 260000, $this->owner);
        $receive->receiveQuantity($this->pusat, $satu, 7, 5000, $this->owner);
        InventorySerial::create(['item_id' => $modem->id, 'serial_number' => 'SBP-1', 'status' => SerialStatus::AVAILABLE->value, 'current_pop_id' => $this->pusat->id]);

        $groups = $this->build([$this->pusat->id]);

        $rowSatu = $this->row($groups, $this->pusat->id, $satu->id);
        $this->assertSame(5000.0, $rowSatu['price'], 'satu harga: price = harga RECEIVE terakhir');
        $this->assertSame([], $rowSatu['price_tiers'], 'price_tiers tetap hanya untuk dua harga');

        $rowDua = $this->row($groups, $this->pusat->id, $dua->id);
        $this->assertNull($rowDua['price'], 'dua harga: harga ada di price_tiers, bukan price');
        $this->assertCount(2, $rowDua['price_tiers']);

        $this->assertNull($this->row($groups, $this->pusat->id, $modem->id)['price']);
    }

    #[Test]
    public function tersedia_serialized_hanya_sn_available(): void
    {
        $modem = $this->buatItem('SB-MDM', 'Modem Papan', 'serialized');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SBM1', 'SBM2', 'SBM3'], 350000, $this->owner);
        InventorySerial::where('serial_number', 'SBM1')->update(['current_pop_id' => $this->cabangA->id]);
        app(InventoryIssueService::class)->issue($this->cabangA, User::factory()->create(), [
            ['item_id' => $modem->id, 'serial_numbers' => ['SBM1']],
        ], $this->owner);

        $groups = $this->build([$this->pusat->id, $this->cabangA->id]);

        $row = $this->row($groups, $this->pusat->id, $modem->id);
        $this->assertSame(2.0, $row['qty']);
        $this->assertSame(TrackingType::SERIALIZED, $row['tracking']);
        $this->assertNotNull($row['condition_summary']);
        $this->assertNull($this->row($groups, $this->cabangA->id, $modem->id), 'SN yang di-issue bukan stok gudang lagi');
    }

    #[Test]
    public function tersedia_roll_dalam_meter_dan_roll_count(): void
    {
        $fo = $this->buatItem('SB-FO', 'Kabel FO Papan', 'roll', 'meter', 1000);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 2, null, 1500000, $this->owner);
        $roll = InventoryRoll::where('item_id', $fo->id)->orderBy('id')->first();
        $roll->update(['length_remaining' => 400]);

        $row = $this->row($this->build([$this->pusat->id]), $this->pusat->id, $fo->id);

        $this->assertSame(1400.0, $row['qty']);
        $this->assertSame('meter', $row['unit']);
        $this->assertSame(2, $row['roll_count']);
    }

    #[Test]
    public function custody_transit_dan_rusak_tidak_ikut_tersedia(): void
    {
        $modem = $this->buatItem('SB-DMG', 'Modem Rusak', 'serialized');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SBD1', 'SBD2', 'SBD3'], 350000, $this->owner);
        InventorySerial::where('serial_number', 'SBD1')->update(['status' => SerialStatus::DAMAGED->value]);

        $kabel = $this->buatItem('SB-CUS', 'Kabel Custody', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 100, 5000, $this->owner);
        $this->kirimKeCabang($this->cabangA, $kabel, 100);
        app(InventoryIssueService::class)->issue($this->cabangA, User::factory()->create(), [
            ['item_id' => $kabel->id, 'qty' => 30],
        ], $this->owner);

        $groups = $this->build([$this->pusat->id, $this->cabangA->id]);

        $this->assertSame(2.0, $this->row($groups, $this->pusat->id, $modem->id)['qty'], 'SN DAMAGED tidak tersedia');
        $this->assertSame(70.0, $this->row($groups, $this->cabangA->id, $kabel->id)['qty'], 'yang dipegang teknisi tidak ikut');
    }

    #[Test]
    public function pengelompokan_gudang_lalu_kategori_dan_pusat_dulu(): void
    {
        $katB = ItemCategory::create(['code' => 'sb_b', 'name' => 'Kat B', 'default_unit' => 'pcs', 'is_active' => true, 'sort_order' => 20]);
        $katA = ItemCategory::create(['code' => 'sb_a', 'name' => 'Kat A', 'default_unit' => 'pcs', 'is_active' => true, 'sort_order' => 10]);
        $i10 = $this->buatItem('SB-I10', 'Barang 10', 'quantity', 'pcs', null, 'sb_a');
        $i2 = $this->buatItem('SB-I2', 'Barang 2', 'quantity', 'pcs', null, 'sb_a');
        $iB = $this->buatItem('SB-IB', 'Barang B', 'quantity', 'pcs', null, 'sb_b');
        $receive = app(InventoryReceiveService::class);
        foreach ([$i10, $i2, $iB] as $item) {
            $receive->receiveQuantity($this->pusat, $item, 50, 1000, $this->owner);
        }
        $this->kirimKeCabang($this->cabangB, $i2, 5);
        $this->kirimKeCabang($this->cabangA, $i2, 5);
        InventoryBalance::where('item_id', $i10->id)->where('lot_no', '')->update(['minimum_stock' => 99]);

        $groups = $this->build([$this->cabangB->id, $this->cabangA->id, $this->pusat->id]);

        $this->assertSame([$this->pusat->id, $this->cabangA->id, $this->cabangB->id], $groups->pluck('pop.id')->all());

        $pusat = $groups->first();
        $this->assertSame([$katA->id, $katB->id], $pusat['categories']->pluck('category.id')->all());
        $this->assertSame(['Barang 2', 'Barang 10'], $pusat['categories']->first()['rows']->pluck('item.name')->all(), 'urut natural');
        $this->assertSame(2, $pusat['categories']->first()['sku_count']);
        $this->assertSame(3, $pusat['sku_count']);
        $this->assertSame(1, $pusat['low_count']);
        $this->assertSame(1, $groups->get(1)['sku_count']);
        $this->assertSame(0, $groups->get(1)['low_count']);
    }

    #[Test]
    public function kategori_baru_muncul_otomatis_dan_kategori_kosong_tidak_tampil(): void
    {
        $baru = ItemCategory::create(['code' => 'sb_baru', 'name' => 'Kategori Baru', 'default_unit' => 'pcs', 'is_active' => true, 'sort_order' => 99]);
        ItemCategory::create(['code' => 'sb_kosong', 'name' => 'Kategori Kosong', 'default_unit' => 'pcs', 'is_active' => true, 'sort_order' => 98]);
        $item = $this->buatItem('SB-NEW', 'Barang Baru', 'quantity', 'pcs', null, 'sb_baru');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 3, 1000, $this->owner);

        $categories = $this->build([$this->pusat->id])->first()['categories'];

        $this->assertSame([$baru->id], $categories->pluck('category.id')->all());
    }

    #[Test]
    public function is_low_memakai_minimum_stock_lot_kosong(): void
    {
        $rendah = $this->buatItem('SB-LOW', 'Barang Rendah', 'quantity');
        $aman = $this->buatItem('SB-OK', 'Barang Aman', 'quantity');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $rendah, 5, 1000, $this->owner);
        $receive->receiveQuantity($this->pusat, $aman, 50, 1000, $this->owner);
        InventoryBalance::where('item_id', $rendah->id)->where('lot_no', '')->update(['minimum_stock' => 10]);
        InventoryBalance::where('item_id', $aman->id)->where('lot_no', '')->update(['minimum_stock' => 10]);

        $modem = $this->buatItem('SB-SLOW', 'Modem Rendah', 'serialized');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SBL1'], 350000, $this->owner);
        // Serialized: ambang disimpan di baris lot_no='' ber-qty 0 (storeThreshold).
        InventoryBalance::create(['pop_id' => $this->pusat->id, 'item_id' => $modem->id, 'lot_no' => '', 'qty' => 0, 'minimum_stock' => 3]);

        $group = $this->build([$this->pusat->id])->first();

        $this->assertTrue($this->row(collect([$group]), $this->pusat->id, $rendah->id)['is_low']);
        $this->assertSame(10.0, $this->row(collect([$group]), $this->pusat->id, $rendah->id)['min']);
        $this->assertFalse($this->row(collect([$group]), $this->pusat->id, $aman->id)['is_low']);
        $this->assertTrue($this->row(collect([$group]), $this->pusat->id, $modem->id)['is_low']);
        $this->assertSame(2, $group['low_count']);
    }

    #[Test]
    public function last_opname_at_terisi_dari_ledger_stock_opname(): void
    {
        $item = $this->buatItem('SB-OPN', 'Barang Opname', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 5, 1000, $this->owner);
        $this->assertNull($this->row($this->build([$this->pusat->id]), $this->pusat->id, $item->id)['last_opname_at']);

        $this->travelTo(now()->subDays(3));
        $this->opname($item, $this->pusat->id);
        $this->travelBack();
        $this->opname($item, $this->cabangA->id);

        $row = $this->row($this->build([$this->pusat->id]), $this->pusat->id, $item->id);
        $this->assertNotNull($row['last_opname_at']);
        $this->assertTrue($row['last_opname_at']->isSameDay(now()->subDays(3)), 'opname cabang lain tidak ikut');
    }

    private function opname(Item $item, int $popId): void
    {
        InventoryTransaction::create([
            'type' => InventoryTransactionType::STOCK_OPNAME,
            'reference_number' => 'OPN-TEST-'.$popId,
            'item_id' => $item->id,
            'lot_no' => '',
            'qty' => 5,
            'to_pop_id' => $popId,
            'reason' => 'stock_opname_diff',
            'created_by' => $this->owner->id,
        ]);
    }

    #[Test]
    public function pop_filter_hanya_menyempitkan_scope(): void
    {
        $item = $this->buatItem('SB-POPF', 'Barang Filter Pop', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 20, 1000, $this->owner);
        $this->kirimKeCabang($this->cabangA, $item, 4);
        $this->kirimKeCabang($this->cabangB, $item, 6);

        $this->assertCount(0, $this->build([$this->cabangA->id], $this->filters(popId: $this->cabangB->id)), 'popId di luar scope tidak membocorkan');

        $groups = $this->build([$this->cabangA->id, $this->cabangB->id], $this->filters(popId: $this->cabangA->id));
        $this->assertSame([$this->cabangA->id], $groups->pluck('pop.id')->all());
    }

    #[Test]
    public function gudang_tanpa_stok_jadi_band_kosong_hanya_saat_tidak_menyaring(): void
    {
        $item = $this->buatItem('SB-BAND', 'Barang Band', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 3, 1000, $this->owner);
        $scope = [$this->pusat->id, $this->cabangA->id];

        $tanpaFilter = $this->build($scope);
        $this->assertCount(2, $tanpaFilter);
        $this->assertCount(0, $tanpaFilter->get(1)['categories']);
        $this->assertSame(0, $tanpaFilter->get(1)['sku_count']);

        $this->assertCount(1, $this->build($scope, $this->filters(search: 'Band')));
    }

    #[Test]
    public function barang_tanpa_kategori_masuk_grup_tanpa_kategori(): void
    {
        $item = $this->buatItem('SB-NOCAT', 'Barang Tanpa Kategori', 'quantity', 'pcs', null, null);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 9, 1000, $this->owner);

        $categories = $this->build([$this->pusat->id])->first()['categories'];

        $this->assertCount(1, $categories);
        $this->assertNull($categories->first()['category']);
        $this->assertSame($item->id, $categories->first()['rows']->first()['item']->id);
    }

    #[Test]
    public function barang_di_kategori_nonaktif_tetap_tampil_bila_ada_stok(): void
    {
        $item = $this->buatItem('SB-INACT', 'Barang Kategori Mati', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 9, 1000, $this->owner);
        ItemCategory::where('code', 'kabel_dropcore')->update(['is_active' => false]);

        $row = $this->row($this->build([$this->pusat->id]), $this->pusat->id, $item->id);

        $this->assertNotNull($row, 'stok fisik tidak boleh hilang dari papan karena kategorinya dinonaktifkan');
    }

    #[Test]
    public function filter_kategori_tracking_dan_low_only_mempertahankan_yang_lain(): void
    {
        $lain = ItemCategory::create(['code' => 'sb_lain', 'name' => 'Kat Lain', 'default_unit' => 'pcs', 'is_active' => true, 'sort_order' => 50]);
        $qRendah = $this->buatItem('SB-F1', 'Qty Rendah', 'quantity');
        $qAman = $this->buatItem('SB-F2', 'Qty Aman', 'quantity');
        $qLain = $this->buatItem('SB-F3', 'Qty Kategori Lain', 'quantity', 'pcs', null, 'sb_lain');
        $modem = $this->buatItem('SB-F4', 'Modem Filter', 'serialized');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $qRendah, 2, 1000, $this->owner);
        $receive->receiveQuantity($this->pusat, $qAman, 80, 1000, $this->owner);
        $receive->receiveQuantity($this->pusat, $qLain, 1, 1000, $this->owner);
        $receive->receiveSerialized($this->pusat, $modem, ['SBF1'], 350000, $this->owner);
        InventoryBalance::whereIn('item_id', [$qRendah->id, $qAman->id, $qLain->id])->where('lot_no', '')->update(['minimum_stock' => 10]);

        $ids = fn (BoardFilters $f) => $this->build([$this->pusat->id], $f)
            ->flatMap(fn ($g) => $g['categories'])->flatMap(fn ($c) => $c['rows'])
            ->pluck('item.id')->sort()->values()->all();

        $kategoriDefault = ItemCategory::where('code', 'kabel_dropcore')->value('id');

        $this->assertSame([$qRendah->id, $qAman->id, $modem->id], $ids($this->filters(categoryId: $kategoriDefault)));
        $this->assertSame([$qRendah->id, $qAman->id, $qLain->id], $ids($this->filters(tracking: 'quantity')));
        $this->assertSame([$qRendah->id, $qLain->id], $ids($this->filters(lowOnly: true)));
        $this->assertSame([$qRendah->id], $ids($this->filters(categoryId: $kategoriDefault, tracking: 'quantity', lowOnly: true)));
        $this->assertSame([$qAman->id], $ids($this->filters(tracking: 'quantity', search: 'Aman')));
        $this->assertSame([], $ids($this->filters(categoryId: $lain->id, lowOnly: false, tracking: 'serialized')));
    }
}
