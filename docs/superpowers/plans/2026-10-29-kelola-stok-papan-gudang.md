# Papan Kelola Stok Gudang Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti penyajian `warehouse.stock.index` menjadi papan Gudang → Kategori → Barang (tabel berkelompok + tab Matriks + drawer rincian) tanpa mengubah logika/data Gudang.

**Architecture:** Read-model baru `WarehouseStockBoardService` menyusun data dari `WarehouseStockPositionService::availableByPopAndItem()` (dijadikan public) plus query kecil per tabel. Controller tipis memilih tampilan lewat `?view=`; papan dibangun dulu di `?view=papan`, baru di task terakhir dijadikan default sementara Per Lot/Ringkasan pindah ke `?view=rincian`. Blade dipecah jadi partial di `resources/views/warehouse/stock/board/`; Alpine untuk lipat/buka dan drawer.

**Tech Stack:** PHP 8.3, Laravel 13, PHPUnit 12 (atribut `#[Test]`), Blade + Alpine 3 (Vite), Tailwind 4, sqlite `:memory:` untuk tes.

**Spec:** `docs/superpowers/specs/2026-10-29-kelola-stok-papan-gudang-design.md`

## Global Constraints

- Tanpa migrasi, tanpa tabel baru, tanpa permission baru, tanpa alur mutasi baru (spec §1, §8).
- Custody teknisi, transit, dan "Dalam perjalanan" **tidak** tampil di papan (spec §1 "Di luar cakupan").
- Istilah "lot" tidak muncul di UI baru maupun pencarian; `lot_no` hanya dibawa di URL aksi sesuai spec §6.
- Semua query stok dibatasi `$popIds` dari `EffectiveAccessService`; filter `pop_id` dari URL hanya menyempitkan (spec §8). Jangan pakai `$user->pops()`.
- "Tersedia": QUANTITY = SUM `inventory_balances.qty`; SERIALIZED = COUNT SN `AVAILABLE`; ROLL = SUM `length_remaining` roll `AVAILABLE`/`RECEIVED` dalam meter (spec §5).
- Target aksi dirender server lewat `route()`; klien tidak merakit path (`PostTargetRenderedServerSideTest`).
- Komentar bahasa Indonesia, jelaskan *kenapa*. Enum, bukan string literal (`TrackingType`, `SerialStatus`, `RollStatus`, `ItemCondition`).
- Jalankan hanya file tes terdampak: `php artisan test --compact tests/Feature/...`. **Jangan** full suite.
- `vendor/bin/pint --dirty --format agent` sebelum menutup tiap task. `npm run build` setelah ubah Blade/JS (task 4–6).
- **Jangan commit** kecuali pengguna meminta (aturan repo + preferensi pengguna: kerja di `dev`). Task ditutup dengan tes hijau + pint, bukan commit.
- Fitur baru wajib bertes; nama tes sesuai gejala.

## Review Focus

Input/kondisi yang spec implikasikan tapi tidak dilindungi tes langsung; tiap baris dipasang tesnya di task pemilik:

1. SN/kode roll milik gudang di luar scope tidak boleh memunculkan baris, label `Cocok:`, atau nama gudang (Task 3).
2. Barang tanpa kategori (`item_category_id` null) tidak boleh membuat halaman error; tampil di grup "Tanpa Kategori" (Task 2).
3. Barang dengan kategori **nonaktif** tetap tampil bila punya stok (stok nyata tidak boleh hilang dari papan) (Task 2).
4. Kata kunci berisi `%`, `_`, atau sangat panjang tidak boleh melebarkan hasil atau error; harus lewat `LikeSearch::sanitize` (Task 3).
5. Pengguna tanpa gudang dalam scope melihat keadaan kosong "tidak punya akses" tanpa nama/jumlah apa pun; gudang tanpa stok tampil sebagai band kosong, bukan hilang (Task 3 + 4).

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `app/Support/BoardFilters.php` (baru) | Value object filter papan; satu tempat normalisasi input |
| `app/Services/WarehouseStockPositionService.php` (ubah) | `availableByPopAndItem()` jadi public; tambah `conditionByPopAndItem()` |
| `app/Services/WarehouseStockBoardService.php` (baru) | `build()`, `matrix()`, `emptyState()`; read-only |
| `app/Http/Controllers/Warehouse/WarehouseStockController.php` (ubah) | Cabang `view=papan`/`matrix`; di Task 7 jadi default |
| `resources/views/warehouse/stock/board/` (baru) | `_toolbar`, `_table`, `_matrix`, `_drawer`, `_actions`, `_empty` |
| `resources/views/warehouse/stock/index.blade.php` (ubah) | Toggle tampilan 3 mode; `@include` papan |
| `tests/Feature/Warehouse/StockBoardTestCase.php` (baru) | Base class tes: seeding + helper `buatItem()`, `buatPop()` |
| `tests/Feature/Warehouse/StockBoard*Test.php` (baru) | Tes per perilaku |

