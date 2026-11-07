# Analisa Performa — Setelah Inject Seeder 15k Pelanggan

> **Status 2026-11-05:** Temuan di bawah adalah snapshot historis. Indeks ADHOC-266 sudah diterapkan di database development, belum production. Endpoint parsial ADHOC-263 baru mencakup sebagian panel. ADHOC-269 sudah membatch saldo halaman gudang dan mengagregasi kas worksheet. ADHOC-270 memadatkan analitik NOC dan mengikat cache pada scope POP. Optimasi leaderboard ADHOC-268 mengagregasi histori eskalasi/pengembalian di SQL, tetapi bottleneck halaman penuh, target 15k–50k, dan Lighthouse >90 belum selesai/diverifikasi.

## Pengukuran ulang development

Data saat ini sekitar 50 ribu pelanggan. Angka dari `perf:pages` di bawah bukan p95 atau skor browser; cache dan beban mesin dapat memengaruhi perbandingan.

| Route | ms | Query | Model | Sisa masalah |
| --- | ---: | ---: | ---: | --- |
| `/noc/dashboard` | 1.268 | 78 | 2.597 | ADHOC-278: agregasi issue/daerah/matriks SQL, durasi dan workload ringkas. Baseline giliran ini 6.450 ms/87 query/12.515 model; hasil satu run development; bukti p95 lokal ada di ADHOC-279 di bawah. |
| `/warehouse/reports` | 948 | 47 | 9.814 | Setelah batch saldo QUANTITY/SERIALIZED/ROLL; tidak ada pola 20+ query identik. Sebelumnya 3.229 ms/221 query. |
| `/fop-tasks` | 3.064 | 51 | 21.782 | Hidrasi besar dan query survei. |
| `/collector-worksheet` | 2.060 | 32 | 74 | Setelah agregasi kas admin/teknisi; sebelumnya 2.896 ms/50.108 model. Endpoint summary 998 ms/13 model; query agregat pembayaran dan invoice masih mahal. |
| `/reports/collector-monthly` | 6.451 | 24 | — | JOIN/agregat historis masih berat. |

### Bukti p95 Dashboard NOC — ADHOC-279 (2026-11-05)

Metode: `php artisan perf:pages --uri=/noc/dashboard --user=superadmin@whusnet.com`, satu proses PHP per sampel yang mengukur request internal Laravel Kernel (belum termasuk jaringan dan render browser). P95 memakai nearest-rank: urutkan waktu lalu ambil posisi `ceil(0,95 × n)`. Semua sampel berstatus 200. Dataset 15k adalah schema MySQL terisolasi dari `DummyScaleSeeder` (15.007 pelanggan, 3.150 tiket); dataset development 50k berisi 50.107 pelanggan dan 10.500 tiket. Seeder dan distribusi POP kedua dataset berbeda, sehingga angka bukan perbandingan skala yang sepenuhnya terkendali.

| Dataset / kondisi | Sampel | p50 | p95 | Maksimum | Request ≥1.500 ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| 15k, cache NOC dihapus sebelum tiap request | 100 | 364 ms | 647 ms | 1.181 ms | 0 |
| 15k, cache berjalan normal | 30 | 114 ms | 162 ms | 169 ms | 0 |
| 15k, 5 pekerja ×20 dari cache kosong | 100 | 185 ms | 304 ms | 844 ms | 0 |
| 50k, cache NOC dihapus sebelum tiap request | 100 | 542 ms | 918 ms | 5.311 ms | 2 |
| 50k, cache berjalan normal | 30 | 266 ms | 576 ms | 1.808 ms | 1 |
| 50k, 5 pekerja ×20, run pertama | 100 | 322 ms | 747 ms | 16.779 ms | 5 |
| 50k, 5 pekerja ×20 dari cache kosong, ulang | 100 | 251 ms | 432 ms | 1.935 ms | 2 |

Tiga key yang dihapus secara spesifik untuk sampel dingin adalah statistik, telemetri, dan tren bulanan milik akun benchmark; cache global tidak dibersihkan. Pengukuran cache normal mengikuti TTL aplikasi. Pada 50k, outlier pertama uji paralel memiliki 8,5–14,7 detik waktu SQL dan muncul pada query berbeda; pengulangan jauh lebih cepat. Penyebab pasti lonjakan belum ditetapkan. Request pertama setelah menyiapkan dataset, di luar 100 sampel terukur, adalah 1.742 ms (15k) dan 5.460 ms (50k).

**Kesimpulan terbatas:** p95 request internal pada dua titik skala tersebut berada di bawah target 1.500 ms dalam rangkaian lokal ini. Klaim "setiap request <1.500 ms" tidak berlaku pada 50k; p95 produksi, latensi HTTP/browser, dan beban serentak yang lebih tinggi dari lima pekerja belum dibuktikan. Artefak mentah: `storage/app/perf/noc-15k-cold-100.json`, `noc-15k-normal-cache-30.json`, `noc-15k-concurrent-cold-start-5x20.json`, `noc-50k-cold-100.json`, `noc-50k-warm-30.json`, `noc-50k-concurrent-5x20.json`, dan `noc-50k-concurrent-cold-start-5x20.json`. Schema benchmark 15k adalah `whusnet_perf_noc_15k_20261105` dan tidak mengganti database aplikasi utama.

### Pengukuran HTTP lokal Dashboard NOC — ADHOC-280 (2026-11-06)

Request GET terautentikasi diukur dengan `curl` sampai respons selesai, memakai Owner all-POP yang sama dan rumus p95 nearest-rank di atas. Dataset 50k lewat Nginx/PHP-FPM lokal pada port aplikasi; dataset 15k terisolasi lewat `php artisan serve` sementara di container aplikasi dengan `DB_DATABASE=whusnet_perf_noc_15k_20261105`. Keduanya memakai MySQL lokal, tetapi stack server dan distribusi data berbeda. Angka ini mencakup HTTP loopback dan transfer respons, belum mencakup render browser atau jaringan pengguna.

| Dataset / kondisi HTTP | Sampel | p50 | p95 | Maksimum | Request ≥1.500 ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| 15k, `artisan serve`, tiga cache NOC dihapus sebelum tiap request | 100 | 233 ms | 269 ms | 314 ms | 0 |
| 50k, Nginx/PHP-FPM, tiga cache NOC dihapus sebelum tiap request | 100 | 538 ms | 678 ms | 1.145 ms | 0 |
| 50k, Nginx/PHP-FPM, cache berjalan normal | 100 | 124 ms | 154 ms | 322 ms | 0 |
| 50k, Nginx/PHP-FPM, 5 pekerja ×20, cache dikosongkan di awal, run 1 | 100 | 207 ms | 331 ms | 651 ms | 0 |
| 50k, Nginx/PHP-FPM, 5 pekerja ×20, cache dikosongkan di awal, run 2 | 100 | 197 ms | 280 ms | 698 ms | 0 |

