# STATUS_FLOW.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini menjelaskan alur status pelanggan, status kelengkapan data, status layanan, status invoice, dan status pembayaran.

AI/developer wajib mengikuti dokumen ini ketika membuat fitur yang mengubah status.

---

# 1. Status Kelengkapan Data Pelanggan

## 1.1 Daftar Status

Status kelengkapan data:

1. `draft`
2. `perlu_dilengkapi`
3. `lengkap`
4. `siap_billing`

## 1.2 Penjelasan Status

### draft

Digunakan ketika data pelanggan baru dibuat tetapi masih sangat belum lengkap.

Contoh:

* Baru mengisi nama.
* Belum memilih POP.
* Belum memilih paket.
* Belum ada alamat lengkap.

### perlu_dilengkapi

Digunakan ketika data pelanggan sudah ada, tetapi masih ada field wajib yang belum terisi.

Contoh:

* Nama dan nomor HP ada.
* Alamat ada.
* Tetapi paket belum dipilih.
* Atau tanggal jatuh tempo belum diisi.

### lengkap

Digunakan ketika semua field wajib sudah terisi.

Field wajib:

1. Nama lengkap.
2. Nomor HP.
3. Alamat lengkap.
4. Desa/Kelurahan.
5. Kecamatan.
6. Kota/Kabupaten.
7. POP/Cabang.
8. Paket internet.
9. Harga bulanan.
10. Tanggal aktivasi.
11. Tanggal jatuh tempo.
12. Status layanan.

### siap_billing

Digunakan ketika pelanggan sudah lengkap dan layanan sudah diaktifkan untuk proses billing.

## 1.3 Alur Status Kelengkapan

Alur normal:

```txt
draft
→ perlu_dilengkapi
→ lengkap
→ siap_billing
```

## 1.4 Aturan Transisi Status Kelengkapan

### draft → perlu_dilengkapi

Terjadi ketika:

* pelanggan disimpan,
* sebagian data sudah terisi,
* masih ada field wajib kosong.

### perlu_dilengkapi → lengkap

Terjadi ketika:

* semua field wajib sudah terisi,
* validasi kelengkapan berhasil.

### lengkap → siap_billing

Terjadi ketika:

* admin mengaktifkan layanan,
* sistem memastikan pelanggan memiliki paket aktif,
* sistem memastikan tanggal aktivasi dan jatuh tempo ada,
* sistem menyimpan layanan aktif.

### lengkap → perlu_dilengkapi

Terjadi jika:

* field wajib yang sebelumnya terisi dihapus,
* paket dihapus,
* POP dihapus,
* tanggal jatuh tempo dikosongkan.

### siap_billing → perlu_dilengkapi

Hanya boleh terjadi jika ada perubahan data penting yang membuat pelanggan tidak valid lagi.

Contoh:

* paket pelanggan dihapus,
* harga layanan dikosongkan,
* POP pelanggan dihapus.

Jika sudah ada invoice aktif, perubahan status ini harus hati-hati dan masuk audit log.

## 1.5 Larangan

1. `draft` tidak boleh langsung menjadi `siap_billing`.
2. `perlu_dilengkapi` tidak boleh langsung dibuatkan invoice.
3. `lengkap` belum tentu boleh ditagih jika layanan belum diaktifkan.
4. `siap_billing` tidak boleh diberikan jika paket belum aktif.
5. Status tidak boleh diubah manual tanpa validasi.

---

# 2. Status Layanan Pelanggan

## 2.1 Daftar Status

Status layanan pelanggan:

1. `calon_pelanggan`
2. `survey`
3. `menunggu_pemasangan`
4. `aktif`
5. `isolir`
6. `nonaktif`
7. `berhenti`

## 2.2 Penjelasan Status

### calon_pelanggan

Pelanggan baru didaftarkan tetapi belum masuk proses teknis.

### survey

Pelanggan sedang menunggu atau menjalani proses survey.

### menunggu_pemasangan

Survey selesai dan pelanggan menunggu pemasangan.

### aktif

Layanan pelanggan aktif dan dapat ditagih.

### isolir

Layanan dihentikan sementara.

