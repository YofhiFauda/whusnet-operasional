# Analisis Akurasi Migrasi `sand_db_sandya.sql`

Dokumen ini merangkum hasil analisis migrasi data legacy ke sistem baru berdasarkan struktur dump `sand_db_sandya.sql`, implementasi migrasi saat ini, dan verifikasi test yang sudah dijalankan.

## 1. Analisis Akurasi Umum

Kesimpulan umum:

- Akurasi migrasi untuk **alur bisnis inti** tergolong tinggi.
- Akurasi untuk **field turunan / display / teknis** berada di tingkat sedang karena data legacy tidak selalu konsisten.
- Migrasi ini sudah layak dipakai untuk operasional billing, tapi tidak semua kolom legacy bisa dianggap 1:1.

Estimasi praktis:

- **85-90% akurat** untuk kebutuhan operasional.
- **Mendekati 1:1** untuk relasi inti pelanggan, layanan, invoice, dan payment.
- **Tidak lossless penuh** untuk alamat, petugas survey, teknisi instalasi, dan beberapa catatan teknis.

Yang paling kuat:

- `PE...` sebagai pelanggan.
- `RQ...` sebagai request / permintaan.
- `IN...` / `IDBIAYA` sebagai invoice / biaya.
- `IDTRANSAKSI` sebagai penghubung pembayaran.

## 2. Analisis Per Tabel

### Customers

Kondisi:

- 42 baris pelanggan legacy `PE...` berhasil dipetakan.
- Nama lengkap dan identitas dasar cukup kuat.
- Alamat jalan kadang langsung ada, kadang perlu fallback dari `DESA`, `KEC`, dan `KOTA`.

Skor praktis:

- **97.6%**

Catatan:

- Tabel ini paling stabil untuk migrasi.
- Risiko utama ada di alamat dan kontak yang kosong / bernilai `NULL`.

### Services

Kondisi:

- 42/42 baris punya relasi inti yang jelas: `IDPERMINTAAN`, `IDPENGGUNA`, `IDPAKET`, `STATUS`.
- Field `DISURVEY` hanya terisi di sebagian baris.
- Field `DIPROSES` juga tidak selalu terisi.

Skor praktis:

- **79.2%**

Catatan:

- Relasi layanan aman.
- Field petugas survey / teknisi instalasi tidak selalu 1:1 karena legacy sering menyimpan kode user internal, bukan nama.

### Survey

Kondisi:

- 42/42 baris survey bisa dipetakan ke pasangan `PE...` dan `RQ...`.
- Foto, kebutuhan alat, catatan, dan sebagian metadata sering kosong.

Skor praktis:

- **59.5%**

Catatan:

- Ini tabel yang paling banyak bergantung pada fallback dan normalisasi.
- Secara operasional cukup untuk histori survey, tapi bukan salinan lengkap semua detail lapangan.

### Invoices

Kondisi:

- 39 baris invoice legacy tersedia.
- Nominal dan tanggal terbit relatif konsisten.
- Hanya sebagian baris yang punya link customer/request yang kuat secara langsung.

Skor praktis:

- **Tinggi untuk baris lengkap**
- **Tidak semua baris 1:1**

Catatan:

- Ini tabel billing utama, jadi penting menjaga `old_invoice_id`, `old_cost_id`, dan `billing_period`.
- Pada record tertentu, relasi harus dicari lewat request atau service.

### Payments

Kondisi:

- 39 baris payment tersedia.
- Nominal dan tanggal pembayaran cukup stabil.
- Sebagian payment perlu fallback lewat invoice / request.

Skor praktis:

- **88.0%**

Catatan:

- Payment relatif aman untuk histori transaksi.
- Tantangan utama ada pada relasi request yang tidak selalu eksplisit.

## 3. Analisis Field Lossless vs Lossy

### Field Yang Cenderung Lossless

Field yang paling mendekati 1:1:

- `old_customer_id`
- `old_request_id`
- `old_invoice_id` / `old_cost_id`
- `old_payment_id`
- `billing_period`
- `total_amount`
- `issue_date`
- `due_date`
- `amount`
- `payment_date`
- `service_status`
- `customer_code` / relasi POP yang memang sudah jelas di sumber

### Field Yang Butuh Normalisasi

Field yang tidak selalu bisa di-copy mentah:

- `full_address`
- `surveyors`
- `installation_technicians`
- `activated_by_name`
- `survey_photo`
- `survey_note`
- `required_tools`
- `installation_note`

### Field Yang Sering Hilang Di Sumber Legacy

Field yang memang banyak kosong / tidak konsisten:

- `DISURVEY`
- `DIPROSES`
- `FOTORUMAH`
- `ALATPASIF`
- sebagian `KETERANGAN`
- sebagian `BULANTAGIHAN` dan relasi payment request

## 4. Risiko Operasional

Risiko utama:

1. **Alamat legacy tidak seragam**
   - Ada yang pakai `ALMT`
   - Ada yang pakai `ALAMAT`
   - Ada yang hanya punya `DESA`, `KEC`, `KOTA`

2. **Petugas survey / teknisi sering tersimpan sebagai kode**
   - Contoh `PG000014`, `PG000017`
   - Harus diselesaikan dulu ke tabel `pengguna` agar terbaca sebagai nama

3. **Beberapa relasi invoice/payment tidak selalu eksplisit**
   - Kadang link langsung tersedia
   - Kadang harus lewat request atau layanan pelanggan

4. **Field teknis legacy tidak selalu lengkap**
   - Ini bukan bug migrasi semata
   - Sumber lama memang menyimpan data yang parsial

## Ringkasan Akhir

Jika tujuan migrasi adalah membuat data legacy siap dipakai di sistem billing baru, maka hasilnya sudah cukup kuat.

Jika tujuan migrasi adalah mempertahankan semua kolom legacy secara mentah tanpa perubahan, hasilnya tidak sepenuhnya 1:1 karena sistem baru memang melakukan:

- normalisasi alamat,
- resolusi kode user internal,
- fallback relasi request/invoice/payment,
- dan pengisian field display yang semula kosong.

Verdict:

- **Layak untuk operasional**
- **Tidak lossless penuh**
- **Cocok untuk billing, cukup untuk histori teknis, dan perlu fallback untuk data legacy yang tidak konsisten**