Semua 500 sampel di tabel berstatus HTTP 200. Eviksi tiga key berhasil pada 100/100 sampel 15k dan 99/100 sampel 50k; pada satu sampel 50k dua key sudah kedaluwarsa sebelum eviksi. Kedua run paralel dimulai setelah tiga key terisi dan eviksi `1,1,1`. Satu run paralel tambahan setelah jeda memiliki eviksi awal `0,0,1`, p95 382 ms dan maksimum 1.035 ms (100 respons 200), sehingga tidak dimasukkan sebagai run awal tiga-key-dingin. Request pertama untuk login dan pemanasan tidak masuk sampel.

Artefak mentah: `storage/app/perf/noc-15k-http-serve-cold-100.json`, `noc-50k-http-cold-100.json`, `noc-50k-http-sequential-100.json`, `noc-50k-http-concurrent-5x20.json`, `noc-50k-http-concurrent-cold-repeat-5x20.json`, dan `noc-50k-http-concurrent-repeat-5x20.json`. Tidak ada perubahan kode aplikasi. Instrumentasi `perf:pages` memasang listener SQL/model dan menjalankan Laravel Kernel di proses CLI; hasil HTTP yang lebih rendah tidak membuktikan listener sebagai penyebab outlier CLI. Lonjakan SQL 8,5–14,7 detik pada run CLI sebelumnya belum terdiagnosis dan masih dapat berulang pada kondisi lain. P95 produksi, beban lebih dari lima pekerja, render browser, serta jaminan setiap request <1.500 ms belum terbukti.

### Reproduksi lonjakan dan perbaikan cache NOC — ADHOC-281 (2026-11-06)

Pada 50.107 pelanggan, uji ulang `perf:pages` lima pekerja ×20 saat cache kedaluwarsa mereproduksi lonjakan: p95 592 ms, maksimum 14.719 ms, tiga request ≥1.500 ms. Tiga pekerja pertama menghitung cache yang sama secara serentak; masing-masing menghabiskan 11,4–11,8 detik pada query tren tiket, dengan SQL total 12,8–13,8 detik. Pekerja berikutnya membaca cache dan jauh lebih cepat. `EXPLAIN ANALYZE` query tren saat sendiri selesai sekitar 377 ms untuk 8.940 tiket, memakai index `tickets_type_index` dan 8.940 lookup primary key `customers`. Full scan `pops` yang pernah 2,56 detik hanya menyentuh sekitar dua baris; itu bukan bukti kebutuhan indeks POP. Pola serentak dan penurunan waktu setelah serialisasi mendukung cache stampede sebagai penyebab lonjakan lokal, meski faktor beban host/MySQL tetap bisa memengaruhi angka absolut.

Cache statistik (TTL 60 detik), tren bulanan (300 detik), dan telemetri (60 detik) kini memakai lock atomik per key user+fingerprint scope POP saat miss. Request yang menunggu memeriksa cache ulang setelah memperoleh lock; bila tunggu 30 detik habis, jalur `Cache::remember` lama dipakai agar request tidak berubah menjadi error lock. Lock otomatis kedaluwarsa setelah 60 detik. Key dan isi cache, rumus analitik, serta pembatasan POP tidak diubah.

| Kondisi 50k, lima pekerja ×20 | Sampel | p50 | p95 | Maksimum | Request ≥1.500 ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| Sebelum lock, cache sudah kedaluwarsa | 100 | — | 592 ms | 14.719 ms | 3 |
| Hanya cache tren memakai lock | 100 | 256 ms | 461 ms | 2.099 ms | 2 |
| Ketiga cache memakai lock, run 1, eviksi `1,1,1` | 100 | 248 ms | 407 ms | 1.341 ms | 0 |
| Ketiga cache memakai lock, run 2, eviksi `1,1,1` | 100 | 251 ms | 390 ms | 838 ms | 0 |
| HTTP Nginx/PHP-FPM, ketiga cache memakai lock, eviksi `1,1,1` | 100 | 217 ms | 308 ms | 734 ms | 0 |

Semua 500 sampel pada tabel berstatus 200. Artefak mentah: `storage/app/perf/noc-50k-cli-repro-5x20.json`, `noc-50k-cli-after-lock-5x20.json`, `noc-50k-cli-all-locks-5x20.json`, `noc-50k-cli-all-locks-repeat-5x20.json`, dan `noc-50k-http-all-locks-5x20.json`. Tes `NocDashboardTest`: 27 lulus/127 assertion, termasuk perubahan scope POP; Pint lulus. Hasil ini membuktikan perbaikan pada rangkaian lokal tersebut, bukan jaminan setiap request <1.500 ms atau p95 produksi. Tes awal yang menahan lock dua detik mengandalkan ambang waktu, sehingga dapat sensitif terhadap mesin CI yang sangat lambat.

### P95 pada replika image produksi — ADHOC-285 (2026-11-07)

Pengukuran ini memakai image lokal `whusnet-perf-prod-app:20261106` dan `whusnet-perf-prod-web:20261106` dari `Dockerfile.prod`, tanpa bind mount kode. Hash controller NOC dalam image sama dengan workspace saat pengukuran. Aplikasi berjalan dengan `APP_ENV=production`, `APP_DEBUG=false`, config/route/view/event cache, OPcache aktif (`validate_timestamps=0`), PHP `memory_limit=512M`, dan pool FPM 20 worker. Container aplikasi dibatasi 2 CPU/2 GiB, Nginx 0,5 CPU/256 MiB. Entrypoint migrasi dilewati; hanya schema dummy terisolasi yang dipakai: 15.007 pelanggan/3.150 tiket (`whusnet_perf_noc_15k_20261105`) dan 50.000 pelanggan/10.500 tiket (`whusnet_perf_prod_50k_20261107`). Schema 50k dibuat dari migrasi dan seeder yang tersedia; schema 15k masih memiliki beberapa migrasi setelah 2026-11-05 yang tertunda, tetapi indeks NOC 2026-11-05 sudah ada. Distribusi data kedua schema tidak identik.

GET `/noc/dashboard` dijalankan sebagai Owner all-POP melalui Nginx/PHP-FPM pada loopback. `curl` mencatat status, waktu total sampai respons selesai, TTFB, dan ukuran respons. P95 memakai nearest-rank `ceil(0,95 × n)`. Cache dingin menghapus hanya key statistik, tren bulanan, dan telemetri akun benchmark sebelum tiap request. Pada masing-masing seri dingin 99/100 eviksi menghasilkan `1,1,1`; satu sampel lainnya menemui key yang telah kedaluwarsa secara alami. Seri paralel dimulai setelah key diisi lalu eviksi `1,1,1`. Cache normal mengikuti TTL aplikasi. Login dan satu request pemeriksaan awal tidak masuk sampel.

