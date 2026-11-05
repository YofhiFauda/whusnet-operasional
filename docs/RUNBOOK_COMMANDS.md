# Runbook: Command Artisan Whusnet Operasional

Panduan kapan dan dengan urutan apa tiap command dijalankan. Ditulis 2026-07-21,
diperbarui 2026-08-10 (langkah tambal bulan + cek scheduler di kelompok C,
kelompok C-2 go-live "pelanggan saja"), 2026-11-02 (struktur folder per
kategori pakai + tabel rekap semua command), 2026-11-05 (`--terminate-missing`
di `app:customer-package-reconcile-excel` — auto putus langganan pelanggan
luar Excel).

Aturan umum sebelum mulai:

- Command yang **menulis data** hampir semuanya punya mode aman (`--dry-run`
  atau default cetak-daftar). **Selalu jalankan mode aman dulu, baca hasilnya,
  baru eksekusi.**
- Command terjadwal (kelompok A) tidak perlu dijalankan manual dalam keadaan
  normal — scheduler yang memanggilnya.
- Cek daftar lengkap kapan saja: `php artisan list`.

---

## Struktur folder `app/Console/Commands/` — per kategori pakai

Command difisikkan ke subfolder sesuai kategori pakai (2026-11-02), bukan
ditumpuk rata di `app/Console/Commands/`. Laravel men-scan subfolder secara
rekursif (`Illuminate\Foundation\Console\Kernel::load()` lewat Symfony
`Finder`) — pindah folder **tidak** mengubah nama command (`$signature`/
atribut `#[Signature(...)]`), cuma `namespace` tiap file yang ikut
menyesuaikan path (`App\Console\Commands\<Folder>`). Jalankan `php artisan
list` buat memastikan command yang dipindah tetap kedaftar.

| Folder | Kriteria | Isi |
|---|---|---|
| `Scheduled/` | Dipanggil scheduler (`routes/console.php`) lewat `Schedule::command(...)`. **Jangan jalankan manual** dalam keadaan normal. | `CheckCountdownStatus`, `GenerateMonthlyInvoicesCommand`, `ClosePeriodCommand`, `AutoWriteOffTerminatedCustomersCommand`, `PruneReadNotifications`, `CheckFopTaskSlaBreach`, `PruneWebhookOutbox`, `ReleaseOverdueTasksToPending`, `NotifyUnsettledTechnicianCommand` |
| `Operational/` | Manual, situasional, **benar-benar dipakai ke depan** — perawatan rutin billing/RBAC, atau rangkaian migrasi cabang baru yang masih akan dipakai tiap ada cabang baru. | `CustomerPackageReconcileFromExcelCommand`, `AuditDuplicateInvoicesCommand`, `ReconcileInvoiceStatusCommand`, `ApplyCustomerBalanceCommand`, `BackfillActivationDateCommand`, `GeneratePermissionsCommand`, `CustomersBackfillPortalLoginId`, `MigrateLegacyDataCommand`, `BackfillLegacyDeviceAndPaymentDataCommand`, `BackfillDeviceRetrievedStatusCommand`, `FixLegacyBillingBatch2Command`, `VerifyPersonBackfill`, `BackfillLegacyServiceBillingFieldsCommand` |
| `OneOff/` | Sudah dieksekusi buat nambal bug/insiden historis tertentu. Dibiarkan di repo buat jejak & referensi kalau bug sejenis muncul lagi, tapi **tidak diharapkan dijalankan lagi** di kondisi normal. | `BackfillCidDefaultDistributionSegmentCommand`, `BackfillStatusTimestamps`, `BackfillNotificationType`, `BackfillCustomerBalanceFromOverpay`, `BackfillRetrievalLogWarehousePopCommand`, `BackfillRollUnitPriceSnapshotCommand`, `BackfillInvoiceItemsCommand`, `CleanupLegacyDuplicateInvoicesCommand` |
| `DevOnly/` | **Jangan pernah jalankan di produksi.** Alat bantu development/benchmark/debugging. | `CustomersPortalSetPasswordForTesting`, `SeedVolumeForBenchmark`, `FindPolicy`, `MeasurePagesCommand` |