Contoh penyebab:

* belum bayar,
* masalah administrasi,
* permintaan internal.

### nonaktif

Layanan tidak aktif tetapi data pelanggan masih disimpan.

### berhenti

Pelanggan sudah berhenti berlangganan.

## 2.3 Alur Status Layanan Normal

```txt
calon_pelanggan
→ survey
→ menunggu_pemasangan
→ aktif
```

## 2.4 Alur Status Layanan Setelah Aktif

```txt
aktif
→ isolir
→ aktif
```

```txt
aktif
→ nonaktif
```

```txt
aktif
→ berhenti
```

## 2.5 Aturan Transisi Status Layanan

### calon_pelanggan → survey

Terjadi ketika pelanggan dijadwalkan survey.

### survey → menunggu_pemasangan

Terjadi ketika survey layak dan pelanggan siap dipasang.

### menunggu_pemasangan → aktif

Terjadi ketika pemasangan selesai dan layanan diaktifkan.

### aktif → isolir

Terjadi ketika layanan dihentikan sementara.

### isolir → aktif

Terjadi ketika layanan dipulihkan.

### aktif → berhenti

Terjadi ketika pelanggan berhenti permanen.

### aktif → nonaktif

Terjadi ketika layanan tidak aktif tetapi belum dinyatakan berhenti permanen.

## 2.6 Larangan

1. Pelanggan `calon_pelanggan` tidak boleh dibuatkan invoice aktif.
2. Pelanggan `survey` tidak boleh dibuatkan invoice aktif.
3. Pelanggan `menunggu_pemasangan` tidak boleh dibuatkan invoice aktif kecuali kebijakan bisnis mengizinkan.
4. Pelanggan `berhenti` tidak boleh dibuatkan invoice baru.
5. Pelanggan `nonaktif` tidak boleh dibuatkan invoice baru.
6. Pelanggan `isolir` bisa memiliki tunggakan, tetapi tidak otomatis dibuatkan invoice baru kecuali aturan bisnis mengizinkan.

---

# 3. Hubungan Status Kelengkapan dan Status Layanan

## 3.1 Syarat Masuk Billing

Pelanggan dapat masuk billing jika:

```txt
status_kelengkapan_data = siap_billing
DAN
status_layanan = aktif
```

Atau dalam tahap awal MVP bisa menggunakan:

```txt
status_kelengkapan_data = siap_billing
```

Tetapi aturan yang lebih aman adalah wajib aktif.

## 3.2 Kombinasi Valid

| Status Kelengkapan | Status Layanan      | Boleh Buat Invoice?                                |
| ------------------ | ------------------- | -------------------------------------------------- |
| draft              | calon_pelanggan     | Tidak                                              |
| perlu_dilengkapi   | calon_pelanggan     | Tidak                                              |
| lengkap            | calon_pelanggan     | Tidak                                              |
| lengkap            | survey              | Tidak                                              |
| lengkap            | menunggu_pemasangan | Tidak                                              |
| siap_billing       | aktif               | Ya                                                 |
| siap_billing       | isolir              | Tidak untuk invoice baru, kecuali kebijakan khusus |
| siap_billing       | berhenti            | Tidak                                              |
| siap_billing       | nonaktif            | Tidak                                              |

## 3.3 Aturan Utama

1. Status kelengkapan menentukan apakah data valid.
2. Status layanan menentukan apakah layanan aktif.
3. Invoice membutuhkan data valid dan layanan aktif.
4. Payment membutuhkan invoice.
5. Laporan dapat membaca semua status, tetapi transaksi harus mengikuti aturan.

---

# 4. Status Invoice

## 4.1 Daftar Status Invoice

Status invoice:

1. `belum_dibayar`
2. `sebagian`
3. `lunas`
4. `batal`

## 4.2 Alur Status Invoice

```txt
belum_dibayar
→ sebagian
→ lunas
```

```txt
belum_dibayar
→ lunas
```

```txt
belum_dibayar
→ batal
```

```txt
sebagian
→ lunas
```

```txt
sebagian
→ batal
```

## 4.3 Aturan Status Invoice

