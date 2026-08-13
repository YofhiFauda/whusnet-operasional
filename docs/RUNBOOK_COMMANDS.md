# Runbook: Command Artisan Whusnet Operasional

Panduan kapan dan dengan urutan apa tiap command dijalankan. Ditulis 2026-07-21,
diperbarui 2026-08-10 (langkah tambal bulan + cek scheduler di kelompok C,
kelompok C-2 go-live "pelanggan saja").

Aturan umum sebelum mulai:

- Command yang **menulis data** hampir semuanya punya mode aman (`--dry-run`
  atau default cetak-daftar). **Selalu jalankan mode aman dulu, baca hasilnya,
  baru eksekusi.**
- Command terjadwal (kelompok A) tidak perlu dijalankan manual dalam keadaan
  normal — scheduler yang memanggilnya.
- Cek daftar lengkap kapan saja: `php artisan list`.

---

## A. Terjadwal otomatis — biasanya tidak usah disentuh

Dijadwalkan di `routes/console.php`. Jalan sendiri selama `php artisan schedule:work`
(atau cron di server) hidup.

| Command | Jadwal | Isi |
|---|---|---|
| `check:countdown` | tiap 5 menit | Cek pelanggan yang menginap terlalu lama di status survey/pemasangan (SLA), kirim notifikasi Telegram |
| `billing:generate-monthly-invoices` | tanggal 1, jam 01:00 | Terbitkan tagihan BULANAN semua pelanggan aktif |

> **`fop:reset-cancelled-tasks` DIHAPUS (2026-08-13, ADHOC-34).** Dulu jalan tiap 00:01 dan
> mengembalikan Task FOP `dibatalkan` menjadi `in_progress`. Dihapus karena menghapus keputusan
> manusia tiap malam tanpa jejak di riwayat tiket, memakai status palsu (`in_progress` padahal
> tak ada teknisi yang mengerjakan), tidak memperbarui `task_date`, tidak menyinkronkan Task
> eksekusi, dan tanpa batas umur. **Pembatalan Task FOP bersifat final.** Penundaan sehari lewat
> Pending (alasan tercatat) atau ubah tanggal — termasuk tombol "Jadwalkan ke hari ini" di papan FOP.

Jalankan manual hanya kalau: scheduler mati dan tagihan bulan itu belum terbit,
atau sedang menguji. Untuk yang billing, ada mode aman:

```bash
php artisan billing:generate-monthly-invoices --dry-run   # lihat yang akan dibuat
php artisan billing:generate-monthly-invoices             # eksekusi

php artisan billing:generate-monthly-invoices --period=2026-07 --dry-run   # bulan lampau
php artisan billing:generate-monthly-invoices --period=2026-07
```

Tanpa `--period`, yang diterbitkan adalah **bulan berjalan**. Bulan yang sudah
lewat (scheduler mati saat tanggal 1, atau data baru diimpor pertengahan bulan)
hanya bisa ditambal dengan `--period=YYYY-MM` eksplisit. Format selain `YYYY-MM`
ditolak.

Jatuh tempo selalu tanggal 10 periodenya — bukan "tanggal jalan + 10 hari" —
jadi menambal bulan lampau tetap menghasilkan tanggal tempo yang benar
(dan otomatis lewat tempo alias tunggakan).

Aman diulang: pelanggan yang sudah punya tagihan langganan di periode itu
dilewati (lihat `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md`).

---

## B. Setup & perawatan sistem

### `rbac:generate-permissions`

Membangun ulang daftar permission dari tabel `features` × `actions`.

Jalankan setelah: menambah fitur/aksi baru di master RBAC. Tidak merusak data
permission yang sudah ada.

```bash
php artisan rbac:generate-permissions
```

### `find-policy`

Alat bantu debugging saja — mencari policy yang terdaftar. Tidak menulis apa pun.

---

## C. Migrasi data legacy — **urutannya wajib**

Ini rangkaian, bukan command tunggal. Jalankan berurutan, satu cabang selesai
dulu sebelum cabang berikutnya.

