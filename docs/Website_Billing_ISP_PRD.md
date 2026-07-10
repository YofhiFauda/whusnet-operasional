# PRD — Website Billing ISP Berbasis Master Data Pelanggan Lengkap

## 1. Nama Produk

Website Billing ISP Berbasis Master Data Pelanggan

## 2. Ringkasan Produk

Website ini adalah sistem internal untuk perusahaan ISP yang digunakan untuk mengelola data pelanggan secara lengkap, lalu menggunakan data tersebut sebagai dasar pembuatan billing, tagihan, pembayaran, dan laporan.

Fokus utama sistem adalah memastikan seluruh data pelanggan lama maupun pelanggan baru dapat dimasukkan ke sistem baru dengan rapi, baik melalui:

1. Input manual langsung dari halaman sistem.
2. Import data massal melalui Excel/CSV.

Setelah data pelanggan tersimpan dan dianggap lengkap, barulah sistem dapat membuat tagihan berdasarkan paket layanan pelanggan tersebut.

Dengan kata lain, sistem ini memiliki prinsip utama:

```text
Data Pelanggan Lengkap
→ Paket Layanan Aktif
→ Tagihan
→ Pembayaran
→ Status Billing
```

## 3. Latar Belakang Masalah

Perusahaan ISP sudah memiliki data pelanggan lama dari sistem sebelumnya. Ketika membuat website billing baru, masalah utama bukan hanya membuat fitur pembayaran, tetapi memastikan data pelanggan lama bisa masuk ke sistem baru dan menjadi data master yang valid.

Permasalahan utama:

1. Data pelanggan lama masih berasal dari database lama.
2. Data pelanggan perlu dimasukkan ulang ke sistem baru.
3. Data bisa dimasukkan secara manual atau import Excel/CSV.
4. Sistem baru harus mampu menampung data pelanggan lengkap, bukan hanya nama dan tagihan.
5. Billing harus berdasarkan data pelanggan, bukan dibuat terpisah.
6. Struktur POP/Cabang perlu ada sejak awal.
7. Hak akses pengguna perlu diatur menggunakan RBAC.
8. Sistem harus bisa membedakan pelanggan yang datanya sudah lengkap dan belum lengkap.

## 4. Tujuan Produk

### 4.1 Tujuan Utama

Membangun sistem billing ISP baru yang menjadikan master data pelanggan lengkap sebagai pusat sistem, sehingga proses billing, pembayaran, laporan, dan operasional pelanggan dapat berjalan berdasarkan data yang valid.

### 4.2 Tujuan MVP

MVP adalah versi awal yang wajib dibuat terlebih dahulu. Fokus MVP:

1. Mengelola POP/Cabang.
2. Mengelola user dan hak akses.
3. Mengelola paket internet.
4. Menginput data pelanggan lengkap secara manual.
5. Mengimport data pelanggan lama dari Excel/CSV.
6. Memvalidasi kelengkapan data pelanggan.
7. Mengaktifkan layanan pelanggan berdasarkan paket.
8. Membuat tagihan berdasarkan data layanan pelanggan.
9. Mencatat pembayaran pelanggan.
10. Menampilkan dashboard sederhana.
11. Menampilkan laporan pelanggan, tagihan, dan pembayaran.

### 4.3 Tujuan Jangka Panjang

Setelah MVP stabil, sistem dapat dikembangkan menjadi:

1. Integrasi MikroTik.
2. Auto generate tagihan bulanan.
3. Auto suspend pelanggan.
4. Payment gateway.
5. Ticketing gangguan.
6. Modul teknisi lapangan.
7. Monitoring jaringan.
8. Inventory perangkat.
9. WhatsApp notification.
10. Aplikasi mobile teknisi.

## 5. Batasan Produk Tahap Awal

Pada tahap awal, sistem tidak perlu langsung memiliki:

1. Integrasi otomatis dengan MikroTik.
2. Integrasi payment gateway.
3. Auto suspend pelanggan.
4. Monitoring OLT/SNMP.
5. Aplikasi mobile.
6. Ticketing kompleks.
7. Inventory detail.
8. Laporan keuangan kompleks.
9. Multi-company.

Tahap awal hanya fokus pada:

```text
POP + RBAC + Paket + Data Pelanggan Lengkap + Import + Billing + Pembayaran
```

## 6. Prinsip Utama Sistem

### 6.1 Data Pelanggan Adalah Pusat Sistem

Semua proses dalam sistem harus berawal dari data pelanggan.

Billing tidak boleh berdiri sendiri tanpa pelanggan.

```text
Pelanggan
→ Paket
→ Layanan Aktif
→ Tagihan
→ Pembayaran
```

### 6.2 Billing Berdasarkan Data Pelanggan

Sistem hanya boleh membuat billing/tagihan jika pelanggan memiliki data minimal yang valid.

Data minimal:

1. Nama pelanggan.
2. Nomor HP.
3. Alamat pemasangan.
4. POP/Cabang.
5. Paket internet.
6. Harga layanan.
7. Tanggal aktivasi atau tanggal mulai tagihan.
8. Tanggal jatuh tempo.
9. Status layanan.

### 6.3 Input Manual dan Import Masuk ke Struktur yang Sama

Data pelanggan yang berasal dari input manual maupun import Excel/CSV harus masuk ke struktur database yang sama.

```text
Input Manual ─┐
              ├── Validasi Data → Master Data Pelanggan
Import Excel ─┘
```

Tidak boleh ada perbedaan struktur antara pelanggan hasil input manual dan pelanggan hasil import.

### 6.4 Data Belum Lengkap Tidak Boleh Langsung Masuk Billing

Jika data pelanggan belum lengkap, pelanggan tetap boleh disimpan, tetapi statusnya harus menjadi:

```text
Draft / Perlu Dilengkapi
```

