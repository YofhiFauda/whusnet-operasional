# Papan Kelola Stok: Gudang → Kategori → Barang

Tanggal: 2026-10-29
Status: Spec untuk ditinjau; belum diimplementasikan.
Menggantikan: `docs/plan/warehouse/rancangan-kelola-stok-per-gudang-dan-kategori.md` (tanggal 2026-10-28). Dokumen lama masih menyebut "lot" dan tiga tab; keduanya tidak berlaku lagi.

## 1. Tujuan

Petugas Gudang harus bisa:

1. **Mencari** barang X dan langsung melihat di gudang mana saja barang itu ada (kebutuhan utama).
2. **Memantau** isi gudang per kategori, termasuk Gudang Pusat ketika gudang sudah banyak (kebutuhan kedua).

Logika dan data Gudang sudah benar; yang diperbaiki hanya **penyajian** di `warehouse.stock.index`. Analogi pengguna: Gudang = ruangan, **rak = kategori barang** (`item_categories`). Rak bukan posisi fisik. Tidak ada tabel rak, kode rak, kapasitas rak, atau perpindahan antar-rak. Tidak ada migrasi.

### Di luar cakupan
- Custody teknisi tidak ditampilkan di layar ini (permintaan eksplisit pengguna).
- Kolom "Dalam perjalanan" ditunda ke versi berikutnya (YAGNI); mudah ditambah belakangan.
- Tidak ada alur mutasi baru. Semua aksi memakai aksi yang sudah ada.
- "Lot" bukan konsep pengguna. Kolom DB `lot_no` tetap ada, tetapi hanya dipakai QUANTITY untuk membedakan harga lama/baru (maksimal 2 per barang, dibuat otomatis; ADHOC-75). Istilah lot tidak muncul di UI baru maupun di pencarian.

## 2. Keputusan desain

| Keputusan | Pilihan |
|---|---|
| Layar utama | Satu tabel berkelompok Gudang → Kategori → Barang |
| Pencarian | Memfilter tabel yang sama; gudang/kategori yang tidak cocok disembunyikan, yang cocok terbuka otomatis. Tidak ada tab "Cari" terpisah |
| Monitoring banyak gudang | Tab **Matriks** (baris barang per kategori × kolom gudang) |
| Rincian per barang | **Drawer** (menggantikan modal Serial Number dan modal Roll) |
| Aksi | Menu ⋮ yang sudah ada, tanpa aksi baru |
| Tampilan awal | Pengguna multi-gudang: tabel semua gudang dalam scope, band gudang terlipat bila gudang > 5. Pengguna satu gudang: gudangnya terbuka |
| Tampilan lama | Per Lot dan Ringkasan dipindah ke `?view=rincian` sementara, dihapus setelah papan terbukti |

## 3. Tata letak

```text
[Cari nama/kode/SN/kode roll] [Kategori ▾] [Tracking ▾] [Menipis]   [Bentang semua] [Ciutkan]   (Tabel | Matriks)

▼ GUDANG PUSAT  [PUSAT]   12 SKU · 2 menipis                 ← band gudang, gelap, bisa dilipat
   ▼ Modem & ONT   3 SKU                                      ← band kategori, bisa dilipat
     Kode        Barang          Tracking  Tersedia   Kesehatan   Kondisi            Opname        Aksi
     TP-LINK-2AE Modem TP-LINK   SN        152 unit   ● Aman      148 Baru · 4 Bekas 3 hari lalu   ⋮
   ▶ Splitter & ODP  4 SKU
▶ CABANG JETIS  [CABANG]   9 SKU · 1 menipis
```

Diambil dari `docs/plan/warehouse/design-preview.html`: band gelap per gudang, band kategori yang dapat dilipat, tombol bentang/ciutkan, pill filter, dan drawer dari klik baris. Dibuang dari preview: kolom Custody Teknisi, kode `TRAY-…`, kapasitas rak, dan KPI yang datanya tidak ada di sistem.

### Kolom tabel

| Kolom | Isi |
|---|---|
| Kode + Barang | kode, nama, spesifikasi singkat |
| Tracking | SN / Roll / Quantity |
| **Tersedia** | angka tebal + satuan (unit / meter / pcs); ROLL menambah baris kecil "N roll" |
| Kesehatan | Aman / Menipis + `Min` (ambang yang sudah ada) |
| Kondisi | SN: `148 Baru · 4 Bekas`. QUANTITY: bila 2 harga, "2 harga: lama N · baru M". ROLL: kosong |
| Opname terakhir | "3 hari lalu" atau "Belum pernah" |
| Aksi | menu ⋮ (bagian 6) |

