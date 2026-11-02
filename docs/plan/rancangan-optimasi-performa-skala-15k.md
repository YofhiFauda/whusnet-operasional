# Rancangan Optimasi Performa — Skala 15k–50k Pelanggan

Dasar: baseline `php artisan perf:pages --with-samples` (179 halaman, 15k pelanggan dummy,
`memory_limit` 512M). Hasil mentah: `storage/app/perf/baseline-15k.json`.

## Alat ukur

`php artisan perf:pages [--only=/uri] [--with-samples] [--slow=1500]` — satu proses PHP per halaman.
Tanda: `LAMBAT` (≥1500 ms), `MEMORI` (≥128 MB), `N+1?` (≥100 query), `MUAT-SEMUA?` (≥5000 model),
`OOM`/`TIMEOUT`. Blok tambahan: SQL identik berulang ≥20x (N+1) dan hasil EXPLAIN SELECT ≥50 ms
(full scan / filesort / temporary table → kandidat index).

## Prinsip perbaikan

1. **Agregasi di SQL**, bukan `->get()` lalu loop PHP.
2. **Eager load** untuk N+1; jangan hidrasi relasi yang tidak dipakai tampilan.
3. **Muat lazy** data yang hanya dibutuhkan setelah klik (URL aksi tetap dirender server, bukan dirakit klien).
4. **Paginasi / chunk** untuk daftar; batasi rentang waktu untuk riwayat.
5. **Index** hanya berdasarkan EXPLAIN nyata, migrasi terpisah.
6. **Cache pendek** (≤60 dtk) untuk angka dashboard — kunci wajib memuat POP scope user.
7. Tiap perbaikan: tes regresi (jumlah query / bentuk payload) + ukur ulang dengan `perf:pages`.

## Daftar halaman (urut prioritas)

| # | Halaman | Baseline | Penyebab | Rencana | Status |
|---|---|---|---|---|---|
| 1 | `/network/peta` | OOM | Semua port + pelanggan + 15k kartu ONT dimuat sekaligus | Port lazy per node (`network.map.ports`), kartu ONT opt-in `?ont=1` | **Sebagian**: server 4,1 s → 2,6 s, 237 MB. Tab Pohon & Inventaris hanya dirender saat dibuka (`?tab=`); kanvas: layout O(N²) → O(N), PON terlipat default bila > 400 node. ODP dimuat per PON (`network.map.children`, cache 5 mnt per user/filter): HTML tab Topologi 1,96 MB mentah / 198 KB gzip (dulu ±30 MB: blok SSR "fallback PHPUnit" merender semua node+aset ke HTML; data kini di `<script type=application/json>`; `index` dibekukan agar tidak reaktif), model tampilan di-cache 5 mnt per user+filter (`?fresh=1` membuang): TTFB 2,7 s → 53 ms saat hit (tab Inventaris 22 MB). Sisa server: 25k model (serial, transaksi, node dihidrasi 2x oleh hierarchy service) |
| 2 | `/fop-tasks` | 15 s, 6483 query | N+1 + `update fop_tasks set priority` di dalam request GET | Tidak perlu diubah: 15 s hanya penulisan ulang prioritas satu kali (data dummy acak); kondisi stabil 773 ms / 67 query. Catatan: `date(work_date) = ?` tidak sargable | **Selesai (tanpa perubahan kode)** |
| 3 | `/` (dashboard) | 4,4 s, 202 MB, 61k model | Muat semua baris lalu hitung di PHP; `sum(payments)` 587 ms | `omsetStats` jadi satu query agregat, tren pembayaran `GROUP BY` di SQL, index `invoices` (migrasi `2026_10_28_120000`) | **Selesai**: 4,4 s/202 MB/61k model → 1,2 s/52 MB/3k model |
| 4 | `/noc/dashboard` | 4 s, 40k model | `buildPerPopAnalytics`, `loadComplaintWindow` | SLA compliance & pelanggan per POP per bulan jadi agregat SQL; eager load kolom minimal; tiket periode dimuat sekali | **Selesai**: dingin 4,0 s → 2,2 s, hangat (cache) 0,25 s; model 40k → 19k |
| 5 | `/invoices` | 1,6 s | `count(*)` 909 ms | Index `(issue_date, id)` untuk `order by` daftar | Belum (sudah 0,5 s; filesort 233 ms) |
| 6 | `/warehouse/reports`, `/warehouse/history` | 313 query / 17k model | N+1 + muat semua | Riwayat: dua fase (kolom ramping untuk grup/urutan, muat penuh hanya 30 kartu per halaman). Laporan: index gabungan ledger `(item_id, serial/roll/to_pop/from_pop, created_at)`; hitungan batch `WarehouseStockAsOfService` (313 query) belum | **Riwayat selesai**: 1,5 s/140 MB/17k model → 0,76 s/80 MB/9,5k. **Laporan sebagian** (index saja; waktu tak bisa dibandingkan karena laptop sedang sibuk) |
| 7 | `/reports/collector-payments`, `/customer-acquisitions` | 1496 / 798 query | N+1 | Eager load | **Selesai**: collector-payments 1496 → 20 query (preload nomor cicilan `Payment::preloadInstallmentContext`), customer-acquisitions 798 → 28 (cache kategori yang `null`) |
| 8 | `/collector-worksheet`, `/fop`, `/network/nodes` | 6k–22k model | Muat semua | `/collector-worksheet`: tunggakan per kolektor dijumlah di SQL, kolom ramping untuk kas tunai. `/fop`, `/network/nodes` sudah < 0,6 s, tidak diubah | **Selesai**: `/collector-worksheet` 2,8 s/112 MB → 0,77 s/66 MB |