---

### Task 1: `BoardFilters` + perubahan `WarehouseStockPositionService`

**Files:**
- Create: `app/Support/BoardFilters.php`
- Modify: `app/Services/WarehouseStockPositionService.php` (`availableByPopAndItem` line ~286 → public; tambah `conditionByPopAndItem`)
- Create: `tests/Feature/Warehouse/StockBoardTestCase.php`
- Test: `tests/Feature/Warehouse/StockBoardFiltersTest.php`, tambah ke `tests/Feature/WarehouseStockPositionTest.php`

**Interfaces:**
- Produces: `final readonly class App\Support\BoardFilters { public function __construct(public ?int $popId, public ?int $categoryId, public ?string $tracking, public string $search, public bool $lowOnly); public static function fromRequest(Illuminate\Http\Request $request, ?int $resolvedPopId): self; public function isFiltering(): bool }` — `isFiltering()` true bila categoryId/tracking/search/lowOnly terisi (popId tidak dihitung).
- Produces: `WarehouseStockPositionService::availableByPopAndItem(Collection $popIds): array` (public, bentuk sama: `[itemId => [popId => ['pop_id','qty','lots'=>[['lot_no','qty']]]]]`).
- Produces: `WarehouseStockPositionService::conditionByPopAndItem(Collection $popIds): Collection` → `Collection<"popId-itemId", Collection<int, array{condition: ItemCondition, checked: bool, count: int}>>`.
- Produces (tes): `abstract class Tests\Feature\Warehouse\StockBoardTestCase extends TestCase` dengan `User $owner`, `Pop $pusat/$cabangA/$cabangB`, `buatItem(string $code, string $name, string $tracking, string $unit = 'pcs', ?float $meterPerRoll = null, ?string $categoryCode = 'kabel_dropcore'): Item`, `buatPop(string $code, string $name, string $type): Pop`, `userDenganScope(array $popIds): User` (role `pop_admin` + scope `selected_pop`; ikuti cara `WarehouseStockPageTest` membuat `$popAdminA`).