Trait bersama (`ParsesLegacySqlDump`) tetap di `app/Console/Commands/Concerns/`
— bukan command, jadi tidak ikut kategori di atas.

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

**Batasi ke satu Pop** — `--pop-id=<id>` atau `--pop-name=<nama persis>`. Tanpa
keduanya = semua Pop (default). Cek id/nama: `php artisan tinker --execute
'App\Models\Pop::orderBy("name")->get(["id","name"])->each(fn($p)=>print("{$p->id}\t{$p->name}\n"));'`

```bash
php artisan billing:generate-monthly-invoices --period=2026-11 --pop-id=5
php artisan billing:generate-monthly-invoices --period=2026-11 --pop-name=Sambit
```

**Tahan satu Pop sementara data belum rapi** (mis. sedang membenarkan massal
paket/pelanggan Pop itu, takut keburu digenerate pas cron tanggal 1 jalan).
Pause berlaku untuk cron (run penuh) **dan** `--pop-id`/`--pop-name` eksplisit —
Pop yang dipause ditolak eksplisit, bukan diam-diam dilewati:

```bash
php artisan billing:pause-pop --pop-id=5 --note="data paket sedang dibenarkan"
php artisan billing:paused-pops                 # cek Pop mana saja yang sedang ditahan
php artisan billing:resume-pop --pop-id=5       # cabut setelah data rapi, baru generate lagi
```

State pause disimpan di cache (`Cache::forever`, store `database` — persist
antar proses, gak hilang sampai di-`resume`), bukan kolom baru di `pops`. Cron
`monthlyOn(1, '01:00')` otomatis baca state ini tiap jalan — gak perlu matikan
scheduler buat nahan satu Pop.

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

### `customers:backfill-portal-login-id` — akun portal (`customer_portal_accounts`)

**Sejak 2026-08-27 JARANG perlu dijalankan manual** — pembuatan akun
`pending_claim` sekarang otomatis lewat `PortalAuthService::ensureAccountExists()`,
dipanggil dari titik yang sama dengan penerbitan QR/PIN (`CustomerWorkflowService`
saat `WAITING_INSTALLATION`, & manual dari halaman staf `/customers/{id}/qr`).
Pelanggan BARU gak butuh command ini lagi.

Masih perlu dijalankan kalau:

- **Abis `migrate:fresh`/impor data legacy** yang QR/PIN-nya diterbitkan LEWAT
  jalur lama (mis. seed manual, data lama sebelum hook otomatis dipasang) —
  akun `pending_claim`-nya gak ikut lahir otomatis.
- **`--resync`** — `login_id` sebagian akun `pending_claim` basi (formula lama
  `registration_prefix`, direvisi ke `cid_prefix` 2026-08-26). Cuma nyentuh
  baris `pending_claim`, baris `active` sengaja dibiarkan (lihat docblock
  command).

```bash
docker compose exec app php artisan customers:backfill-portal-login-id --dry-run
docker compose exec app php artisan customers:backfill-portal-login-id

# kalau login_id basi (formula lama)
docker compose exec app php artisan customers:backfill-portal-login-id --resync --dry-run
docker compose exec app php artisan customers:backfill-portal-login-id --resync
```

Aman diulang — pelanggan yang sudah punya akun portal dilewati.

Gejala kalau ini kelewat: `POST /api/customer-portal/auth/claim` balas 401
generik `"Login ID atau PIN salah."` walau `login_id`+PIN yang dikirim BENAR
— karena baris akunnya belum ada sama sekali (bukan salah kredensial).

---

## C. Migrasi data legacy — **urutannya wajib**

Ini rangkaian, bukan command tunggal. Jalankan berurutan, satu cabang selesai
dulu sebelum cabang berikutnya.

### Langkah 0 — Pastikan Pop `type=pusat` sudah ada

Command **wajib** diberi `--pusat-code` (pop_code dari Pop `type=pusat` yang
sudah ada) — Cabang baru yang dibuat command ini jadi anak (`parent_id`) Pusat
itu. Tanpa Pusat yang sudah ada, command berhenti dengan error, bukan diam-diam
bikin Cabang orphan. Kalau belum ada, buat dulu lewat Master POP (`type=pusat`)
sekali di awal — satu Pusat cukup dipakai berulang untuk semua Cabang.

