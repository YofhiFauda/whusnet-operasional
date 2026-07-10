# Ringkasan Analisis Migrasi Legacy

Dokumen ini merangkum alur analisis dari awal percakapan sampai titik sekarang, termasuk perbaikan yang sudah dilakukan, tingkat akurasi migrasi data legacy `sand_db_sandya.sql`, dan langkah audit berikutnya yang paling berguna.

## 1. Kronologi Analisis

### A. Masalah awal import

Awalnya ada dua gejala utama:

- `import_batches.imported_rows` tercatat `0` padahal ada row valid yang seharusnya tersimpan.
- Validasi multisheet gagal pada beberapa sheet karena row tertentu tidak ikut terbentuk, terutama pada customer dan relasi yang bergantung ke legacy ID.

Masalah ini ditelusuri ke filter import dan mapping legacy ID.

### B. Klarifikasi `PE` dan `PG`

Dari data legacy:

- `PE...` adalah pelanggan.
- `PG...` adalah user internal / petugas.

Implikasinya:

- Filter import tidak boleh mengabaikan semua ID non-`PE`.
- Hanya akun internal seperti `PG*` yang perlu dilewati.
- Legacy seperti `CUST-*` tetap harus diproses sebagai pelanggan.

### C. Fallback alamat

Ada data pelanggan yang alamatnya kosong atau tidak lengkap.

Sumber legacy menunjukkan alamat bisa datang dari:

- `ALMT`
- `ALAMAT`
- atau komposisi `DESA`, `KEC`, `KOTA`

Karena itu, migrasi disesuaikan agar tidak bergantung ke satu kolom saja.

### D. Fallback petugas survey dan teknisi

`DISURVEY`, `DIPROSES`, dan `VERIFIED` di dump legacy sering berisi kode `PG...`, bukan nama manusia.

Karena itu migrasi disesuaikan agar:

- kode `PG...` diselesaikan ke `pengguna`
- field `surveyors`, `installation_technicians`, dan `activated_by_name` tersimpan dalam bentuk yang terbaca

### E. Kunci relasi request

Data legacy tidak selalu konsisten memakai satu key saja. Yang terlihat:

- `RQ...` sebagai request utama
- kadang relasi juga muncul lewat `IDPENGGUNA`
- pada beberapa area, `IDSURVEY` ikut berguna sebagai fallback

Karena itu, resolver survey/request dibuat lebih toleran terhadap variasi key.

## 2. Analisa Akurasi Umum

Kesimpulan umum:

- Akurasi migrasi untuk **alur bisnis inti** tinggi.
- Akurasi untuk **field turunan / display / teknis** sedang.
- Migrasi ini sudah layak dipakai untuk operasional billing, tetapi bukan salinan mentah 1:1 untuk semua kolom.

Estimasi praktis:

- **85-90% akurat** untuk kebutuhan operasional.
- **Mendekati 1:1** untuk relasi inti pelanggan, layanan, invoice, dan payment.
- **Tidak lossless penuh** untuk alamat, petugas survey, teknisi instalasi, dan beberapa catatan teknis.

## 3. Analisa Per Tabel

### Customers

Temuan:

- 42 baris pelanggan `PE...` berhasil dipetakan.
- Nama lengkap dan identitas dasar cukup kuat.
- Alamat jalan kadang langsung ada, kadang perlu fallback.

Skor praktis:

- **97.6%**

Catatan:

- Tabel ini paling stabil.
- Risiko utama ada di alamat dan kontak yang kosong atau `NULL`.

### Services

Temuan:

- 42/42 baris punya relasi inti yang jelas.
- `DISURVEY` hanya terisi di sebagian baris.
- `DIPROSES` juga tidak selalu terisi.

Skor praktis:

- **79.2%**

Catatan:

- Relasi layanan aman.
- Nama petugas sering perlu diselesaikan dari kode `PG...`.

### Survey

Temuan:

- 42/42 baris survey bisa dipetakan ke `PE...` + `RQ...`.
- Foto, kebutuhan alat, dan catatan sering kosong.

Skor praktis:

- **59.5%**

Catatan:

- Tabel ini paling banyak bergantung pada fallback dan normalisasi.

### Invoices

Temuan:

- 39 baris invoice legacy tersedia.
- Nominal dan tanggal terbit relatif konsisten.
- Tidak semua baris punya link customer/request yang kuat secara langsung.

Catatan:

- Tabel billing utama ini cukup kuat untuk operasional.
- Beberapa baris perlu resolusi lewat request atau service.

### Payments

Temuan:

- 39 baris payment tersedia.
- Nominal dan tanggal pembayaran cukup stabil.
- Sebagian payment perlu fallback lewat invoice / request.

Skor praktis:

- **88.0%**

Catatan:

- Histori transaksi cukup bisa dipakai.
- Relasi request tidak selalu eksplisit.