### Langkah 1 — Impor utama

```bash
php artisan app:import-legacy-sql jetis_db_aplikasi_jetis.sql --branch-code=C --branch-name=Jetis
php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman
```

Mengimpor pelanggan, layanan, data teknis, tagihan, dan pembayaran. `--branch-code`
wajib diisi berbeda per cabang — ID legacy (`PE`/`RQ`/`IDBIAYA`) dimulai dari 1 di
tiap sistem lama, jadi tanpa pemisahan cabang keduanya bertabrakan.

Tanpa opsi `--branch-*`, command akan bertanya interaktif.

Ada opsi `--without-billing` untuk mengimpor pelanggan/layanan/data teknis **tanpa**
tagihan & pembayaran legacy. Dipakai hanya pada skenario go-live "pelanggan saja" —
lihat kelompok C-2. Untuk migrasi biasa, jangan dipakai.

### Langkah 2 — Lengkapi perangkat & pembayaran

```bash
php artisan app:backfill-legacy-device-payment jetis_db_aplikasi_jetis.sql
```

Mengisi MAC/serial/merek perangkat dan detail pembayaran asli (metode, penerima,
penyetor, biaya pasang) dari 4 tabel legacy yang tidak terbaca di langkah 1.
**Harus setelah langkah 1** — ia menambal baris yang sudah ada, bukan membuat baru.

### Langkah 3 — Status alat pelanggan putus

```bash
php artisan app:backfill-device-retrieved --dry-run
php artisan app:backfill-device-retrieved
```

Mengisi `device_retrieved_at` untuk pelanggan `terminated`, sumbernya
`STATUSTINDAKANALAT` di dump.

### Langkah 4 — Rapikan label & biaya bulanan

```bash
php artisan app:fix-legacy-billing-batch2
```

Membetulkan label `LEGACY` pada nomor tagihan/pembayaran dan memastikan biaya
bulanan rutin tidak ikut membawa biaya di luar standar (`other_fee`).

### Langkah 5 — Tambal bulan yang tidak ada di dump

**Langkah paling gampang terlewat.** Dump legacy berhenti di bulan terakhir
sistem lama. Bulan-bulan sesudahnya tidak akan pernah punya tagihan: impor tidak
membuatnya (tidak ada datanya), dan `billing:generate-monthly-invoices` terjadwal
hanya di tanggal 1 — kalau import dikerjakan tanggal 10, tanggal 1 bulan itu
sudah lewat dan tidak akan diulang.

Gejalanya di aplikasi: pelanggan aktif, jatuh tempo sudah lewat, tapi tidak ada
tagihan yang muncul sama sekali.

Cek dulu bulan terakhir yang punya tagihan:

```sql
SELECT MAX(billing_period) FROM invoices;
```

Lalu isi tiap bulan dari situ sampai bulan berjalan, satu per satu:

```bash
php artisan billing:generate-monthly-invoices --period=2026-07 --dry-run
php artisan billing:generate-monthly-invoices --period=2026-07
php artisan billing:generate-monthly-invoices --period=2026-08 --dry-run
php artisan billing:generate-monthly-invoices --period=2026-08
```

Tanpa `--period`, command memakai bulan berjalan — bulan yang sudah lewat tidak
akan ikut tertambal. Aman diulang: pelanggan yang sudah punya tagihan langganan
di periode itu dilewati.

Perhatikan: tagihan periode lampau langsung jatuh tempo di masa lalu (tanggal 10
periodenya), jadi begitu terbit ia otomatis terhitung tunggakan. Itu memang
benar secara bisnis, tapi pastikan bagian penagihan tahu sebelum dijalankan.

### Langkah 6 — Periksa hasil

```bash
php artisan billing:audit-duplicate-invoices
php artisan persons:verify-backfill
php artisan billing:reconcile-invoice-status
```

Ketiganya read-only. Lihat kelompok D.

### Langkah 7 — Pastikan scheduler hidup