| Dataset / kondisi HTTP | n | p50 | p95 | Maksimum | ≥1.500 ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| 15k, cache normal | 100 | 309 ms | 402 ms | 1.605 ms | 1 |
| 15k, cache dingin per request | 100 | 482 ms | 609 ms | 699 ms | 0 |
| 15k, lima pekerja ×20, cache kosong di awal | 100 | 305 ms | 365 ms | 680 ms | 0 |
| 50k, cache normal | 100 | 351 ms | 388 ms | 468 ms | 0 |
| 50k, cache dingin per request | 100 | 776 ms | 858 ms | 1.004 ms | 0 |
| 50k, lima pekerja ×20, cache kosong, run 1 | 100 | 382 ms | 756 ms | 1.695 ms | 2 |
| 50k, lima pekerja ×20, cache kosong, run 2 | 100 | 373 ms | 508 ms | 900 ms | 0 |
| 50k, sepuluh pekerja ×20, cache kosong | 200 | 488 ms | 773 ms | 1.256 ms | 0 |

Semua 900 sampel berstatus HTTP 200. Satu request awal di luar sampel memakan 1.130 ms pada 15k dan 2.946 ms pada 50k. Dua outlier 50k run pertama ada pada sampel pertama pekerja 1/2 (1.667/1.695 ms); TTFB masing-masing 1.643/1.688 ms, sehingga jeda utamanya terjadi sebelum transfer respons. Run ulang tidak mereproduksi outlier tersebut. P95 di bawah 1.500 ms pada rangkaian ini, tetapi target **setiap request <1.500 ms tidak tercapai**.

Artefak mentah: `storage/app/perf/noc-prodlike-15k-warm-100.json`, `noc-prodlike-15k-cold-100.json`, `noc-prodlike-15k-concurrent-5x20.json`, `noc-prodlike-50k-warm-100.json`, `noc-prodlike-50k-cold-100.json`, `noc-prodlike-50k-concurrent-5x20.json`, `noc-prodlike-50k-concurrent-repeat-5x20.json`, dan `noc-prodlike-50k-concurrent-10x20.json`. Tidak ada perubahan kode aplikasi dan **full test suite tidak dijalankan** sesuai arahan user; verifikasi task ini adalah request HTTP dan perhitungan ulang JSON.

**Batas kesetaraan:** image, OPcache, dan FPM mengikuti konfigurasi produksi, tetapi database/Redis berada pada host Docker lokal yang sama, bukan layanan produksi; proxy Coolify/Traefik, TLS, jaringan pengguna, lalu lintas modul lain, dan kapasitas CPU/RAM server produksi asli tidak termasuk. Cookie secure dimatikan khusus HTTP loopback. Karena itu hasil ini adalah p95 **replika software produksi lokal**, bukan p95 produksi aktual atau jaminan kapasitas produksi. Container, jaringan, env minimum, dan cookie sementara sudah dibersihkan; kedua schema benchmark sengaja disimpan untuk reproduksi.

**Bug yang ditemukan pada NOC:** kunci cache statistik filter tanggal custom sebelumnya memakai variabel yang tidak didefinisikan, sehingga dua rentang tanggal berbagi hasil; sudah diperbaiki dan diuji. Cache statistik, tren, dan telemetri sebelumnya tidak memuat scope POP, sehingga penyempitan akses bisa menampilkan data lama sampai TTL habis; sudah diperbaiki dan diuji setelah persetujuan user. Di `NocAnalyticsService`, pembagian MTTR remote/field dan remote solve rate membandingkan enum `TicketHandler` dengan string `fop`; ini berpotensi salah klasifikasi, belum diubah dalam task performa agar angka operasional tidak berubah tanpa review terpisah.

**Koreksi diagnosis:** N+1 pada GET `/warehouse/reports` berasal dari `balanceAsOf()` yang dipanggil dalam loop `buildMovementSummary()`. `receivedInPeriod()` juga dipanggil per POP×item, tetapi pada export Excel. Indeks urutan daftar yang baru terbukti membantu EXPLAIN daftar; belum membuktikan laporan kolektor bulanan membaik. Rute mati `api/fop/pipeline` dan bug export gudang `Border::TOP` (OpenSpout) yang tercatat saat tes belum diperbaiki karena di luar task performa ini. Tes lama periode tanpa transaksi sudah dikoreksi agar sesuai aturan item idle yang tetap membawa stok awal/akhir.


Dikonfirmasi dengan `php artisan perf:pages` (query log asli, DB mysql container `whusnet-app`, bukan tebakan dari baca kode). Command ini sudah ada di repo (`app/Console/Commands/MeasurePagesCommand.php`) — load tiap halaman GET dalam proses terisolasi, catat waktu, jumlah query, jumlah model Eloquent yang di-hidrasi, dan EXPLAIN query SELECT yang ≥ 50ms.

Database saat pengukuran: `customers` ≈ 49.463 baris (bukan hanya 15k yang baru di-inject — akumulasi), `ticket_histories` ≈ 26.815 baris.

## Ringkasan hasil ukur

| Halaman | Waktu | Query | Model dihidrasi | Tanda |
|---|---|---|---|---|
| `/noc/dashboard` (Ticketing) | **9142 ms** | 96 | **64.069** | LAMBAT, MUAT-SEMUA |
| `/network/nodes` (Master Node / sumber data Peta Infra) | **9851 ms** | 55 | 6.832 | LAMBAT, MUAT-SEMUA |
| `/fop-tasks` | 3833 ms | 69 | **21.450** | LAMBAT, MUAT-SEMUA |
| `/fop` | 2803 ms | 81 | **24.098** | LAMBAT, MUAT-SEMUA |
| `/customers` | 2282 ms | 53 | 171 | LAMBAT |
| `/business-development/business-customers` | 2443 ms | 25 | 53 | LAMBAT |
| `/fop/analitik` | 1053 ms | 36 | 12.003 | MUAT-SEMUA? |
| `/fop-tasks/history` | 579 ms | 28 | 9.669 | MUAT-SEMUA? |

Halaman lain yang diukur (`tickets/dibatalkan`, `tickets/history`, `tickets/selesai`, `tickets/{id}`, `master/network-node-types`, dll) **aman** — bukan ini sumber keluhan "Ticketing lambat", sumbernya NOC Dashboard/Worksheet (halaman "Tiket Masuk"/"Diproses" sekarang hidup di `noc/dashboard` & `noc/worksheet`, bukan `/tickets` generik — rute itu sudah dihapus, lihat CLAUDE.md §Sinkronisasi).

## Temuan per halaman (root cause, bukan simtom)

