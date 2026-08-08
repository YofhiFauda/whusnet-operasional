# Runbook: Command Artisan Whusnet Operasional

Panduan kapan dan dengan urutan apa tiap command dijalankan. Ditulis 2026-07-21.

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
| `fop:reset-cancelled-tasks` | tiap hari 00:01 | Kembalikan FOP task berstatus `dibatalkan` ke `in_progress` untuk hari berikutnya |
| `billing:generate-monthly-invoices` | tanggal 1, jam 01:00 | Terbitkan tagihan BULANAN semua pelanggan aktif |

Jalankan manual hanya kalau: scheduler mati dan tagihan bulan itu belum terbit,
atau sedang menguji. Untuk yang billing, ada mode aman:

```bash
php artisan billing:generate-monthly-invoices --dry-run   # lihat yang akan dibuat
php artisan billing:generate-monthly-invoices             # eksekusi
```

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

### Langkah 5 — Periksa hasil

```bash
php artisan billing:audit-duplicate-invoices
```

Lihat kelompok D.

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
php artisan billing:audit-duplicate-invoices        # periksa hasil
```

### Pemeriksaan rutin bulanan

```bash
php artisan billing:audit-duplicate-invoices        # ada tagihan dobel?
```

Kalau kolom Sumber memunculkan `PERLU CEK`, berhenti dan telusuri — artinya ada
jalur pembuatan invoice yang lolos dari dua lapis pengaman.

### Tagihan bulanan tidak terbit (scheduler mati)

```bash
php artisan billing:generate-monthly-invoices --dry-run
php artisan billing:generate-monthly-invoices
```

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

---

## Referensi

- `routes/console.php` — jadwal
- `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md` — lapis pencegahan tagihan dobel
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`, `docs/IMPORT_SPEC.md` — detail migrasi
- `docs/ID_NUMBERING_RULES.md` — kenapa `--branch-code` wajib beda per cabang