Impor yang benar tidak ada gunanya kalau tagihan bulan depan tidak terbit.
Sebelum menutup pekerjaan migrasi, pastikan proses penjadwal benar-benar jalan:

```bash
docker compose ps scheduler          # harus healthy
docker logs whusnet-scheduler --tail 20
```

Container `scheduler` yang mati/`unhealthy` adalah penyebab paling sering
tagihan bulanan tidak terbit — lihat "Tagihan bulanan tidak terbit" di bawah.

---

## C-2. Go-live "pelanggan saja" — tanpa riwayat billing legacy

**Alternatif kelompok C, bukan tambahan.** Pilih salah satu, jangan dua-duanya.

### Kapan dipakai

Kalau yang dibutuhkan dari sistem lama hanya **master data pelanggan**, tagihan
bulan berjalan diselesaikan manual di luar sistem, dan sistem baru mulai menagih
bersih dari bulan berikutnya.

Alasannya struktural, bukan selera. Hasil telusur dump jetis (2026-08-10):

- `biaya_tagihan` (2149 baris) adalah **kontrak biaya per pemasangan** — kolomnya
  `BIAYAPASANG`/`BIAYABULANAN`/`TOTALBIAYA`, **tanpa kolom periode dan tanpa status
  bayar**. Bukan tagihan per bulan.
- Satu-satunya jejak per bulan, `apikeuangan_buktitransaksitagihan` (2170 baris,
  kolom `BULANTAGIHAN`), isinya 9–127 baris per bulan untuk ~1900 pelanggan.
- Tabel arsip/jurnal yang mungkin memuat sisanya semuanya kosong:
  `apikeuangan_buktitransaksiterkumpul`, `jurnalharian`, `detail_jurnal_harian`,
  `penagihan`, `transaksi`, `tglpenagihan`, `setting_billing`.

Akibatnya riwayat yang bisa diimpor hanya menutup **~4,7%** bulan yang benar-benar
dilalui pelanggan (1737 invoice vs 37.081 pelanggan-bulan; 1631 dari 1683 pelanggan
cuma punya 1 invoice legacy). Impor ulang tidak akan memperbaikinya — datanya
memang tidak ada di dump. Riwayat sebolong itu lebih menyesatkan daripada kosong,
karena terbaca sebagai tunggakan.

**Jangan** menambalnya dengan generate retroaktif sejak tanggal aktivasi: itu
menerbitkan puluhan ribu tagihan yang semuanya langsung berstatus tunggakan,
padahal mayoritas sudah dibayar tunai bertahun lalu. Itu memalsukan piutang.

### Langkah

**1. Backup — wajib.** Reset ini membuang 1737 invoice **dan 1726 payment**
legacy. Riwayat tagihannya tipis, tapi payment adalah catatan uang yang benar-benar
diterima.

```bash
docker exec whusnet-db mysqldump -uroot -proot whusnet_operasional > backup-sebelum-reset-$(date +%F).sql
```

**2. Reset & fondasi**

```bash
php artisan migrate:fresh --seed
php artisan rbac:generate-permissions
```

**3. Impor pelanggan saja**

```bash
php artisan app:import-legacy-sql jetis_db_aplikasi_jetis.sql --branch-code=C --branch-name=Jetis --without-billing
php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --without-billing
```

`--without-billing` mengosongkan sheet `invoices` & `payments`, tapi **tetap**
membawa `customer_services.monthly_price` (dari `BIAYABULANAN`) dan
`activation_date` (dari `prosedure_permintaan_wifi`/`riwayat_pelanggan`). Keduanya
dari tabel legacy yang berbeda dan wajib ada supaya penagihan bulan depan jalan —
dijaga `tests/Feature/ImportLegacyTanpaBillingTest.php`.

**4. Lengkapi data lapangan**

```bash
php artisan app:backfill-legacy-device-payment jetis_db_aplikasi_jetis.sql
php artisan app:backfill-device-retrieved --dry-run
php artisan app:backfill-device-retrieved
```