### 1. Ticketing — `/noc/dashboard` (9,1 detik, 64 ribu model)

`app/Http/Controllers/NocDashboardController.php:575-619` — `buildPerformanceLeaderboard()`. Leaderboard performa Helpdesk/NOC ambil **4 query `->get()` terpisah** ke `TicketHistory` (selesai, eskalasi-ke-NOC, eskalasi-ke-FOP, dikembalikan), hanya histori selesai yang eager-load relasi `ticket`, lalu di-`groupBy('actor_id')` **di PHP**, bukan di SQL. Tanpa filter tanggal (default), ini menyapu seluruh 26.815 baris `ticket_histories` 4 kali dan menghidrasi puluhan ribu model Eloquent di memori hanya untuk dikelompokkan — hal yang SQL `GROUP BY` + agregat bisa kerjakan di DB.

EXPLAIN: full scan `ticket_histories` (~26.815 baris) + filesort.

### 2. Master Node / Peta Infra — `/network/nodes` (9,85 detik)

`app/Http/Controllers/Network/NetworkNodeController.php:159-165` (ada duplikat logic di `:461-467`). `$availableCustomers` di-filter `whereIn('pop_id', $visiblePopIds)->whereDoesntHave('portAssignments', ...)->limit(500)`. `limit(500)` tidak menolong — `whereDoesntHave` jadi `NOT EXISTS` berkorelasi, MySQL tetap scan hampir seluruh tabel `customers` buat evaluasi tiap baris sebelum limit diterapkan. EXPLAIN: **full scan `customers` (~49.463 baris)** + filesort. Query ini sendirian makan 5,3 detik dari total 9,85 detik halaman.

Catatan: dokumen `analisis_performa_peta_infrastruktur_isp.md` (sudah ada sebelumnya di repo) menjelaskan Peta Infra dari sisi **frontend** (DOM/SVG 16 ribu kartu, tanpa culling) — itu penyebab lain yang terpisah, bukan backend. Dua-duanya sama-sama dipicu oleh skala data yang naik bareng (jumlah ONT ≈ jumlah pelanggan aktif), tapi mekanismenya beda: satu query DB lambat, satu DOM/Alpine kebanyakan elemen.

### 3. FOP — `/fop` & `/fop-tasks` (21–24 ribu model dihidrasi per request)

`app/Http/Controllers/FopTaskController.php:1583-1623` (`autoSyncAndCalculatePriority()`), dipanggil di awal `index()` (baris 49) — **jalan di SETIAP request** ke `/fop-tasks` (dan dipanggil ulang dari `/fop`). Ini "jaring pengaman" yang:
- `Customer::whereIn('status', ['calon_pelanggan','waiting_survey'])->whereDoesntHave('fopTasks', ...)->get()` — load semua baris cocok, bukan cuma hitung.
- Query serupa untuk status `waiting_installation`.
- Untuk tiap baris hasil, panggil `FopTaskProvisioningService::ensureForCustomer()` satu-per-satu (bukan batch).

Dengan 15k+ pelanggan, jumlah yang match kedua `whereIn` status itu jadi besar, dan tiap kandidat dicek ulang via subquery `whereDoesntHave` (NOT EXISTS) + loop PHP panggil service per baris. Ini linear-naik terhadap total pelanggan, dieksekusi ulang **tiap kali siapa pun membuka papan FOP**, bukan cuma sekali saat status pelanggan berubah (padahal sudah ada komentar di kode sendiri bahwa pembuatan FopTask seharusnya terjadi di `FopTaskProvisioningService` saat transisi status — loop ini cuma dimaksudkan sebagai jaring pengaman untuk data lama yang "terlanjur tidak punya anchor").

### 4. List Pelanggan — `/customers` & `/business-development/business-customers` (~1,5–2,4 detik)

Query `count()` pakai `EXISTS` berkorelasi ke `customer_services`/`invoices` (statusCounts, overdueCount — lihat `RendersCustomerList.php:378,388` dari analisa sebelumnya) kena **temporary table** + **filesort** di EXPLAIN. Lebih ringan dari 3 di atas, tapi tetap kena setiap buka halaman karena tidak di-cache.

## Bukan ini sumbernya (sudah dicek, aman)

- Index FK (`pop_id`, `mini_pop_id`, `collector_id`, `distribution_id`, `termination_reason_id`) — semua `foreignId()->constrained()`, otomatis terindeks.
- Index pencarian (`customer_code`, `cid`, `primary_phone`, `identity_number`) — sudah ada index eksplisit dari migration `add_customer_search_prefix_indexes`.
- View composer badge sidebar (`AppServiceProvider.php:365-439`) — teori awal sebelum log diukur. Tetap valid sebagai beban tambahan kecil di SETIAP halaman (karena nempel ke layout global, dipakai semua halaman termasuk FOP/Ticketing/Master Node), tapi **bukan** kontributor dominan — 4 temuan di atas jauh lebih berat (detik, bukan puluhan ms) dan sudah terbukti dari query log, bukan kecurigaan.

## Kesimpulan penyebab utama

Bukan "kekurangan index" generik. Pola yang berulang di 3 dari 4 halaman termahal: **`whereDoesntHave()` / NOT EXISTS berkorelasi di atas tabel `customers` yang sekarang besar**, dijalankan ulang tiap request (bukan di-cache / di-precompute), kadang dalam loop PHP per-baris (`autoSyncAndCalculatePriority`), kadang buat sekadar menghitung/mengelompokkan data yang semestinya bisa selesai di SQL (`buildPerformanceLeaderboard`). Di data kecil, NOT EXISTS murah. Begitu `customers` sampai puluhan ribu baris, biayanya melompat jadi full scan tabel — dan karena dieksekusi ulang di setiap buka halaman (bukan dihitung sekali/di-cache), Lighthouse Performance score ikut anjlok di SEMUA halaman yang kena, sesuai laporan: FOP, Ticketing (NOC Dashboard), Peta Infra/Master Node.

## Status saat snapshot awal

Dokumen ini analisa + bukti ukur pada saat dibuat. Perbaikan (ubah `whereDoesntHave` jadi agregat SQL, pindahkan jaring pengaman FOP ke job terjadwal/queue, cache leaderboard NOC, index pendukung untuk `portAssignments`) belum disentuh — tunggu konfirmasi/prioritas sebelum ubah kode, sesuai CLAUDE.md §Cara Kerja Task (scope check dulu, baru coding).

---

## Lanjutan — Sweep Seluruh Aplikasi (138 + 188 halaman, termasuk halaman detail)

