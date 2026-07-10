# FILE: docs/PROJECT_CONTEXT.md

```md
# Project Context
# Website Billing ISP Berbasis Master Data Pelanggan

## Nama Produk
Website Billing ISP Berbasis Master Data Pelanggan

## Ringkasan Produk
Website ini adalah sistem internal perusahaan ISP untuk mengelola data pelanggan secara lengkap dan menjadikan data tersebut sebagai dasar billing, tagihan, pembayaran, dan laporan.

Fokus utama sistem bukan langsung membuat pembayaran, tetapi memastikan data pelanggan lama dan pelanggan baru dapat masuk ke sistem baru dengan rapi.

Data pelanggan dapat dimasukkan melalui:

1. Input manual langsung dari halaman sistem.
2. Import massal melalui Excel/CSV.

Setelah data pelanggan lengkap dan valid, barulah sistem boleh membuat tagihan berdasarkan paket layanan pelanggan.

## Prinsip Utama
Urutan logika sistem:

Data Pelanggan Lengkap
→ Paket Layanan Aktif
→ Tagihan
→ Pembayaran
→ Status Billing

Versi operasional:

POP/Cabang
→ RBAC
→ Paket Internet
→ Input/Import Data Pelanggan
→ Validasi Kelengkapan Data
→ Aktivasi Layanan
→ Tagihan
→ Pembayaran
→ Laporan

## Latar Belakang Masalah
Perusahaan ISP sudah memiliki data pelanggan lama dari sistem sebelumnya.

Masalah utama sistem baru:

1. Data pelanggan lama masih berasal dari database lama.
2. Data pelanggan perlu dimasukkan ulang ke sistem baru.
3. Data bisa dimasukkan manual atau import Excel/CSV.
4. Sistem harus menampung data pelanggan lengkap.
5. Billing harus berdasarkan data pelanggan, bukan dibuat terpisah.
6. Struktur POP/Cabang perlu ada sejak awal.
7. Hak akses perlu diatur menggunakan RBAC.
8. Sistem harus membedakan data pelanggan lengkap dan belum lengkap.

## Tujuan Utama Produk
Membangun sistem billing ISP baru yang menjadikan master data pelanggan lengkap sebagai pusat sistem, sehingga billing, pembayaran, laporan, dan operasional pelanggan berjalan berdasarkan data yang valid.

## Tujuan MVP
MVP wajib fokus pada:

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
12. Menyediakan audit log untuk perubahan penting.

## Batasan Produk Tahap Awal
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

## Prinsip Data Pelanggan
Semua proses sistem harus berawal dari data pelanggan.

Billing tidak boleh berdiri sendiri tanpa pelanggan.

Pelanggan
→ Paket
→ Layanan Aktif
→ Tagihan
→ Pembayaran

## Prinsip Input Manual dan Import
Data pelanggan dari input manual dan import Excel/CSV harus masuk ke struktur database yang sama.

Tidak boleh ada perbedaan struktur antara pelanggan input manual dan pelanggan hasil import.

## Prinsip Data Belum Lengkap
Pelanggan belum lengkap tetap boleh disimpan.

Status yang digunakan:

- Draft
- Perlu Dilengkapi
- Lengkap
- Siap Billing

Pelanggan belum lengkap tidak boleh masuk proses billing aktif.

## Struktur POP/Cabang
POP/Cabang digunakan untuk mengelompokkan pelanggan berdasarkan wilayah operasional ISP.

Fungsi POP/Cabang:

1. Menentukan wilayah pelanggan.
2. Membatasi akses admin cabang.
3. Membuat laporan per cabang.
4. Mengelompokkan teknisi.
5. Menjadi dasar pengembangan jaringan ke depan.

Struktur POP:

Perusahaan / Pusat
└── POP Pusat
    └── POP Cabang
        └── Mini POP
            └── Pelanggan

## Role Utama
Sistem minimal memiliki role:

1. Owner
2. Admin Pusat
3. Admin Cabang
4. Finance/Kasir
5. Teknisi
6. Customer Service

## Alur Utama Sistem

### Flow Input Manual Pelanggan
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

### Flow Import Pelanggan Lama
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

### Flow Validasi Data Pelanggan
Pelanggan Disimpan
→ Sistem Mengecek Field Wajib
→ Jika Ada Field Kosong
   → Status Kelengkapan = Perlu Dilengkapi
   → Billing Belum Bisa Aktif
→ Jika Semua Field Wajib Terisi
   → Status Kelengkapan = Lengkap
   → Admin Bisa Menjadikan Siap Billing

### Flow Aktivasi Billing
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

### Flow Pembuatan Tagihan
Finance/Admin Buka Data Pelanggan
→ Pilih Buat Tagihan
→ Sistem Mengambil Paket Aktif
→ Sistem Mengambil Harga Layanan
→ Sistem Mengambil Tanggal Jatuh Tempo
→ Sistem Membuat Invoice
→ Status Invoice = Belum Dibayar

### Flow Pembayaran
Finance Login
→ Cari Pelanggan / Invoice
→ Buka Tagihan
→ Input Nominal Pembayaran
→ Pilih Metode Pembayaran
→ Upload Bukti Jika Ada
→ Simpan
→ Sistem Mengupdate Status Tagihan
→ Riwayat Pembayaran Tersimpan

## Kesimpulan Product Context
Produk ini bukan sekadar aplikasi pembayaran.

Produk ini adalah sistem billing ISP yang dimulai dari master data pelanggan lengkap.

Urutan logika yang wajib dijaga:

POP/Cabang
→ RBAC
→ Paket Internet
→ Input/Import Data Pelanggan
→ Validasi Kelengkapan Data
→ Aktivasi Layanan
→ Tagihan
→ Pembayaran
→ Laporan
FILE: docs/MVP_SCOPE.md
# MVP Scope
# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan MVP
MVP adalah versi awal yang wajib dibuat terlebih dahulu.

Fokus MVP:

1. Sistem dapat login.
2. Sistem memiliki role dan permission.
3. User dapat dibatasi berdasarkan POP/Cabang.
4. Admin dapat membuat struktur POP/Cabang.
5. Admin dapat membuat paket internet.
6. Admin dapat input pelanggan manual.
7. Admin dapat import pelanggan lama dari Excel/CSV.
8. Sistem dapat validasi kelengkapan data pelanggan.
9. Pelanggan lengkap dapat diaktifkan untuk billing.
10. Tagihan dapat dibuat manual berdasarkan pelanggan aktif.
11. Pembayaran dapat dicatat.
12. Dashboard menampilkan ringkasan.
13. Laporan sederhana dapat difilter dan diexport.
14. Perubahan penting tercatat di audit log.

## Fitur yang Masuk MVP

### 1. Login
- Login user internal.
- Redirect berdasarkan role jika dibutuhkan.
- Logout.

### 2. User Management
- CRUD user.
- Assign role ke user.
- Assign POP ke user.

### 3. RBAC
- CRUD role.
- CRUD permission.
- Assign permission ke role.
- Proteksi menu.
- Proteksi route.
- Proteksi data berdasarkan POP.

### 4. Master POP/Cabang
- CRUD POP.
- Parent-child POP.
- Tipe POP: Pusat, Cabang, Mini POP.
- Assign user ke POP.
- Filter pelanggan berdasarkan POP.

### 5. Master Paket Internet
- CRUD paket internet.
- Kategori paket.
- Kecepatan download/upload.
- Harga bulanan.
- PPN/diskon jika ada.
- Status aktif/nonaktif.

### 6. Input Manual Pelanggan
- Form bertahap atau tab.
- Data identitas.
- Data alamat.
- Data POP.
- Data paket/layanan.
- Data billing dasar.
- Dokumen pendukung opsional.
- Status kelengkapan data.

### 7. Import Excel/CSV Pelanggan Lama
- Download template.
- Upload file.
- Mapping kolom.
- Validasi data.
- Preview data.
- Konfirmasi import.
- Import batch.
- Import error.
- Data hasil import bisa diedit manual.

### 8. Validasi Kelengkapan Data
- Cek field wajib.
- Hitung persentase kelengkapan.
- Tampilkan field yang belum lengkap.
- Mencegah pelanggan belum lengkap masuk billing aktif.

### 9. Aktivasi Layanan Pelanggan
- Cek kelengkapan data.
- Pilih paket aktif.
- Tentukan tanggal aktivasi.
- Tentukan tanggal jatuh tempo.
- Simpan riwayat aktivasi.
- Ubah status menjadi siap billing/aktif.

### 10. Tagihan Manual
- Buat invoice dari pelanggan aktif.
- Ambil harga dari layanan pelanggan.
- Cek invoice dobel per periode.
- Status invoice: belum dibayar, sebagian, lunas, batal.
- Filter invoice berdasarkan POP, periode, status, pelanggan.

### 11. Pembayaran
- Input pembayaran.
- Pilih invoice.
- Input nominal.
- Pilih metode pembayaran.
- Upload bukti pembayaran opsional.
- Update status invoice.
- Riwayat pembayaran tampil di detail pelanggan.

### 12. Dashboard
- Total pelanggan.
- Total pelanggan aktif.
- Total pelanggan belum lengkap.
- Total pelanggan siap billing.
- Total pelanggan per POP.
- Total tagihan bulan ini.
- Total pembayaran bulan ini.
- Total tunggakan.
- Tagihan jatuh tempo.
- Pelanggan yang perlu dilengkapi.

### 13. Laporan
- Laporan pelanggan lengkap.
- Laporan pelanggan belum lengkap.
- Laporan pelanggan aktif.
- Laporan pelanggan isolir.
- Laporan pelanggan per POP.
- Laporan tagihan bulanan.
- Laporan pembayaran bulanan.
- Laporan tunggakan.
- Laporan pembayaran per metode.
- Laporan import data.
- Export Excel/CSV.

### 14. Audit Log
- Catat create, update, delete, import, payment validation.
- Catat user yang mengubah.
- Catat waktu perubahan.
- Catat data sebelum dan sesudah jika memungkinkan.
- Modul wajib audit: pelanggan, paket, POP, tagihan, pembayaran, user, role, data teknis.

## Fitur yang Tidak Masuk MVP
Fitur berikut harus ditunda sampai MVP stabil:

1. Integrasi MikroTik.
2. Auto generate tagihan bulanan kompleks.
3. Auto suspend pelanggan.
4. Payment gateway.
5. Ticketing gangguan.
6. Modul teknisi lapangan kompleks.
7. Monitoring jaringan.
8. Monitoring OLT/SNMP.
9. Inventory perangkat detail.
10. WhatsApp notification.
11. Aplikasi mobile teknisi.
12. Multi-company.
13. Laporan keuangan kompleks.

## Aturan Scope
AI tidak boleh menambahkan fitur di luar MVP.

Jika ada fitur yang terlihat berguna tetapi tidak ada dalam MVP, catat sebagai:

```md
Post-MVP Backlog:
- Nama fitur
- Alasan fitur
- Dependency
- Risiko jika dibuat sekarang

Jangan langsung implementasikan fitur post-MVP.