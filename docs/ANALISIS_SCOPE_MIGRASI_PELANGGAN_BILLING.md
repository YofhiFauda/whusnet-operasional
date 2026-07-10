# Analisis Scope Migrasi Pelanggan dan Billing

## Tujuan Analisis

Dokumen ini merangkum analisis kesesuaian antara:

1. Implementasi project saat ini.
2. `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`.
3. `docs/Website_Billing_ISP_PRD.md`.
4. Struktur data lama `sand_db_sandya.sql`.

Fokus kebutuhan sementara adalah membuat website baru yang hanya berisi:

- Data pelanggan dari data lama.
- Data layanan pelanggan.
- Data paket internet.
- Data tagihan/billing.
- Data pembayaran.
- Tampilan dan pencarian data pelanggan.
- Billing sederhana/manual.

Website baru untuk tahap ini belum perlu menjadi sistem operasional ISP lengkap.

---

## Kesimpulan Utama

`docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md` sudah tepat dijadikan acuan utama untuk kebutuhan saat ini.

Dokumen tersebut merupakan versi scope yang lebih ramping dari `docs/Website_Billing_ISP_PRD.md`, tetapi tetap sesuai dengan prinsip PRD:

```text
Pelanggan
→ Paket/Layanan
→ Tagihan
→ Pembayaran
→ Laporan
```

Namun implementasi project saat ini belum sepenuhnya siap untuk migrasi data lama dari `sand_db_sandya.sql`.

Project sudah memiliki banyak fondasi yang dibutuhkan, tetapi proses import legacy masih perlu disesuaikan agar cocok dengan karakter data lama yang tidak selalu lengkap dan tidak selalu rapi.

---

## Kesesuaian Scope Migrasi Dengan PRD

### Sesuai

Scope migrasi masih sesuai dengan PRD karena tetap mempertahankan prinsip utama:

- Billing tidak berdiri sendiri.
- Tagihan harus terhubung ke pelanggan.
- Pembayaran harus terhubung ke invoice dan pelanggan.
- Data pelanggan lama harus bisa masuk ke sistem baru.
- Data hasil import bisa dicari, difilter, diedit, dan digunakan untuk billing.

### Penyederhanaan Dari PRD

Scope migrasi memang memangkas beberapa bagian dari PRD besar:

- Workflow teknisi lengkap.
- Survey sebagai proses operasional.
- Pemasangan sebagai proses operasional.
- Inventory perangkat.
- Monitoring jaringan.
- Payment gateway.
- Auto suspend.
- Auto generate invoice kompleks.
- WhatsApp notification.
- Ticketing.
- Modul keuangan/jurnal lengkap.

Penyederhanaan ini benar untuk tahap saat ini karena kebutuhan utama adalah migrasi data lama dan billing manual.

---

## Kesesuaian Scope Migrasi Dengan `sand_db_sandya.sql`

Scope migrasi sudah cocok dengan struktur data lama.

Tabel lama yang relevan:

| Tabel Lama | Fungsi | Status Kebutuhan |
| --- | --- | --- |
| `pengguna` | Data pelanggan dan staff lama | Dipakai untuk pelanggan |
| `prosedure_permintaan_wifi` | Layanan/request pelanggan | Dipakai untuk layanan pelanggan |
| `paket` | Master paket internet | Dipakai untuk master paket |
| `biaya_tagihan` | Biaya awal/tagihan | Dipakai untuk invoice awal atau histori biaya |
| `penagihan` | Tagihan bulanan | Dipakai jika datanya tersedia |
| `apikeuangan_buktitransaksitagihan` | Bukti transaksi tagihan | Dipakai untuk payment atau histori tagihan |
| `apikeuangan_buktitransaksilunas` | Bukti transaksi lunas | Dipakai untuk pembayaran historis |
| `apikeuangan_buktitransaksipemasangan` | Bukti pembayaran pemasangan | Dipakai jika bisa dihubungkan ke pelanggan |
| `laporan_pemasangan_wifi` | Data teknis hasil pemasangan | Dipakai sebagai detail teknis pelanggan |
| `survey_pemasangan_wifi` | Data survey lama | Opsional sebagai histori |
| `tb_alamat` | Referensi wilayah | Opsional untuk melengkapi wilayah |
| `cabang` | Data cabang lama | Dipakai sebagai POP/cabang sederhana |

Data lama tidak perlu ditiru struktur aplikasinya secara penuh.

Yang penting adalah data penting dari tabel lama bisa masuk ke struktur baru yang lebih sederhana.

---

## Implementasi Yang Sudah Ada

Project saat ini sudah memiliki banyak modul yang dibutuhkan:

- Login.
- User management.
- Role dan permission.
- RBAC.
- POP/cabang.
- Master paket internet.
- Data pelanggan manual.
- Detail pelanggan.
- Import pelanggan.
- Import multi-sheet.
- Tagihan manual.
- Pembayaran manual.
- Laporan pelanggan.
- Laporan tagihan.
- Laporan pembayaran.
- Laporan import.
- Audit log.
- Data teknis pelanggan.

