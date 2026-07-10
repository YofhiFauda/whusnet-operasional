# BUSINESS_RULES.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini berisi aturan bisnis utama yang wajib diikuti dalam pengembangan Website Billing ISP.
Semua fitur yang dibuat oleh AI/developer wajib mematuhi aturan ini.

Jika ada instruksi coding yang bertentangan dengan dokumen ini, AI wajib berhenti dan meminta konfirmasi.

---

# 1. Prinsip Utama Sistem

Sistem ini bukan sekadar aplikasi pembayaran. Sistem ini adalah aplikasi billing ISP yang menjadikan master data pelanggan sebagai pusat sistem.

Urutan logika utama:
```txt
POP/Cabang
→ RBAC (Role, Permission, Scope)
→ Paket Internet
→ Input/Import Data Pelanggan
→ Validasi Kelengkapan Data
→ Aktivasi Layanan
→ Tagihan
→ Pembayaran
→ Laporan
```

Aturan Utama:
1. Billing/tagihan tidak boleh dibuat tanpa data pelanggan aktif.
2. Pembayaran tidak boleh dibuat tanpa invoice yang valid.
3. Invoice tidak boleh dibuat jika pelanggan belum berstatus aktif atau siap billing.
4. Pelanggan boleh disimpan walaupun datanya belum lengkap (status draft/perlu dilengkapi).
5. Pelanggan yang belum lengkap tidak boleh masuk ke billing aktif.
6. Data pelanggan manual dan import harus masuk ke struktur database yang sama.
7. Setiap data penting dan perubahan status wajib dicatat di audit log.
8. User hanya boleh mengakses fitur dan data sesuai kombinasi Role, Permission, dan POP Scope yang ditugaskan.
9. **Pencegahan Kehilangan History:** Penghapusan akun user (Sales, Teknisi, Kasir, dsb) dilarang menggunakan cascade delete yang dapat menghapus transaksi atau log aktivitas mereka. Akun user wajib menggunakan soft delete, dan relasi transaksi luar wajib menggunakan constraint `onDelete('restrict')` atau `onDelete('set null')` agar data historis laporan keuangan dan audit log tetap utuh.

---

# 2. Aturan POP/Cabang

## 2.1 Fungsi POP/Cabang
POP/Cabang digunakan untuk:
1. Mengelompokkan pelanggan berdasarkan wilayah operasional.
2. Membatasi akses Admin POP atau role lokal lainnya.
3. Menjadi dasar filter laporan keuangan dan operasional.
4. Mengelompokkan penugasan tim teknisi dan sales lapangan.

## 2.2 Struktur POP
Struktur POP yang digunakan bersifat hierarkis:
```txt
Perusahaan / Pusat
└── POP Pusat
    └── POP Cabang
        └── Mini POP
            └── Pelanggan
```

## 2.3 Aturan Operasional POP
1. Setiap pelanggan wajib terhubung ke salah satu POP.
2. POP dapat memiliki parent POP dan child POP (Mini POP).
3. POP memiliki tipe: `pusat`, `cabang`, dan `mini_pop`.
4. POP dapat aktif atau nonaktif. POP nonaktif tidak boleh dipilih untuk pelanggan baru atau perpindahan paket.
5. Filter laporan dan dashboard wajib mendukung penyaringan berbasis POP.
6. Data transaksi pelanggan, invoice, dan pembayaran wajib menyimpan field `pop_id`.

---

# 3. Aturan Advanced RBAC (Role & User Scope)

Sistem memisahkan secara mutlak antara **Role** (hak fungsi aksi/permission) dan **User Scope** (hak wilayah cakupan data POP).

## 3.1 Sembilan Role Utama & Batasan Hak Bisnis

### 1. Owner
*   **Fungsi:** Pemilik bisnis tingkat tertinggi.
*   **Hak Akses:** Otoritas penuh (all permissions) untuk melihat dan mengubah seluruh konfigurasi sistem, keuangan, data pengguna, audit log, serta pengaturan keamanan global.

### 2. Atasan
*   **Fungsi:** Manager / Manajemen Pusat.
*   **Hak Akses:** Memantau dashboard global, melihat semua laporan keuangan, dan memantau audit log.
*   **Larangan Keras:** Tidak boleh melakukan penambahan, perubahan (update), atau penghapusan (delete) pada data konfigurasi sistem inti, user, role, dan transaksi pembayaran.

### 3. Admin
*   **Fungsi:** Operator Pusat.
*   **Hak Akses:** Mengelola master data POP, paket internet, user management global, registrasi pelanggan, verifikasi, manajemen tagihan, dan validasi pembayaran.
*   **Larangan Keras:** Tidak boleh mengubah kontrol akses Owner tanpa izin tertulis atau mengubah konfigurasi sistem sensitif yang khusus untuk Owner.

