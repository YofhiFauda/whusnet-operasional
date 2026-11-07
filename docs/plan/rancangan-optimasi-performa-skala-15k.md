# Rancangan Optimasi Performa — Skala 15k–50k Pelanggan

> **Status 2026-11-05: belum selesai.** Tabel baseline berasal dari run lama; status di bawah dikoreksi dengan pengukuran ulang development sekitar 50 ribu pelanggan. Target semua halaman <1.500 ms, <128 MB, tanpa N+1, dan Lighthouse >90 belum terpenuhi/diverifikasi. Migration indeks ADHOC-266 belum diterapkan ke production.

Sudah di kode: indeks daftar/constraint transisi (ADHOC-266), beberapa refresh parsial (ADHOC-263), agregasi histori NOC (ADHOC-268), batch saldo halaman gudang dan agregasi kas worksheet (ADHOC-269), serta query tren/pelanggan kronis ramping dan cache NOC terikat scope POP (ADHOC-270). Masih perlu: sisa hidrasi analitik NOC/FOP, query invoice dan payment worksheet, optimasi laporan kolektor bulanan dengan tes angka keuangan, sisa refresh halaman penuh, dan pengukuran Lighthouse/p95 produksi.


Dasar: baseline `php artisan perf:pages --with-samples` (179 halaman, 15k pelanggan dummy,
`memory_limit` 512M). Hasil mentah: `storage/app/perf/baseline-15k.json`.

**Bukti NOC ADHOC-279:** 100 sampel cache dingin per dataset MySQL: p95 647 ms pada 15.007 pelanggan dan 918 ms pada 50.107 pelanggan. Namun 50k memiliki request maksimum 5.311 ms sekuensial dan 16.779 ms pada satu run lima pekerja paralel; target setiap request <1.500 ms belum konsisten. Ini pengukuran Laravel Kernel lokal, belum p95 HTTP/produksi. Metode dan artefak mentah ada di `analisa-performa-seeder-15k.md` bagian Bukti p95 Dashboard NOC.

**Bukti HTTP lokal ADHOC-280:** 100 request cache dingin memberi p95 269 ms pada 15k (`artisan serve`) dan 678 ms pada 50k (Nginx/PHP-FPM). Pada 50k, dua run lima pekerja ×20 dari cache kosong memberi p95 331/280 ms; seluruh 500 sampel utama HTTP 200 dan di bawah 1.500 ms. Stack 15k/50k berbeda, outlier CLI ADHOC-279 belum terdiagnosis, dan p95 produksi belum diukur. Metode serta artefak mentah ada di analisis bagian Pengukuran HTTP lokal.

**ADHOC-281 (cache stampede NOC):** Uji CLI lima pekerja ×20 pada 50k mereproduksi tiga request awal 13,9–14,7 detik saat cache kedaluwarsa; query tren yang sama dihitung serentak. Lock atomik per key user+scope POP diterapkan pada cache statistik, tren bulanan, dan telemetri, dengan TTL data tetap 60/300/60 detik serta fallback jika lock timeout. Dua run CLI dari tiga cache kosong: p95 407/390 ms, maksimum 1.341/838 ms; HTTP Nginx/PHP-FPM 100 request: p95 308 ms, maksimum 734 ms, semua 200. Ini bukti lokal, belum menggantikan p95 produksi atau jaminan semua request selalu <1.500 ms. Rincian ada di analisis bagian ADHOC-281.