Field legacy penting juga sudah mulai tersedia:

| Tabel Baru | Field Legacy |
| --- | --- |
| `customers` | `old_customer_id` |
| `internet_packages` | `old_package_id` |
| `customer_services` | `old_request_id`, `old_cost_id` |
| `invoices` | `old_invoice_id`, `old_cost_id` |
| `payments` | `old_payment_id`, `old_transaction_id` |
| `customer_technical_details` | `old_report_id`, `old_customer_id`, `old_request_id` |

Ini berarti fondasi database sudah cukup dekat dengan kebutuhan migrasi.

---

## Bagian Implementasi Yang Terlalu Besar Untuk Tahap Ini

Untuk kebutuhan sementara, bagian berikut tidak perlu dikembangkan lebih jauh dulu:

- Workflow survey.
- Workflow pemasangan.
- Modul modem/ONT/router sebagai proses operasional.
- Upload dokumen pelanggan kompleks.
- Audit log terlalu luas.
- RBAC terlalu detail.
- Permission terlalu granular.
- Laporan terlalu banyak variasi.
- Master status pelanggan yang terlalu kompleks.
- Region master yang terlalu ketat jika data lama belum rapi.

Bagian tersebut tidak harus dihapus, tetapi sebaiknya tidak menjadi fokus sampai migrasi pelanggan dan billing lama stabil.

---

## Gap Utama Implementasi Saat Ini

### 1. Validasi Import Pelanggan Masih Terlalu Ketat

Scope migrasi menyebut data lama yang belum lengkap tetap boleh masuk dan diberi status `perlu_dilengkapi`.

Namun implementasi import saat ini masih cenderung menolak data jika:

- Nomor HP kosong.
- Alamat kosong.
- Desa tidak ditemukan.
- Kecamatan kosong.
- Kota kosong.
- POP tidak ditemukan.

Padahal pada `sand_db_sandya.sql`, banyak data `pengguna` memiliki:

- `KOTA` kosong.
- `KEC` kosong.
- `DESA` kosong.
- `HP` berisi `null` sebagai string.
- Alamat tidak selalu terstruktur.

Rekomendasi:

Data pelanggan lama sebaiknya tetap masuk selama minimal memiliki `old_customer_id` dan salah satu identitas dasar seperti nama, alamat, nomor HP, atau NIK.

Jika field wajib billing belum lengkap, statusnya cukup menjadi `perlu_dilengkapi`.

---

### 2. Mapping Status Legacy Belum Lengkap

Data lama memakai status seperti:

| Status Lama | Status Baru |
| --- | --- |
| `ACTIVE` | `aktif` |
| `PUTUS` | `berhenti` |
| `GAGAL` | `gagal` atau `nonaktif` |
| `DISURVEI` | `survey` |
| `PENGAJUAN` | `calon_pelanggan` |
| kosong/null | `belum_diketahui` |

Implementasi perlu memastikan semua status lama diterima dan dimapping dengan benar, bukan dianggap invalid.

---

### 3. Relasi Pembayaran Lama Tidak Selalu Berbasis Invoice ID

Data pembayaran lama banyak memakai:

- `IDUNIQ`
- `IDTRANSAKSI`
- `IDPERMINTAAN`
- `BULANTAGIHAN`
- `BAYAR`

Implementasi sekarang masih lebih mudah jika ada `old_invoice_id`.

Masalahnya, di data lama pembayaran bisa lebih mudah dicocokkan lewat:

- `IDTRANSAKSI` ke `biaya_tagihan.IDBIAYA`.
- `IDPERMINTAAN` ke layanan pelanggan.
- `BULANTAGIHAN` sebagai periode tagihan.

Rekomendasi:

Import pembayaran harus bisa menerima pembayaran walaupun `old_invoice_id` tidak ada, selama masih bisa dicocokkan lewat `old_transaction_id` atau `old_request_id`.

---

### 4. Invoice Historis Perlu Lebih Fleksibel

Invoice/tagihan lama bisa berasal dari:

- `biaya_tagihan`.
- `penagihan`.
- `apikeuangan_buktitransaksitagihan`.

Implementasi sudah memiliki `old_invoice_id` dan `old_cost_id`, tetapi proses import masih perlu disesuaikan agar bisa membuat invoice dari data biaya/tagihan lama yang tidak selalu sempurna.

Rekomendasi:

Gunakan aturan:

- Jika ada `penagihan.IDTAGIHAN`, gunakan sebagai `old_invoice_id`.
- Jika tidak ada, gunakan `biaya_tagihan.IDBIAYA` sebagai `old_cost_id`.
- Jika hanya ada bukti transaksi, bentuk invoice historis sederhana berdasarkan `IDTRANSAKSI`, `IDPERMINTAAN`, dan `BULANTAGIHAN`.

