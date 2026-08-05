# Rencana: Fitur Geospasial (Maps) Whusnet Operasional

## Context

Sistem ini ISP operasional dengan pekerjaan yang **inheren geografis** — survey, instalasi, maintenance, ambil alat, semuanya terjadi di titik fisik. Tapi seluruh sistem memperlakukan lokasi sebagai **teks**: level terhalus yang bisa difilter/diagregasi adalah *desa* (`fop_tasks.village_id`), dan koordinat hanya dipakai sebagai deep-link keluar ke Google Maps (`https://www.google.com/maps/search/?api=1&query=lat,lng` di 7 view).

Akibatnya, keputusan yang seharusnya dibantu data lokasi dibuat dari hafalan operator:
- Assign teknisi/tim tidak mempertimbangkan jarak sama sekali (`FopTaskController::assignToTeam():659-744` — kriteria hanya teknisi & tanggal).
- NOC tak bisa melihat bahwa 5 tiket adalah 1 backbone cut; agregasi terhalus = `district` (`NocDashboardController.php:67-73`).
- ODP tidak eksis sebagai entitas — hanya string bebas di 5 tempat berbeda, jadi "pelanggan mana saja di ODP ini" tidak bisa dijawab tanpa `LIKE`.

Tujuan: menjadikan lokasi sebagai **data kelas satu** yang bisa dipetakan, diukur jaraknya, dan diagregasi — untuk mempercepat dispatch lapangan, mempercepat diagnosis gangguan massal, dan memperbaiki kualitas data koordinat di sumbernya.

Keputusan yang sudah diambil user:
- Provider: **Leaflet + OpenStreetMap** (gratis, tanpa API key).
- Prioritas: **peta aset jaringan**, **peta task harian FOP**, **map picker koordinat**.
- GPS teknisi: **hanya saat check-in/check-out task** (bukan live tracking).
- Tabel aset jaringan (ODP): **dibuat sekarang**, sekalian dengan fitur peta.

---

## Penilaian jujur: impact, risiko, efektivitas

Tabel ini menjawab pertanyaan "apakah benar berfungsi dan efektif" per fitur. Peringkat efektivitas **sepenuhnya bergantung pada kelengkapan koordinat** — itu sebabnya Fase 0 tidak bisa dilewati.

| Fitur | Impact nyata | Risiko | Efektif? |
|---|---|---|---|
| **Map picker koordinat** (registrasi, survey, POP, ODP) | Memperbaiki data di sumbernya. Tanpa ini semua fitur peta lain menampilkan peta bolong. Teknisi di lokasi tekan "Ambil lokasi saya" → akurasi ±10m, jauh lebih baik dari ketik manual | Rendah. `navigator.geolocation` butuh HTTPS (sudah, via Cloudflare Tunnel). Akurasi GPS HP di dalam rumah bisa ±50m — perlu tampilkan nilai akurasi & minta konfirmasi | **Ya, tertinggi.** Satu-satunya fitur yang efektif sejak hari pertama karena tidak bergantung data lama |
| **Peta aset jaringan** (POP → Mini POP → ODP → pelanggan) | Menjawab "ODP mana yang penuh", "pelanggan mana di ODP ini", "ODP terdekat dari calon pelanggan" — sekarang mustahil. Mengubah `nearest_odp` dari tebakan teknisi jadi hasil hitung | **Tinggi.** Manfaatnya nol sampai koordinat ODP terisi, dan itu **kerja lapangan manual**: ratusan ODP harus dikunjungi & dipin. Kode selesai dalam 2 sprint; pengisian data bisa berbulan-bulan | **Ya, tapi tertunda.** Efektif setelah pengisian ODP per-cabang selesai. Jangan janjikan hasil di sprint yang sama |
| **Peta task harian FOP** | Dispatcher lihat 20 task hari ini di peta per tim → sadar 2 task bersebelahan dipegang tim berbeda. Menghemat perjalanan nyata di area padat (Jetis) | Sedang. Bisa jadi "peta cantik yang tak dipakai" kalau tak menempel pada keputusan yang sudah ada. **Wajib** dipasang di alur `assignToTeam`/`switchTeam` yang sudah ada, bukan halaman peta terpisah | **Ya, bersyarat** — hanya kalau tampil di dalam papan `/fop-tasks`, bukan menu baru |
| **GPS check-in/out** | Bukti kehadiran di lokasi. Bonus besar: mengoreksi koordinat pelanggan otomatis — kalau teknisi check-in 300m dari koordinat tercatat, tandai koordinat perlu ditinjau | Sedang. Sensitif ketenagakerjaan → harus **1 titik saja per aksi**, bukan pelacakan berkala, dan transparan ke teknisi. HP tanpa izin lokasi tak boleh memblokir "Mulai Task" | **Ya**, dengan syarat tidak memblokir alur kerja saat GPS gagal |
| **Peta insiden NOC** (tidak dipilih user) | Deteksi gangguan massal dari kerapatan titik | Rendah | Ditunda. Efektif setelah koordinat lengkap; lihat "Di luar scope" |