Scope diperbesar: ukur SEMUA rute GET ber-auth (`php artisan perf:pages`, tanpa `--only`), sekali tanpa sample id (138 halaman) dan sekali dengan sample id buat halaman detail seperti `/network/nodes/1`, `/tasks/1` (188 halaman). Tujuan: cari bottleneck/N+1/kandidat cache di LUAR 4 halaman yang sudah dicatat di atas, dan bedakan yang murni "butuh index" vs yang "butuh di-cache" vs yang "query-nya sendiri salah (N+1 / muat-semua)".

### Tabel lengkap — halaman bermasalah (gabungan 2 sweep, durasi terburuk per halaman)

| Halaman | Waktu | Query | Model dihidrasi | Memori | Tanda |
|---|---|---|---|---|---|
| `/` (Dashboard beranda) | **10589 ms** | 70 | 10.316 | 70,5 MB | LAMBAT, MUAT-SEMUA |
| `/noc/dashboard` | 8801–7673 ms | 88–96 | 46.228–64.069 | 118,5 MB | LAMBAT, MUAT-SEMUA |
| `/network/peta` (Peta Infrastruktur) | 5309 ms | 38 | **44.018** | **292,8 MB** | LAMBAT, **MEMORI**, MUAT-SEMUA |
| `/fop` | 2339–4229 ms | 66–81 | 24.096 | 116,4 MB | LAMBAT, MUAT-SEMUA |
| `/collector-worksheet` | 3460–3618 ms | 31 | **48.994** | 118,5 MB | LAMBAT, MUAT-SEMUA |
| `/fop-tasks` | 1808–3043 ms | 58–69 | 21.450 | 112,5 MB | LAMBAT, MUAT-SEMUA |
| `/warehouse/reports` | 2094–2481 ms | **311** | 14.403 | 54,5 MB | LAMBAT, **N+1 terbukti**, MUAT-SEMUA |
| `/reports/collector-payments` | 1530–1700 ms | 19 | 20.474 | **167 MB** | LAMBAT, MEMORI, MUAT-SEMUA |
| `/network/nodes` | 1682–2378 ms | 55 | 6.832 | 74,5 MB | LAMBAT, MUAT-SEMUA |
| `/network/nodes/1` (detail node) | 287 ms | 37 | 6.231 | 72,5 MB | MUAT-SEMUA |
| `/fop/analitik` | 892–965 ms | 36 | 12.003 | 74,5 MB | MUAT-SEMUA |
| `/fop-tasks/history` | 846–896 ms | 28 | 9.669 | 86,5 MB | MUAT-SEMUA |
| `/warehouse/history` | 521–556 ms | 33 | 9.666 | 80,5 MB | MUAT-SEMUA |

Halaman lain yang diukur (131 sisanya dari 138, termasuk hampir semua halaman Master, RBAC, Billing non-report, serta halaman detail `customers/{id}`, `invoices/{id}`, dll) **tidak flag masalah** — jadi bottleneck app ini terkonsentrasi, bukan menyebar rata ke semua halaman.

### Temuan baru per halaman

#### 5. Dashboard beranda `/` — 10,6 detik, PALING LAMBAT DI SELURUH APLIKASI

`app/Http/Controllers/DashboardController.php:343-358` (`omsetPerSegmen()` / sekitarnya). Query `SUM(CASE WHEN ...)` buat "Omset per Segmen (Sales/Teknisi/Bisnis)" JOIN 5 tabel berantai: `customers → users (sales) → roles → customer_services → internet_packages`, dibungkus `LOWER(COALESCE(sales_roles.code, ''))` — **fungsi di atas kolom yang dibandingkan bikin index `roles.code` (kalau ada) tidak terpakai**, MySQL harus evaluasi ekspresi itu baris per baris. Query ini sendiri 1,7 detik. Ditambah query lain di halaman yang full scan `customers` buat widget "aktivitas terbaru" (`data_completeness_status in (...)` tanpa index kolom itu). Total 70 query buat satu halaman beranda — **halaman paling sering dibuka siapa pun yang login**, jadi paling kerasa lambatnya ke semua role.

**Kandidat utama buat di-cache**, bukan cuma index — angka omset/sales/teknisi/bisnis realistis diperbarui per beberapa menit, bukan tiap request.

#### 6. Peta Infrastruktur `/network/peta` — backend JUGA berat, bukan cuma DOM

Beda dari `analisis_performa_peta_infrastruktur_isp.md` yang fokus ke rendering **frontend** (16 ribu kartu SVG tanpa culling) — ukuran ini nunjukkin **backend-nya sendiri sudah berat sebelum sampai ke browser**: `InfrastructureMapController::index` (route `network.map.index`) makan 5,3 detik, 292,8 MB memori (flag MEMORI — jauh di atas threshold 128 MB), 44.018 model dihidrasi. EXPLAIN nemu 3 query berat:
- full scan `network_nodes` (~3.474 baris) + filesort — subquery count port per node, dieksekusi per baris (`(select count(*) from network_node_ports where ...)` sebagai kolom select, bukan JOIN+GROUP BY).
- full scan `network_port_assignments as assignment` (~42.504 baris) + temporary table — hitung port terpakai per PON.
- full scan `inventory_transactions` (~65.492 baris) — subquery `MAX(id)` berkorelasi buat ambil transaksi terakhir per serial.

Jadi payload 14,9 MB JSON yang dikeluhkan di dokumen lama itu sendiri **mahal buat dibangun di server**, bukan cuma mahal buat dirender Alpine. Dua masalah, dua lapis, sama-sama perlu dibenahi.

#### 7. Worksheet Kolektor `/collector-worksheet` — model dihidrasi TERBANYAK di seluruh app (48.994)

`app/Http/Controllers/CollectorWorksheetController.php`. EXPLAIN: full scan `invoices` (~111.438 baris) + temporary table (subquery korelasi `collector_id` dari `customers` per baris invoice — pola sama dengan masalah di List Pelanggan, lihat §4), plus filesort `customers` buat `whereNull('collector_id')->orderBy('full_name')->limit(50)` (limit 50 tapi tetap filesort penuh karena tidak ada index pada kombinasi itu). 48 ribu model ini kemungkinan besar dari kombinasi beberapa panel (unpaid totals per kolektor + daftar pelanggan unassigned + riwayat pembayaran) yang semua dihitung penuh di request yang sama — perlu ditelusuri lebih detail per panel kalau mau dibenahi (belum ditelusuri sampai baris pasti, beda dengan temuan lain di dokumen ini yang sudah sampai ke baris kode).

#### 8. Laporan Gudang `/warehouse/reports` — satu-satunya N+1 yang TERBUKTI lewat tanda tangan SQL berulang