### Langkah 1 — Impor utama
    /**
     * JIKA INGIN MIGRASI TANPA BILING LAMA
     * php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --pusat-code=WHUSNET --without-billing
     *
     * JIKA INGIN MIGRASI DENGAN BILING LAMA
     * php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --pusat-code=WHUSNET
     *
     *
     * JIKA POP DAN MINI POP SUDAH DISI MAKA LAKUKAN COMMAND INI AGAR POP DAN MINI POP TIDAK TEMBAK DEFAULT
     * docker compose exec app php artisan app:import-legacy-sql sand_db_sandya.sql --pusat-code=WHUSNET --no-interaction
     * php artisan app:import-legacy-sql sand_db_sandya.sql --pusat-code=WHUSNET --no-interaction
     *
     * JIKA NOMOR kategori_perangkat_jaringan LEGACY BEDA DENGAN NOMOR MINI POP
     * SUNGGUHAN DI CABANG TUJUAN (mis. dump ini semuanya kategori=1 tapi
     * secara fisik harus jadi Mini POP D6, bukan D1 — karena D1..D5 sudah
     * dipakai OLT lain di Cabang D yang sama):
     * php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --pusat-code=WHUSNET --mini-pop-map=1:6
     *  */
    protected $signature = 'app:import-legacy-sql
                        {file? : The path to the legacy sql dump. Default: sand_db_sandya.sql}
                        {--branch-code= : Tentukan Kode Cabang POP target (contoh: C, D)}
                        {--branch-name= : Tentukan Nama Cabang POP target (contoh: Jetis, Siman)}
                        {--pusat-code= : WAJIB. pop_code dari Pop type=pusat yang sudah ada (dibuat manual di Master POP) — Cabang baru dari command ini jadi anak Pusat tsb}
                        {--without-billing : Impor pelanggan/layanan/data teknis saja, tanpa tagihan & pembayaran legacy}
                        {--mini-pop-map= : Peta ulang nomor kategori_perangkat_jaringan LEGACY (khusus per-dump) ke segmen Mini POP SUNGGUHAN di Master POP, format "1:6,2:7". Wajib dipakai kalau dump berasal dari instalasi/cabang lama yang penomoran OLT-nya sendiri (mis. selalu mulai dari 1) tidak sama dengan penomoran Mini POP nyata di bawah Cabang tujuan (mis. dump ini semuanya harus jadi Mini POP D6, bukan D1). Kategori yang tidak disebut di peta ini tetap dipakai apa adanya.}';
```bash
php artisan app:import-legacy-sql jetis_db_aplikasi_jetis.sql --branch-code=C --branch-name=Jetis --pusat-code=WHUSNET
php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --pusat-code=WHUSNET
```

Mengimpor pelanggan, layanan, data teknis, tagihan, dan pembayaran. `--branch-code`
wajib diisi berbeda per cabang — ID legacy (`PE`/`RQ`/`IDBIAYA`) dimulai dari 1 di
tiap sistem lama, jadi tanpa pemisahan cabang keduanya bertabrakan. `--pusat-code`
wajib sama untuk semua Cabang di bawah satu Pusat yang sama.

Tanpa opsi `--branch-*`, command akan bertanya interaktif — `--pusat-code` tetap
wajib walau interaktif.

Ada opsi `--without-billing` untuk mengimpor pelanggan/layanan/data teknis **tanpa**
tagihan & pembayaran legacy. Dipakai hanya pada skenario go-live "pelanggan saja" —
lihat kelompok C-2. Untuk migrasi biasa, jangan dipakai.

#### `--mini-pop-map` — kalau penomoran Mini POP legacy beda dengan Master POP asli

Command menerjemahkan `kategori_perangkat_jaringan` di dump legacy jadi segmen
Mini POP **apa adanya** (`1` → cari/bikin `<kode_cabang>1`, `2` → `<kode_cabang>2`,
dst). Ini benar SELAMA penomoran lokal dump lama itu sudah selaras dengan
penomoran Mini POP sungguhan di Cabang tujuan.