`app:fix-legacy-billing-batch2` **dilewati** — tugasnya merapikan label `LEGACY`
pada tagihan/pembayaran, yang di skenario ini tidak ada.

**5. Verifikasi**

```sql
SELECT COUNT(*) FROM invoices;    -- harus 0
SELECT COUNT(*) FROM payments;    -- harus 0
SELECT COUNT(*) FROM customers WHERE status = 'active';
SELECT COUNT(*) FROM customer_services WHERE monthly_price > 0;
SELECT COUNT(*) FROM customer_services WHERE activation_date >= '<bulan-mulai-menagih>-01';
```

Dua baris terakhir yang menentukan berhasil-tidaknya:

- `monthly_price = 0` ⇒ pelanggan **dilewati diam-diam** oleh generator. Aktif,
  tapi tidak pernah ditagih, tanpa error. Jumlahnya harus ≈ jumlah pelanggan aktif.
- `activation_date` yang jatuh di bulan mulai menagih ⇒ ikut dilewati juga (bulan
  aktivasi ditagih lewat invoice AWAL dari verifikasi). Benar untuk pelanggan baru,
  **salah** kalau ada pelanggan lama yang tanggal aktivasinya keliru terisi.

**6. Jangan tambal bulan lampau.** Ini justru kebalikan dari kelompok C langkah 5:
bulan berjalan diselesaikan manual, jadi biarkan kosong. Cukup lihat rencananya:

```bash
php artisan billing:generate-monthly-invoices --period=<bulan-depan> --dry-run
```

Scheduler yang menerbitkannya sendiri tanggal 1 jam 01:00 — pastikan container
`scheduler` healthy (kelompok C langkah 7).

**7. Pagi tanggal 1, pastikan terbit**

```bash
php artisan billing:audit-duplicate-invoices --period=<bulan-depan>
```

### Konsekuensi yang harus disepakati sebelum jalan

- Sistem tidak punya tunggakan sama sekali di hari go-live. Pelanggan yang memang
  menunggak dari sistem lama harus dicatat manual — tidak akan muncul sendiri.
- Laporan keuangan & rekap kolektor tidak punya data pembanding periode sebelumnya.
- Riwayat pembayaran per pelanggan mulai dari nol; yang lama hanya ada di file
  backup langkah 1.

---

## D. Perawatan billing

### `billing:audit-duplicate-invoices` — read-only, aman kapan saja

```bash
php artisan billing:audit-duplicate-invoices                # semua periode
php artisan billing:audit-duplicate-invoices --period=2026-07
php artisan billing:audit-duplicate-invoices --strict       # exit 1 kalau ada temuan
```

Melaporkan pelanggan yang punya lebih dari satu tagihan langganan pada periode
sama. Kolom **Sumber**:

- `legacy` — warisan migrasi, sudah diketahui, bukan masalah baru
- `PERLU CEK` — **ada yang lolos dari pengaman**. Ini yang harus diselidiki.

Tidak pernah menulis apa pun. Cocok dijalankan rutin (mingguan) atau setiap
selesai migrasi.

### `billing:backfill-activation-date` — betulkan tanggal aktivasi lama

```bash
php artisan billing:backfill-activation-date              # cuma cetak daftar
php artisan billing:backfill-activation-date --limit=10 --force   # coba sebagian
php artisan billing:backfill-activation-date --force      # eksekusi penuh
```

Membetulkan `customer_services.activation_date` yang masih berisi tanggal
**daftar**, bukan tanggal layanan menyala. Sumber tanggal berurutan: invoice
`AWAL` → catatan pemasangan → kalau kosong dua-duanya, dilaporkan `REVIEW MANUAL`
(tidak ditebak).

Baris hasil migrasi legacy dilewati — tanggalnya sudah benar dari sistem lama.

Setiap perubahan masuk audit log (`action = backfill_activation_date`) lengkap
dengan nilai lama, nilai baru, dan sumbernya.

---

## E. Queue, worker & batas PHP

Ditulis 2026-08-11, setelah pembacaan kwitansi massal ternyata berjalan inline di dalam request.