Status selalu berupa teks, tidak hanya warna. Band gudang yang terlipat tetap menampilkan jumlah SKU dan jumlah menipis, sehingga Pusat dapat dipantau tanpa membuka semuanya.

### Tab Matriks

Baris = barang, dikelompokkan per kategori (dapat dilipat). Kolom = gudang dalam scope (Pusat dahulu, lalu cabang; kolom pertama dan header ditempel, area gudang dapat digeser horizontal). Sel = jumlah tersedia dengan satuannya. Sel `0` dan sel di bawah ambang diberi warna **dan** teks (⚠). Klik sel membuka drawer barang itu di gudang tersebut. Filter dan pencarian yang sama berlaku. Satu baris selalu satu satuan, sehingga tidak ada penjumlahan campuran.

### Drawer

Dibuka dari klik baris tabel atau klik sel matriks. Isi:

- Header: gudang, kategori, nama barang.
- Strip ringkas: Tersedia, Kondisi, Nilai.
- Pencarian dan filter kondisi (SN).
- Tabel rincian. SN: serial, kondisi, harga beli, diterima. ROLL: kode roll, sisa meter, vendor, diterima, nilai. QUANTITY: satu baris per tingkat harga (harga, jumlah) dengan aksi Opname masing-masing (bagian 6).
- Footer: menu aksi yang sama dengan baris.

Data diambil dari `warehouse.stock.serials` dan `warehouse.stock.rolls` yang sudah ada (keduanya sudah mengirim harga, tanggal diterima, kondisi, sisa meter). Untuk QUANTITY, rincian harga ikut dalam data baris (`price_tiers`), tidak perlu fetch. Esc menutup drawer; fokus dikembalikan ke baris pemicu; seluruh baris dapat dijangkau keyboard.

## 4. Arsitektur

```text
WarehouseStockController::index()      tipis: scope → BoardFilters → service → view
        │
        ├─ WarehouseStockBoardService   (BARU, read-only)  build() / matrix()
        │       └─ WarehouseStockPositionService::availableByPopAndItem()  (jadi public)
        │       └─ WarehouseStockPositionService::conditionByPopAndItem()  (BARU)
        └─ BoardFilters                 (BARU, value object)
```

### 4.1 `BoardFilters`
Value object: `popId`, `categoryId`, `tracking` (whitelist `TrackingType`), `search` (lewat `LikeSearch::sanitize`), `lowOnly`. Satu tempat menormalkan input supaya controller tipis dan service tidak membaca `Request`.

### 4.2 `WarehouseStockBoardService`

```php
build(Collection $popIds, BoardFilters $f): Collection  // Gudang → Kategori → Baris
matrix(Collection $popIds, BoardFilters $f): array      // kategori × barang × gudang
```

Bentuk `build()`:

```text
[ pop, sku_count, low_count,
  categories: [ category, sku_count,
    rows: [ item, tracking, qty, unit, roll_count?, min, is_low,
            lot_no?  (QUANTITY satu harga), price_tiers? (QUANTITY dua harga: lot_no, qty, price),
            condition_summary? (SN), last_opname_at, match_label? ] ] ]
```

Urutan stabil: gudang (Pusat dahulu, lalu cabang, nama), kategori (`ordered()`), barang (nama natural). Kategori tanpa barang yang lolos filter tidak ditampilkan. Gudang dalam scope tanpa stok tetap tampil sebagai band kosong ("Belum ada stok tersedia") kecuali filter/pencarian sedang aktif.

### 4.3 Perubahan di `WarehouseStockPositionService`
- `availableByPopAndItem()` dari `private` menjadi `public`, perilaku tidak berubah (sudah menjadi sumber angka Ringkasan, sehingga angka papan dan Ringkasan lama tetap sama).
- Tambah `conditionByPopAndItem(Collection $popIds)`: padanan `conditionByItem()` dengan dimensi gudang, populasi sama (SN `AVAILABLE`).
- `held`, `in_transit`, dan `problem` tidak dipanggil papan.

### 4.4 Controller
`index()` membuat `BoardFilters`, memanggil service, dan merender view. Tampilan lama dipindah ke metode privat/`?view=rincian` tanpa ubah perilaku. Pemilihan gudang memakai `resolveSelectedPopId()` yang sudah ada. Pengguna dengan satu gudang (`lockedSinglePop`) langsung melihat gudangnya terbuka.