## Kriteria selesai

Tidak ada halaman `OOM`/`TIMEOUT`; semua halaman < 1500 ms dan < 128 MB pada 15k pelanggan;
tidak ada halaman ≥100 query tanpa alasan tertulis.

## Peta Infrastruktur mode ONT (`/network/peta?ont=1`)

Sumber: `analisis_performa_peta_infrastruktur_isp.md` (snapshot 92 MB: 16.244 kartu, 277 ribu elemen DOM, tab ±4 GB).
Bukan `<canvas>` — SVG + Alpine, semua kartu dirender. Canvas ditunda; ukur dulu setelah langkah di bawah.

| # | Temuan analisis | Status |
|---|---|---|
| 1 | Render tanpa culling | **Selesai**: `cullVisible()` merender hanya kartu di viewport + 1 layar margin; garis (`edgePaths`) ikut dipangkas |
| 2 | Proxy reaktif pada data besar | **Sebagian**: `layout` dibekukan, `computeLayout` membaca `Alpine.raw(nodes)`. `nodes` sendiri masih reaktif (proxy hanya untuk node yang diakses) |
| 3 | Payload ONT gemuk (JSON 16,9 MB) | **Selesai**: kartu ONT keluar dari payload awal. Induknya (ODP) membawa `lazy_url`, Slot membawa `ont_batch_url`; Buka Semua / mode ONT memuat per Slot (puluhan permintaan, 3 paralel) lewat `network.map.children-batch`. Indeks pencarian tetap lengkap. Cache 5 mnt per user+filter (`peta:ont:*`, array biasa). Server `?ont=1&expand=all`: 237 MB, 25k model (sebelum 263 MB) |
| 4 | Dunia setinggi 1,2 juta px (satu baris per ONT) | **Selesai**: ONT satu induk disusun dalam blok 6 kolom (`computeLayout`), tinggi ±3x lebih pendek (ODP dengan ≤6 ONT = 1 baris) |
| 5 | `edgePaths()` via `x-html` dibangun ulang tiap pilihan | **Selesai**: garis dasar hanya bergantung pada kartu terlihat; jalur aktif di lapisan sendiri (`activePathSvg`, beberapa garis) |
| 6 | Ikhtisar semantik (`zoomFit` tak berguna di skala ini) | **Selesai**: di bawah zoom 0,3 kartu ONT (dan garisnya) disembunyikan, tampil sampai ODP + petunjuk; zoom minimum 0,25 → 0,1; `centerOn(id)` untuk lompat ke hasil cari |
| 7 | Renderer `<canvas>` | **Ditunda**: ukur memori tab (Chrome Task Manager) dulu setelah #1–#3 |

Sisa server mode ONT: `inventory_serials` 5 s dan `inventory_transactions` 0,4 s (hierarchy service) — dingin ±13 s, hasil di-cache untuk permintaan lazy berikutnya.

**Pencarian pelanggan:** indeks halaman tidak memuat SN (dan tanpa `?ont=1` tidak memuat pelanggan). Kotak cari memanggil `network.map.search` (nama / CID / SN, maks. 20 hasil, scope POP + izin `customers.detail.devices.view_sensitive` untuk SN). Memilih hasil TIDAK memuat ulang halaman: `network.map.ont` mengembalikan satu kartu ONT + induknya, klien memuat ODP induk bila perlu (`ensureLoaded`), membuka lipatan, memilih kartu, dan menggeser kanvas ke sana. (Versi pertama membuka `?ont=1&focus=` — membangun ulang seluruh ONT, lebih berat; dibuang.)