Pelanggan baru bisa masuk proses billing jika status datanya sudah:

```text
Lengkap / Siap Billing
```

## 7. Struktur POP/Cabang

### 7.1 Tujuan POP/Cabang

POP/Cabang digunakan untuk mengelompokkan pelanggan berdasarkan wilayah operasional ISP.

Fungsi POP/Cabang:

1. Menentukan wilayah pelanggan.
2. Membatasi akses admin cabang.
3. Membuat laporan per cabang.
4. Mengelompokkan teknisi.
5. Menjadi dasar pengembangan jaringan ke depan.

### 7.2 Struktur POP yang Disarankan

```text
Perusahaan / Pusat
└── POP Pusat
    └── POP Cabang
        └── Mini POP
            └── Pelanggan
```

### 7.3 Field POP/Cabang

| Field | Keterangan |
|---|---|
| ID POP | ID unik POP |
| Nama POP | Nama cabang/POP |
| Tipe POP | Pusat, Cabang, Mini POP |
| Parent POP | Relasi ke POP di atasnya |
| Alamat POP | Alamat lokasi POP |
| Desa/Kelurahan | Lokasi |
| Kecamatan | Lokasi |
| Kota/Kabupaten | Lokasi |
| Latitude | Koordinat |
| Longitude | Koordinat |
| PIC POP | Penanggung jawab |
| Nomor HP PIC | Kontak PIC |
| Status POP | Aktif / Nonaktif |

## 8. Role dan Hak Akses

### 8.1 Role Utama

Sistem minimal memiliki role:

1. Owner
2. Admin Pusat
3. Admin Cabang
4. Finance/Kasir
5. Teknisi
6. Customer Service

### 8.2 Penjelasan Role

#### Owner

Memiliki akses penuh ke seluruh sistem.

Hak akses:

- Melihat semua data.
- Mengelola semua POP.
- Mengelola semua user.
- Mengelola role dan permission.
- Mengelola pelanggan.
- Mengelola tagihan.
- Mengelola pembayaran.
- Melihat semua laporan.

#### Admin Pusat

Mengelola operasional utama lintas cabang.

Hak akses:

- Mengelola POP.
- Mengelola pelanggan semua cabang.
- Mengelola paket.
- Mengelola tagihan.
- Mengelola pembayaran.
- Melihat laporan semua cabang.

#### Admin Cabang

Mengelola data pelanggan di cabangnya sendiri.

Hak akses:

- Melihat pelanggan cabangnya.
- Menambah pelanggan cabang.
- Mengedit data pelanggan cabang.
- Melihat tagihan cabang.
- Melihat pembayaran cabang.
- Tidak bisa melihat cabang lain.

#### Finance/Kasir

Mengelola tagihan dan pembayaran.

Hak akses:

- Melihat data pelanggan.
- Melihat tagihan.
- Mencatat pembayaran.
- Upload bukti pembayaran.
- Cetak invoice/kwitansi.
- Tidak bisa mengubah data teknis pelanggan.

#### Teknisi

Mengelola data teknis pelanggan.

Hak akses:

- Melihat pelanggan yang ditugaskan.
- Mengisi data survey.
- Mengisi data pemasangan.
- Mengisi data modem/ONT/router.
- Tidak bisa mencatat pembayaran.

#### Customer Service

Mengelola informasi dasar pelanggan dan membantu pelayanan.

Hak akses:

- Melihat data pelanggan.
- Mengubah data kontak pelanggan.
- Melihat status pembayaran.
- Tidak bisa mengubah nominal tagihan.
- Tidak bisa validasi pembayaran.

### 8.3 Matriks Hak Akses

| Fitur | Owner | Admin Pusat | Admin Cabang | Finance | Teknisi | CS |
|---|---|---|---|---|---|---|
| Kelola POP | Ya | Ya | Tidak | Tidak | Tidak | Tidak |
| Kelola User | Ya | Ya | Tidak | Tidak | Tidak | Tidak |
| Kelola Role | Ya | Tidak | Tidak | Tidak | Tidak | Tidak |
| Kelola Paket | Ya | Ya | Tidak | Tidak | Tidak | Tidak |
| Input Pelanggan | Ya | Ya | Ya | Tidak | Tidak | Ya |
| Import Pelanggan | Ya | Ya | Ya | Tidak | Tidak | Tidak |
| Edit Data Pelanggan | Ya | Ya | Ya | Terbatas | Terbatas | Terbatas |
| Lihat Data Pelanggan | Ya | Ya | Ya | Ya | Ya | Ya |
| Validasi Kelengkapan Data | Ya | Ya | Ya | Tidak | Tidak | Tidak |
| Buat Tagihan | Ya | Ya | Ya | Ya | Tidak | Tidak |
| Catat Pembayaran | Ya | Ya | Ya | Ya | Tidak | Tidak |
| Edit Pembayaran | Ya | Ya | Tidak | Terbatas | Tidak | Tidak |
| Isi Survey | Ya | Ya | Tidak | Tidak | Ya | Tidak |
| Isi Pemasangan | Ya | Ya | Tidak | Tidak | Ya | Tidak |
| Isi Setting Modem | Ya | Ya | Tidak | Tidak | Ya | Tidak |
| Lihat Laporan Semua Cabang | Ya | Ya | Tidak | Tidak | Tidak | Tidak |
| Lihat Laporan Cabang Sendiri | Ya | Ya | Ya | Ya | Tidak | Ya |

## 9. Status Data Pelanggan

Sistem harus membedakan status pelanggan dari sisi kelengkapan data dan status layanan.

### 9.1 Status Kelengkapan Data

| Status | Penjelasan |
|---|---|
| Draft | Data baru dibuat, masih sangat belum lengkap |
| Perlu Dilengkapi | Ada field wajib yang belum terisi |
| Lengkap | Data utama pelanggan sudah lengkap |
| Siap Billing | Data lengkap dan pelanggan sudah punya paket aktif |