Kalau tidak selaras — contoh: seluruh baris `sand_db_sandya.sql` bernilai
`kategori_perangkat_jaringan=1`, tapi secara fisik itu harus jadi **D6** (karena
D1–D5 di Cabang D/Siman sudah dipakai OLT lain, bukan D1) — pemetaan default
akan salah bikin/menempel ke `D1`. Petakan dulu nomor legacy → segmen asli:

```bash
php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --pusat-code=WHUSNET --mini-pop-map=1:6
```

Format `legacy:asli`, boleh banyak pasangan sekaligus kalau satu dump punya
beberapa kategori yang semuanya perlu remap: `--mini-pop-map=1:6,2:7,3:8`.
Kategori yang tidak disebut di peta tetap dipakai apa adanya (opsional,
backward compatible — tanpa flag ini command jalan seperti biasa).

**Cek dulu isi datanya sebelum menjalankan** — kalau satu dump ternyata punya
beberapa Mini POP fisik berbeda yang perlu remap, semuanya harus masuk peta
sekali jalan; kalau cuma sebagian yang dipetakan, sisanya ikut nempel ke
segmen default yang salah.

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
php artisan app:import-legacy-sql jetis_db_aplikasi_jetis.sql --branch-code=C --branch-name=Jetis --pusat-code=WHUSNET --without-billing
php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --pusat-code=WHUSNET --without-billing
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

**4b. Tambal `due_date` layanan kosong**

Bug lama di importer: sheet `services` dulu mengirim `due_date` string kosong,
jadi kolom ini NULL untuk **semua** pelanggan migrasi (bukan cuma yang
`--without-billing`). Karena jatuh tempo termasuk field wajib
`CustomerValidationService`, pelanggan tertahan di status `perlu_dilengkapi`
walau sebenarnya aktif dan ditagih tiap bulan.

```bash
php artisan billing:backfill-legacy-service-fields              # mode daftar saja
php artisan billing:backfill-legacy-service-fields --force      # eksekusi

# kalau monthly_price ikut 0 (paket legacy 'default'/'undefined'),
# tambahkan sumber harga dari dump asli:
php artisan billing:backfill-legacy-service-fields --dump=sand_db_sandya.sql --force
```

Mengisi `due_date` = `activation_date` + 1 bulan, dan `monthly_price` kalau ≤ 0
(urutan sumber: `biaya_tagihan.BIAYABULANAN` dari dump → invoice BULANAN
terakhir → harga paket → kalau semua kosong, dilaporkan REVIEW MANUAL, tidak
ditebak). Tiap tulis masuk audit log (`action = backfill_legacy_service_fields`)
dan memicu `Customer::recalculateCompleteness()`. Hanya menyentuh baris legacy
(`old_request_id` terisi) — baris pendaftaran normal tidak tersentuh.

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

### `app:customer-package-reconcile-excel` — migrasi/rekonsiliasi paket & harga dari Excel tagihan cabang

> **UI web buat command ini DICOBA lalu DIHAPUS (2026-11-03)** — user: lebih susah dipahami lewat UI dibanding langsung di terminal (istilah teknisnya — CID, kode paket katalog, config alias/skip — tetap kelihatan teknis walau sudah dibungkus form). Command ini TETAP cuma lewat terminal. Kalau ide UI muncul lagi, baca dulu kenapa percobaan sebelumnya dibatalkan sebelum mencoba ulang.

Dipakai saat data `internet_package_id`/`monthly_price` di `customer_services`
(hasil migrasi legacy, atau salah input manual) tidak sesuai dengan Excel
tagihan resmi cabang (mis. "Tagihan Sambit Oktober 2026.xlsx"). Dalam SATU
transaksi: membetulkan paket + harga pelanggan, menyinkronkan
`customers.internet_package_id` (kolom duplikat yang dibaca List/Detail
Pelanggan), **dan** menghitung ulang invoice BULANAN `belum_dibayar` yang
nominalnya sudah sempat terbit stale sebelum harga dibetulkan. Satu command
untuk ketiganya — jangan bikin command terpisah buat salah satunya (keputusan
user 2026-11-02, "1 command aja biar semuanya teratasi").

```bash
php artisan app:customer-package-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>" --dry-run
php artisan app:customer-package-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>" --apply
```

**Selalu `--dry-run` dulu** (default tanpa `--apply` — cetak daftar perubahan,
tidak menulis apa pun), baca hasilnya, baru `--apply`.