**Risiko lintas fitur yang harus ditangani eksplisit:**

1. **Koordinat redundan di 2 tabel** — `customers.latitude/longitude` DAN `customer_addresses.latitude/longitude`, ditulis dua kali oleh importer (`CustomerController.php:2252-2253` dan `:2270-2271`). View berbeda baca sumber berbeda: `customers/show.blade.php:289` pakai fallback berantai, `surveys/report.blade.php:183` pakai `customers` saja, `tasks/own.blade.php:143` pakai `customer_addresses` saja. **Peta yang membaca sumber berbeda dari form yang menulis = pin pindah sendiri.** Harus disatukan sebelum apa pun digambar.
2. **Data legacy rusak format** — `normalizeCoordinate()` (`CustomerController.php:3004-3032`) menyisipkan titik desimal dengan heuristik ("negatif → `-7.x`, diawali 1 → `11x.x`"). Heuristik itu duplikat di `MigrateLegacyDataCommand.php:1700-1710`. Artinya sebagian koordinat lama adalah hasil *tebakan*, bukan pengukuran. Peta akan memperlihatkan titik di tengah sawah — dan itu justru **berguna** sebagai alat pembersih data, asalkan diberi label.
3. **Kebocoran lintas POP** — endpoint yang men-serve titik peta mengembalikan banyak baris sekaligus, jalur paling gampang bocor. Wajib `applyUserScope()` / `EffectiveAccessService::getAllowedPopIds()`, dan `hasAllPopAccess()` untuk membedakan "akses penuh" dari "scope belum di-setup" (array kosong ambigu — lihat CLAUDE.md).
4. **Tanpa rate limit sama sekali** di aplikasi ini (grep `throttle` → hanya `config/auth.php` password reset). Endpoint GeoJSON yang mengembalikan ribuan titik tanpa throttle = alat scraping basis pelanggan. Harus `throttle` + batas bbox/limit.
5. **Tile OSM punya usage policy** — dilarang untuk aplikasi produksi volume tinggi tanpa caching. Untuk ~10 user internal masih dalam batas wajar, tapi rencanakan tile proxy/cache kalau user bertambah.
6. **Alpine dari CDN** (`layouts/app.blade.php:22`) — tak bisa `Alpine.plugin()` dari bundle Vite. Komponen peta harus pola vanilla JS + `@push('scripts')`, seperti `customers/index.blade.php` yang sudah ada.

---

## Rencana implementasi

Ini bukan satu sprint. Usulan: **Sprint 9 — Geospasial**, dengan Fase 0 sebagai gerbang. Sprint aktif sekarang S8.10 (FOP Notification Dashboard) — jangan dicampur.

### Fase 0 — Fondasi data (gerbang, wajib lebih dulu)

Tanpa ini, semua fase berikutnya membangun di atas data yang tak diketahui kualitasnya.

**0.1 Ukur dulu.** Artisan command `geo:audit` baru (`app/Console/Commands/GeoAuditCommand.php`) yang melaporkan:
- Jumlah pelanggan dengan/tanpa koordinat, dipecah per POP.
- Ketidaksesuaian antara `customers.latitude/longitude` dan `customer_addresses.latitude/longitude`.
- Koordinat di luar bounding box wajar Indonesia/Jawa Timur (indikasi hasil heuristik yang salah).
- Jumlah `odp_code` unik dari `customers.odp_code` ∪ `customer_technical_details.odp_number` ∪ `customer_surveys.nearest_odp` — ini yang menentukan berapa ODP harus dipin di lapangan.

> Angka ini belum bisa saya ukur: DB MySQL ada di dalam docker (`Host: db`) dan tak terjangkau dari sisi Windows. **Jalankan `geo:audit` dulu; hasilnya menentukan apakah Fase 3 layak dijalankan sekarang atau ditunda.**