- [ ] **Step 1: Tulis tes gagal.** `StockBoardFiltersTest`: `fromRequest_menormalkan_input` (kategori `'abc'` → null, tracking `'sembarang'` → null, tracking `'roll'` → `'roll'`, search `'a%b'` melewati `LikeSearch::sanitize`, `low_stock_only=1` → true); `isFiltering_false_bila_hanya_pop_dipilih`. Di `WarehouseStockPositionTest`: `available_by_pop_and_item_publik_dan_sama_dengan_ringkasan` (angka `per_pop` dari `summarize()` sama dengan `availableByPopAndItem()`); `condition_by_pop_and_item_memisah_kondisi_per_gudang` (receive 2 SN di pusat, 1 di cabangA, tandai satu `used_damaged`; assert hitungan per kunci `"{pop}-{item}"` dan SN non-`AVAILABLE` tidak dihitung).
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --compact tests/Feature/Warehouse/StockBoardFiltersTest.php` → FAIL (kelas tidak ada).
- [ ] **Step 3: Implement** `BoardFilters` (normalisasi tracking via `array_column(TrackingType::cases(),'value')`), ubah visibilitas `availableByPopAndItem`, tambah `conditionByPopAndItem` (salin query `conditionByItem` tetapi `groupBy('current_pop_id','item_id',…)`). Buat `StockBoardTestCase` dengan seeding yang sama seperti `WarehouseStockPositionTest::setUp` (Feature/Action/Role/Permission/WarehouseFeature/RolePermission/ItemCategory seeder).
- [ ] **Step 4: Jalankan** kedua file tes → PASS; jalankan juga `tests/Feature/WarehouseStockPositionTest.php` utuh (regresi) → PASS.
- [ ] **Step 5:** `vendor/bin/pint --dirty --format agent`.

---

### Task 2: `WarehouseStockBoardService::build()` (angka + pengelompokan + scope)

**Files:**
- Create: `app/Services/WarehouseStockBoardService.php`
- Test: `tests/Feature/Warehouse/StockBoardBuildTest.php`

**Interfaces:**
- Consumes: Task 1 (`BoardFilters`, `availableByPopAndItem`, `conditionByPopAndItem`).
- Produces: `WarehouseStockBoardService::build(Collection $popIds, BoardFilters $f): Collection` — daftar grup gudang, tiap elemen array:
  `['pop' => Pop, 'sku_count' => int, 'low_count' => int, 'categories' => Collection<array{category: ?ItemCategory, sku_count: int, rows: Collection}>]`
  dan tiap row array: `['item' => Item, 'tracking' => TrackingType, 'qty' => float, 'unit' => string, 'roll_count' => ?int, 'min' => ?float, 'is_low' => bool, 'lot_no' => ?string, 'price_tiers' => array<int, array{lot_no: string, qty: float, price: ?float}>, 'condition_summary' => ?Collection, 'last_opname_at' => ?Carbon, 'match_label' => ?string]`.
  Konstruktor tanpa argumen wajib; boleh menerima `WarehouseStockPositionService` via container.

- [ ] **Step 1: Tulis tes gagal** (`StockBoardBuildTest` extends `StockBoardTestCase`; stok dibuat lewat `InventoryReceiveService::receiveQuantity/receiveSerialized/receiveRoll` seperti tes yang ada):
  - `tersedia_quantity_menjumlah_dua_harga_dan_menandai_price_tiers`: dua receive harga beda hingga terbentuk 2 lot (ikuti `analisa-2-slot-harga-quantity`) → `qty` = jumlah keduanya, `price_tiers` ada 2 entri dengan `lot_no`, `lot_no` row null; satu harga → `lot_no` terisi, `price_tiers` kosong.
  - `tersedia_serialized_hanya_sn_available`: 3 SN receive, 1 di-issue ke teknisi → `qty` 2; `condition_summary` tidak null.
  - `tersedia_roll_dalam_meter_dan_roll_count`: 2 roll @1000 m, satu dikonsumsi sebagian → `qty` = SUM sisa meter, `unit` `'meter'`, `roll_count` 2.
  - `custody_transit_dan_rusak_tidak_ikut_tersedia`: barang di custody teknisi / SN `DAMAGED` tidak menambah `qty`.
  - `pengelompokan_gudang_lalu_kategori_dan_pusat_dulu`: urutan gudang pusat → cabang (nama), kategori mengikuti `ItemCategory::ordered()`, barang nama natural; `sku_count`/`low_count` benar.
  - `kategori_baru_muncul_otomatis_dan_kategori_kosong_tidak_tampil`.
  - `is_low_memakai_minimum_stock_lot_kosong`: threshold lewat `InventoryBalance` `lot_no=''` (pola `WarehouseStockThresholdTest`) → `is_low` true & `low_count` naik.
  - `last_opname_at_terisi_dari_ledger_stock_opname`.
  - `popFilter_hanya_menyempitkan_scope`: `$popIds=[cabangA]`, `BoardFilters(popId: cabangB)` → hasil kosong, bukan data cabangB; `popId: cabangA` → hanya cabangA.
  - `barang_tanpa_kategori_masuk_grup_tanpa_kategori` (category `null`) — Review Focus 2.
  - `barang_di_kategori_nonaktif_tetap_tampil_bila_ada_stok` — Review Focus 3.
  - `filter_kategori_tracking_dan_lowOnly_mempertahankan_yang_lain`.
- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/Warehouse/StockBoardBuildTest.php` → FAIL.
- [ ] **Step 3: Implement `build()`.** Urutan: persempit `$popIds` dengan `popId` → ambil `Pop::warehouse()->whereIn('id', …)` diurutkan `type` (pusat dulu) lalu nama → `availableByPopAndItem` → muat `Item::with('category')` untuk id yang ada, terapkan filter kategori/tracking/search nama-kode (`LikeSearch`-style `like`) → threshold: satu query `InventoryBalance` `lot_no=''` untuk pasangan terkait → harga per `(item_id, lot_no)` dari `InventoryTransaction` tipe RECEIVE (last-cost, urut `id` asc ditimpa; pola controller lama) → `roll_count` satu query `InventoryRoll` grouped → opname terakhir satu query `STOCK_OPNAME` grouped `to_pop_id,item_id` → `conditionByPopAndItem`. Hindari N+1: semua query per-tabel dikelompokkan. `lowOnly` membuang row yang tidak `is_low` (dan kategori/gudang yang kosong akibatnya, kecuali aturan band kosong Task 3).
- [ ] **Step 4: Jalankan** file tes → PASS.
- [ ] **Step 5:** pint.