### 9.2 Status Layanan Pelanggan

| Status | Penjelasan |
|---|---|
| Calon Pelanggan | Baru didaftarkan, belum aktif |
| Survey | Menunggu atau sedang proses survey |
| Menunggu Pemasangan | Sudah survey, belum dipasang |
| Aktif | Layanan aktif dan bisa ditagih |
| Isolir | Layanan dihentikan sementara |
| Nonaktif | Layanan tidak aktif |
| Berhenti | Pelanggan sudah berhenti berlangganan |

## 10. Modul Utama MVP

### 10.1 Modul Master POP/Cabang

#### Deskripsi

Modul ini digunakan untuk membuat struktur wilayah operasional pelanggan.

#### Requirement

1. Admin dapat membuat POP Pusat.
2. Admin dapat membuat POP Cabang.
3. Admin dapat membuat Mini POP.
4. Pelanggan wajib dihubungkan ke salah satu POP.
5. User cabang dapat dibatasi hanya ke POP tertentu.

#### Acceptance Criteria

- POP dapat dibuat, diedit, dan dinonaktifkan.
- POP dapat memiliki parent-child.
- Pelanggan dapat difilter berdasarkan POP.
- Admin Cabang hanya melihat pelanggan POP yang ditugaskan.

### 10.2 Modul RBAC

#### Deskripsi

Modul ini digunakan untuk mengatur akses user berdasarkan role dan permission.

#### Requirement

1. Sistem memiliki role.
2. Sistem memiliki permission.
3. User dapat memiliki role.
4. Role dapat memiliki banyak permission.
5. User dapat dibatasi berdasarkan POP.

#### Acceptance Criteria

- User hanya melihat menu sesuai hak akses.
- User tidak bisa membuka URL fitur yang tidak diizinkan.
- Admin cabang tidak bisa melihat data cabang lain.
- Teknisi tidak bisa membuka menu pembayaran.
- Finance tidak bisa mengubah data modem.

### 10.3 Modul Master Paket Internet

#### Deskripsi

Modul paket menyimpan daftar layanan internet yang dapat dipilih pelanggan.

#### Field Paket

| Field | Keterangan |
|---|---|
| Nama Paket | Nama paket |
| Kategori Paket | Home, Business, Dedicated, Promo |
| Kecepatan Download | Mbps |
| Kecepatan Upload | Mbps |
| Harga Bulanan | Harga paket |
| PPN | Jika ada |
| Diskon Default | Jika ada |
| Total Harga | Harga final |
| Profile Teknis | Opsional |
| Deskripsi | Keterangan paket |
| Status | Aktif / Nonaktif |

#### Acceptance Criteria

- Paket dapat dibuat dan diedit.
- Paket dapat dinonaktifkan.
- Paket dapat dipilih saat input pelanggan.
- Harga paket dapat menjadi dasar tagihan.

### 10.4 Modul Input Manual Data Pelanggan

#### Deskripsi

Modul ini digunakan untuk memasukkan data pelanggan satu per satu secara langsung dari website.

Data pelanggan harus dibuat dalam bentuk form bertahap/tab agar tidak terlalu panjang.

#### Struktur Form Pelanggan

Form pelanggan dibagi menjadi:

1. Data Identitas.
2. Data Alamat.
3. Data POP/Cabang.
4. Data Paket/Layanan.
5. Data Survey.
6. Data Pemasangan.
7. Data Modem/Perangkat.
8. Data Billing.
9. Dokumen Pendukung.

#### Acceptance Criteria

- Admin dapat menyimpan data pelanggan walaupun belum lengkap.
- Sistem menandai field wajib yang belum diisi.
- Sistem memberi status kelengkapan data.
- Data pelanggan belum lengkap tidak bisa masuk billing aktif.
- Data pelanggan lengkap bisa diubah menjadi siap billing.

### 10.5 Modul Import Excel/CSV Data Pelanggan

#### Deskripsi

Modul ini digunakan untuk memasukkan data pelanggan lama secara massal dari file Excel/CSV.

#### Alur Import

```text
Admin Upload File
→ Sistem Membaca File
→ Sistem Mapping Kolom
→ Sistem Validasi Data
→ Sistem Menampilkan Preview
→ Admin Konfirmasi Import
→ Data Masuk ke Master Pelanggan
→ Sistem Membuat Log Import
```

#### Requirement

1. Sistem menyediakan template Excel/CSV.
2. Admin dapat upload file.
3. Sistem membaca data pelanggan.
4. Sistem menampilkan preview.
5. Sistem menandai data valid dan invalid.
6. Sistem mencegah duplikasi.
7. Sistem menyimpan ID pelanggan lama.
8. Sistem menyimpan log batch import.

#### Field Import Minimum

| Kolom | Wajib | Keterangan |
|---|---|---|
| ID Pelanggan Lama | Ya | Referensi dari sistem lama |
| Nama Lengkap | Ya | Nama pelanggan |
| Nomor HP | Ya | Kontak pelanggan |
| Alamat | Ya | Alamat pemasangan |
| POP/Cabang | Ya | Cabang pelanggan |
| Nama Paket | Ya | Paket pelanggan |
| Harga Paket | Ya | Harga layanan |
| Tanggal Aktivasi | Ya | Tanggal mulai layanan |
| Tanggal Jatuh Tempo | Ya | Tanggal jatuh tempo |
| Status Layanan | Ya | Aktif, isolir, nonaktif |
| Username PPPoE | Tidak | Jika ada |
| Password PPPoE | Tidak | Jika ada |
| ODP | Tidak | Jika ada |
| Port ODP | Tidak | Jika ada |
| Serial Number Modem | Tidak | Jika ada |

#### Validasi Import