**0.2 Satukan sumber koordinat.** Pilih `customer_addresses` sebagai satu-satunya sumber kebenaran (di situlah `CustomerController::store()/update()` sudah menulis, `:587-588`, `:876-877`).
- Migrasi backfill: `customer_addresses` diisi dari `customers` untuk baris yang kosong.
- Accessor tunggal di `app/Models/Customer.php` — `coordinates(): ?array` dan `mapsUrl(): ?string`, meniru pola `Ticket::customerMapsUrl()` yang sudah ada (`app/Models/Ticket.php:131-138`).
- Ganti **semua** 7 pembacaan ad-hoc di view dengan accessor itu. `customers.latitude/longitude` jadi kolom legacy read-only (jangan di-drop dulu — importer & command migrasi legacy masih menulisnya).
- Seragamkan format URL Maps: `verifications/admin.blade.php:162` masih pakai `maps.google.com/?q=`, beda dari 6 view lain.

**0.3 Index.** Composite index `(latitude, longitude)` di `customer_addresses`, plus `pops`. Cukup untuk bbox query pada skala 20.000 baris — belum perlu ekstensi spasial.

*Test:* `GeoAuditCommandTest`, `CustomerCoordinateSourceTest` (accessor konsisten antar jalur registrasi/import/legacy).

### Fase 1 — Tabel aset jaringan (ODP/ODC)

Mengubah ODP dari string jadi entitas. Ini bagian paling berdampak jangka panjang **dan paling berisiko** karena menyentuh 5 modul.

- Migrasi `network_assets`: `id`, `pop_id` (FK), `parent_id` (self-ref, mendukung ODC → ODP), `type` (enum `odc`/`odp`), `code` (unik global, ikuti pola `distributions` yang sudah dibuat unik global di migrasi `2026_06_19_140908`), `name`, `latitude`/`longitude` `decimal(10,7)` nullable, `total_ports`, `village_id` (FK), `status`, `notes`.
- Enum baru `app/Enums/NetworkAssetType.php` — jangan string literal (aturan CLAUDE.md).
- Model `NetworkAsset` + `scopeForUser()` meniru `Pop::scopeForUser()` (`app/Models/Pop.php:121-145`).
- Kolom FK **tambahan, bukan pengganti**: `customers.network_asset_id` nullable. Kolom string `odp_code`/`odp_number`/`nearest_odp` **tetap ada** selama masa transisi — dua sumber sementara lebih aman dari migrasi paksa yang menghapus data legacy yang tak bisa dicocokkan.
- Command `geo:seed-odp-from-legacy` — bikin baris `network_assets` dari `odp_code` distinct, **tanpa koordinat** (koordinat diisi lapangan). Laporkan berapa yang tak bisa dicocokkan.
- Master data CRUD: controller + view di `app/Http/Controllers/Master/NetworkAssetController.php` + `resources/views/master/network-asset/`, ikuti struktur `Master/PopController.php` & `resources/views/master/pop/`.
- Permission dari `features` × `actions` via `PermissionGeneratorService` — **jangan hardcode**. Feature baru `network_assets`.
- Kapasitas port: hitung terpakai dari `COUNT(customers.network_asset_id)`, jangan simpan counter (menghindari dua sumber kebenaran).

*Test:* `NetworkAssetCrudTest`, `NetworkAssetScopeTest` (kebocoran lintas POP), `SeedOdpFromLegacyTest`.

### Fase 2 — Komponen peta reusable + map picker

**2.1 Komponen peta.** Leaflet dari npm (`leaflet ^1.9`), **bukan** CDN — supaya CSS-nya masuk pipeline Tailwind v4 (`resources/css/app.css` sudah `@source` scan file JS, `:7-10`).
- Jangan import di `resources/js/app.js` — itu ter-load di **semua** halaman (bundle sekarang 115KB; Leaflet + CSS menambah ~150KB untuk halaman yang tak butuh peta). Tambah entry point kedua di `vite.config.js:8`: `resources/js/map.js`, dipanggil per halaman lewat `@vite(['resources/js/map.js'])` di dalam `@push('scripts')`.
- Blade component `<x-map>` di `resources/views/components/map.blade.php` — props: `points`, `center`, `zoom`, `mode` (`view`/`picker`), `clustered`.
- **Dark mode wajib**: sistem punya token light/dark (`resources/css/app.css:12-201`) dan toggle (`layouts/app.blade.php:11-19`). Peta harus ikut — filter CSS grayscale/invert pada tile layer saat `.dark`, atau tile layer terpisah.