### 4.5 View
`resources/views/warehouse/stock/index.blade.php` (1100 baris) dipecah:
`stock/board/_toolbar`, `_table`, `_matrix`, `_drawer`, `_actions`. Alpine dipakai untuk lipat/buka dan drawer (sudah dibundel Vite; wajib `npm run build`). Target aksi POST/GET dirender server lewat `route()`; klien tidak merakit path (aturan repo, `PostTargetRenderedServerSideTest`).

## 5. Perhitungan "Tersedia"

Satu aturan, sama dengan layar lama:

| Tracking | Tersedia = | Baris kecil |
|---|---|---|
| QUANTITY | jumlah `inventory_balances.qty` (semua tingkat harga) | bila 2 harga: "2 harga: lama N · baru M" |
| SERIALIZED | jumlah SN berstatus `AVAILABLE` | `148 Baru · 4 Bekas` |
| ROLL | jumlah `length_remaining` roll `AVAILABLE`/`RECEIVED`, dalam **meter** | "12 roll" |

Tidak termasuk "Tersedia": barang di teknisi, transit, retur menunggu verifikasi, karantina/rusak, terpasang di pelanggan, aset di node jaringan. Band kategori hanya menampilkan **jumlah SKU**, tidak pernah jumlah fisik. Kondisi SN memakai label yang ada: Baru, Bekas Belum Dicek, Bekas Sudah Dicek, Bekas Rusak (kondisi tidak otomatis berarti siap issue).

`is_low` memakai `minimum_stock` dari `inventory_balances` (`lot_no=''`), sama dengan `InventoryBalance::isLowStock()` sekarang.

## 6. Aksi dan `lot_no`

Aksi tetap lima, dengan syarat tampil yang sama: Kirim Transfer (gudang `pusat`, `canTransfer`), Minta Stok (gudang `cabang`, `canRequestStock`), Serah Teknisi (gudang `cabang`, `canIssue`), Opname/Sesuaikan (`canAdjust`), Atur Ambang (`canAdjust`).

Hasil audit `lot_no` di form tujuan:

| Form | Pemakaian `lot_no` | Konsekuensi |
|---|---|---|
| Transfer, Issue, Minta Stok | prefill saja; pilihan lot diambil form dari endpoint `available-stock` | aman tanpa `lot_no` |
| Atur Ambang | ambang disimpan di `lot_no=''` | aman tanpa `lot_no` |
| **Opname / Sesuaikan** | mencari saldo `where lot_no = query('lot_no','')` | **wajib membawa `lot_no` yang tepat** untuk QUANTITY |

Aturan baris:
- QUANTITY satu harga: baris membawa `lot_no` tingkat itu di semua URL aksi.
- QUANTITY dua harga: baris utama menyembunyikan Opname dan menampilkan "Opname per harga → buka rincian"; di drawer, tiap tingkat harga punya aksi Opname sendiri yang membawa `lot_no`-nya. Aksi lain tetap di baris utama tanpa `lot_no`.
- SERIALIZED dan ROLL: tanpa `lot_no`, seperti sekarang.

## 7. Pencarian

- Nama/kode barang memfilter baris.
- SN dan kode roll dicari dengan dua query kecil yang dibatasi `whereIn(current_pop_id, $popIds)`; hasilnya pasangan (gudang, barang) dan nilai yang cocok. Pasangan itu masuk hasil tabel dengan `match_label` "Cocok: SN …" / "Cocok: roll …", supaya angka tersaring tidak disalahpahami sebagai total stok.
- Hasil pencarian SN dibatasi (mis. 50 pasangan) sebagai pengaman, dengan petunjuk bila terpotong.
- Satu gudang dipilih: pencarian dibatasi gudang itu; tersedia aksi "Cari di semua gudang terjangkau" yang hanya menghapus filter gudang (scope POP server tetap berlaku).
- Hasil kosong dibedakan: (a) tidak ada kecocokan, (b) gudang belum punya stok tersedia, (c) pengguna tidak punya gudang yang boleh diakses.
- Chip filter aktif menampilkan gudang/kategori/tracking/menipis dengan "Hapus semua". Mengubah satu filter mempertahankan yang lain.

## 8. Keamanan dan scope POP