**Syarat kolom Excel** (header harus ada, nama kolom paket punya trailing
space di sumbernya — `"paket "`):

| Kolom | Isi |
|---|---|
| `Nama` | Nama pelanggan — dicocokkan EXACT (dinormalisasi) ke `customers.full_name` di dalam `pop_id` yang sama. Nama ganda/ambigu (>1 match) dilewati, dilaporkan, TIDAK ditebak. |
| `Paket` | Nominal harga bulanan (angka) |
| `paket ` (ada spasi di belakang) | Kode paket katalog (`package_code`), mis. `NetP110`, `Net138` |

**Diskriminator paket**: dibandingkan HARGA **+** SPEED paket saat ini vs
target hasil resolve kode Excel — reassign cuma kalau salah satu beda. Jangan
disederhanakan jadi "beda harga saja" atau "beda id baris katalog saja" —
keduanya sudah pernah salah (lihat ADHOC-258/`CustomerPackageReconcileFromExcelCommandTest`
untuk kasus false negative & false positifnya).

**Kalau ada kasus khusus** (alias nama beda format, kode paket typo di
Excel, baris yang harus dilewati) — pakai `--config=path/ke/config.json`,
pola (lihat contoh nyata `docs/database/excel/sambit-okt2026-config.json`):

```json
{
  "name_aliases": { "Nama Di Excel": "Nama Di Sistem" },
  "skip_names": ["Nama Yang Dilewatin"],
  "package_code_typo_fixes": { "KodeTypo": "KodeBenar" },
  "manual_overrides": { "CID-PELANGGAN": "KodePaketBenar" }
}
```

#### `manual_overrides` — koreksi migrasi skala besar yang gak bisa dicocokkan otomatis

**Kapan dipakai**: migrasi cabang besar (ribuan pelanggan), sebagian nama gak
bisa dicocokkan otomatis dari Excel (ambigu, typo parah, atau memang gak ada
baris Excel-nya) dan masuk daftar `skip_names`/"dilewati" — butuh review
manual satu-satu buat mastiin identitas pelanggannya.

**JANGAN betulin kasus ini lewat tombol "Ganti Paket" di UI** — itu lewat
`CustomerPackageService::change()`, yang MEMANG DIRANCANG reprorate invoice
periode berjalan (blend hari-paket-lama + hari-paket-baru) kalau tagihan
bulan itu sudah terbit. Itu benar untuk event ganti paket SUNGGUHAN, tapi
**salah untuk koreksi migrasi**: pelanggan migrasi gak pernah beneran pakai
"paket lama" yang salah itu — itu cuma kesalahan data, bukan hari yang
beneran mereka jalani di paket lama. Mem-prorate koreksi migrasi berarti
menagih pelanggan sebagian pakai harga yang gak pernah mereka pakai sungguhan.

**Skenario nyata yang wajib dihindari**: migrasi cabang 5.000 pelanggan,
2.000 auto-match lewat Excel (aman — lihat di bawah), 3.000 sisanya masih
direview manual. Review itu bisa makan waktu berminggu-minggu, sementara
`billing:generate-monthly-invoices` tetap jalan tiap tanggal 1 — kalau salah
satu dari 3.000 itu dibetulin lewat "Ganti Paket" SETELAH tagihan bulan itu
terbit, pelanggan itu kena prorate yang gak seharusnya.

**Solusinya**: setelah identitas pelanggan dipastikan manual (CID-nya, bukan
tebakan nama), tambahkan ke `manual_overrides` (`cid => package_code`) di
config yang sama, lalu jalankan command ini lagi (dry-run dulu). Baris
`manual_overrides` diproses lewat jalur koreksi **yang sama persis** dengan
baris Excel — timpa langsung ke harga target + sinkron `customers.internet_package_id`
+ koreksi invoice BULANAN `belum_dibayar`/`paid_amount=0` yang stale, **tanpa
prorate** — satu-satunya jalur migrasi yang aman dari skema upgrade/downgrade,
berapa lama pun migrasinya berjalan.

```json
"manual_overrides": {
  "G00RQ000024": "NetP110",
  "G1X1BRQ000031": "Net138"
}
```