**ADHOC-285 (replika image produksi lokal):** Image `Dockerfile.prod` tanpa bind mount, APP_ENV production, OPcache, cache bootstrap, FPM 20 worker, aplikasi 2 CPU/2 GiB, Nginx 0,5 CPU/256 MiB, dan dua schema dummy terisolasi dipakai untuk 900 request HTTP. P95 cache dingin 100 sampel: 609 ms pada 15k dan 858 ms pada 50k. Pada 50k, p95 lima pekerja ×20 adalah 756/508 ms (dua run) dan sepuluh pekerja ×20 adalah 773 ms. Semua status 200, tetapi maksimum 1.605 ms (15k normal) dan 1.695 ms (50k paralel pertama) melewati target setiap request <1.500 ms. Infrastruktur database/Redis, Traefik/TLS, dan jaringan produksi asli belum diuji; ini bukti software produksi lokal, bukan p95 produksi aktual. Detail dan JSON mentah ada di analisis ADHOC-285.

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
| 2 | `/fop-tasks` | 15 s, 6483 query | N+1 + `update fop_tasks set priority` di dalam request GET | Profil hidrasi dan query survei pada dataset kini; pertahankan semantik auto-sync. `date(work_date) = ?` perlu EXPLAIN terpisah | **Belum tuntas**: 3.064 ms, 51 query, 21.782 model pada pengukuran ulang. |
| 3 | `/` (dashboard) | 4,4 s, 202 MB, 61k model | Muat semua baris lalu hitung di PHP; `sum(payments)` 587 ms | `omsetStats` jadi satu query agregat, tren pembayaran `GROUP BY` di SQL, index `invoices` (migrasi `2026_10_28_120000`) | **Selesai**: 4,4 s/202 MB/61k model → 1,2 s/52 MB/3k model |
| 4 | `/noc/dashboard` | 4 s, 40k model | `buildPerPopAnalytics`, `loadComplaintWindow` | SLA compliance & pelanggan per POP per bulan jadi agregat SQL; eager load kolom minimal; tiket periode dimuat sekali | **Sebagian**: tren/pelanggan kronis dipadatkan dan cache scope POP aman; lanjutan ADHOC-278 mengagregasi issue/daerah/matriks, memadatkan durasi dan workload; dari baseline giliran ini 6.450 ms/87 query/12.515 model menjadi 1.268 ms/78 query/2.597 model pada satu run development (52,5 MB). P95 request internal development di 15k/50k diukur pada ADHOC-279 (lihat analisis); konsistensi semua request, p95 produksi, dan scan tiket belum tuntas. Metrik MTTR remote/field perlu koreksi terpisah. |
| 5 | `/invoices` | 1,6 s | `count(*)` 909 ms | Indeks `issue_date` untuk urutan global daftar | **Indeks selesai di development** (ADHOC-266); waktu halaman belum diukur ulang |
| 6 | `/warehouse/reports`, `/warehouse/history` | 313 query / 17k model | N+1 + muat semua | Riwayat: dua fase (kolom ramping untuk grup/urutan, muat penuh hanya 30 kartu per halaman). Laporan: index gabungan ledger `(item_id, serial/roll/to_pop/from_pop, created_at)`; hitungan batch `WarehouseStockAsOfService` (313 query) belum | **Sebagian**: GET laporan 948 ms, 47 query, 9.814 model setelah batch saldo; export Excel masih per item dan bug OpenSpout `Border::TOP` masih terbuka. |
| 7 | `/reports/collector-payments`, `/customer-acquisitions` | 1496 / 798 query | N+1 | Eager load | **Selesai**: collector-payments 1496 → 20 query (preload nomor cicilan `Payment::preloadInstallmentContext`), customer-acquisitions 798 → 28 (cache kategori yang `null`) |
| 8 | `/collector-worksheet`, `/fop`, `/network/nodes` | 6k–22k model | Muat semua | `/collector-worksheet`: tunggakan per kolektor dijumlah di SQL, kolom ramping untuk kas tunai. `/fop`, `/network/nodes` sudah < 0,6 s, tidak diubah | **Sebagian**: worksheet 2.060 ms/74 model, summary 998 ms/13 model setelah agregasi kas; query invoice/payment masih berat. |

## Kriteria selesai

**Status saat ini: belum terpenuhi.** Tidak ada halaman `OOM`/`TIMEOUT`; semua halaman < 1500 ms dan < 128 MB pada 15k pelanggan;
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
