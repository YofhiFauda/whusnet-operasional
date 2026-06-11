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