Kalau CID gak ketemu di Pop ini, atau kode paketnya gak ada di katalog —
dilaporkan di tabel "Dilewati", TIDAK ditebak/dipaksa.

**Tidak pernah disentuh**: invoice `sebagian`/`lunas` (cuma `belum_dibayar`
`paid_amount=0`), kolom Collector, pelanggan yang namanya ambigu/tidak
ketemu. Aman diulang — dry-run ulang pasca-apply harus hasilnya 0 diubah.

```bash
# Verifikasi idempoten setelah apply:
php artisan app:customer-package-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>" --dry-run   # harus 0 diubah
```

#### `--terminate-missing` — putus langganan otomatis buat pelanggan luar Excel (2026-11-05)

**Opt-in, default OFF.** Pelanggan di Pop yang sama tapi **TIDAK ADA** baris
Excel-nya (bukan nama yang di-skip, bukan nama ambigu, bukan yang dikoreksi
lewat `manual_overrides`) dianggap sudah putus langganan — rekap Excel jadi
sumber kebenaran TERBARU, bukan cuma buat koreksi harga/paket. Dipakai kalau
tujuannya memang menyamakan List Pelanggan sepenuhnya dengan rekap Excel
terbaru (bukan sekadar membetulkan harga/paket pelanggan yang masih aktif).

```bash
# Dry-run WAJIB dulu — cek tabel "Akan Diputus Langganan" sebelum --apply
php artisan app:customer-package-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>" --config=<config>.json --terminate-missing --actor-id=<user_id>

php artisan app:customer-package-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>" --config=<config>.json --terminate-missing --actor-id=<user_id> --apply
```

- **`--actor-id` wajib** kalau `--terminate-missing` dipakai — console command
  tidak punya `Auth::user()`, user id ini yang tercatat sebagai pelaku di audit
  log. Cek id user yang mau dipakai: `php artisan tinker --execute
  'echo \App\Models\User::where("email","<email>")->first()?->id;'`
- Lewat jalur resmi `CustomerTerminationService::terminate()` — SAMA dengan
  form "Request Putus Langganan" di UI. Alasan putus auto-dibuat sekali
  (idempotent) bernama **"Rekonsiliasi Data Excel"**, denda selalu `0`.
- **"Modem belum diambil" muncul otomatis** — bukan field yang diisi command
  ini. Begitu pelanggan baru pertama kali di-terminate,
  `customer_devices.device_retrieved_at` tetap NULL (belum ada task AMBIL_MODEM
  yang selesai), jadi pelanggan itu langsung muncul di List Putus Langganan
  dengan tombol "Ambil Alat" tersedia — tanpa command ini perlu membuat
  FopTask apa pun.
- **Tanpa flag ini, behaviour command 100% tidak berubah** — aman dipakai
  terus buat koreksi harga/paket biasa.

**Yang TIDAK ikut diputus** (sengaja dilindungi, perlu review manual dulu):
nama di `skip_names`, nama ambigu (>1 kandidat di Pop yang sama), CID yang
disebut di `manual_overrides` (walau koreksinya sendiri gagal), dan pelanggan
di Pop lain. Yang ikut jadi kandidat hanya status `active`/`suspended` —
pelanggan yang sudah `terminated`/`draft`/dll dilewati diam-diam (tidak valid
jadi target transisi putus langganan).

> ⚠️ **Ini aksi destruktif (putus langganan sungguhan), bukan rollback
> impor.** Pastikan tabel dry-run "Akan Diputus Langganan" sudah dicek
> satu-satu — nama yang nyasar ke situ karena beda tulisan/typo harus
> dibetulkan dulu lewat `name_aliases` di config, BUKAN langsung `--apply`.


### Cara pakai:

1. Dry-run dulu (wajib, cek diff tanpa tulis DB):
php artisan app:customer-package-reconcile-excel "docs/database/excel/NamaFile.xlsx" --pop-name=Sambit --config=docs/database/excel/sambit-config.json --terminate-missing --actor-id=1
Lihat tabel "Akan Diputus Langganan" — cek dulu nama-namanya bener-bener harus putus, bukan korban nama beda tulisan/typo yang harusnya kena alias/skip_names/manual_overrides di config.