---

### Task 3: Pencarian SN/kode roll, label `Cocok:`, dan `emptyState()`

**Files:**
- Modify: `app/Services/WarehouseStockBoardService.php`
- Test: `tests/Feature/Warehouse/StockBoardSearchTest.php`

**Interfaces:**
- Consumes: Task 2 `build()`.
- Produces: `build()` kini menerapkan `$f->search` ke nama/kode **dan** SN/kode roll; row hasil kecocokan SN/roll membawa `match_label` `"Cocok: SN {serial}"` / `"Cocok: roll {roll_code}"` (bila banyak, label pertama + " +N lagi").
- Produces: `WarehouseStockBoardService::emptyState(Collection $popIds, Collection $groups, BoardFilters $f): ?string` → `'no_access'` (popIds kosong), `'no_match'` (`$f->isFiltering()` dan tak ada row), `'no_stock'` (tak ada row, tidak filtering), `null` (ada row).
- Produces: pada pencarian aktif gudang/kategori tanpa row **tidak** dikembalikan; tanpa pencarian gudang tanpa stok dikembalikan sebagai grup dengan `categories` kosong (band kosong).

- [ ] **Step 1: Tulis tes gagal** (`StockBoardSearchTest`):
  - `cari_sn_menemukan_barang_dan_gudang_yang_benar_dengan_label_cocok`.
  - `cari_kode_roll_menemukan_roll_dan_gudangnya`.
  - `sn_gudang_luar_scope_tidak_ditemukan_dan_tidak_bocor` — SN ada di cabangB, `$popIds=[cabangA]`, cari SN itu → tidak ada row, tidak ada `match_label`, `emptyState` `'no_match'` (Review Focus 1).
  - `kata_kunci_wildcard_dan_panjang_tidak_melebar` — search `'%'`, `'_'`, string 500 karakter → hasil identik dengan `LikeSearch::sanitize` (tidak mengembalikan semua barang) dan tanpa exception (Review Focus 4).
  - `pencarian_nama_dan_kode_memfilter_baris`.
  - `hasil_pencarian_sn_dibatasi_50_pasangan` — 60 SN berbeda item sehingga >50 pasangan → tepat 50 pasangan dan penanda terpotong tersedia lewat `emptyState`-nya tetap null; tambahkan properti `truncated` bila perlu (nama field: tentukan satu, mis. `search_truncated` pada grup pertama — pilih satu dan konsisten dengan Task 4).
  - `empty_state_membedakan_no_access_no_match_no_stock` (Review Focus 5).
  - `gudang_tanpa_stok_tampil_sebagai_band_kosong_hanya_tanpa_filter`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implement.** Dua query kecil: `InventorySerial` `whereIn('current_pop_id',$popIds)` + `status AVAILABLE` + `serial_number like` → `limit(50)` ambil `(current_pop_id,item_id,serial_number)`; `InventoryRoll` serupa untuk `roll_code`, status `AVAILABLE`/`RECEIVED`. Hasil menambah set (pop,item) yang lolos filter nama/kode (union), dan mengisi `match_label`. Sanitasi memakai `BoardFilters->search` yang sudah `LikeSearch::sanitize`. Implement `emptyState()`.