1. Invoice baru default `belum_dibayar`.
2. Jika pembayaran valid kurang dari total invoice, status menjadi `sebagian`.
3. Jika pembayaran valid sama dengan total invoice, status menjadi `lunas`.
4. Jika pembayaran valid lebih dari total invoice, sistem harus memberi peringatan atau mencatat kelebihan.
5. Invoice lunas tidak boleh menerima pembayaran tambahan kecuali ada mekanisme koreksi.
6. Invoice batal tidak boleh menerima pembayaran.
7. Pembatalan invoice harus masuk audit log.

---

# 5. Status Pembayaran

## 5.1 Daftar Status Pembayaran

Status pembayaran:

1. `pending`
2. `valid`
3. `ditolak`

## 5.2 Alur Status Pembayaran

```txt
pending
→ valid
```

```txt
pending
→ ditolak
```

Untuk MVP, jika pembayaran langsung dianggap sah oleh kasir:

```txt
valid
```

## 5.3 Aturan Pembayaran

1. Pembayaran `valid` menambah paid amount invoice.
2. Pembayaran `pending` belum mengubah status invoice menjadi lunas.
3. Pembayaran `ditolak` tidak memengaruhi invoice.
4. Perubahan status pembayaran harus masuk audit log.
5. Finance/Kasir dapat mencatat pembayaran.
6. Teknisi tidak boleh mencatat pembayaran.
7. Customer Service tidak boleh memvalidasi pembayaran.

---

# 6. Status Import

## 6.1 Daftar Status Import Batch

Status import batch:

1. `uploaded`
2. `previewed`
3. `validated`
4. `imported`
5. `failed`
6. `cancelled`

## 6.2 Alur Import

```txt
uploaded
→ previewed
→ validated
→ imported
```

Atau jika gagal:

```txt
uploaded
→ previewed
→ failed
```

## 6.3 Aturan Import

1. Data tidak boleh masuk master pelanggan sebelum status `imported`.
2. Data invalid harus tercatat di import error.
3. Admin harus melihat preview sebelum import.
4. Import yang gagal tidak boleh menyimpan data valid sebagian tanpa catatan.
5. Import harus memiliki batch log.

---

# 7. Status POP

## 7.1 Daftar Status POP

Status POP:

1. `aktif`
2. `nonaktif`

## 7.2 Aturan POP

1. POP aktif dapat digunakan untuk pelanggan baru.
2. POP nonaktif tidak boleh digunakan untuk pelanggan baru.
3. POP nonaktif tetap dapat muncul di histori pelanggan lama.
4. POP yang masih memiliki pelanggan aktif sebaiknya tidak dihapus.
5. Gunakan nonaktif, bukan hard delete.

---

# 8. Status Paket Internet

## 8.1 Daftar Status Paket

Status paket:

1. `aktif`
2. `nonaktif`

## 8.2 Aturan Paket

1. Paket aktif dapat dipilih untuk pelanggan baru.
2. Paket nonaktif tidak dapat dipilih untuk pelanggan baru.
3. Paket nonaktif tetap boleh muncul pada histori pelanggan lama.
4. Perubahan harga paket tidak boleh mengubah invoice lama.
5. Harga layanan pelanggan harus disimpan sebagai snapshot.

---

# 9. Aturan Implementasi Status

AI/developer wajib:

1. Membuat status sebagai enum/constant agar tidak typo.
2. Tidak menulis string status sembarangan di banyak tempat.
3. Membuat helper/service untuk validasi transisi status.
4. Menulis audit log untuk perubahan status penting.
5. Menolak transisi status yang tidak valid.
6. Menampilkan pesan error yang jelas jika transisi ditolak.

---

# 10. Larangan Umum

1. Jangan membuat invoice untuk pelanggan yang belum siap billing.
2. Jangan membuat payment untuk invoice yang batal.
3. Jangan mengubah invoice lunas tanpa audit log.
4. Jangan membuat CID sebelum pelanggan aktif.
5. Jangan menghapus POP yang masih dipakai pelanggan aktif.
6. Jangan menghapus paket yang masih dipakai invoice lama.
7. Jangan mengizinkan user cabang mengubah data cabang lain.