2. Kalau aman, jalanin beneran + --apply:
php artisan app:customer-package-reconcile-excel "docs/database/excel/NamaFile.xlsx" --pop-name=Sambit --config=docs/database/excel/sambit-config.json --terminate-missing --actor-id=1 --apply

Catatan:
- --actor-id = user id pelaku (buat audit log). Cek id user yang mau dipakai lewat php artisan tinker → User::where('email','...')->first()->id atau liat tabel users.
- Gak pake --terminate-missing sama sekali = behavior lama, gak ada yg diputus (aman, backward compatible).
- Pelanggan yang keliru ketemu di tabel "Akan Diputus" karena nama beda tulisan → betulin lewat name_aliases di config, jangan apply dulu.
- Alasan putus otomatis ke "Rekonsiliasi Data Excel" (reason baru, auto dibuat sekali, bisa dicek/edit di Master Alasan Putus Langganan).
- Kalau docker: docker compose exec -u www-data app php artisan ... (ganti php artisan di atas).

### `app:customer-serial-reconcile-excel` — selaraskan Serial Number dari rekap kegiatan Excel cabang

Dipakai saat Serial Number (SN) modem/ONT yang tampil di Detail Pelanggan
sudah usang — beda dengan rekap kegiatan lapangan Excel kolektor/teknisi
cabang (mis. "SN-SAMBIT-26 - rekap kegiatan.xlsx"), baik karena SN-nya pernah
diganti (kegiatan "ganti modem") maupun karena typo input manual.

```bash
php artisan app:customer-serial-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>"
php artisan app:customer-serial-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>" --apply
```

**Selalu jalan tanpa `--apply` dulu** (default dry-run — cetak daftar
perubahan, tidak menulis apa pun), baca hasilnya, baru tambahkan `--apply`.

**Syarat kolom Excel** (header harus ada):

| Kolom | Isi |
|---|---|
| `Kegiatan` | `psb` / `ganti modem` / dll — TIDAK dibedakan di command ini, lihat penjelasan "baris terakhir menang" di bawah |
| `Tgl` | Tanggal kegiatan — dipakai menentukan baris mana yang terbaru per pelanggan |
| `SN BARU` | SN yang berlaku setelah kegiatan itu — ini yang jadi kandidat nilai final |
| `CID` | Dicocokkan ke `customers.cid` lewat **6 digit angka pertama** yang ditemukan di kolom ini (bukan di akhir — CID rekap manual diakhiri `_DESA_NAMA`, bukan digit), di dalam `pop_id` yang sama. Toleran typo prefix (`G1X1BRQ000006` di Excel vs `G00RQ000006` di DB tetap cocok, karena 6 digit angkanya sama) |

**Baris terakhir menang**: per pelanggan (dikelompokkan oleh 6-digit CID),
baris dengan `Tgl` **terbaru** yang dipakai, `SN BARU` baris itu jadi nilai
final — baik baris itu `psb` maupun `ganti modem`, dan berapa kali pun
rantai gantinya (ganti modem bisa berkali-kali, ambil ujung rantai).

**Target tulis** ikut urutan cadangan yang sama dengan Detail Pelanggan:
`customer_devices.serial_number` kalau baris itu sudah ada → kalau tidak,
`customer_technical_details.router_or_ont_serial` → kalau tidak ada juga,
fallback `customers.ont_sn`. Command ini **tidak** membuat baris
`customer_devices` baru.

**Tidak pernah disentuh**: pelanggan yang SN-nya berasal dari Inventori
Gudang (`InventorySerial` status `installed`) — itu dikelola lewat alur
Gudang (Reassign/Transfer/dll), menimpa langsung di sini bikin data Gudang &
Detail Pelanggan menyimpang. Baris begini muncul di tabel "Dilewati".

**Kalau ada CID yang mau dilewatkan atau dikoreksi manual** (CID ambigu/gak
ketemu otomatis, atau perlu set SN tertentu tanpa lewat Excel) — pakai
`--config=path/ke/config.json`:

```json
{
  "skip_cids": ["G1X1BRQ000090"],
  "manual_overrides": { "G00RQ000091": "SNBENERNYA123" }
}
```