- [ ] **Step 4: Jalankan** `StockBoardSearchTest` + `StockBoardBuildTest` → PASS.
- [ ] **Step 5:** pint.

---

### Task 4: Controller `?view=papan` + tabel berkelompok + toolbar + keadaan kosong

**Files:**
- Modify: `app/Http/Controllers/Warehouse/WarehouseStockController.php` (`index()` — cabang awal sebelum logika lama)
- Modify: `resources/views/warehouse/stock/index.blade.php` (toggle 3 mode: Papan / Per Lot / Ringkasan; `@include` papan bila `$viewMode === 'papan'`)
- Create: `resources/views/warehouse/stock/board/_toolbar.blade.php`, `_table.blade.php`, `_empty.blade.php`
- Test: `tests/Feature/Warehouse/StockBoardPageTest.php`

**Interfaces:**
- Consumes: `BoardFilters::fromRequest()`, `build()`, `emptyState()`.
- Produces: `index()` dengan `?view=papan` merender `warehouse.stock.index` dengan variabel `viewMode='papan'`, `groups`, `emptyState`, `boardFilters`, `categories`, `pops`, `lockedSinglePop`, `canAdjust/canTransfer/canIssue/canRequestStock` (dihitung di controller, bukan lagi di Blade, untuk cabang papan). Cabang lama tidak diubah.
- Alpine: komponen `stockBoard({ collapseGudangOver: 5 })` di `x-data` pembungkus tabel — state `openPops`, `openCategories`; tombol "Bentang semua"/"Ciutkan". Gudang terbuka awal: semua bila ≤5 gudang atau `lockedSinglePop`; bila ada pencarian/filter aktif, hanya grup yang punya row (otomatis karena `build()` membuang yang kosong).

- [ ] **Step 1: Tulis tes gagal** (`StockBoardPageTest`):
  - `papan_menampilkan_band_gudang_band_kategori_dan_baris_barang` (`assertSeeInOrder` nama gudang → nama kategori → nama barang).
  - `papan_tidak_menampilkan_kolom_custody_teknisi` (`assertDontSee('Custody')`, `assertDontSee('Di teknisi')`).
  - `papan_tidak_memakai_istilah_lot_di_ui` (`assertDontSee('Lot')` pada papan dengan stok QUANTITY dua harga; "harga" boleh).
  - `kolom_tersedia_menampilkan_satuan_sesuai_tracking` (`152 unit`, `meter`, "N roll").
  - `status_menipis_berupa_teks` (`assertSee('Menipis')`).
  - `filter_gudang_kategori_tracking_dan_menipis_tampil_sebagai_chip_dengan_hapus_semua`.
  - `pencarian_otomatis_menyembunyikan_gudang_yang_tidak_cocok`.
  - `keadaan_kosong_tiga_jenis_menampilkan_pesan_berbeda` (tiga teks berbeda; `no_access` untuk user tanpa scope tidak menampilkan nama gudang mana pun) (Review Focus 5).
  - `pop_id_di_luar_scope_tidak_membocorkan_data`: `popAdminA` + `pop_id=cabangB` → tidak ada nama/jumlah cabangB.
  - `satu_gudang_langsung_terbuka_dan_aksi_cari_di_semua_gudang_menghapus_filter_gudang`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implement** cabang `papan` di `index()` (tentukan scope seperti kode lama, `BoardFilters::fromRequest($request, $popFilter)`), partial toolbar (form GET `route('warehouse.stock.index')` dengan hidden `view=papan`, input cari, select kategori/tracking, pill Menipis, chip filter), `_table` (band gudang gelap + ringkasan SKU/menipis, band kategori, baris dengan 7 kolom spec §3, band kosong), `_empty` (3 pesan), toggle 3 mode di `index.blade.php`. Kolom Aksi baru diisi di Task 5 (sementara sel kosong bertanda `{{-- aksi: Task 5 --}}` — tidak boleh tersisa setelah Task 5). Responsif: mobile menumpuk kolom sekunder (Kondisi/Opname) di bawah nama barang.
