# Analisis Hasil Migrasi Data Legacy `sand_db_sandya.sql`

## Pertanyaan

Jika data dari website lama `sand_db_sandya.sql` dimasukkan ke database pada project ini, apakah semua data akan terisi penuh dan tampil lengkap di:

1. Detail Pelanggan
2. Billing / Tagihan
3. Pembayaran

---

## Jawaban Singkat

Tidak otomatis 100%.

Yang akan terisi adalah **data legacy yang memang punya padanan field dan relasi di sistem baru**. Jadi hasil migrasi bisa sangat lengkap, tetapi tetap bergantung pada:

- kelengkapan data sumber lama,
- kecocokan mapping field,
- kecocokan relasi antar tabel legacy,
- dan ada atau tidaknya master data referensi di sistem baru.

---

## Kesimpulan Operasional

### 1. Detail Pelanggan

Sebagian besar data utama pelanggan bisa terisi, terutama:

- identitas pelanggan,
- alamat,
- POP / cabang,
- paket,
- status layanan,
- histori survey,
- histori pemasangan,
- data teknis,
- dokumen jika ada padanannya.

Namun tidak semua field lama wajib tampil sebagai data aktif di sistem baru.
Beberapa field hanya disimpan sebagai informasi legacy atau catatan historis.

### 2. Billing / Tagihan

Data billing dapat terisi jika legacy memiliki:

- `biaya_tagihan`,
- `penagihan`,
- atau data lain yang bisa dipetakan ke `invoice`.

Tagihan historis bisa muncul jika relasinya berhasil dicocokkan lewat:

- `old_customer_id`,
- `old_request_id`,
- `old_cost_id`,
- `old_invoice_id`.

Kalau relasi tidak lengkap, data billing tetap bisa masuk sebagai histori parsial atau masuk ke error/review untuk dicocokkan manual.

### 3. Pembayaran

Data pembayaran dapat terisi jika legacy memiliki:

- bukti transaksi,
- nominal bayar,
- tanggal bayar,
- dan relasi yang cukup ke invoice atau request pelanggan.

Pembayaran lama tidak selalu harus punya `old_invoice_id` langsung.
Di data legacy, pencocokan bisa saja perlu lewat:

- `old_transaction_id`,
- `old_request_id`,
- `billing_period`.

---

## Kenapa Tidak Bisa Dijamin 100%

### 1. Tidak semua kolom legacy punya padanan langsung

Kolom yang tidak dipetakan ke schema baru tidak akan muncul otomatis.

### 2. Data legacy sering tidak rapi

Di data lama, banyak field bisa kosong, `null`, atau tidak konsisten formatnya.

### 3. Relasi antar tabel lama tidak selalu eksplisit

Contoh umum:

- pembayaran ada,
- invoice tidak eksplisit,
- tapi masih bisa dicocokkan lewat `IDTRANSAKSI` atau `IDPERMINTAAN`.

### 4. Sebagian data teknis hanya disimpan sebagai informasi

Data teknis legacy tetap masuk, tetapi tidak semuanya diubah menjadi workflow operasional teknisi baru.

---

## Apa Yang Biasanya Terlihat di Detail Pelanggan

Jika migrasi berhasil dengan mapping yang tersedia, Detail Pelanggan biasanya menampilkan:

- identitas pelanggan,
- alamat,
- POP / cabang,
- status kelengkapan data,
- layanan aktif,
- paket internet,
- invoice/tagihan historis,
- pembayaran historis,
- survey,
- pemasangan,
- perangkat,
- dokumen,
- data teknis legacy.

Jadi detail pelanggan bisa terlihat sangat lengkap, tetapi isi lengkap itu tetap tergantung pada data sumber lama.

---

## Apa Yang Biasanya Terlihat di Billing / Pembayaran

Jika mapping legacy berjalan baik, billing akan menampilkan:

- nomor invoice historis,
- periode tagihan,
- tanggal terbit,
- tanggal jatuh tempo,
- total tagihan,
- nominal bayar,
- sisa tagihan,
- status invoice,
- status pembayaran.

Pembayaran juga dapat muncul di detail pelanggan selama relasi ke invoice atau request berhasil dipetakan.

---

## Data Yang Tidak Akan Terisi Otomatis

Data berikut tidak dijamin masuk penuh:

- field legacy yang tidak dipetakan,
- data yang memang kosong di sumber lama,
- relasi yang tidak bisa dicocokkan,
- informasi yang hanya ada sebagai catatan bebas tanpa struktur,
- modul yang memang tidak ada padanan di sistem baru.

---

## Status Analisa

| Area | Status |
| --- | --- |
| Detail Pelanggan | Hampir lengkap, tergantung data legacy |
| Billing / Tagihan | Lengkap jika relasi invoice/tagihan tersedia |
| Pembayaran | Lengkap jika relasi transaksi berhasil dicocokkan |
| Semua field legacy 100% terisi | Tidak bisa dijamin |

---

## Kesimpulan Akhir

Jika data dari `sand_db_sandya.sql` dimigrasikan ke project ini dengan mapping yang sudah disiapkan, maka hasilnya bisa sangat lengkap dan layak dipakai untuk operasional.

Tetapi jawaban yang paling akurat tetap:

```text
Tidak semua data legacy akan terisi 100%.
Yang terisi adalah data yang memang punya padanan field, relasi, dan referensi master di sistem baru.
```

Untuk kebutuhan praktis, ini sudah cukup untuk:

1. menampilkan Detail Pelanggan,
2. menampilkan Billing / Tagihan,
3. menampilkan Pembayaran,
4. mempertahankan histori legacy yang relevan.