Sistem harus mengecek:

1. ID pelanggan lama tidak duplikat.
2. Nama pelanggan tidak kosong.
3. Nomor HP tidak kosong.
4. POP/Cabang tersedia di master POP.
5. Paket tersedia di master paket.
6. Harga paket berupa angka.
7. Tanggal valid.
8. Status layanan sesuai pilihan sistem.
9. Data teknis boleh kosong tetapi ditandai belum lengkap.

#### Acceptance Criteria

- Admin dapat mengupload file Excel/CSV.
- Sistem menampilkan data preview sebelum import.
- Sistem menolak data yang tidak valid.
- Sistem menjelaskan alasan data gagal.
- Sistem menyimpan data valid ke master pelanggan.
- Sistem menyimpan log import.
- Data hasil import bisa diedit seperti data input manual.

### 10.6 Modul Master Data Pelanggan

#### Deskripsi

Modul pelanggan adalah inti utama sistem. Semua billing, pembayaran, layanan, survey, pemasangan, dan modem harus terhubung ke pelanggan.

#### 10.6.1 Data Identitas Pelanggan

| Field | Wajib MVP | Keterangan |
|---|---|---|
| ID Pelanggan Baru | Ya | Dibuat otomatis oleh sistem |
| ID Pelanggan Lama | Tidak | Untuk data migrasi |
| Nama Lengkap | Ya | Nama pelanggan |
| NIK/Nomor Identitas | Tidak | Bisa dilengkapi |
| Jenis Kelamin | Tidak | Opsional |
| Nomor HP Utama | Ya | Kontak utama |
| Nomor HP Alternatif | Tidak | Opsional |
| Email | Tidak | Opsional |
| Tanggal Registrasi | Ya | Tanggal pelanggan masuk |
| Status Kelengkapan Data | Ya | Draft, perlu dilengkapi, lengkap |
| Status Pelanggan | Ya | Aktif, isolir, nonaktif, dll |

#### 10.6.2 Data Alamat Pelanggan

| Field | Wajib MVP | Keterangan |
|---|---|---|
| Alamat Lengkap | Ya | Alamat pemasangan |
| Desa/Kelurahan | Ya | Lokasi |
| Kecamatan | Ya | Lokasi |
| Kota/Kabupaten | Ya | Lokasi |
| Provinsi | Tidak | Opsional |
| Latitude | Tidak | Bisa dilengkapi |
| Longitude | Tidak | Bisa dilengkapi |
| Foto Rumah | Tidak | Opsional |
| Foto KTP | Tidak | Opsional |
| Foto Kontrak | Tidak | Opsional |

#### 10.6.3 Data POP/Cabang Pelanggan

| Field | Wajib MVP | Keterangan |
|---|---|---|
| POP Pusat | Ya | Relasi ke POP |
| POP Cabang | Ya | Relasi ke cabang |
| Mini POP | Tidak | Jika ada |
| Area/Wilayah | Tidak | Opsional |
| PIC Cabang | Tidak | Bisa otomatis dari POP |

#### 10.6.4 Data Paket/Layanan Pelanggan

| Field | Wajib MVP | Keterangan |
|---|---|---|
| Paket Internet | Ya | Relasi ke master paket |
| Kecepatan Download | Ya | Dari paket |
| Kecepatan Upload | Ya | Dari paket |
| Harga Bulanan | Ya | Harga saat pelanggan aktif |
| Diskon | Tidak | Jika ada |
| PPN | Tidak | Jika ada |
| Total Tagihan Bulanan | Ya | Harga final |
| Tanggal Aktivasi | Ya | Mulai layanan |
| Tanggal Jatuh Tempo | Ya | Tanggal bayar |
| Siklus Tagihan | Ya | Bulanan |
| Status Layanan | Ya | Aktif, suspend, berhenti |

#### 10.6.5 Data Survey

| Field | Wajib MVP | Keterangan |
|---|---|---|
| Status Survey | Tidak | Belum survey, layak, tidak layak |
| Tanggal Survey | Tidak | Opsional |
| Jam Mulai Survey | Tidak | Opsional |
| Jam Selesai Survey | Tidak | Opsional |
| Petugas Survey | Tidak | Teknisi |
| Kebutuhan Alat | Tidak | Kabel, konektor, dll |
| Estimasi Kabel | Tidak | Meter |
| ODP Terdekat | Tidak | Jika ada |
| Foto Survey | Tidak | Opsional |
| Catatan Survey | Tidak | Hasil survey |

#### 10.6.6 Data Pemasangan

| Field | Wajib MVP | Keterangan |
|---|---|---|
| Status Pemasangan | Tidak | Belum dipasang, dijadwalkan, selesai |
| Tanggal Jadwal | Tidak | Opsional |
| Jam Jadwal | Tidak | Opsional |
| Teknisi Pemasangan | Tidak | Petugas |
| Tanggal Selesai | Tidak | Jika selesai |
| Foto Pemasangan | Tidak | Opsional |
| Catatan Pemasangan | Tidak | Keterangan teknisi |

#### 10.6.7 Data Modem/ONT/Router

| Field | Wajib MVP | Keterangan |
|---|---|---|
| Jenis Perangkat | Tidak | Modem/ONT/Router |
| Merk | Tidak | Contoh ZTE, Huawei, TP-Link |
| Tipe | Tidak | Model perangkat |
| Serial Number | Tidak | SN perangkat |
| MAC Address | Tidak | Jika ada |
| Username PPPoE | Tidak | Jika pakai PPPoE |
| Password PPPoE | Tidak | Field sensitif |
| SSID WiFi | Tidak | Nama WiFi |
| Password WiFi | Tidak | Field sensitif |
| IP Address | Tidak | Jika static |
| VLAN ID | Tidak | Jika ada |
| ODP | Tidak | Titik distribusi |
| Port ODP | Tidak | Nomor port |
| Redaman | Tidak | Jika dicatat |
| Mode Koneksi | Tidak | Bridge/Router |
| Catatan Teknis | Tidak | Keterangan tambahan |