- Service hanya menerima `popIds` dari `EffectiveAccessService` (`hasAllPopAccess()` / `getAllowedPopIds()`); tidak ada query pelanggan/stok tanpa scope. Filter gudang dari URL hanya **menyempitkan** scope, tidak pernah melebarkan (perilaku yang sama dengan `WarehouseStockPositionService::summarize()` hari ini).
- Query SN dan roll memakai `whereIn` pada `popIds`: SN/roll milik gudang di luar scope tidak muncul dan keberadaannya tidak bocor lewat pencarian.
- Drawer memakai endpoint yang sudah menjalankan `assertPopInScope()`; gudang di luar scope ditolak keras.
- Tombol aksi hanya dirender bila permission aksinya ada; spec ini tidak menambah permission atau alur mutasi.
- Pengguna tanpa gudang yang boleh diakses melihat keadaan kosong (c), tanpa nama atau jumlah apa pun.

## 9. Kinerja dan batas

Semua gudang dan baris dalam scope dirender di server lalu dilipat dengan Alpine. Untuk puluhan SKU per gudang ini aman. Agregasi memakai query per-tabel (balance, serial, roll) yang dikelompokkan di DB, bukan N+1.

**Batas yang diketahui:** bila jumlah gudang × SKU membesar sampai tabel terasa berat (acuan kasar: > ~2.000 baris), cadangannya adalah memuat isi band gudang saat dibuka lewat partial server-side. Tidak dibangun sekarang (YAGNI); diukur setelah rilis.

## 10. Tes

Fitur baru wajib bertes. Jalankan hanya file terdampak, bukan full suite.

1. **Angka Tersedia** benar untuk QUANTITY, SERIALIZED, ROLL; custody, transit, dan rusak tidak ikut terhitung; QUANTITY dua harga dijumlah.
2. **Pengelompokan** Gudang → Kategori benar; kategori master baru muncul otomatis; kategori kosong tidak tampil.
3. **Scope POP**: `pop_id` luar scope di URL tidak melebarkan hasil; SN/kode roll gudang luar scope tidak ditemukan dan tidak berlabel; drawer luar scope ditolak.
4. **Pencarian** SN dan kode roll memberi label `Cocok:` dan memunculkan gudang yang benar; pencarian nama/kode memfilter baris.
5. **Hasil kosong** tiga jenis dibedakan.
6. **Matriks**: sel sama dengan angka tabel; sel menipis ditandai teks.
7. **Aksi**: URL aksi dirender server; QUANTITY satu harga membawa `lot_no`; QUANTITY dua harga menyembunyikan Opname di baris utama dan menyediakannya per harga di drawer; syarat `pusat`/`cabang` dan permission tetap.
8. **Regresi**: `?view=rincian` tetap bekerja; `PostTargetRenderedServerSideTest` lulus; angka papan sama dengan Ringkasan lama untuk data yang sama.

Nama tes mengikuti gejala (mis. `WarehouseStockBoardScopeLeakTest`, `WarehouseStockBoardQuantityTwoPriceActionTest`).

## 11. Urutan pengerjaan

1. Service: `availableByPopAndItem()` public, `conditionByPopAndItem()`, `BoardFilters`, `WarehouseStockBoardService::build()` + tes angka/pengelompokan/scope.
2. Pencarian SN/roll + label `Cocok:` + tes.
3. View tabel berkelompok + toolbar + chip + keadaan kosong; controller baru; `?view=rincian` untuk tampilan lama.
4. Drawer + aksi (termasuk aturan `lot_no`) + tes aksi.
5. `matrix()` + tab Matriks + tes.
6. `npm run build`, `vendor/bin/pint`, uji tampilan desktop dan mobile.
7. Setelah dipakai dan stabil: hapus tampilan Per Lot/Ringkasan lama dan modal SN/roll yang tidak terpakai; perbarui `docs/warehouse/user-flow.md` dan `docs/TASKS.md`.

## 12. Scope proyek

Task aktif di `docs/TASKS.md` saat spec ditulis adalah ADHOC-202 (cutover hierarki infrastruktur); pekerjaan ini di luar sprint itu dan dikerjakan atas permintaan langsung pengguna. Kerja di branch `dev`.

## 13. Rujukan

`docs/plan/warehouse/design-preview.html`, `docs/warehouse/README.md`, `docs/warehouse/business-logic.md`, `docs/warehouse/user-flow.md`, `docs/plan/warehouse/analisa-2-slot-harga-quantity.md`, `docs/plan/warehouse/analisa-ui-ux-warehouse.md`, `app/Http/Controllers/Warehouse/WarehouseStockController.php`, `app/Services/WarehouseStockPositionService.php`, `resources/views/warehouse/stock/index.blade.php`.