**2.2 Map picker.** Pasang di 4 form yang sudah punya input lat/lng manual:
- `resources/views/customers/create.blade.php:192-197` dan `edit.blade.php:224-229`
- `resources/views/master/pop/create.blade.php:194-212` dan `edit.blade.php:209-227`
- Baru: form `network-asset` (Fase 1)
- Baru: `resources/views/surveys/report.blade.php` — sekarang koordinat **read-only** (`:183-187`), padahal teknisi survey adalah orang yang benar-benar berdiri di lokasi. Ini titik pengumpulan data terbaik yang sekarang terbuang.

Pola picker: peta + marker draggable + tombol "Ambil lokasi saya" (`navigator.geolocation.getCurrentPosition`) + tampilkan akurasi dalam meter + input teks lat/lng **tetap ada** dan tersinkron dua arah (jangan hapus — fallback saat peta gagal load / offline). Validasi server yang sudah ada tak berubah (`CustomerRegistrationRequest.php:35-36`).

*Test:* Feature test bahwa lat/lng dari picker tersimpan lewat jalur yang sama dengan input manual. Perilaku peta itu sendiri tak bisa di-PHPUnit — verifikasi manual (lihat bagian Verifikasi).

### Fase 3 — Peta aset jaringan

- Endpoint GeoJSON `GET /api/geo/network` — `NetworkAsset` + `Pop` + pelanggan, di-scope `EffectiveAccessService`, wajib parameter bbox + `throttle`.
- Halaman `/master/network-asset/map`: layer POP (marker besar) → Mini POP → ODP (warna per okupansi port) → pelanggan (cluster). Garis penghubung parent-child sebagai polyline sederhana — **bukan** rute kabel sebenarnya, dan harus diberi label demikian supaya tak disalahartikan sebagai as-built.
- Fitur "ODP terdekat" di form survey: hitung jarak haversine di PHP dari kandidat hasil bbox query (jangan pakai ekstensi spasial — DB default sqlite di dev, MySQL di prod; haversine di PHP portabel dan cukup untuk ≤ ratusan kandidat).
- Permission halaman sendiri: `network_assets.map.view` (aturan CLAUDE.md: jangan numpang permission generik).

### Fase 4 — Peta task harian FOP

**Tempelkan pada papan yang sudah ada, jangan bikin menu baru.**
- Panel peta yang bisa di-collapse di dalam `resources/views/fop_tasks/index.blade.php` (1240 baris, sudah punya 4 `x-data` dan sudah bangun `maps_url` di JS `:1211`).
- Titik diwarnai per **tim** (`FopTaskTeam`, dibangun `FopTaskTeamService::rebuildTeamsForDate()`), difilter tanggal yang sama dengan papan.
- Klik marker → buka modal task yang sudah ada, jangan buat modal baru.
- Di dalam `assignToTeam` / `switchTeam` (`FopTaskController.php:659-744`, `FopDashboardController.php:265-352`): tampilkan **jarak ke task terdekat tim tujuan** sebagai informasi. **Jangan** jadikan aturan penolakan — guard yang ada (tanggal, roster, konflik in_progress) sudah cukup, dan jarak bukan alasan sah menolak assign (bisa ada alasan lain: keahlian, alat, permintaan pelanggan).

### Fase 5 — GPS check-in/out

- Migrasi: `tasks.start_latitude/start_longitude/start_accuracy_m` dan `complete_*` yang sama, semua nullable.
- `TaskService::start()` (`app/Services/TaskService.php:124-164`) dan `complete()` (`:170-231`) menerima koordinat opsional. **Tidak wajib** — task tetap bisa dimulai tanpa izin lokasi. Ini keputusan produk, bukan kelalaian: memblokir "Mulai Task" karena GPS mati akan menghentikan pekerjaan lapangan.
- UI: `resources/views/tasks/own.blade.php` minta lokasi saat tombol Mulai/Selesai ditekan, tampilkan status ("Lokasi terekam ±12m" / "Lokasi tak tersedia — task tetap bisa dimulai").
- Nilai turunan paling berguna: kalau check-in > 200m dari koordinat pelanggan tercatat, tandai `customer_addresses` perlu ditinjau + tawarkan koreksi ke posisi teknisi. **Ini mesin pembersih data koordinat yang berjalan sendiri** — mungkin manfaat terbesar dari seluruh rencana ini.
- Transparansi: teks jelas di UI teknisi bahwa 1 titik direkam saat mulai & selesai, tidak ada pelacakan di antaranya.

*Test:* `TaskGpsCheckInTest` — task tanpa koordinat tetap bisa start/complete; koordinat tersimpan saat dikirim; deteksi selisih jarak memicu penandaan.

---

## File utama yang disentuh