#### 10.6.8 Data Billing Pelanggan

| Field | Wajib MVP | Keterangan |
|---|---|---|
| Tanggal Mulai Billing | Ya | Awal penagihan |
| Tanggal Jatuh Tempo | Ya | Tanggal bayar |
| Periode Tagihan | Ya | Bulanan |
| Nominal Tagihan Default | Ya | Dari harga layanan |
| Status Billing | Ya | Aktif, ditahan, berhenti |
| Metode Pembayaran Default | Tidak | Cash, transfer, dll |
| Catatan Billing | Tidak | Opsional |

#### Acceptance Criteria Master Pelanggan

- Data pelanggan bisa dibuat manual.
- Data pelanggan bisa berasal dari import.
- Data pelanggan memiliki status kelengkapan.
- Pelanggan belum lengkap tetap bisa disimpan.
- Pelanggan belum lengkap tidak bisa dibuatkan tagihan aktif.
- Pelanggan lengkap bisa masuk status siap billing.
- Semua data pelanggan dapat dicari dan difilter.
- Detail pelanggan menampilkan semua tab data.

### 10.7 Modul Validasi Kelengkapan Data Pelanggan

#### Deskripsi

Modul ini mengecek apakah data pelanggan sudah cukup lengkap untuk masuk proses billing.

#### Field Wajib Agar Siap Billing

Pelanggan hanya bisa menjadi Siap Billing jika field berikut sudah terisi:

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

#### Field Teknis yang Disarankan Tetapi Tidak Wajib MVP

1. Username PPPoE.
2. Password PPPoE.
3. Serial Number modem.
4. ODP.
5. Port ODP.
6. SSID WiFi.
7. Password WiFi.

#### Acceptance Criteria

- Sistem dapat menampilkan persentase kelengkapan data.
- Sistem menampilkan daftar field yang belum lengkap.
- Sistem mencegah pelanggan belum lengkap masuk billing aktif.
- Admin dapat melihat daftar pelanggan yang perlu dilengkapi.

### 10.8 Modul Aktivasi Layanan Pelanggan

#### Deskripsi

Modul ini digunakan untuk mengubah pelanggan yang datanya lengkap menjadi pelanggan aktif yang siap ditagih.

#### Alur Aktivasi

```text
Data Pelanggan Lengkap
→ Admin Review
→ Pilih Paket Aktif
→ Tentukan Tanggal Aktivasi
→ Tentukan Tanggal Jatuh Tempo
→ Aktifkan Layanan
→ Pelanggan Masuk Siap Billing/Aktif
```

#### Acceptance Criteria

- Pelanggan tidak bisa diaktifkan jika data wajib belum lengkap.
- Pelanggan aktif harus memiliki paket.
- Pelanggan aktif harus memiliki nominal tagihan.
- Tanggal jatuh tempo wajib ada.
- Sistem menyimpan riwayat aktivasi.

### 10.9 Modul Tagihan

#### Deskripsi

Tagihan dibuat berdasarkan data layanan aktif milik pelanggan.

#### Prinsip Tagihan

Tagihan tidak dibuat manual dari nol. Tagihan harus berasal dari:

```text
Pelanggan Aktif + Paket Aktif + Harga Layanan + Periode Tagihan
```

#### Field Tagihan

| Field | Keterangan |
|---|---|
| Nomor Invoice | Nomor tagihan |
| Pelanggan | Relasi ke pelanggan |
| POP/Cabang | Mengikuti pelanggan |
| Paket | Paket pelanggan |
| Periode Tagihan | Bulan/tahun tagihan |
| Tanggal Terbit | Tanggal invoice dibuat |
| Tanggal Jatuh Tempo | Tanggal bayar |
| Subtotal | Harga layanan |
| Diskon | Jika ada |
| PPN | Jika ada |
| Total Tagihan | Total akhir |
| Status Tagihan | Belum dibayar, sebagian, lunas, batal |

#### Cara Pembuatan Tagihan MVP

Untuk MVP, ada dua opsi:

##### Opsi 1 — Manual per Pelanggan

Admin/Finance membuka pelanggan lalu membuat tagihan.

Cocok untuk awal development.

##### Opsi 2 — Generate Bulanan Sederhana

Sistem membuat tagihan untuk semua pelanggan aktif pada periode tertentu.

Cocok jika data pelanggan sudah rapi.

#### Rekomendasi MVP

Gunakan manual per pelanggan dahulu, lalu setelah stabil lanjut ke generate bulanan.

#### Acceptance Criteria

- Tagihan hanya bisa dibuat untuk pelanggan aktif/siap billing.
- Tagihan mengambil harga dari layanan pelanggan.
- Tagihan memiliki periode.
- Tagihan memiliki status.
- Tagihan dapat difilter berdasarkan POP, periode, status, dan pelanggan.

### 10.10 Modul Pembayaran

#### Deskripsi

Pembayaran digunakan untuk mencatat pelunasan tagihan pelanggan.

#### Field Pembayaran

| Field | Keterangan |
|---|---|
| Nomor Pembayaran | ID pembayaran |
| Nomor Invoice | Relasi ke tagihan |
| Pelanggan | Relasi ke pelanggan |
| POP/Cabang | Mengikuti pelanggan |
| Tanggal Bayar | Tanggal pembayaran |
| Metode Bayar | Cash, transfer, QRIS, dll |
| Nominal Bayar | Jumlah pembayaran |
| Penerima | User yang mencatat |
| Bukti Pembayaran | Opsional |
| Status Pembayaran | Pending, valid, ditolak |
| Catatan | Keterangan |

#### Aturan Pembayaran