- [ ] **Step 4:** `npm run build`; jalankan `StockBoardPageTest` → PASS. Jalankan `tests/Feature/WarehouseStockPageTest.php` (tampilan lama default belum diubah) → PASS.
- [ ] **Step 5:** pint.

---

### Task 5: Aksi baris (aturan `lot_no`) + drawer rincian

**Files:**
- Create: `resources/views/warehouse/stock/board/_actions.blade.php`, `_drawer.blade.php`
- Modify: `resources/views/warehouse/stock/board/_table.blade.php`, `app/Http/Controllers/Warehouse/WarehouseStockController.php` (variabel aksi per row — disusun di Blade partial dari `$canX`, tidak ada logic baru di controller)
- Test: `tests/Feature/Warehouse/StockBoardActionsTest.php`

**Interfaces:**
- Consumes: variabel Task 4; endpoint existing `route('warehouse.stock.serials')`, `route('warehouse.stock.rolls')` (query `pop_id`,`item_id`); rute aksi existing `warehouse.transfers.create`, `warehouse.stock-requests.create`, `warehouse.issues.create`, `warehouse.adjustments.balance.create`, `warehouse.stock.threshold.create`.
- Produces: event window `open-stock-drawer` dengan detail JSON (dirender server via `@js`): `{popId, itemId, itemName, popName, categoryName, tracking, unit, serialsUrl, rollsUrl, priceTiers: [{lotNo, qty, price, opnameUrl}], actions: {transferUrl, requestStockUrl, issueUrl, adjustUrl, thresholdUrl}}` — URL `null` bila syarat tidak terpenuhi.
- Aturan URL aksi (spec §6): Transfer hanya `pop.type==='pusat'` & `canTransfer`; Minta Stok dan Serah Teknisi hanya `cabang` & izin masing-masing; Opname & Ambang butuh `canAdjust`. `lot_no` ikut di semua URL hanya untuk QUANTITY satu harga; QUANTITY dua harga: Opname baris utama = `null` (diganti tautan "Opname per harga → buka rincian" yang membuka drawer), aksi lain tanpa `lot_no`; Opname per tingkat harga hanya di `priceTiers[].opnameUrl` dengan `lot_no`-nya; SERIALIZED/ROLL tanpa `lot_no`.

- [ ] **Step 1: Tulis tes gagal** (`StockBoardActionsTest`; periksa HTML/JSON dalam respons — URL dicari dengan `route(..., [...])` persis):
  - `url_aksi_dirender_server_dan_sesuai_tipe_gudang` (pusat: transfer ada, minta stok tidak; cabang sebaliknya + serah teknisi).
  - `quantity_satu_harga_membawa_lot_no_di_semua_url_aksi`.
  - `quantity_dua_harga_menyembunyikan_opname_di_baris_utama_dan_menyediakannya_per_harga`.
  - `serialized_dan_roll_tanpa_lot_no`.
  - `aksi_hilang_bila_permission_tidak_ada` (user hanya `warehouse.view`).
  - `drawer_memuat_url_serials_dan_rolls_dari_route_bukan_dirakit_klien`.
  - `endpoint_serials_dan_rolls_luar_scope_tetap_ditolak` (regresi: `assertPopInScope` → 403).
  - `PostTargetRenderedServerSideTest` jalankan sebagai regresi di Step 4.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implement** `_actions` (menu ⋮ memakai pola dropdown `window.calcDropdownPos` + `x-teleport` seperti `index.blade.php` lama; satu partial dipakai baris, drawer-footer, dan sel matriks Task 6) dan `_drawer` (panel kanan Alpine: header, strip ringkas, pencarian + filter kondisi SN, tabel SN/Roll/QUANTITY-tiers dari fetch ke `serialsUrl`/`rollsUrl` + `priceTiers`; Esc menutup; fokus kembali ke baris pemicu; `role="dialog"`, `aria-modal`). Klik baris membuka drawer; tombol aksi memakai `@click.stop`. Hapus penanda `{{-- aksi: Task 5 --}}`.