### Driver queue harus SERAGAM di semua container

Pernah terbelah: `app` memakai `sync` (dari `docker-compose.yml`), sementara `horizon` & `scheduler` memakai `redis`. Akibatnya job yang lahir dari klik admin dijalankan inline di dalam request — unggah 100 kwitansi = 100 pembacaan berurutan, hampir pasti timeout — sedangkan job yang lahir dari cron diproses Horizon. Dua perilaku berbeda untuk kode yang sama.

```bash
# harus sama untuk ketiganya
docker exec whusnet-app php artisan config:show queue.default
docker exec whusnet-horizon php artisan config:show queue.default
docker exec whusnet-scheduler php artisan config:show queue.default
```

> ⚠️ **JANGAN menduplikasi env runtime di `docker-compose.yml`** — berlaku untuk `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, dan apa pun yang `phpunit.xml` timpa saat testing. Env di `environment:` compose menjadi env level-OS yang mengisi `$_SERVER`, dan `env()` Laravel membaca `$_SERVER` **lebih dulu** daripada `putenv()` — sehingga `force="true"` di `phpunit.xml` selalu kalah. Sudah menggigit dua kali:
>
> | Env | Akibatnya |
> |---|---|
> | `QUEUE_CONNECTION: redis` | test mendorong job ke Redis **asli** → 576 job gagal, bertambah tiap run |
> | `CACHE_STORE: file` | seluruh test berbagi cache file **persisten lintas run**; `user.1.permissions` yang ter-cache `["*"]` membuat 9 test RBAC merah tanpa satu baris logika permission pun yang salah |
>
> Lapis kedua ada di `Tests\TestCase::setUp()` yang memaksa `cache.default`/`session.driver` ke `array`. Lapis itu **bukan pengganti** aturan di atas — isolasi test tidak boleh bergantung pada seluk-beluk presedensi env, tapi env runtime juga tidak boleh bocor ke test.

> ⚠️ **JANGAN menaruh `QUEUE_CONNECTION` di `docker-compose.yml`.** Nilainya sudah ada di `.env`. Menaruhnya di compose membuatnya jadi env var level-OS yang mengisi `$_SERVER`, dan `env()` Laravel membaca `$_SERVER` **lebih dulu** daripada `putenv()` — sementara `force="true"` di `phpunit.xml` hanya menulis `putenv()`. Hasilnya `php artisan test` berjalan dengan `queue=redis` dan mendorong job ke **Redis asli**; Horizon lalu menjalankan job berisi model yang di-serialize dengan koneksi `sqlite` milik test. Terjadi 2026-08-11: **576 job gagal** `SQLiteDatabaseDoesNotExistException`, bertambah ±188 tiap kali suite dijalankan. Aturan yang sama sudah berlaku untuk `DB_*` — lihat catatan di `docker-compose.yml`.

Cek cepat kalau curiga bocor lagi:

```bash
docker exec whusnet-app php artisan test --compact
docker exec whusnet-app php artisan queue:failed   # harus tetap kosong sesudahnya
```

Pakai `composer test` (menjalankan `config:clear` dulu), bukan `php artisan test` langsung.

### Dua antrean, sengaja dipisah

| Antrean | Isi | Supervisor | Timeout |
|---|---|---|---|
| `default` | event broadcast (dashboard FOP, task teknisi, status tagihan), notifikasi | `supervisor-1` | 60 dtk |
| `kwitansi` | `MatchPaymentReceipt` — pdftotext, raster per halaman | `supervisor-kwitansi` | 300 dtk |

Kalau digabung, unggah bulk 100 kwitansi menaruh 100 job lambat di depan barisan dan **layar realtime berhenti bergerak** sampai tumpukan habis — tanpa error, tanpa petunjuk.

Rantai angka yang harus dijaga berjenjang: `MatchPaymentReceipt::$timeout` (240) ≤ timeout supervisor (300) < `REDIS_QUEUE_RETRY_AFTER` (360). Kalau `retry_after` lebih kecil dari timeout job, job yang **masih berjalan** dianggap mati dan diambil worker kedua — satu berkas dibaca dua kali. Dijaga `tests/Unit/MatchPaymentReceiptQueueTest.php`.

### Worker TIDAK memuat kode baru sampai di-restart

Worker antrean adalah proses yang hidup terus — PHP memuat kelas sekali saat worker start, lalu memakainya untuk semua job berikutnya. **Mengubah kode tidak berpengaruh sampai worker-nya diganti.**

Gejalanya menyesatkan: jalankan lewat `tinker` atau test, hasilnya benar; jalankan lewat aplikasi sungguhan, hasilnya perilaku LAMA — seolah perbaikannya tidak berfungsi. Terjadi 2026-08-11 pada pemecahan lembar kwitansi: unggahan lewat UI masih menghasilkan satu berkas untuk 100 kwitansi karena Horizon sudah hidup 7 jam, sejak sebelum pemecahnya ada.

```bash
docker compose restart horizon        # cara paling langsung
# atau, tanpa menjatuhkan container:
docker exec whusnet-app php artisan horizon:terminate
```

> **Setiap kali menyentuh kode yang dipakai job** (`app/Jobs/`, service yang dipanggil dari job, model yang di-serialize) — restart Horizon sebelum menguji lewat aplikasi. Kalau tidak, yang diuji adalah kode kemarin.

### Kegagalan job jadi SENYAP

Dengan `sync`, job gagal muncul sebagai error di request. Dengan `redis`, ia mendarat di `failed_jobs` tanpa ada yang tahu.

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

### Batas PHP yang memotong diam-diam

`docker/php/local.ini`:

| Setelan | Nilai | Kenapa |
|---|---|---|
| `max_file_uploads` | 100 | Default PHP **20**, sementara unggah kwitansi menerima 100. PHP memotong sisanya **tanpa error** — admin mengira 100 berkas masuk, padahal 20. |
| `max_input_vars` | 5000 | Batch kolektor 200 baris = 800 variabel. Sekarang aman karena dikirim JSON, tapi pemotongannya juga senyap kalau suatu saat jadi form biasa. |

`PaymentReceiptController::store()` juga membandingkan `files_count` (jumlah yang dipilih browser) dengan jumlah yang tiba, lalu **menolak seluruh unggahan** kalau lebih sedikit. Batas `local.ini` hidup di lapisan yang tidak ikut ter-deploy bersama kode; pemeriksaan ini yang membuat pemotongan bersuara di mana pun kodenya dijalankan.

> `docker compose restart app` **gagal** setelah `local.ini` ditulis ulang — bind-mount satu berkas memegang inode lama. Pakai `docker compose up -d --force-recreate app`.

### Healthcheck worker

`pgrep` (procps) dan `nc` (netcat-openbsd) dipasang di `Dockerfile` khusus untuk HEALTHCHECK horizon/scheduler/reverb. Tanpa keduanya healthcheck gagal dengan `pgrep: not found` / `nc: not found` — container ditandai `unhealthy` padahal prosesnya normal, sehingga kegagalan scheduler yang **sebenarnya** (tagihan bulanan tak terbit) tidak terlihat. Itu persis yang menyembunyikan lubang tagihan Juli–Agustus 2026.

---

## Urutan untuk skenario yang sering terjadi

### Pasang sistem dari nol

```bash
php artisan migrate --seed
php artisan rbac:generate-permissions
```

### Impor data cabang baru dari sistem lama

```bash
php artisan app:import-legacy-sql <file>.sql --branch-code=X --branch-name=Nama
php artisan app:backfill-legacy-device-payment <file>.sql
php artisan app:backfill-device-retrieved --dry-run
php artisan app:backfill-device-retrieved
php artisan app:fix-legacy-billing-batch2
php artisan billing:generate-monthly-invoices --period=YYYY-MM --dry-run   # tiap bulan
php artisan billing:generate-monthly-invoices --period=YYYY-MM            # setelah dump
php artisan billing:audit-duplicate-invoices        # periksa hasil
php artisan persons:verify-backfill
php artisan billing:reconcile-invoice-status
docker compose ps scheduler                         # pastikan penjadwal hidup
```

### Go-live "pelanggan saja" (tanpa riwayat billing legacy)

```bash
docker exec whusnet-db mysqldump -uroot -proot whusnet_operasional > backup-sebelum-reset-$(date +%F).sql
php artisan migrate:fresh --seed
php artisan rbac:generate-permissions
php artisan app:import-legacy-sql <file>.sql --branch-code=X --branch-name=Nama --without-billing
php artisan app:backfill-legacy-device-payment <file>.sql
php artisan app:backfill-device-retrieved --dry-run
php artisan app:backfill-device-retrieved
# TIDAK ada generate untuk bulan lampau — scheduler yang terbitkan tanggal 1
```

Lengkapnya + konsekuensinya: kelompok C-2.

### Pemeriksaan rutin bulanan

```bash
php artisan billing:audit-duplicate-invoices        # ada tagihan dobel?
```

Kalau kolom Sumber memunculkan `PERLU CEK`, berhenti dan telusuri — artinya ada
jalur pembuatan invoice yang lolos dari dua lapis pengaman.

### Tagihan bulanan tidak terbit (scheduler mati)

Periksa dulu sampai bulan mana tagihan ada, dan apakah penjadwalnya memang hidup:

```bash
docker compose ps scheduler
php artisan tinker --execute 'echo \App\Models\Invoice::max("billing_period");'
```

Tambal tiap bulan yang bolong, dari bulan setelah yang terakhir ada:

```bash
php artisan billing:generate-monthly-invoices --period=2026-07 --dry-run
php artisan billing:generate-monthly-invoices --period=2026-07
php artisan billing:generate-monthly-invoices --dry-run    # bulan berjalan
php artisan billing:generate-monthly-invoices
```

Menghidupkan scheduler saja **tidak** menambal bulan yang sudah lewat — jadwalnya
`monthlyOn(1)`, tanggal 1 yang terlewat tidak akan diulang.

---

## Yang TIDAK boleh dilakukan

1. **Jangan** jalankan `app:import-legacy-sql` dua kali untuk dump yang sama
   tanpa memeriksa hasil impor pertama — pengecekan duplikat di-scope per cabang,
   bukan global.
2. **Jangan** jalankan command backfill dengan `--force` sebelum membaca hasil
   mode amannya.
3. **Jangan** melewati urutan di kelompok C. Langkah 2–4 menambal data hasil
   langkah 1; dijalankan lebih dulu, tidak ada yang ditambal.
4. **Jangan** menghapus tagihan yang sudah lunas lewat jalur apa pun. Yang salah
   dibatalkan (`InvoiceStatus::BATAL`) + alasan + audit log.
5. **Jangan** mencampur kelompok C dan C-2 — pilih salah satu. Dan **jangan**
   jalankan `migrate:fresh` di kelompok C-2 sebelum backup langkah 1 benar-benar
   ada dan bisa dibaca: 1726 payment legacy hilang bersamanya.
6. **Jangan** menerbitkan tagihan retroaktif sejak tanggal aktivasi untuk menambal
   riwayat legacy yang bolong. Yang dihasilkan bukan data pulih, tapi puluhan ribu
   tunggakan palsu atas uang yang sudah dibayar. Lihat alasannya di C-2.
7. **Jangan** menganggap migrasi selesai begitu impor sukses. Belum selesai
   sebelum langkah 5–7 kelompok C dikerjakan: bulan setelah dump ditambal,
   hasilnya diaudit, dan scheduler dipastikan hidup.

---

## Referensi

- `routes/console.php` — jadwal
- `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md` — lapis pencegahan tagihan dobel
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`, `docs/IMPORT_SPEC.md` — detail migrasi
- `docs/ID_NUMBERING_RULES.md` — kenapa `--branch-code` wajib beda per cabang