1. Pembayaran harus terhubung ke pelanggan.
2. Pembayaran idealnya terhubung ke invoice.
3. Jika nominal sama dengan total tagihan, status tagihan menjadi lunas.
4. Jika nominal kurang dari total tagihan, status tagihan menjadi dibayar sebagian.
5. Jika pembayaran ditolak, status tagihan tidak berubah menjadi lunas.
6. Perubahan pembayaran harus masuk audit log.

#### Acceptance Criteria

- Finance dapat mencatat pembayaran.
- Pembayaran muncul di detail pelanggan.
- Status tagihan berubah sesuai nominal pembayaran.
- Bukti pembayaran dapat diupload.
- Pembayaran dapat difilter berdasarkan tanggal, POP, metode, dan status.

### 10.11 Modul Dashboard

#### Deskripsi

Dashboard menampilkan ringkasan data pelanggan dan billing.

#### Isi Dashboard MVP

1. Total pelanggan.
2. Total pelanggan aktif.
3. Total pelanggan belum lengkap.
4. Total pelanggan siap billing.
5. Total pelanggan per POP.
6. Total tagihan bulan ini.
7. Total pembayaran bulan ini.
8. Total tunggakan.
9. Tagihan jatuh tempo.
10. Data pelanggan yang perlu dilengkapi.

#### Acceptance Criteria

- Owner melihat semua data.
- Admin pusat melihat semua cabang.
- Admin cabang hanya melihat cabangnya.
- Dashboard dapat difilter berdasarkan POP dan periode.

### 10.12 Modul Laporan

#### Laporan MVP

Laporan yang dibutuhkan:

1. Laporan pelanggan lengkap.
2. Laporan pelanggan belum lengkap.
3. Laporan pelanggan aktif.
4. Laporan pelanggan isolir.
5. Laporan pelanggan per POP.
6. Laporan tagihan bulanan.
7. Laporan pembayaran bulanan.
8. Laporan tunggakan.
9. Laporan pembayaran per metode.
10. Laporan import data.

#### Acceptance Criteria

- Laporan dapat difilter berdasarkan tanggal.
- Laporan dapat difilter berdasarkan POP.
- Laporan dapat diexport ke Excel/CSV.
- Admin cabang hanya bisa export data cabangnya.

### 10.13 Modul Audit Log

#### Deskripsi

Audit log menyimpan riwayat perubahan data penting.

#### Data yang Dicatat

1. User yang melakukan perubahan.
2. Waktu perubahan.
3. Modul yang diubah.
4. Data sebelum perubahan.
5. Data setelah perubahan.
6. Jenis aksi: create, update, delete, import, payment validation.
7. IP address jika tersedia.

#### Modul yang Wajib Diaudit

1. Pelanggan.
2. Paket.
3. POP.
4. Tagihan.
5. Pembayaran.
6. User.
7. Role.
8. Data modem/teknis.

#### Acceptance Criteria

- Perubahan pelanggan tercatat.
- Perubahan pembayaran tercatat.
- Perubahan tagihan tercatat.
- Perubahan role tercatat.
- Owner/Admin Pusat dapat melihat audit log.

## 11. Alur Utama Sistem

### 11.1 Flow Input Manual Pelanggan

```text
Admin Login
→ Buka Menu Pelanggan
→ Klik Tambah Pelanggan
→ Isi Data Identitas
→ Isi Data Alamat
→ Pilih POP/Cabang
→ Pilih Paket
→ Isi Data Billing Dasar
→ Simpan
→ Sistem Validasi Kelengkapan
→ Jika Belum Lengkap: Status Perlu Dilengkapi
→ Jika Lengkap: Status Siap Billing
```

### 11.2 Flow Import Pelanggan Lama

```text
Admin Login
→ Buka Menu Import Pelanggan
→ Download Template
→ Isi Data dari Database Lama
→ Upload Excel/CSV
→ Sistem Membaca File
→ Sistem Mapping Kolom
→ Sistem Validasi Data
→ Sistem Menampilkan Preview
→ Admin Konfirmasi Import
→ Data Valid Masuk Master Pelanggan
→ Data Invalid Masuk Daftar Error
→ Sistem Membuat Log Import
```

### 11.3 Flow Validasi Data Pelanggan

```text
Pelanggan Disimpan
→ Sistem Mengecek Field Wajib
→ Jika Ada Field Kosong
    → Status Kelengkapan = Perlu Dilengkapi
    → Billing Belum Bisa Aktif
→ Jika Semua Field Wajib Terisi
    → Status Kelengkapan = Lengkap
    → Admin Bisa Menjadikan Siap Billing
```

### 11.4 Flow Aktivasi Billing

```text
Admin Buka Detail Pelanggan
→ Sistem Cek Kelengkapan Data
→ Jika Lengkap
    → Admin Klik Aktifkan Billing
    → Sistem Menyimpan Layanan Aktif
    → Sistem Menentukan Nominal Tagihan
    → Pelanggan Menjadi Aktif/Siap Ditagih
→ Jika Belum Lengkap
    → Sistem Menolak Aktivasi
    → Sistem Menampilkan Field yang Kurang
```

### 11.5 Flow Pembuatan Tagihan

```text
Finance/Admin Buka Data Pelanggan
→ Pilih Buat Tagihan
→ Sistem Mengambil Paket Aktif
→ Sistem Mengambil Harga Layanan
→ Sistem Mengambil Tanggal Jatuh Tempo
→ Sistem Membuat Invoice
→ Status Invoice = Belum Dibayar
```

### 11.6 Flow Pembayaran

```text
Finance Login
→ Cari Pelanggan / Invoice
→ Buka Tagihan
→ Input Nominal Pembayaran
→ Pilih Metode Pembayaran
→ Upload Bukti Jika Ada
→ Simpan
→ Sistem Mengupdate Status Tagihan
→ Riwayat Pembayaran Tersimpan
```