- [ ] **Step 4:** `npm run build`; jalankan `StockBoardActionsTest`, `StockBoardPageTest`, `tests/Feature/PostTargetRenderedServerSideTest.php` → PASS. Verifikasi manual di browser: klik baris SN → drawer berisi serial + harga + diterima; klik baris roll → kode roll + sisa meter; Esc menutup.
- [ ] **Step 5:** pint.

---

### Task 6: `matrix()` + tab Matriks

**Files:**
- Modify: `app/Services/WarehouseStockBoardService.php`, controller (`?view=matrix`), `index.blade.php` (toggle Tabel | Matriks di dalam mode papan)
- Create: `resources/views/warehouse/stock/board/_matrix.blade.php`
- Test: `tests/Feature/Warehouse/StockBoardMatrixTest.php`

**Interfaces:**
- Produces: `WarehouseStockBoardService::matrix(Collection $popIds, BoardFilters $f): array` → `['pops' => Collection<Pop>, 'categories' => Collection<array{category: ?ItemCategory, rows: Collection<array{item: Item, tracking: TrackingType, unit: string, cells: array<int, array{qty: float, is_low: bool}>}>}>]`. `cells` memuat kunci setiap pop dalam `pops` (qty 0.0 bila tidak ada stok). Sumber angka identik dengan `build()` (panggil bersama, jangan hitung ulang berbeda).
- View: kolom pertama + header sticky, area gudang scroll horizontal; sel 0 atau menipis diberi warna **dan** teks (⚠); klik sel dengan stok membuka drawer (event `open-stock-drawer` Task 5); sel 0 tidak membuka drawer.

- [ ] **Step 1: Tulis tes gagal:** `sel_matriks_sama_dengan_angka_tabel` (bandingkan `matrix()` vs `build()` untuk fixture 3 gudang × 3 tracking); `sel_nol_dan_menipis_ditandai_teks`; `kolom_pusat_dulu_lalu_cabang`; `filter_dan_pencarian_yang_sama_berlaku`; `matriks_satu_gudang_tetap_valid` (satu kolom); `matriks_luar_scope_tidak_memuat_kolom_gudang_lain`; `halaman_matriks_dirender_dengan_view_matrix`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implement** `matrix()` (pivot dari hasil `build()`), controller cabang `view=matrix`, partial `_matrix`, toggle.
- [ ] **Step 4:** `npm run build`; `StockBoardMatrixTest` + `StockBoardPageTest` → PASS. Cek manual lebar sempit (mobile) — tabel menggeser horizontal tanpa memecah layout.
- [ ] **Step 5:** pint.

---

### Task 7: Jadikan papan default, pindahkan tampilan lama ke `?view=rincian`, rapikan tes & dokumen

**Files:**
- Modify: `WarehouseStockController::index()` (default `viewMode` = `papan`; nilai `rincian` = Per Lot lama; `ringkasan` tetap), `index.blade.php` (toggle: Papan | Rincian | Ringkasan; teks "Per Lot" → "Rincian"), tes lama yang memakai default (lihat daftar di bawah), `docs/warehouse/user-flow.md`, `docs/TASKS.md`, `docs/plan/warehouse/rancangan-kelola-stok-per-gudang-dan-kategori.md` (tambah catatan "Digantikan oleh spec 2026-10-29").
- Test: `tests/Feature/Warehouse/StockBoardDefaultViewTest.php`

**Interfaces:**
- Consumes: semua task sebelumnya.
- Produces: `GET warehouse.stock.index` tanpa `view` → papan; `view=rincian` → perilaku Per Lot lama persis; `view=ringkasan` tidak berubah. Parameter lama (`search`, `category_id`, `item_id`, `low_stock_only`, `sort`, `dir`, `pop_id`) tetap bekerja di `rincian`.

