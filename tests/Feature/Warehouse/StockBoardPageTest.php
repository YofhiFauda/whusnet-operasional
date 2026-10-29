<?php

namespace Tests\Feature\Warehouse;

use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Services\InventoryReceiveService;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * Halaman papan Kelola Stok (`?view=papan`): band Gudang → Kategori → Barang,
 * toolbar + chip filter, keadaan kosong, dan kebocoran scope POP.
 *
 * Banyak cek dilakukan pada FRAGMEN papan saja (antara penanda
 * `papan:awal`/`papan:akhir`), karena header gudang & sidebar sah memuat
 * nama gudang (switcher) dan menu "Custody" — yang diuji adalah papannya.
 */
class StockBoardPageTest extends StockBoardTestCase
{
    private function papan(array $query = [], $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->owner)
            ->get(route('warehouse.stock.index', array_merge(['view' => 'papan'], $query)));
    }

    private function fragmen(TestResponse $response): string
    {
        $html = $response->getContent();
        $awal = strpos($html, '<!-- papan:awal -->');
        $akhir = strpos($html, '<!-- papan:akhir -->');
        $this->assertNotFalse($awal, 'penanda papan:awal tidak dirender');
        $this->assertNotFalse($akhir, 'penanda papan:akhir tidak dirender');

        return substr($html, $awal, $akhir - $awal);
    }

    /** Teks fragmen tanpa tag, spasi dirapatkan — padanan assertSeeText. */
    private function teks(string $fragmen): string
    {
        return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($fragmen)));
    }

    /**
     * @param  array<int, string>  $urutan
     */
    private function assertUrutan(array $urutan, string $haystack): void
    {
        $posisi = 0;
        foreach ($urutan as $needle) {
            $ketemu = strpos($haystack, $needle, $posisi);
            $this->assertNotFalse($ketemu, "'{$needle}' tidak ditemukan setelah posisi {$posisi}");
            $posisi = $ketemu + strlen($needle);
        }
    }

    private function stokQuantity(Pop $pop, Item $item, float $qty, ?float $min = null): void
    {
        InventoryBalance::create([
            'pop_id' => $pop->id,
            'item_id' => $item->id,
            'lot_no' => '',
            'qty' => $qty,
            'minimum_stock' => $min,
        ]);
    }

    private function sn(Pop $pop, Item $item, string $serial): void
    {
        InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serial,
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $pop->id,
        ]);
    }

    private function namaKategori(string $code): string
    {
        return ItemCategory::where('code', $code)->value('name');
    }

    #[Test]
    public function papan_menampilkan_band_gudang_band_kategori_dan_baris_barang(): void
    {
        $modem = $this->buatItem('SP-MDM', 'Modem Papan', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->pusat, $modem, 'SPM-1');
        $kabel = $this->buatItem('SP-KBL', 'Kabel Papan Cabang', 'quantity', 'meter');
        $this->stokQuantity($this->cabangA, $kabel, 40);

        $response = $this->papan();

        $response->assertOk();
        $this->assertSame('papan', $response->viewData('viewMode'));
        $fragmen = $this->fragmen($response);
        $this->assertUrutan([
            'Pusat Papan', $this->namaKategori('modem_ont'), 'Modem Papan',
            'Cabang Papan A', $this->namaKategori('kabel_dropcore'), 'Kabel Papan Cabang',
        ], $fragmen);
        // Gudang dalam scope tanpa stok tetap tampil sebagai band kosong.
        $this->assertStringContainsString('Cabang Papan B', $fragmen);
        $this->assertStringContainsString('Belum ada stok tersedia', $fragmen);
    }

    #[Test]
    public function papan_tidak_menampilkan_kolom_custody_teknisi(): void
    {
        $modem = $this->buatItem('SP-CUS', 'Modem Gudang Biasa', 'serialized', 'unit');
        $this->sn($this->pusat, $modem, 'SPC-1');

        $fragmen = $this->fragmen($this->papan()->assertOk());

        $this->assertStringNotContainsString('Custody', $fragmen);
        $this->assertStringNotContainsString('Di teknisi', $fragmen);
        $this->assertStringNotContainsString('Dalam perjalanan', $fragmen);
    }

    #[Test]
    public function papan_tidak_memakai_istilah_lot_di_ui(): void
    {
        $dua = $this->buatItem('SP-Q2', 'Dropcore Dua Harga', 'quantity', 'meter');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $dua, 12, 250000, $this->owner);
        $receive->receiveQuantity($this->pusat, $dua, 10, 260000, $this->owner);

        $response = $this->papan()->assertOk();
        $response->assertDontSeeText('Per Lot');
        $fragmen = $this->fragmen($response);
        $teks = $this->teks($fragmen);

        // Yang diuji teks yang TERLIHAT pengguna (tanpa tag/atribut), tanpa
        // peduli huruf besar-kecil. Atribut boleh membawa `lot_no` (URL aksi,
        // payload drawer) — itu bukan istilah di UI.
        $this->assertDoesNotMatchRegularExpression('/\blot\b/iu', $teks, 'istilah lot tidak boleh muncul di teks papan');
        $this->assertStringContainsString('Harga lama', $teks);
        $this->assertStringContainsString('Harga baru', $teks);
        $this->assertStringContainsString('Rp 250.000', $teks);
        $this->assertStringContainsString('Rp 260.000', $teks);
        $this->assertStringContainsString('22 meter', $teks);
    }

    #[Test]
    public function kolom_tersedia_menampilkan_satuan_sesuai_tracking(): void
    {
        $modem = $this->buatItem('SP-SN', 'Modem Banyak', 'serialized', 'unit', null, 'modem_ont');
        for ($i = 1; $i <= 152; $i++) {
            $this->sn($this->pusat, $modem, sprintf('SPN-%03d', $i));
        }
        $fo = $this->buatItem('SP-FO', 'Kabel FO Papan', 'roll', 'meter', 1000);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 2, null, 1500000, $this->owner);
        $konektor = $this->buatItem('SP-KON', 'Konektor Papan', 'quantity', 'pcs');
        $this->stokQuantity($this->pusat, $konektor, 7.5);

        $teks = $this->teks($this->fragmen($this->papan()->assertOk()));

        $this->assertStringContainsString('152 unit', $teks);
        $this->assertStringContainsString('2.000 meter', $teks);
        $this->assertStringContainsString('2 roll', $teks);
        $this->assertStringContainsString('7,5 pcs', $teks);
        $this->assertStringContainsString('152 Baru', $teks, 'kondisi SN diringkas sebagai teks');
    }

    #[Test]
    public function status_menipis_berupa_teks(): void
    {
        $rendah = $this->buatItem('SP-LOW', 'Barang Menipis Papan', 'quantity');
        $aman = $this->buatItem('SP-OK', 'Barang Aman Papan', 'quantity');
        $this->stokQuantity($this->pusat, $rendah, 5, 10);
        $this->stokQuantity($this->pusat, $aman, 50, 10);

        $teks = $this->teks($this->fragmen($this->papan()->assertOk()));

        $this->assertStringContainsString('Menipis', $teks);
        $this->assertStringContainsString('Aman', $teks);
        $this->assertStringContainsString('Min 10', $teks);
        $this->assertStringContainsString('1 menipis', $teks, 'band gudang menghitung barang menipis');
    }

    #[Test]
    public function filter_gudang_kategori_tracking_dan_menipis_tampil_sebagai_chip_dengan_hapus_semua(): void
    {
        $modem = $this->buatItem('SP-CHP', 'Modem Chip', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->pusat, $modem, 'SPH-1');
        InventoryBalance::create(['pop_id' => $this->pusat->id, 'item_id' => $modem->id, 'lot_no' => '', 'qty' => 0, 'minimum_stock' => 5]);
        $kategoriId = ItemCategory::where('code', 'modem_ont')->value('id');

        $query = ['pop_id' => $this->pusat->id, 'category_id' => $kategoriId, 'tracking' => 'serialized', 'low_stock_only' => 1];
        $response = $this->papan($query)->assertOk();
        $fragmen = $this->fragmen($response);
        $teks = $this->teks($fragmen);

        $this->assertStringContainsString('Gudang: Pusat Papan', $teks);
        $this->assertStringContainsString('Kategori: '.$this->namaKategori('modem_ont'), $teks);
        $this->assertStringContainsString('Tracking: SN', $teks);
        $this->assertStringContainsString('Hanya menipis', $teks);
        $this->assertStringContainsString('Modem Chip', $teks);

        // URL dirakit server: hapus satu chip mempertahankan filter lain.
        $tanpaKategori = route('warehouse.stock.index', ['view' => 'papan', 'pop_id' => $this->pusat->id, 'tracking' => 'serialized', 'low_stock_only' => 1]);
        $this->assertStringContainsString('href="'.e($tanpaKategori).'"', $fragmen);
        // Hapus semua: pop_id dikosongkan eksplisit supaya pilihan gudang di
        // session (switcher header) tidak diam-diam menyaring lagi.
        $hapusSemua = route('warehouse.stock.index', ['view' => 'papan', 'pop_id' => '']);
        $this->assertStringContainsString('href="'.e($hapusSemua).'"', $fragmen);
        $this->assertStringContainsString('Hapus semua', $teks);
        // Form filter mempertahankan mode papan.
        $this->assertStringContainsString('name="view" value="papan"', $fragmen);
    }

    #[Test]
    public function pencarian_otomatis_menyembunyikan_gudang_yang_tidak_cocok(): void
    {
        $alfa = $this->buatItem('SP-ALF', 'Modem Alfa', 'quantity');
        $beta = $this->buatItem('SP-BET', 'Splitter Beta', 'quantity');
        $this->stokQuantity($this->pusat, $alfa, 3);
        $this->stokQuantity($this->cabangA, $beta, 4);
        $gamma = $this->buatItem('SP-GAM', 'ONT Gamma', 'serialized', 'unit');
        $this->sn($this->cabangB, $gamma, 'ZZ-777');

        $teks = $this->teks($this->fragmen($this->papan(['search' => 'Alfa'])->assertOk()));
        $this->assertStringContainsString('Pusat Papan', $teks);
        $this->assertStringContainsString('Modem Alfa', $teks);
        $this->assertStringNotContainsString('Cabang Papan A', $teks);
        $this->assertStringNotContainsString('Splitter Beta', $teks);
        $this->assertStringNotContainsString('Cabang Papan B', $teks, 'band kosong tidak tampil saat mencari');

        $teksSn = $this->teks($this->fragmen($this->papan(['search' => 'ZZ-777'])->assertOk()));
        $this->assertStringContainsString('Cabang Papan B', $teksSn);
        $this->assertStringContainsString('ONT Gamma', $teksSn);
        $this->assertStringContainsString('Cocok: SN ZZ-777', $teksSn);
        $this->assertStringNotContainsString('Pusat Papan', $teksSn);
    }

    #[Test]
    public function keadaan_kosong_tiga_jenis_menampilkan_pesan_berbeda(): void
    {
        $pesanAkses = 'Anda belum punya gudang yang boleh diakses';
        $pesanCocok = 'Tidak ada barang yang cocok';
        $pesanStok = 'Belum ada stok tersedia di gudang dalam cakupan Anda';

        // (b) belum ada stok sama sekali.
        $teksStok = $this->teks($this->fragmen($this->papan()->assertOk()));
        $this->assertStringContainsString($pesanStok, $teksStok);
        $this->assertStringNotContainsString($pesanCocok, $teksStok);
        $this->assertStringNotContainsString($pesanAkses, $teksStok);

        // (a) ada stok, tapi pencarian tidak cocok.
        $item = $this->buatItem('SP-EMP', 'Barang Ada', 'quantity');
        $this->stokQuantity($this->pusat, $item, 2);
        $teksCocok = $this->teks($this->fragmen($this->papan(['search' => 'tidak-ada-apa-apa'])->assertOk()));
        $this->assertStringContainsString($pesanCocok, $teksCocok);
        $this->assertStringNotContainsString($pesanStok, $teksCocok);
        $this->assertStringNotContainsString($pesanAkses, $teksCocok);

        // (c) user tanpa gudang dalam scope: tanpa nama/jumlah gudang apa pun,
        // di seluruh halaman (bukan hanya fragmen).
        $tanpaScope = $this->userDenganScope([]);
        $response = $this->papan([], $tanpaScope)->assertOk();
        $teksAkses = $this->teks($this->fragmen($response));
        $this->assertStringContainsString($pesanAkses, $teksAkses);
        $this->assertStringNotContainsString($pesanStok, $teksAkses);
        $this->assertStringNotContainsString($pesanCocok, $teksAkses);
        foreach (['Pusat Papan', 'Cabang Papan A', 'Cabang Papan B', 'Barang Ada'] as $nama) {
            $response->assertDontSee($nama);
        }
        $this->assertStringNotContainsString('SKU', $teksAkses);
    }

    #[Test]
    public function pop_id_di_luar_scope_tidak_membocorkan_data(): void
    {
        $milikA = $this->buatItem('SP-INA', 'Barang Milik A', 'quantity');
        $rahasia = $this->buatItem('SP-RHS', 'Barang Rahasia B', 'quantity');
        $this->stokQuantity($this->cabangA, $milikA, 5);
        $this->stokQuantity($this->cabangB, $rahasia, 4321);
        $popAdminA = $this->userDenganScope([$this->cabangA->id]);

        $response = $this->papan(['pop_id' => $this->cabangB->id], $popAdminA)->assertOk();

        $response->assertDontSee('Cabang Papan B');
        $response->assertDontSee('Barang Rahasia B');
        $response->assertDontSee('4.321');
        $this->assertStringNotContainsString('4.321', $this->teks($this->fragmen($response)));
    }

    #[Test]
    public function satu_gudang_langsung_terbuka_dan_aksi_cari_di_semua_gudang_menghapus_filter_gudang(): void
    {
        $modem = $this->buatItem('SP-ONE', 'Modem Satu Gudang', 'quantity');
        $this->stokQuantity($this->pusat, $modem, 3);
        $this->stokQuantity($this->cabangA, $modem, 6);

        // Satu gudang dipilih + pencarian aktif → terbuka, dan ada jalan keluar
        // ke semua gudang terjangkau yang HANYA mengosongkan filter gudang.
        $fragmen = $this->fragmen($this->papan(['pop_id' => $this->pusat->id, 'search' => 'Modem'])->assertOk());
        $this->assertStringContainsString('data-pop-count="1"', $fragmen);
        $this->assertStringContainsString('data-force-open="true"', $fragmen);
        $semuaGudang = route('warehouse.stock.index', ['view' => 'papan', 'search' => 'Modem', 'pop_id' => '']);
        $this->assertStringContainsString('href="'.e($semuaGudang).'"', $fragmen);
        $this->assertStringContainsString('Cari di semua gudang terjangkau', $this->teks($fragmen));

        // Pengguna yang terkunci di satu gudang: terbuka walau tanpa pencarian,
        // dan tidak ditawari "semua gudang" (scope-nya memang cuma satu).
        $popAdminA = $this->userDenganScope([$this->cabangA->id]);
        $fragmenTerkunci = $this->fragmen($this->papan(['search' => 'Modem'], $popAdminA)->assertOk());
        $this->assertStringContainsString('data-force-open="true"', $fragmenTerkunci);
        $this->assertStringNotContainsString('Cari di semua gudang terjangkau', $this->teks($fragmenTerkunci));

        // Lebih dari 5 gudang tanpa filter → band dilipat di awal.
        foreach (['X1', 'X2', 'X3'] as $kode) {
            $this->buatPop('SB-'.$kode, 'Cabang Tambahan '.$kode, 'cabang');
        }
        $fragmenBanyak = $this->fragmen($this->papan()->assertOk());
        $this->assertStringContainsString('data-pop-count="6"', $fragmenBanyak);
        $this->assertStringContainsString('data-force-open="false"', $fragmenBanyak);
    }
}