## 12. Struktur Halaman Sistem

### 12.1 Dashboard

Isi:

- Ringkasan pelanggan.
- Ringkasan kelengkapan data.
- Ringkasan tagihan.
- Ringkasan pembayaran.
- Tagihan jatuh tempo.
- Pelanggan yang datanya belum lengkap.

### 12.2 POP/Cabang

Isi:

- Daftar POP.
- Tambah POP.
- Edit POP.
- Detail POP.
- Jumlah pelanggan per POP.
- Status POP.

### 12.3 User & Role

Isi:

- Daftar user.
- Tambah user.
- Pilih role.
- Assign POP.
- Permission role.

### 12.4 Paket Internet

Isi:

- Daftar paket.
- Tambah paket.
- Edit paket.
- Status aktif/nonaktif.

### 12.5 Pelanggan

Isi:

- Daftar pelanggan.
- Search nama/ID/nomor HP.
- Filter POP.
- Filter status pelanggan.
- Filter status kelengkapan data.
- Tambah pelanggan manual.
- Import pelanggan.
- Export pelanggan.

### 12.6 Detail Pelanggan

Detail pelanggan sebaiknya memakai tab:

1. Ringkasan.
2. Identitas.
3. Alamat.
4. POP/Cabang.
5. Paket & Layanan.
6. Survey.
7. Pemasangan.
8. Modem/Perangkat.
9. Billing.
10. Tagihan.
11. Pembayaran.
12. Dokumen.
13. Riwayat Perubahan.

### 12.7 Import Data

Isi:

- Download template.
- Upload file.
- Mapping kolom.
- Preview data.
- Validasi error.
- Tombol import.
- Riwayat import.

### 12.8 Tagihan

Isi:

- Daftar invoice.
- Filter periode.
- Filter status.
- Filter POP.
- Detail invoice.
- Tombol input pembayaran.

### 12.9 Pembayaran

Isi:

- Daftar pembayaran.
- Filter tanggal.
- Filter metode.
- Filter POP.
- Status pembayaran.
- Bukti pembayaran.

### 12.10 Laporan

Isi:

- Laporan pelanggan.
- Laporan tagihan.
- Laporan pembayaran.
- Laporan tunggakan.
- Export Excel/CSV.

## 13. Struktur Database Konseptual

Entitas utama:

1. users
2. roles
3. permissions
4. role_permissions
5. pops
6. user_pops
7. internet_packages
8. customers
9. customer_addresses
10. customer_services
11. customer_surveys
12. customer_installations
13. customer_devices
14. customer_documents
15. invoices
16. payments
17. import_batches
18. import_errors
19. audit_logs

## 14. Relasi Data

- User memiliki Role.
- Role memiliki banyak Permission.
- User dapat memiliki akses ke banyak POP.
- POP memiliki banyak Customer.
- POP dapat memiliki parent POP.
- Customer memiliki Address.
- Customer memiliki Service.
- Customer memiliki Survey.
- Customer memiliki Installation.
- Customer memiliki Device.
- Customer memiliki Document.
- Customer memiliki banyak Invoice.
- Customer memiliki banyak Payment.
- Internet Package digunakan oleh Customer Service.
- Invoice dimiliki Customer.
- Invoice memiliki banyak Payment.
- Import Batch memiliki banyak Import Error.
- Audit Log mencatat perubahan data penting.

## 15. Aturan Bisnis Utama

### 15.1 Aturan Data Pelanggan

1. Setiap pelanggan wajib memiliki ID pelanggan baru.
2. Jika berasal dari data lama, pelanggan menyimpan ID pelanggan lama.
3. Pelanggan wajib memiliki POP/Cabang.
4. Pelanggan wajib memiliki paket sebelum masuk billing.
5. Pelanggan belum lengkap tetap bisa disimpan.
6. Pelanggan belum lengkap tidak bisa dibuatkan tagihan aktif.
7. Pelanggan lengkap bisa masuk status siap billing.

### 15.2 Aturan Import

1. Import tidak boleh langsung merusak data utama.
2. Sistem harus melakukan validasi sebelum import.
3. Data invalid tidak boleh masuk master pelanggan.
4. Data duplicate harus ditandai.
5. Semua proses import harus memiliki log.
6. Data hasil import harus bisa diedit manual.

### 15.3 Aturan Billing

1. Billing hanya dibuat dari pelanggan yang memiliki layanan aktif.
2. Tagihan mengambil nominal dari paket pelanggan.
3. Tagihan memiliki periode.
4. Tagihan memiliki tanggal jatuh tempo.
5. Tagihan tidak boleh dibuat dobel untuk periode yang sama.
6. Tagihan lunas tidak boleh dihapus sembarangan.

### 15.4 Aturan Pembayaran

1. Pembayaran harus terhubung ke invoice.
2. Pembayaran harus terhubung ke pelanggan.
3. Pembayaran harus memiliki nominal.
4. Pembayaran harus memiliki tanggal bayar.
5. Pembayaran lunas mengubah status invoice menjadi lunas.
6. Pembayaran sebagian mengubah status invoice menjadi dibayar sebagian.
7. Perubahan pembayaran harus masuk audit log.

### 15.5 Aturan RBAC

1. User hanya bisa membuka fitur sesuai permission.
2. User cabang hanya bisa melihat data POP yang ditugaskan.
3. Teknisi tidak boleh mencatat pembayaran.
4. Finance tidak boleh mengubah data modem.
5. Customer Service tidak boleh mengubah nominal tagihan.
6. Owner memiliki akses penuh.

## 16. Prioritas Development

### 16.1 Sprint 1 — Pondasi Sistem

Fitur:

1. Setup project.
2. Login.
3. User.
4. Role.
5. Permission.
6. RBAC dasar.
7. Layout dashboard admin.