## 4. Field Lossless vs Lossy

### A. Field yang cenderung lossless

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
- relasi POP / customer / package yang memang jelas di sumber

### B. Field yang perlu fallback

Field yang tidak selalu bisa di-copy mentah:

- `full_address`
- `surveyors`
- `installation_technicians`
- `activated_by_name`
- `survey_photo`
- `survey_note`
- `required_tools`
- `installation_note`

### C. Field yang sebaiknya dibiarkan kosong bila sumber invalid

Ini field yang jangan dipaksa diisi kalau sumber legacy memang kosong / meragukan:

- foto survey yang tidak ada file aslinya
- catatan survey yang tidak pernah ditulis di legacy
- kebutuhan alat yang tidak tercatat
- beberapa metadata teknis yang hanya berisi placeholder seperti `-`, `NULL`, atau string kosong

Prinsipnya:

- lebih baik kosong tetapi jujur daripada diisi asumsi palsu
- fallback hanya untuk data yang bisa direkonstruksi dari sumber lain

## 5. Risiko Operasional

Risiko utama:

1. **Alamat legacy tidak seragam**
   - ada yang pakai `ALMT`
   - ada yang pakai `ALAMAT`
   - ada yang hanya punya `DESA`, `KEC`, `KOTA`

2. **Petugas survey / teknisi sering tersimpan sebagai kode**
   - contoh `PG000014`, `PG000017`
   - harus diselesaikan dulu ke tabel `pengguna`

3. **Beberapa relasi invoice/payment tidak selalu eksplisit**
   - kadang link langsung tersedia
   - kadang harus lewat request atau layanan pelanggan

4. **Field teknis legacy tidak selalu lengkap**
   - ini bukan bug migrasi semata
   - sumber lama memang menyimpan data yang parsial

## 5. Catatan Penting Tentang Perangkat

Ada perbedaan sumber data yang sempat terlihat janggal:

- Ringkasan pelanggan bisa menampilkan `IP Address` dan `ONT Serial Number` dari field pelanggan / detail teknis migrasi.
- Tab `Perangkat` awalnya hanya membaca tabel `customer_devices`.
- Data legacy seperti `SNROOTER_FIBER` dan `IPADDR` tidak otomatis dibuat menjadi row `customer_devices`; data itu masuk ke detail teknis pelanggan.

Karena itu, tab perangkat perlu fallback supaya data legacy tetap terlihat walaupun tabel perangkat modern belum diisi.

## 6. Rangkuman Akhir

Kalau tujuan migrasi adalah membuat data legacy siap dipakai di sistem billing baru, hasilnya sudah cukup kuat.

Kalau tujuan migrasi adalah mempertahankan semua kolom legacy secara mentah tanpa perubahan, hasilnya tidak sepenuhnya 1:1 karena sistem baru memang melakukan:

- normalisasi alamat,
- resolusi kode user internal,
- fallback relasi request/invoice/payment,
- dan pengisian field display yang semula kosong.

Verdict:

- **Layak untuk operasional**
- **Tidak lossless penuh**
- **Cocok untuk billing, cukup untuk histori teknis, dan perlu fallback untuk data legacy yang tidak konsisten**

## 7. Audit Gap List yang Paling Berguna Berikutnya

Langkah berikutnya yang paling berguna adalah membuat audit gap list dengan tiga kategori:

### 1) Field mana yang lossless

Masukkan field yang memang bisa dipindahkan tanpa interpretasi tambahan, misalnya:

- semua key legacy inti
- nominal
- tanggal transaksi
- status transaksi
- relasi yang langsung tersedia di dump

### 2) Field mana yang perlu fallback

Masukkan field yang bisa direkonstruksi dari kolom lain atau tabel lain, misalnya:

- alamat lengkap
- nama petugas survey
- nama teknisi
- contact / identitas yang bisa diisi dari komposisi field lain

### 3) Field mana yang sebaiknya dibiarkan kosong

Masukkan field yang tidak punya sumber valid di legacy, misalnya:

- foto yang memang tidak ada
- catatan yang tidak pernah dicatat
- metadata teknis yang hanya placeholder

### Format audit yang disarankan

Untuk tiap field, catat:

- nama tabel
- nama field legacy
- nama field sistem baru
- status: `lossless`, `fallback`, atau `empty`
- sumber fallback jika ada
- alasan kenapa harus kosong jika tidak valid

Contoh bentuk ringkas:

| Legacy Field | Sistem Baru | Status | Catatan |
|---|---|---|---|
| `ALMT` | `full_address` | fallback | Pakai `DESA, KEC, KOTA` jika kosong |
| `DISURVEY` | `surveyors` | fallback | Resolve `PG...` ke nama user |
| `FOTORUMAH` | `survey_photo` | empty jika kosong | Jangan diisi asumsi |
| `IDPERMINTAAN` | `old_request_id` | lossless | Key utama request |