- [ ] **Step 1: Tulis tes gagal** (`StockBoardDefaultViewTest`): `tanpa_view_merender_papan` (`viewData('viewMode') === 'papan'`); `view_rincian_merender_per_lot_lama` (`viewData('balances')` tersedia); `view_ringkasan_tetap`; `toggle_tiga_mode_menandai_mode_aktif`; `view_tidak_dikenal_jatuh_ke_papan`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implement** pembalikan default. Lalu perbaiki tes lama yang bergantung pada tampilan default Per Lot dengan menambahkan `'view' => 'rincian'` ke request `route('warehouse.stock.index', [...])` yang membaca `viewData('balances')`/HTML Per Lot. File terdampak (berdasarkan grep): `WarehouseStockPageTest`, `WarehouseStockSearchTest`, `WarehouseStockSortAndChipTest`, `WarehouseStockRollTest`, `WarehouseStockThresholdTest` (baris 91), `WarehouseBadgeTerminologyTest`, `WarehouseSwitchPopTest`, `WarehouseMaterialUsageReportTest` (baris 176), `WarehouseStockOpnameTest` (baris 120/128), `WarehouseStockPositionTest` (baris 160 `$default`). Jangan ubah `assertRedirect(route('warehouse.stock.index'))` (tetap valid). Jangan hapus tes mana pun; bila sebuah tes secara eksplisit menguji default Per Lot, ubah ke `view=rincian`, bukan dihapus.
- [ ] **Step 4: Jalankan** tiap file terdampak satu per satu + `StockBoard*Test` + `PostTargetRenderedServerSideTest` → semua PASS. Catat kegagalan apa pun dengan keluaran lengkap sebelum memperbaiki.
- [ ] **Step 5: Dokumen.** `docs/warehouse/user-flow.md`: ganti uraian Kelola Stok dengan papan (Gudang → Kategori → Barang, Matriks, drawer, `view=rincian`). `docs/TASKS.md`: catat pekerjaan ini sebagai task di luar ADHOC-202 dengan status selesai/riskonya (tampilan lama menunggu penghapusan setelah stabil — spec §11.7). Catatan di rancangan lama.
- [ ] **Step 6:** `vendor/bin/pint --dirty --format agent`; `npm run build`; uji manual desktop + mobile (papan, matriks, drawer, keadaan kosong). Penghapusan Per Lot/Ringkasan + modal SN/roll lama **tidak** dilakukan di sini (menunggu stabil).

---

## Self-Review

- **Spec coverage:** §2 keputusan → Task 4/7; §3 tata letak/kolom/matriks/drawer → Task 4/5/6; §4.1–4.5 → Task 1–7; §5 perhitungan → Task 2; §6 aksi & `lot_no` → Task 5; §7 pencarian + hasil kosong + chip → Task 3/4; §8 keamanan → Task 2/3/4/5; §9 kinerja (tanpa N+1) → Task 2 Step 3; §10 tes 1–8 → tersebar (regresi 8 di Task 5/7); §11 urutan → sama dengan urutan task (spec butuh "tampilan lama dipindah" yang di plan ditunda ke Task 7 agar tiap task hijau); §12–13 informatif.
- **Gap yang diputuskan plan:** `roll_count` memakai query tambahan (spec menyebut "N roll" tetapi tidak menyebut sumbernya); `search_truncated` ditandai untuk batas 50 pasangan.
- **Konsistensi tipe:** `BoardFilters` (Task 1) dipakai Task 2–6; shape row `build()` didefinisikan sekali di Task 2 dan dipakai Task 3–6; `open-stock-drawer` didefinisikan di Task 5 dan dipakai Task 6.
- **Risiko terbuka:** (1) ≥10 file tes lama bergantung pada default Per Lot (ditangani Task 7 Step 3); (2) beban render jika baris > ~2.000 (spec §9, tidak dibangun); (3) nama field `search_truncated` harus dipilih satu kali di Task 3 dan dipakai di Task 4 `_toolbar`.