| Fase | File |
|---|---|
| 0 | `app/Console/Commands/GeoAuditCommand.php` (baru), `app/Models/Customer.php`, migrasi backfill + index, 7 view pembaca koordinat (`customers/show`, `customers/index`, `tasks/own`, `tasks/show`, `tasks/partials/own-card`, `surveys/report`, `verifications/admin`) |
| 1 | migrasi `network_assets` + `customers.network_asset_id`, `app/Enums/NetworkAssetType.php`, `app/Models/NetworkAsset.php`, `app/Http/Controllers/Master/NetworkAssetController.php`, `resources/views/master/network-asset/*`, `database/seeders/` (feature+permission), `app/Console/Commands/SeedOdpFromLegacyCommand.php` |
| 2 | `package.json`, `vite.config.js`, `resources/js/map.js` (baru), `resources/views/components/map.blade.php` (baru), `customers/create+edit`, `master/pop/create+edit`, `surveys/report` |
| 3 | `routes/web.php`, `app/Http/Controllers/GeoController.php` (baru), `resources/views/master/network-asset/map.blade.php` |
| 4 | `resources/views/fop_tasks/index.blade.php`, `app/Http/Controllers/FopTaskController.php` |
| 5 | migrasi kolom GPS di `tasks`, `app/Services/TaskService.php`, `app/Http/Controllers/TaskController.php`, `resources/views/tasks/own.blade.php` |

Yang **dipakai ulang**, bukan dibuat baru: `EffectiveAccessService::getAllowedPopIds()`/`hasAllPopAccess()`, `Ticket::customerMapsUrl()` sebagai pola accessor, `Pop::scopeForUser()` sebagai pola scope, `PermissionGeneratorService` untuk permission, `FopTaskTeamService` untuk pewarnaan tim, pola `@push('scripts')` yang sudah dipakai 36 view.

## Di luar scope rencana ini

- Live tracking teknisi (ditolak user — risiko ketenagakerjaan).
- Peta insiden NOC — tunggu koordinat lengkap.
- Rute optimal / TSP untuk urutan kunjungan — butuh routing engine (OSRM) dan data jalan desa yang akurat; belum layak.
- Geocoding otomatis alamat → koordinat: akurasi Nominatim untuk alamat desa Indonesia ("Dukuh Karanglo, RT 02/RW 01") mendekati nol. **Jangan dikerjakan** — pin manual oleh teknisi lebih akurat dan lebih murah.
- PWA / peta offline.
- Rute kabel as-built (butuh survey OTDR, bukan fitur software).

## Verifikasi

1. **Fase 0 gerbang:** `php artisan geo:audit` — baca angka kelengkapan per POP. Kalau < 50% pelanggan berkoordinat di cabang mana pun, **hentikan Fase 3/4** untuk cabang itu dan dahulukan pengisian lewat map picker (Fase 2).
2. `php artisan test --compact --filter=Geo` lalu `--filter=NetworkAsset`, `--filter=TaskGps`.
3. Scope: login sebagai user `pop_admin` satu cabang, panggil `/api/geo/network` → pastikan **nol** titik dari POP lain. Test otomatis wajib, bukan hanya manual.
4. Manual di browser (`composer dev`): buka form registrasi pelanggan → pin di peta → simpan → buka Detail Pelanggan, pastikan koordinat sama; lalu buka halaman Task teknisi untuk pelanggan itu, pastikan koordinat yang tampil **identik** (ini yang membuktikan Fase 0.2 berhasil).
5. Dark mode: toggle tema di setiap halaman berpeta, pastikan tile & marker terbaca.
6. Mobile: buka `/tasks/own` di HP lewat tunnel HTTPS, tekan Mulai Task tanpa memberi izin lokasi → task **harus tetap bisa dimulai**.
7. Regresi: `php artisan test --compact` penuh setelah Fase 0.2 (menyentuh 7 view + accessor yang dipakai lintas modul).
8. `vendor/bin/pint` sebelum commit.

## Catatan proses

- Buat entri **Sprint 9 — Geospasial** di `docs/TASKS.md`; jangan campur ke S8.10 yang sedang jalan.
- Dokumentasi modul baru: `docs/geospasial/` mengikuti struktur seragam (`README.md`, `business-logic.md`, `database-schema.md`, `flowchart.md`, `user-flow.md`). Tambahkan bagian ODP di `docs/master/`.
- Fase 1 mengubah struktur data inti (ODP jadi entitas) — sesuai CLAUDE.md poin "berhenti & tanya", konfirmasi ulang sebelum migrasi dijalankan di data nyata.