### 4. NOC (Network Operations)
*   **Fungsi:** Tim teknis jaringan pusat/cabang.
*   **Hak Akses:** Mengelola data teknis pelanggan (IP Address, VLAN ID, PPPoE, perangkat modem, survey, pemasangan) dan melihat log jaringan.
*   **Larangan Keras:** Tidak boleh mengakses menu pembayaran, membuat invoice baru, memvalidasi pembayaran masuk, atau melihat laporan keuangan.

### 5. Helpdesk
*   **Fungsi:** Helpdesk Tingkat Pusat/Cabang.
*   **Hak Akses:** Melihat data pelanggan umum, mendaftarkan keluhan, membuat registrasi pelanggan baru, melihat status tagihan, mencetak kwitansi pembayaran.
*   **Larangan Keras:** Tidak boleh mengubah nominal tagihan yang sudah terbit, mengubah data teknis sensitif (seperti password PPPoE/WiFi), atau menghapus data pelanggan dari database.

### 6. FOP (Field Operations)
*   **Fungsi:** Koordinator Lapangan Cabang.
*   **Hak Akses:** Mengatur penjadwalan survey, menugaskan tim teknisi ke pelanggan, memvalidasi laporan survey fisik, dan menyetujui kesiapan instalasi.
*   **Larangan Keras:** Tidak boleh mengubah nominal tagihan, mencatat pembayaran keuangan, atau melihat laporan keuangan global.

### 7. Teknisi
*   **Fungsi:** Eksekutor Lapangan.
*   **Hak Akses:** Mengisi laporan survey fisik, melakukan instalasi perangkat modem, mengubah status pemasangan, mengunggah foto teknis, serta melihat/mengubah detail teknis perangkat pelanggan.
*   **Larangan Keras:** Tidak boleh mencatat pembayaran, mengunggah bukti transfer bank, mengubah nominal tagihan, membuat invoice, atau mengakses laporan keuangan.

### 8. Sales
*   **Fungsi:** Agen Penjualan Calon Pelanggan.
*   **Hak Akses:** Menginput form registrasi calon pelanggan baru dan memantau status registrasinya sendiri.
*   **Larangan Keras:** Tidak boleh melihat detail teknis jaringan pelanggan lain, tidak boleh mengakses menu penagihan/pembayaran, dan dilarang melihat laporan keuangan.

### 9. Admin POP
*   **Fungsi:** Administrator Wilayah/Cabang.
*   **Hak Akses:** Mengelola operasional harian cabang terkait, mendaftarkan pelanggan di wilayahnya, menerbitkan invoice manual di wilayahnya, dan mencatat/memvalidasi pembayaran lokal.
*   **Larangan Keras:** Tidak boleh melihat data pelanggan di luar POP wilayah kerjanya (terisolasi scope), tidak boleh mengubah role global, dan dilarang memodifikasi permission matrix.

---

## 3.2 Aturan User Scope (Pembatasan Wilayah Data)

Setiap user dihubungkan ke role beserta **Scope Type** yang menentukan cakupan data yang berhak diakses:

1.  **Scope `all_pop`:**
    *   Akses tanpa filter wilayah POP (melihat seluruh Indonesia).
    *   *Role default:* Owner, Atasan, Admin, NOC Pusat.
2.  **Scope `selected_pop`:**
    *   Hanya dapat melihat data yang terhubung ke POP tertentu yang ditunjuk secara eksplisit di tabel target.
    *   *Role default:* Admin POP, Helpdesk Cabang, FOP Cabang.
    *   *Pengecualian:* Admin POP tidak boleh melihat data dari POP lain.
3.  **Scope `pop_tree`:**
    *   Dapat mengakses data di POP utama yang ditunjuk beserta sub-POP (Mini POP) di bawahnya secara hierarkis.
4.  **Scope `assigned_only`:**
    *   Hanya dapat melihat data tugas (survey/pemasangan/pelanggan) yang di-assign langsung kepada ID user tersebut.
    *   *Role default:* Teknisi Lapangan.
5.  **Scope `own_created`:**
    *   Hanya dapat melihat data pelanggan/registrasi yang dibuat oleh ID user yang bersangkutan.
    *   *Role default:* Sales Lapangan.

---

# 4. Aturan Data Pelanggan

## 4.1 Pelanggan Adalah Pusat Sistem
Semua modul berikut wajib terhubung ke pelanggan:
*   Alamat Lengkap & Koordinat GPS
*   POP/Cabang Penugasan
*   Paket/Layanan Aktif
*   Riwayat Survey & Pemasangan
*   Data Perangkat & Konfigurasi Teknis
*   Dokumen Pendukung (KTP/Foto)
*   Invoice Tagihan
*   Pembayaran & Kuitansi
*   Audit Log Pelanggan