---

### 5. Beberapa Field Legacy Pelanggan Belum Tersimpan Lengkap

Scope migrasi menyebut field seperti:

- `customer_type`
- `company_name`
- `npwp`
- `old_account_status`
- `old_region_id`
- `old_branch_id`
- `ktp_photo`
- `profile_photo`

Sebagian field sudah ada di template import frontend, tetapi belum semuanya tersimpan jelas di struktur baru.

Rekomendasi:

Tambahkan field legacy yang benar-benar diperlukan untuk mempertahankan data lama, terutama yang berasal langsung dari `pengguna`.

---

### 6. Template Download Belum Konsisten Dengan Import Multi-Sheet

Halaman import sudah mengarah ke Excel multi-sheet:

- `customers`
- `packages`
- `services`
- `technical_details`
- `invoices`
- `payments`

Namun route download template masih menghasilkan CSV pelanggan sederhana.

Rekomendasi:

Template download harus diubah menjadi template Excel multi-sheet atau minimal menyediakan contoh CSV per sheet.

---

## Rekomendasi Scope Implementasi Berikutnya

Fokus berikutnya sebaiknya bukan menambah fitur baru.

Fokus yang benar adalah:

```text
Sesuaikan import migrasi legacy agar cocok dengan sand_db_sandya.sql
```

Prioritas kerja:

1. Longgarkan validasi import pelanggan lama.
2. Mapping status lama ke status baru.
3. Mapping cabang lama ke POP/cabang sederhana.
4. Mapping paket lama ke `internet_packages`.
5. Mapping request lama ke `customer_services`.
6. Mapping data teknis lama ke `customer_technical_details`.
7. Mapping `biaya_tagihan` dan `penagihan` ke `invoices`.
8. Mapping `apikeuangan_*` ke `payments`.
9. Perbaiki template import multi-sheet.
10. Pastikan data invalid tidak hilang, tetapi masuk ke import error/review.

---

## Rekomendasi Struktur MVP Sementara

Untuk tahap sekarang, menu minimal cukup:

1. Dashboard sederhana.
2. Data Pelanggan.
3. Import Excel.
4. Paket Internet.
5. Tagihan.
6. Pembayaran.
7. Laporan sederhana.

Menu atau fitur yang sebaiknya tidak ditonjolkan dulu:

- Survey.
- Pemasangan.
- Perangkat.
- Dokumen.
- Audit log detail.
- User/permission detail untuk operasional kompleks.

---

## Keputusan Teknis Yang Disarankan

### Keputusan 1

`docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md` dijadikan acuan utama untuk tahap sekarang.

### Keputusan 2

`docs/Website_Billing_ISP_PRD.md` tetap menjadi roadmap besar, bukan acuan implementasi harian untuk tahap migrasi.

### Keputusan 3

Data teknis lama tetap disimpan, tetapi hanya sebagai informasi detail pelanggan.

Tidak perlu dibuat workflow teknisi lengkap untuk tahap ini.

### Keputusan 4

Import harus menerima data lama yang tidak lengkap.

Data tidak lengkap tidak boleh langsung ditolak jika masih bisa diidentifikasi sebagai pelanggan lama.

### Keputusan 5

Billing tetap manual.

Tidak perlu auto generate invoice bulanan, auto suspend, payment gateway, atau integrasi MikroTik.

---

## Status Kesiapan

| Area | Status |
| --- | --- |
| Struktur pelanggan | Hampir siap |
| Struktur paket | Siap, perlu mapping legacy |
| Struktur layanan pelanggan | Hampir siap |
| Struktur invoice | Siap, perlu mapping historis lebih fleksibel |
| Struktur payment | Siap, perlu mapping transaksi lama |
| Struktur data teknis legacy | Sudah tersedia |
| Import multi-sheet | Ada, perlu penyesuaian legacy |
| Validasi data lama | Perlu dilonggarkan |
| Tampilan pelanggan dan billing | Sudah ada |
| Scope project | Perlu diarahkan ulang ke migrasi-billing |

---

## Kesimpulan Akhir

Project saat ini sudah memiliki fondasi yang cukup untuk membuat website data pelanggan dan billing dari data lama.

Namun implementasi belum bisa dianggap selesai untuk kebutuhan migrasi karena import legacy belum sepenuhnya cocok dengan karakter `sand_db_sandya.sql`.

Scope yang paling tepat untuk tahap sekarang adalah:

```text
Data lama sand_db_sandya.sql
→ ekstrak ke Excel multi-sheet
→ import ke sistem baru
→ tampilkan data pelanggan
→ tampilkan layanan pelanggan
→ tampilkan invoice/tagihan
→ tampilkan pembayaran
→ billing manual
```

Fitur di luar alur tersebut sebaiknya dihentikan sementara sampai migrasi pelanggan dan billing stabil.
