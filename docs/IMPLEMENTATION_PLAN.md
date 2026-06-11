# Implementation Plan
# Website Billing ISP Berbasis Master Data Pelanggan

## Prinsip Urutan Development
Development harus dimulai dari pondasi sistem, bukan dari pembayaran.

Urutan wajib:

1. Pondasi sistem
2. RBAC
3. POP/Cabang
4. Paket Internet
5. Data Pelanggan
6. Import
7. Validasi
8. Aktivasi
9. Tagihan
10. Pembayaran
11. Dashboard
12. Laporan
13. Audit Log
14. Data teknis tambahan

## Sprint 1 — Pondasi Sistem

### Tujuan
Membuat pondasi aplikasi agar user internal dapat login dan sistem memiliki struktur role-permission dasar.

### Fitur
1. Setup project.
2. Setup database.
3. Login.
4. User.
5. Role.
6. Permission.
7. RBAC dasar.
8. Layout dashboard admin.

### Output
- User bisa login.
- Role bisa dibuat.
- Permission bisa digunakan.
- User dapat memiliki role.
- Role dapat memiliki banyak permission.
- Menu tampil berdasarkan role.
- Route terlindungi berdasarkan permission.

### Tidak Boleh Dikerjakan
- POP
- Paket
- Pelanggan
- Import
- Billing
- Pembayaran
- Laporan

---

## Sprint 2 — POP dan Paket

### Tujuan
Membuat struktur wilayah operasional dan master paket internet.

### Fitur
1. Master POP/Cabang.
2. Parent-child POP.
3. Assign user ke POP.
4. Master paket internet.

### Output
- POP bisa dibuat.
- POP bisa memiliki parent.
- POP bisa dinonaktifkan.
- Paket bisa dibuat.
- Paket bisa dinonaktifkan.
- User bisa dibatasi per POP.

### Tidak Boleh Dikerjakan
- Input pelanggan
- Import pelanggan
- Billing
- Pembayaran
- Laporan

---

## Sprint 3 — Master Data Pelanggan Manual

### Tujuan
Membuat form input pelanggan lengkap secara manual.

### Fitur
1. Form input pelanggan.
2. Data identitas.
3. Data alamat.
4. Data POP.
5. Data paket/layanan.
6. Data billing dasar.
7. Status kelengkapan data.

### Output
- Admin bisa input pelanggan manual.
- Pelanggan bisa disimpan walaupun belum lengkap.
- Sistem menandai data lengkap/belum lengkap.
- Pelanggan belum lengkap tidak bisa masuk billing aktif.
- Detail pelanggan menampilkan tab data utama.

### Tidak Boleh Dikerjakan
- Import Excel/CSV
- Invoice
- Pembayaran
- Dashboard laporan kompleks

---

## Sprint 4 — Import Excel/CSV

### Tujuan
Membuat modul migrasi pelanggan lama dari Excel/CSV ke master pelanggan.

### Fitur
1. Template import.
2. Upload Excel/CSV.
3. Mapping kolom.
4. Preview import.
5. Validasi data.
6. Import batch.
7. Import error.
8. Simpan ID pelanggan lama.

### Output
- Data lama bisa masuk ke sistem.
- Data valid tersimpan.
- Data invalid ditolak.
- Alasan error terlihat.
- Import memiliki log batch.
- Data hasil import bisa diedit seperti input manual.

### Tidak Boleh Dikerjakan
- Payment gateway
- Auto billing
- Auto suspend

---

## Sprint 5 — Billing Dasar

### Tujuan
Membuat aktivasi billing pelanggan dan pembuatan tagihan manual.

### Fitur
1. Aktivasi billing pelanggan.
2. Buat tagihan manual.
3. Cek tagihan dobel.
4. Status tagihan.
5. Detail invoice.

### Output
- Pelanggan lengkap bisa dibuatkan tagihan.
- Pelanggan belum lengkap tidak bisa dibuatkan tagihan.
- Invoice mengambil harga dari layanan pelanggan.
- Invoice memiliki periode.
- Invoice tidak dobel untuk periode sama.

### Tidak Boleh Dikerjakan
- Auto generate tagihan bulanan
- Payment gateway
- Auto suspend

---

## Sprint 6 — Pembayaran

### Tujuan
Membuat pencatatan pembayaran dan perubahan status invoice.

### Fitur
1. Input pembayaran.
2. Upload bukti pembayaran.
3. Status pembayaran.
4. Update status invoice.
5. Riwayat pembayaran pelanggan.
6. Audit log pembayaran.

### Output
- Finance dapat mencatat pembayaran.
- Pembayaran muncul di detail pelanggan.
- Invoice bisa lunas atau dibayar sebagian.
- Bukti pembayaran dapat diupload.
- Pembayaran dapat difilter.

### Tidak Boleh Dikerjakan
- Payment gateway
- Auto reminder WhatsApp

---

## Sprint 7 — Dashboard dan Laporan

### Tujuan
Membuat dashboard operasional dan laporan sederhana.

### Fitur
1. Dashboard ringkasan.
2. Laporan pelanggan.
3. Laporan tagihan.
4. Laporan pembayaran.
5. Laporan tunggakan.
6. Export Excel/CSV.

### Output
- Admin dapat melihat kondisi pelanggan dan billing.
- Laporan dapat difilter berdasarkan tanggal.
- Laporan dapat difilter berdasarkan POP.
- Laporan bisa diexport.
- Admin cabang hanya melihat laporan cabangnya.

---

## Sprint 8 — Data Teknis Pelanggan

### Tujuan
Melengkapi data teknis pelanggan setelah flow billing dasar stabil.

### Fitur
1. Data survey.
2. Data pemasangan.
3. Data modem/ONT/router.
4. Data dokumen pelanggan.

### Output
- Data pelanggan menjadi lebih lengkap.
- Teknisi bisa mengisi data teknis.
- Data teknis sensitif dibatasi berdasarkan role.
- Detail pelanggan memiliki tab teknis.

## Catatan Penting
Jangan mengerjakan Sprint 8 sebelum Sprint 1 sampai Sprint 7 stabil.