Output:

- User bisa login.
- Role bisa dibuat.
- Permission bisa digunakan.
- Menu tampil berdasarkan role.

### 16.2 Sprint 2 — POP dan Paket

Fitur:

1. Master POP/Cabang.
2. Parent-child POP.
3. Assign user ke POP.
4. Master paket internet.

Output:

- POP bisa dibuat.
- Paket bisa dibuat.
- User bisa dibatasi per POP.

### 16.3 Sprint 3 — Master Data Pelanggan Manual

Fitur:

1. Form input pelanggan.
2. Data identitas.
3. Data alamat.
4. Data POP.
5. Data paket/layanan.
6. Status kelengkapan data.

Output:

- Admin bisa input pelanggan manual.
- Sistem menandai data lengkap/belum lengkap.

### 16.4 Sprint 4 — Import Excel/CSV

Fitur:

1. Template import.
2. Upload Excel/CSV.
3. Preview import.
4. Validasi data.
5. Import batch.
6. Import error.

Output:

- Data lama bisa masuk ke sistem.
- Data error bisa dilihat.
- Data hasil import bisa diedit.

### 16.5 Sprint 5 — Billing Dasar

Fitur:

1. Aktivasi billing pelanggan.
2. Buat tagihan manual.
3. Cek tagihan dobel.
4. Status tagihan.

Output:

- Pelanggan lengkap bisa dibuatkan tagihan.
- Pelanggan belum lengkap tidak bisa dibuatkan tagihan.

### 16.6 Sprint 6 — Pembayaran

Fitur:

1. Input pembayaran.
2. Upload bukti pembayaran.
3. Status pembayaran.
4. Update status invoice.
5. Riwayat pembayaran pelanggan.

Output:

- Pembayaran bisa dicatat.
- Invoice bisa lunas/sebagian.
- Riwayat pembayaran tampil di detail pelanggan.

### 16.7 Sprint 7 — Dashboard dan Laporan

Fitur:

1. Dashboard ringkasan.
2. Laporan pelanggan.
3. Laporan tagihan.
4. Laporan pembayaran.
5. Export Excel/CSV.

Output:

- Admin bisa melihat kondisi pelanggan dan billing.
- Laporan bisa digunakan operasional.

### 16.8 Sprint 8 — Data Teknis Pelanggan

Fitur:

1. Data survey.
2. Data pemasangan.
3. Data modem/ONT/router.
4. Data dokumen pelanggan.

Output:

- Data pelanggan menjadi lebih lengkap.
- Teknisi bisa mengisi data teknis.

## 17. MVP Final yang Disarankan

MVP final yang paling sesuai dengan masalah ini adalah:

1. Login.
2. RBAC.
3. POP/Cabang.
4. User management.
5. Paket internet.
6. Input manual pelanggan.
7. Import Excel/CSV pelanggan lama.
8. Validasi kelengkapan data pelanggan.
9. Detail pelanggan lengkap.
10. Aktivasi layanan pelanggan.
11. Tagihan manual.
12. Pembayaran.
13. Dashboard.
14. Laporan sederhana.
15. Audit log.

## 18. Fitur Setelah MVP

Setelah MVP stabil, lanjutkan ke:

1. Generate tagihan otomatis bulanan.
2. Auto reminder WhatsApp.
3. Auto suspend.
4. Payment gateway.
5. Integrasi MikroTik.
6. Ticketing gangguan.
7. Inventory perangkat.
8. Monitoring OLT/SNMP.
9. Aplikasi mobile teknisi.

## 19. Risiko dan Solusi

| Risiko | Dampak | Solusi |
|---|---|---|
| Data lama tidak rapi | Import banyak gagal | Buat validasi dan preview import |
| Field pelanggan terlalu banyak | Admin bingung input | Gunakan form bertahap/tab |
| Billing aktif saat data belum lengkap | Tagihan kacau | Buat status kelengkapan data |
| POP belum rapi | Laporan cabang salah | Buat master POP sebelum import pelanggan |
| Hak akses tidak jelas | Data bocor | Terapkan RBAC sejak awal |
| Pembayaran bisa diedit sembarangan | Risiko manipulasi | Gunakan audit log dan pembatasan role |
| Data teknis sensitif terlihat semua user | Risiko keamanan | Batasi akses password modem/PPPoE |

## 20. Success Metrics

Sistem dianggap berhasil jika:

1. Data pelanggan lama bisa diimport ke sistem baru.
2. Data pelanggan bisa diinput manual.
3. Setiap pelanggan memiliki status kelengkapan data.
4. Admin tahu pelanggan mana yang datanya belum lengkap.
5. Billing hanya berjalan untuk pelanggan yang datanya valid.
6. Tagihan dibuat berdasarkan paket aktif pelanggan.
7. Pembayaran bisa dicatat dan mengubah status tagihan.
8. Pelanggan bisa difilter berdasarkan POP/Cabang.
9. Admin cabang hanya melihat data cabangnya.
10. Laporan pelanggan, tagihan, dan pembayaran bisa digunakan.

## 21. Kesimpulan

Produk yang dibangun bukan sekadar aplikasi pembayaran, tetapi sistem billing ISP yang dimulai dari master data pelanggan lengkap.

Urutan logika sistem yang benar adalah:

```text
POP/Cabang
→ RBAC
→ Paket Internet
→ Input/Import Data Pelanggan
→ Validasi Kelengkapan Data
→ Aktivasi Layanan
→ Tagihan
→ Pembayaran
→ Laporan
```

Dengan pendekatan ini, sistem baru tidak hanya bisa menampung data lama, tetapi juga siap dikembangkan menjadi sistem ISP yang lebih besar seperti integrasi MikroTik, payment gateway, ticketing, teknisi, dan monitoring jaringan.