311 query untuk SATU halaman, dan tool otomatis mendeteksi **78× query identik** (beda binding) ke `inventory_transactions` (`id in (…) and to_pop_id = ? and type not in (…)`). Koreksi setelah menelusuri pemanggil: pada GET halaman, query berulang berasal dari `WarehouseReportController::buildMovementSummary()` yang memanggil `WarehouseStockAsOfService::balanceAsOf()` dua kali per kombinasi POP × item. `receivedInPeriod()` dipanggil per kombinasi pada jalur export Excel. GET halaman kini memakai batching yang menjaga replay ledger dan batas POP; jalur export Excel masih menghitung per item dan belum dioptimasi. Ini definisi buku teks dari N+1 — bukan soal index, soal cara query-nya ditulis.

#### 9. Laporan Setoran Kolektor `/reports/collector-payments` — 167 MB memori, 20.474 model dari SATU query tanpa limit

`app/Services/CollectorPaymentReportService.php:38-51` — `Payment::query()->...->orderBy('id')->get()` **tanpa paginate/limit**, narik seluruh baris `payments` dalam rentang tanggal yang dipilih user ke memori buat dihitung manual. Kalau rentang tanggalnya lebar (atau user pilih "semua"), ini scale lurus sama jumlah pembayaran — dan tabel `payments` sekarang ~101.990 baris.

#### 10. Minor tapi gratis buat dibenahi

Rancangan migration konkret untuk kelima titik di bawah (termasuk index yang SUDAH ada di DB dicek langsung, bukan tebakan dari migration) ada di `docs/plan/rancangan-index-performa-15k-seeder.md`.

- `/tasks/1` → EXPLAIN flag full scan `workflow_transition_permissions` (cuma ~12 baris, jadi dampaknya kecil, tapi index `(from_status, to_status)` yang hilang ini kena di SETIAP transisi status task, bukan cuma di halaman ini).
- `/customers/create` → full scan `internet_packages` (~72 baris) dan `agents` (~4 baris) — tabel kecil, dampak kecil, sebutkan saja buat kelengkapan.
- `/invoices`, `/invoices/belum-lunas`, `/invoices/lunas`, `/reports/invoices` → semua full scan `invoices` (~111.438 baris) + filesort cuma buat `ORDER BY issue_date DESC, id DESC LIMIT 10/15` — index pada `issue_date` (atau composite `(invoice_status, issue_date)` untuk varian yang pakai `WHERE invoice_status`) akan menghapus filesort ini.
- `/reports/payments` → sama, full scan `payments` (~101.990 baris) + filesort buat `ORDER BY payment_date DESC`.
- `/reports/collector-monthly` → dua full scan besar (`invoices` ~111.438, `payments` ~101.990) di query JOIN gabungan — beda dari #9 (controller berbeda: `CollectorMonthlyReportController`), index pendukung di `issue_date`/`payment_date` akan membantu di sini juga.

### Kesimpulan diperluas — 3 kategori masalah, jangan disamakan obatnya