```bash
# Verifikasi idempoten setelah apply:
php artisan app:customer-serial-reconcile-excel "docs/database/excel/<file>.xlsx" --pop-name="<Nama POP>"   # harus 0 diubah
```

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

### `billing:apply-balance` — auto-pakai Saldo Pelanggan (ADHOC-92)

```bash
php artisan billing:apply-balance --dry-run                # WAJIB dijalankan dulu, lihat yang AKAN terjadi
php artisan billing:apply-balance                           # eksekusi
php artisan billing:apply-balance --customer=123             # batasi ke satu pelanggan
php artisan billing:apply-balance --period=2026-09 --dry-run # ringkasan dry-run per periode (tidak membatasi eksekusi)
```

Auto-pay utama sudah otomatis lewat `billing:generate-monthly-invoices` (dalam
transaksi yang sama dengan penerbitan tagihan BULANAN). Command ini untuk:

1. **Kasus pinggiran** — saldo yang masuk SETELAH tagihan bulan itu sudah
   terbit (mis. pelanggan titip saldo di tengah bulan padahal tagihan bulan
   itu belum lunas).
2. **Audit sebelum go-live/deploy** — `--dry-run` WAJIB dijalankan dulu
   sebelum tanggal 1 pertama setelah fitur ini di-deploy. Credit lama (dari
   ADHOC-38 atau `payments:backfill-customer-balance`) akan langsung terpakai
   di generator tanggal 1 berikutnya — tinjau daftar pelanggan terdampak
   dulu supaya tidak ada yang kaget tagihannya lunas tanpa uang masuk.

FIFO periode terlama dulu, hanya invoice `bulanan` terbuka (`belum_dibayar`/
`sebagian`). `--period` cuma membatasi RINGKASAN `--dry-run`, tidak membatasi
invoice yang disentuh saat eksekusi nyata — FIFO tetap jalan lintas periode.

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
php artisan app:import-legacy-sql <file>.sql --branch-code=X --branch-name=Nama --pusat-code=WHUSNET
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
php artisan app:import-legacy-sql <file>.sql --branch-code=X --branch-name=Nama --pusat-code=WHUSNET --without-billing
php artisan app:backfill-legacy-device-payment <file>.sql
php artisan app:backfill-device-retrieved --dry-run
php artisan app:backfill-device-retrieved
php artisan billing:backfill-legacy-service-fields              # cek due_date/monthly_price kosong
php artisan billing:backfill-legacy-service-fields --force
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

### Memasang fitur Ambil Modem (DEAC) → Gudang (ADHOC-86/88)

Butuh 2 tabel baru (`task_device_retrievals`, `device_retrieval_logs`), 1 item
master baru (`MODEM-PELANGGAN-LAMA`), dan asset frontend baru. Jalankan di container `app`
(bukan `exec app npm run build` — lihat catatan kepemilikan file di CLAUDE.md):

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=ItemSeeder   # item "Modem Pelanggan Lama" (idempotent)
docker compose exec app php artisan storage:link                 # sekali saja; foto kondisi alat di disk public
docker compose run --rm assets                                   # npm run build (class Tailwind & view baru)
```

Tanpa `migrate` form teknisi dan Terima Retur akan error (tabel tidak ada). Tanpa
`ItemSeeder` pilihan "Modem Pelanggan Lama" tidak muncul (model lain tetap bisa dipilih).
Tanpa `storage:link` foto kondisi tersimpan tapi thumbnail di Detail Task/Riwayat
Task FOP tidak tampil.

Periksa hasilnya (read-only):

```bash
docker compose exec app php artisan tinker --execute 'echo \App\Models\Item::where("code","MODEM-PELANGGAN-LAMA")->count();'   # harus 1
docker compose exec app php artisan tinker --execute 'echo \Schema::hasTable("device_retrieval_logs") ? "ok" : "BELUM";'
```

**Tidak ada backfill massal SN legacy — sengaja.** Data lama punya SN kosong (273
dari 1.957 detail teknis di data dev), SN dobel lintas pelanggan (93), dan tanpa
nama barang; SN didaftarkan satu per satu **saat modemnya benar-benar ditarik**
(SN di stiker fisik yang menjadi kebenaran). Jangan menyalin
`customer_technical_details.router_or_ont_serial` ke `inventory_serials` secara massal.

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