## 4.2 Status Kelengkapan Data
Status kelengkapan data pelanggan diatur sebagai berikut:
1.  `draft`
2.  `perlu_dilengkapi`
3.  `lengkap`
4.  `siap_billing`

Aturan Status:
- Pelanggan baru dari registrasi sales/form manual disimpan dengan status `draft`.
- Jika data wajib siap billing belum terisi, status otomatis diubah ke `perlu_dilengkapi`.
- Jika seluruh field wajib siap billing terisi penuh, status menjadi `lengkap`.
- Ketika layanan fisik telah dipasang dan diaktifkan (melalui verifikasi akhir), status diubah menjadi `siap_billing` (aktif masuk siklus bulanan).
- Pelanggan yang statusnya belum `siap_billing` tidak boleh diterbitkan invoice aktif bulanan.

## 4.3 Field Wajib Siap Billing
Pelanggan hanya boleh masuk ke status `siap_billing` jika field berikut telah diisi:
1.  Nama lengkap.
2.  Nomor HP (WhatsApp).
3.  Alamat lengkap.
4.  Desa/Kelurahan.
5.  Kecamatan.
6.  Kota/Kabupaten.
7.  POP/Cabang.
8.  Paket internet aktif.
9.  Harga bulanan (snapshot saat aktivasi).
10. Tanggal aktivasi layanan.
11. Tanggal jatuh tempo billing bulanan.
12. Status layanan (`aktif`).

## 4.4 Field Teknis (Tidak Wajib MVP)
Field berikut boleh kosong pada awal registrasi, namun wajib dilengkapi oleh Teknisi setelah pemasangan selesai:
1.  Username PPPoE.
2.  Password PPPoE.
3.  Serial Number Modem/ONT.
4.  SSID & Password WiFi.
5.  VLAN ID & Port ODP.
6.  Redaman (dBm).

---

# 5. Aturan Import Data Pelanggan

1.  **Validasi Ketat:** Data hasil import tidak boleh langsung dimasukkan ke master pelanggan aktif tanpa validasi keunikan ID pelanggan lama, kecocokan nama POP, dan nama paket internet yang terdaftar di sistem.
2.  **Preview & Koreksi:** Sistem wajib menampilkan preview data import yang gagal (invalid) dengan menyajikan nomor baris, nama field, dan alasan error agar Admin dapat mengoreksi file sebelum melakukan submit akhir.

---

# 6. Aturan Aktivasi Layanan

1.  Aktivasi layanan mengubah pelanggan berstatus `lengkap` menjadi `siap_billing`.
2.  **Pembuatan CID:** Customer ID (CID) resmi sistem baru hanya boleh di-generate otomatis saat proses verifikasi final / aktivasi pertama kali dilakukan.

---

# 7. Aturan Billing & Invoice

1.  **Bukan Manual dari Nol:** Invoice bulanan harus diturunkan otomatis dari kombinasi: Pelanggan Aktif + Paket Aktif + Harga Layanan Snapshot + Periode Tagihan.
2.  **Pencegahan Invoice Ganda:** Sistem harus memblokir pembuatan invoice dengan periode tagihan yang sama untuk satu pelanggan yang sama guna mencegah double billing.
3.  **Status Invoice:** Terdiri dari `belum_dibayar`, `sebagian`, `lunas`, dan `batal`. Invoice yang sudah berstatus `lunas` tidak boleh dihapus atau dibatalkan tanpa hak akses Owner dan audit log yang ketat.

---

# 8. Aturan Pembayaran

1.  Pembayaran wajib mencatat relasi ke Invoice, Pelanggan, POP/Cabang, dan User Penerima (Kasir/Admin POP yang memproses).
2.  Status pembayaran terdiri dari `pending`, `valid`, dan `ditolak`. Status `ditolak` tidak boleh mengurangi nilai sisa tagihan pada invoice.
3.  Setiap pencatatan pembayaran wajib menulis log ke audit log sistem secara instan.

---

# 9. Aturan Dashboard & Laporan

1.  Dashboard dan laporan wajib mengimplementasikan filter POP otomatis di tingkat query backend (mematuhi User Scope).
2.  Admin POP hanya boleh mengekspor laporan untuk wilayah POP yang ditugaskan padanya.

---

# 10. Aturan Audit Log

1.  Audit log wajib mencatat detail: `user_id`, `action` (create, update, delete, dll), `model_type`, `model_id`, `before_values` (JSON), `after_values` (JSON), `ip_address`, dan `user_agent`.
2.  Audit log dilindungi secara ketat: tidak boleh diedit atau dihapus oleh role manapun kecuali Owner (atau otomatis terarsipkan secara aman di server).