1. **Butuh cache, bukan index** — Dashboard beranda (#5), leaderboard NOC (temuan #1 sebelumnya), badge sidebar global (teori awal). Datanya tidak perlu real-time per klik; aman di-`Cache::remember` beberapa menit.
2. **Butuh ubah cara query (N+1 / muat-semua), index saja tidak cukup** — Laporan Gudang (#8, N+1 terbukti), jaring pengaman FOP (temuan #3 sebelumnya, loop PHP per-baris), `whereDoesntHave`/NOT EXISTS di Master Node & List Pelanggan (temuan #2 & #4 sebelumnya, Peta Infra #6), leaderboard NOC (4× full scan tanpa agregat SQL).
3. **Butuh index biasa** — `invoices.issue_date`, `payments.payment_date`, `workflow_transition_permissions.(from_status, to_status)`. Low-effort, high-certainty, tapi bukan yang bikin halaman jadi hitungan detik — cuma ratusan ms.

Yang PALING berdampak ke keluhan "lemot di mana-mana" ada di kategori 1 & 2, bukan kategori 3. Index pendukung masih worth dikerjakan (gratis, gak ada risiko), tapi jangan dikira itu yang nyelesaikan masalah utama.

Pada saat snapshot ini belum ada kode yang diubah — semua di atas murni hasil ukur `php artisan perf:pages`, nunggu arahan prioritas mana yang dikerjakan duluan.

---

## Lanjutan 2 — Tuntasin §7, dan cek kenapa "Semi SPA" kerasa kayak reload penuh

Dua arah penelusuran: (1) tuntasin `/collector-worksheet` (§7) sampai baris pasti, (2) cek apakah arsitektur "Semi SPA" (Alpine + Reverb/Echo, server-rendered Blade tapi harusnya update partial tanpa reload) benar-benar jalan sesuai niatnya, atau malah diam-diam fallback ke full page load — karena kalau iya, itu ngelipatgandakan ongkos dari semua halaman berat yang udah ditemukan.

### 11. Tuntas §7 — Worksheet Kolektor, root cause exact baris

Dikonfirmasi langsung ke DB (bukan baca kode doang): user pengukur (`superadmin`, id=1) kebetulan jadi `received_by` di **40.031 baris `payments`** (artefak data — kemungkinan dari proses import/seed yang menaruh semua transaksi legacy atas nama admin pertama). Dua pemanggilan di `CollectorWorksheetController::index()` yang HYDRATE baris penuh itu:

- `AdminCashBalanceService.php:86-109` (`tunaiBelumDisetor()`) → `unsettledManualPaymentsQuery($admin)->get(['id','amount','balance_used_amount','overpay_amount'])` — **tanpa limit**, narik SEMUA payment `received_by=$admin->id` yang cocok, cuma buat di-`map()` dan di-`Money::sum()` di PHP.
- `CollectorBalanceService.php:100-122` (`technicianSettlementStatus()`) → `Payment::whereIn('collected_by', $technicians->pluck('id'))->get([...])` — pola sama, 22.821 baris `payments` match kondisi (`payment_status=valid`, `collector_deposit_id` null) di seluruh DB.

Kombinasi ini sumber utama 48.994 model yang dihidrasi di `/collector-worksheet`. **Bukan N+1** (masing-masing cuma 1 query) — ini kelas masalah yang sama dengan leaderboard NOC (#1) dan Dashboard beranda (#5): **narik baris penuh ke PHP cuma buat dijumlah, padahal `SUM()` bisa dikerjakan SQL**. Beda dengan #1/#5, besarnya di sini sangat tergantung PROFIL DATA user yang login (berapa banyak payment yang `received_by`/`collected_by` dia) — di produksi nyata tiap admin kemungkinan cuma "pegang" ratusan, bukan puluhan ribu, TAPI pola kodenya tetap tidak scalable: admin lama yang sudah lama kerja (atau siapa pun yang jadi "admin default" di proses import) akan makin berat seiring waktu, bukan cuma karena jumlah pelanggan total.

### 12. "Harusnya Semi SPA" — ketemu, dan ini konfirmasi kecurigaan user

Dicek 43 file Blade yang pakai `setInterval`/`fetch` (`grep -rl "setInterval\|wire:poll\|fetch(" resources/views`). **Bukan polling buta** — nyaris semua `setInterval` yang ketemu cuma timer countdown client-side (`countdown-timer.blade.php`, `verifications/queue.blade.php` — update tampilan jam, tidak nge-hit server). Jadi bukan di situ masalahnya.

Masalah sebenarnya ada di CARA event realtime (Reverb/Echo, broadcast) di-handle, dan di satu halaman ini beda dari yang lain:

**`/fop` (FOP Dashboard) — `resources/views/fop/dashboard.blade.php:794-800`:**
```js
window.Echo.private(`fop.${popId}`)
    .listen('TaskStarted', () => this.refreshTaskStats())
    .listen('TaskCompleted', () => this.refreshTaskStats())
    .listen('SurveyStarted', () => this.refreshDashboardContainers())
    .listen('SurveyCompleted', () => this.refreshDashboardContainers())
    .listen('InstallationStarted', () => this.refreshDashboardContainers())
    .listen('InstallationCompleted', () => this.refreshDashboardContainers());
```
`refreshTaskStats()` (baris 811-826) dan `refreshDashboardContainers()` (baris 828-842) ISINYA SAMA:
```js
const res = await fetch(window.location.href);   // ← fetch ULANG SELURUH halaman /fop
const html = await res.text();
const doc = new DOMParser().parseFromString(html, 'text/html');
[...].forEach(id => { /* cuma comot innerHTML beberapa <div>, buang sisanya */ });
```
Ini **BUKAN** partial update yang murah — `fetch(window.location.href)` menjalankan ULANG seluruh `FopDashboardController::index()`, query yang SAMA PERSIS dengan buka `/fop` dari nol (2,3–4,2 detik, 24 ribu model, termasuk jaring pengaman `autoSyncAndCalculatePriority()`-nya FopTaskController kalau alur itu ikut terpanggil). Lalu 95% hasil HTML-nya DIBUANG, cuma diambil `stat-cards-container`, `antrian-survey-container`, `status-teknisi-container`, `emergency-ticker-container`, `customer-pipeline-funnel`.

**Dampaknya ganda:**
1. **Per event, bukan per user action.** Event ini broadcast otomatis tiap kali SIAPA PUN di POP itu mulai/selesai task/survey/instalasi — sepanjang hari, tanpa staf yang buka dashboard melakukan apa pun.
2. **Per viewer, bukan sekali.** Reverb broadcast ke SEMUA yang subscribe channel `fop.{popId}`. Kalau 5 orang staf FOP sama-sama punya tab `/fop` terbuka, SATU event task-selesai = **5× fetch halaman 2-4 detik itu berbarengan**, bukan 1×.
3. **Fallback-nya makin parah:** kalau DOM target (`stat-cards-container` dkk) tidak ketemu atau fetch gagal, baris 862 & 866 jatuh ke `window.location.reload()` — full browser reload, bukan cuma fetch diam-diam.

**Bandingkan dengan `/fop-tasks` (`fop_tasks/index.blade.php:2350-2353` + `refreshFopTaskRow()` baris 2298-2313) — ini CONTOH YANG BENAR:**
```js
window.Echo.private('fop-tasks.' + popId)
    .listen('.FopTaskUpdated', (e) => this.refreshFopTaskRow(e.fop_task_id))
    .listen('TaskStarted', (e) => { if (e.fop_task_id) this.refreshFopTaskRow(e.fop_task_id); })
    .listen('TaskCompleted', (e) => { if (e.fop_task_id) this.refreshFopTaskRow(e.fop_task_id); });
```
`refreshFopTaskRow()` fetch ke endpoint KHUSUS **`/fop-tasks/{taskId}/row`** — satu baris, bukan seluruh papan. Ini semi-SPA yang bener: event spesifik → fetch spesifik → DOM update spesifik. Jadi arsitekturnya BISA bener (ada presedennya di repo yang sama), `/fop` dashboard cuma belum ikut pola itu.

**Kesimpulan:** halaman yang justru PALING SERING kena refresh otomatis di seluruh app (`/fop`, karena event task/survey/instalasi terjadi terus-menerus sepanjang hari kerja) adalah JUGA halaman paling berat untuk di-render ulang (temuan §3 & tabel sweep). Ini bukan masalah tambahan yang berdiri sendiri — ini PENGALI dari semua masalah backend di `/fop` yang sudah ditemukan: setiap kali masalah backend itu "kena", bukan cuma 1 orang yang nunggu, tapi N tab terbuka × tiap event yang lewat sepanjang hari.

**Update — ternyata SISTEMIK, bukan cuma `/fop`.** Dicek 2 halaman terberat lain yang juga pakai Reverb/Echo, polanya SAMA:

- **`/noc/dashboard`** (§1, 9,1 detik, 64 ribu model — TERBERAT di seluruh app) → `resources/views/noc/dashboard.blade.php:523,558`: `window.Echo.private('tickets.{popId}').listen('.TicketQueueUpdated', () => refetchAndSwap())`, dan `refetchAndSwap()` isinya `fetch(window.location.href, ...)` — fetch ULANG seluruh dashboard NOC (termasuk leaderboard 4× full-scan `ticket_histories` di §1) tiap kali ada tiket masuk/dieskalasi/diselesaikan. Tiket adalah entitas paling sering berubah status di seluruh sistem — ini kemungkinan AMPLIFIER PALING PARAH karena frekuensi event-nya paling tinggi.
- **`/collector-worksheet`** (§7, 3,6 detik, 48 ribu model) → `resources/views/partials/collector-realtime.blade.php:115-158`, di-`@include` dari KEDUA `collector-worksheet/index.blade.php` dan `show.blade.php`: `window.Echo.private(...).listen('.CollectorDepositUpdated', ...).listen('.CollectorActivityUpdated', ...).listen('.CashDepositUpdated', ...)`, handler-nya juga `fetch(window.location.href, ...)` + DOMParser swap satu container.

Jadi **3 dari 4 halaman terberat di seluruh aplikasi** (`/noc/dashboard`, `/fop`, `/collector-worksheet`) pakai pola "fetch ulang HALAMAN PENUH tiap broadcast event" — cuma `/fop-tasks` yang punya endpoint partial yang benar (`refreshFopTaskRow()` → `/fop-tasks/{id}/row`). Ini bukan kebetulan tiga tempat kepikiran solusi sama — ini satu pola arsitektur yang ditiru ke beberapa halaman tanpa endpoint ringan yang setara dengan `/fop-tasks/{id}/row` untuk dashboard/aggregate view (masuk akal, karena dashboard nampilin AGREGAT, bukan satu baris — endpoint ringan buat ini bentuknya beda: JSON kecil berisi angka-angka, bukan HTML satu row). `/network/peta` dan halaman Master lain TIDAK kena pola ini (tidak pakai Echo sama sekali untuk auto-refresh), jadi aman dari pengali ini — masalah di situ murni query lambat di initial load (§6), bukan dikali ulang oleh event.

### 13. Temuan sampingan — rute mati `api/fop/pipeline`

Saat nyoba ukur endpoint `api/*` yang dilewatin sweep otomatis (`perf:pages` nge-skip prefix `api/` dari daftar target, lihat `MeasurePagesCommand::SKIP_PREFIXES`), ketemu `GET /api/fop/pipeline` (`routes/web.php:1275`, nama rute `fop.pipeline`) **selalu 500** — `storage/logs/laravel.log`: `Call to undefined method App\Http\Controllers\FopDashboardController::pipeline()`. Metode itu sudah tidak ada (kemungkinan sudah dipindah jadi inline di `index()`, lihat `getCustomerPipelineSummary()` di baris 379), tapi rute lama belum dihapus. Dicek `grep` ke seluruh `resources/views` — **tidak ada yang memanggil rute ini**, jadi murni bug/dead code, bukan kontributor lambat yang aktif. Aman dihapus kapan saja, tapi dicatat di sini karena ditemukan dalam proses yang sama.

## Kesimpulan akhir — urutan prioritas gabungan

1. **Ganti `fetch(window.location.href)` jadi endpoint ringan khusus di 3 halaman** (`/noc/dashboard`, `/fop`, `/collector-worksheet`) — ikuti pola `refreshFopTaskRow()` yang sudah benar di `/fop-tasks`, tapi bentuk endpointnya JSON kecil (bukan HTML row) karena ini dashboard agregat. Prioritas #1 karena ini PENGGANDA — jalan otomatis, broadcast ke semua viewer, tiap event sepanjang hari kerja. Selama endpoint-nya masih fetch halaman penuh, optimasi backend apa pun di 3 halaman itu cuma mengecilkan angka yang tetap dikali ulang N kali per hari.
2. Kategori 1 & 2 yang sudah dicatat (cache Dashboard/leaderboard NOC/collector-worksheet; ubah N+1 gudang & jaring pengaman FOP & NOT EXISTS Peta Infra-Master Node).
3. Index biasa (kategori 3, lihat `docs/plan/rancangan-index-performa-15k-seeder.md`) + hapus rute mati `fop.pipeline` — gratis, kapan saja.

Pada saat snapshot ini belum ada kode yang diubah. Status terbaru ada di awal dokumen.

---

## Status dokumen ini sebagai basis keputusan eksekusi

Ditanya langsung: apakah rancangan di atas SUDAH cukup buat memutuskan "biar gak bottleneck, query mana yang N+1, performa real + Lighthouse > 90, mana yang pakai cache, biar jadi semi-SPA beneran" — jawabannya **sebagian cukup, 3 celah yang harus ditutup dulu sebelum ini jadi rencana eksekusi, bukan cuma catatan diagnosa**:

### Sudah cukup
- **Bottleneck & root cause** — tiap temuan di atas sudah sampai ke file:baris pasti, bukan kecurigaan (kecuali §7 yang baru tuntas barusan, dan §4 `collector-monthly` yang masih nunggu EXPLAIN ulang).
- **N+1 yang TERBUKTI vs yang cuma "muat-semua"** — dipisah eksplisit: cuma `/warehouse/reports` (§8, `WarehouseStockAsOfService.php:83-93`) yang N+1 sungguhan (query identik berulang). Sisanya (NOC leaderboard, Dashboard omset, jaring pengaman FOP, collector-worksheet) itu "satu query besar tanpa agregat SQL" atau "NOT EXISTS di tabel besar" — beda akar, beda obat, sudah dibedakan di §Kesimpulan diperluas.
- **Mana yang kandidat cache** — sudah ada daftar (Dashboard omset §5, leaderboard NOC §1, collector-worksheet §7, badge sidebar global) tapi BELUM ada TTL + strategi invalidasi konkret (lihat celah #2 di bawah).

### Belum cukup — 3 celah sebelum bisa dieksekusi

1. **Lighthouse > 90 belum divalidasi sama sekali di sesi ini.** Semua angka di dokumen ini dari `perf:pages` — ukuran BACKEND (ms, query, model), bukan skor Lighthouse asli (yang juga menghitung JS/CSS payload, render-blocking resource, dll — ingat temuan `analisis_performa_peta_infrastruktur_isp.md` soal 374 ribu directive Alpine di Peta Infra, itu sisi FRONTEND yang perf:pages tidak ukur sama sekali). Repo ini SUDAH punya tooling buat ini (`lighthouserc.cjs`, `run-lighthouse-groups.sh`, `lighthouse-summary.cjs`, folder `lighthouse-reports/`) tapi belum dijalankan di sesi ini. **Harus ada baseline Lighthouse aktual dulu** (skor sekarang, per halaman) sebelum "> 90" bisa jadi target yang bisa diverifikasi — tanpa baseline, tidak ada cara tahu seberapa jauh, dan tidak ada cara konfirmasi berhasil setelah fix.
2. **Cache belum punya spesifikasi TTL + invalidasi.** "Kandidat cache" sudah ada daftarnya, tapi keputusan eksekusi butuh per-item: TTL berapa detik/menit, di-invalidate oleh event apa (atau murni time-based), dan apakah aman stale untuk kasus itu (misal: leaderboard NOC aman stale 1 menit, tapi badge "tunggakan kolektor" di halaman pembayaran mungkin tidak boleh stale sama sekali). Belum ditulis — baru "ini kandidat cache", belum "cache ini TTL X, invalidate saat Y".
3. **Endpoint ringan pengganti `fetch(window.location.href)` belum didesain bentuknya.** §12 baru bilang "harus ada endpoint ringan kayak `/fop-tasks/{id}/row`", tapi buat dashboard agregat (NOC/FOP/Collector) bentuknya beda — JSON berisi angka-angka mana yang di-refresh per event (misal `TaskCompleted` di `/fop` cuma perlu angka `stat-cards`, tidak perlu re-render `customer-pipeline-funnel`). Ini butuh rancangan endpoint per dashboard, belum ada di dokumen ini.

**Rekomendasi urutan kalau mau lanjut ke rancangan eksekusi:** (a) jalankan Lighthouse baseline dulu pakai tooling yang sudah ada di repo buat dapat angka nyata per halaman, (b) tulis spesifikasi cache (TTL + invalidasi) per item kandidat, (c) desain bentuk endpoint ringan buat 3 dashboard di §12. Ketiganya analisa/rancangan lanjutan, BUKAN eksekusi kode — konsisten sama instruksi "jangan eksekusi dahulu".
