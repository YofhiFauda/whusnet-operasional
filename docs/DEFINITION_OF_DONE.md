# DEFINITION_OF_DONE.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini menjelaskan standar sebuah task boleh dianggap selesai.

AI/developer wajib membaca dokumen ini sebelum:

* memulai task,
* menyelesaikan task,
* mengubah status task menjadi Done,
* lanjut ke task berikutnya.

Task tidak boleh dianggap selesai hanya karena kode sudah dibuat.

Task dianggap selesai jika:

1. Sesuai scope.
2. Tidak keluar dari PRD.
3. Tidak membuat fitur post-MVP.
4. Acceptance criteria terpenuhi.
5. Validasi berjalan.
6. RBAC aman.
7. Query POP scope benar.
8. Tidak merusak modul lain.
9. Sudah dijelaskan cara test.
10. `docs/TASKS.md` sudah diperbarui.

---

# 1. Definition of Done Umum

Sebuah task boleh dipindahkan ke Done jika memenuhi seluruh kriteria berikut:

* [ ] Task yang dikerjakan sesuai dengan task aktif di `docs/TASKS.md`.
* [ ] Tidak ada fitur di luar task aktif.
* [ ] Tidak ada fitur dari sprint berikutnya yang ikut dibuat.
* [ ] Tidak ada fitur post-MVP yang dibuat tanpa persetujuan.
* [ ] Semua acceptance criteria task terpenuhi.
* [ ] File yang dibuat/diubah relevan dengan task.
* [ ] Tidak ada perubahan file yang tidak berhubungan.
* [ ] Validasi input sudah dibuat jika task memiliki form/input.
* [ ] Error handling dasar tersedia.
* [ ] Route dilindungi authentication jika berada di area admin.
* [ ] Route dilindungi permission jika membutuhkan RBAC.
* [ ] Query data cabang dibatasi berdasarkan POP jika relevan.
* [ ] Data penting tidak dihapus sembarangan.
* [ ] Audit log dibuat jika task menyentuh data penting.
* [ ] Cara test manual sudah dijelaskan.
* [ ] Risiko atau TODO dicatat jika ada.
* [ ] `docs/TASKS.md` diupdate.

---

# 2. Definition of Done untuk Setup Project

Task setup project dianggap selesai jika:

* [ ] Project dapat dijalankan lokal.
* [ ] Environment tersedia.
* [ ] Database terkoneksi.
* [ ] Migration dasar dapat dijalankan.
* [ ] Folder `docs/` tersedia.
* [ ] `AGENTS.md` tersedia.
* [ ] Dokumentasi kontrol AI tersedia.
* [ ] Tidak ada fitur bisnis yang dibuat terlalu awal.
* [ ] Cara menjalankan project ditulis dengan jelas.

Tidak boleh dianggap selesai jika:

* Project belum bisa jalan.
* Database belum terkoneksi.
* Dokumentasi kontrol AI belum tersedia.
* AI langsung membuat fitur pelanggan, billing, atau pembayaran.

---

# 3. Definition of Done untuk Authentication

Task authentication dianggap selesai jika:

* [ ] User dapat login.
* [ ] User dapat logout.
* [ ] Halaman admin tidak bisa diakses tanpa login.
* [ ] Password disimpan dalam bentuk hash.
* [ ] User nonaktif tidak bisa login jika status user sudah diterapkan.
* [ ] Owner pertama tersedia melalui seeder.
* [ ] Redirect setelah login jelas.
* [ ] Tidak ada fitur role kompleks jika task hanya auth dasar.
* [ ] Cara test login/logout dijelaskan.

Tidak boleh dianggap selesai jika:

* Halaman admin masih bisa dibuka tanpa login.
* Password disimpan plain text.
* Seeder user owner belum tersedia.
* Auth bercampur dengan fitur POP/pelanggan/billing.

---

# 4. Definition of Done untuk RBAC

Task RBAC dianggap selesai jika:

* [ ] Role tersedia.
* [ ] Permission tersedia.
* [ ] User dapat memiliki role.
* [ ] Role dapat memiliki banyak permission.
* [ ] Permission dapat dicek dari user login.
* [ ] Menu tampil berdasarkan permission.
* [ ] Route dilindungi middleware permission.
* [ ] User tidak bisa membuka URL fitur yang tidak diizinkan.
* [ ] Admin Cabang tidak bisa melihat data cabang lain jika POP scope sudah diterapkan.
* [ ] Teknisi tidak bisa membuka pembayaran.
* [ ] Finance tidak bisa mengubah data modem.
* [ ] Customer Service tidak bisa mengubah nominal tagihan.
* [ ] Perubahan role/permission masuk audit log jika audit foundation sudah tersedia.

Tidak boleh dianggap selesai jika:

* Hanya menu yang disembunyikan tetapi route masih bisa dibuka.
* Permission hanya ditulis di frontend.
* Query data tidak mempertimbangkan scope POP.
* Role dibuat tetapi tidak digunakan pada route.

---

# 5. Definition of Done untuk POP/Cabang

Task POP/Cabang dianggap selesai jika:

* [ ] Tabel POP tersedia.
* [ ] Model POP tersedia.
* [ ] POP memiliki kode POP.
* [ ] POP memiliki nama.
* [ ] POP memiliki tipe: pusat, cabang, mini_pop.
* [ ] POP memiliki parent-child.
* [ ] POP memiliki status aktif/nonaktif.
* [ ] POP dapat dibuat.
* [ ] POP dapat diedit.
* [ ] POP dapat dinonaktifkan.
* [ ] POP nonaktif tidak bisa dipilih untuk pelanggan baru.
* [ ] User dapat diassign ke POP jika task assign user ke POP dikerjakan.
* [ ] Query berbasis POP bisa dibuat untuk membatasi data cabang.
* [ ] Perubahan POP masuk audit log jika audit foundation tersedia.

Tidak boleh dianggap selesai jika:

* POP hanya berupa text biasa di pelanggan.
* Tidak ada parent-child.
* Tidak ada pembatasan status aktif/nonaktif.
* Tidak ada kode POP padahal ID numbering membutuhkan `pop_code`.

---

# 6. Definition of Done untuk ID Numbering POP

Task ID numbering dianggap selesai jika:

* [ ] POP memiliki `pop_code`.
* [ ] POP memiliki `registration_prefix`.
* [ ] POP memiliki `cid_prefix`.
* [ ] Tabel sequence tersedia.
* [ ] Sequence registration tersedia per POP.
* [ ] Sequence CID tersedia per POP.
* [ ] ID Request dibuat otomatis saat pelanggan pertama disimpan.
* [ ] CID dibuat otomatis saat pelanggan diaktifkan.
* [ ] Format ID Request sesuai aturan.
* [ ] Format CID sesuai aturan.
* [ ] ID tidak duplikat.
* [ ] Sequence aman dari race condition.
* [ ] ID tidak dibuat dengan cara `count(customers) + 1`.
* [ ] Import pelanggan juga mendapat ID Request.
* [ ] CID tidak dibuat sebelum pelanggan aktif/siap billing.

Tidak boleh dianggap selesai jika:

* Running number global, bukan per POP.
* ID dibuat manual oleh admin.
* ID rawan duplikat.
* CID dibuat saat pelanggan masih draft.

---

# 7. Definition of Done untuk Paket Internet

Task paket internet dianggap selesai jika:

* [ ] Tabel paket tersedia.
* [ ] Model paket tersedia.
* [ ] Paket memiliki nama.
* [ ] Paket memiliki kategori.
* [ ] Paket memiliki kecepatan download.
* [ ] Paket memiliki kecepatan upload.
* [ ] Paket memiliki harga bulanan.
* [ ] Paket memiliki PPN/diskon jika digunakan.
* [ ] Paket memiliki total harga.
* [ ] Paket memiliki status aktif/nonaktif.
* [ ] Paket dapat dibuat.
* [ ] Paket dapat diedit.
* [ ] Paket dapat dinonaktifkan.
* [ ] Paket aktif dapat dipilih saat input pelanggan.
* [ ] Paket nonaktif tidak dapat dipilih untuk pelanggan baru.
* [ ] Perubahan paket tidak mengubah invoice lama.

Tidak boleh dianggap selesai jika:

* Paket tidak memiliki harga.
* Paket tidak memiliki status.
* Harga invoice lama masih bergantung langsung ke master paket tanpa snapshot.

---

# 8. Definition of Done untuk Pelanggan Manual

Task pelanggan manual dianggap selesai jika:

* [ ] Customer dapat dibuat.
* [ ] Customer memiliki POP.
* [ ] Customer memiliki data identitas.
* [ ] Customer memiliki alamat.
* [ ] Customer dapat memilih paket.
* [ ] Customer memiliki data layanan.
* [ ] Customer memiliki status kelengkapan data.
* [ ] Customer memiliki status layanan.
* [ ] Pelanggan belum lengkap tetap bisa disimpan.
* [ ] Field wajib yang kosong ditampilkan.
* [ ] Persentase kelengkapan dihitung jika task validasi dikerjakan.
* [ ] Pelanggan belum lengkap tidak bisa masuk billing aktif.
* [ ] ID Request dibuat otomatis jika ID numbering sudah masuk sprint.
* [ ] Admin Cabang hanya melihat pelanggan POP yang ditugaskan.
* [ ] Detail pelanggan dapat dibuka.

Tidak boleh dianggap selesai jika:

* Pelanggan bisa dibuat tanpa POP.
* Pelanggan belum lengkap bisa dibuatkan invoice.
* Input manual masuk struktur berbeda dari import.
* Data layanan tidak terhubung ke paket.

---

# 9. Definition of Done untuk Import Pelanggan

Task import pelanggan dianggap selesai jika:

* [ ] Template import tersedia.
* [ ] File Excel/CSV bisa diupload.
* [ ] Header file terbaca.
* [ ] Mapping kolom tersedia jika dibutuhkan.
* [ ] Sistem membaca data.
* [ ] Sistem memvalidasi field wajib.
* [ ] Sistem mengecek duplikasi.
* [ ] Sistem mengecek POP.
* [ ] Sistem mengecek paket.
* [ ] Sistem mengecek harga.
* [ ] Sistem mengecek tanggal.
* [ ] Sistem mengecek status layanan.
* [ ] Preview data tampil.
* [ ] Data valid dan invalid dipisahkan.
* [ ] Error import menjelaskan alasan gagal.
* [ ] Admin dapat konfirmasi import.
* [ ] Data valid masuk master pelanggan.
* [ ] Data invalid tidak masuk master pelanggan.
* [ ] Import batch tersimpan.
* [ ] Import error tersimpan.
* [ ] Data hasil import bisa diedit manual.
* [ ] Import tidak membuat invoice otomatis pada MVP.
* [ ] Import tidak membuat payment otomatis.
* [ ] Import membuat ID Request jika ID numbering sudah diterapkan.

Tidak boleh dianggap selesai jika:

* Data langsung masuk tanpa preview.
* Data invalid ikut tersimpan.
* Import tidak punya log.
* Import membuat invoice otomatis.
* Data import masuk tabel berbeda dari input manual.

---

# 10. Definition of Done untuk Validasi Kelengkapan Data

Task validasi kelengkapan dianggap selesai jika:

* [ ] Sistem mengecek semua field wajib.
* [ ] Sistem menampilkan field yang belum lengkap.
* [ ] Sistem menampilkan status kelengkapan.
* [ ] Sistem mengubah status otomatis sesuai data.
* [ ] Sistem mencegah pelanggan belum lengkap masuk billing aktif.
* [ ] Admin dapat melihat daftar pelanggan perlu dilengkapi.
* [ ] Validasi berjalan untuk input manual.
* [ ] Validasi berjalan untuk import.
* [ ] Perubahan field wajib memicu ulang validasi.

Tidak boleh dianggap selesai jika:

* Status kelengkapan diubah manual tanpa validasi.
* Field wajib tidak jelas.
* Pelanggan belum lengkap tetap bisa diaktifkan.

---

# 11. Definition of Done untuk Aktivasi Layanan

Task aktivasi layanan dianggap selesai jika:

* [ ] Sistem mengecek kelengkapan data.
* [ ] Pelanggan belum lengkap ditolak.
* [ ] Pelanggan harus memiliki paket aktif.
* [ ] Pelanggan harus memiliki nominal tagihan.
* [ ] Tanggal aktivasi wajib ada.
* [ ] Tanggal jatuh tempo wajib ada.
* [ ] Status pelanggan berubah sesuai aturan.
* [ ] Status kelengkapan menjadi siap billing.
* [ ] CID dibuat jika ID numbering diterapkan.
* [ ] Riwayat aktivasi tersimpan.
* [ ] Audit log dibuat jika audit foundation tersedia.

Tidak boleh dianggap selesai jika:

* Pelanggan draft bisa diaktifkan.
* CID dibuat sebelum pelanggan lengkap.
* Aktivasi tidak mengecek paket dan harga.

---

# 12. Definition of Done untuk Invoice/Tagihan

Task invoice dianggap selesai jika:

* [ ] Invoice hanya dibuat untuk pelanggan aktif/siap billing.
* [ ] Invoice mengambil harga dari layanan pelanggan.
* [ ] Invoice menyimpan snapshot harga.
* [ ] Invoice memiliki periode.
* [ ] Invoice memiliki tanggal terbit.
* [ ] Invoice memiliki tanggal jatuh tempo.
* [ ] Invoice memiliki status.
* [ ] Invoice tidak dobel untuk customer dan periode sama.
* [ ] Invoice terhubung ke customer.
* [ ] Invoice terhubung ke POP.
* [ ] Invoice dapat difilter berdasarkan POP.
* [ ] Invoice dapat difilter berdasarkan periode.
* [ ] Invoice dapat difilter berdasarkan status.
* [ ] Invoice dapat difilter berdasarkan pelanggan.
* [ ] Admin Cabang hanya melihat invoice POP yang ditugaskan.
* [ ] Perubahan invoice masuk audit log jika audit foundation tersedia.

Tidak boleh dianggap selesai jika:

* Invoice bisa dibuat untuk pelanggan belum lengkap.
* Invoice dibuat tanpa customer.
* Invoice dibuat tanpa periode.
* Invoice dobel untuk periode yang sama.
* Invoice mengambil harga langsung dari master paket tanpa snapshot.

---

# 13. Definition of Done untuk Pembayaran

Task pembayaran dianggap selesai jika:

* [ ] Payment terhubung ke invoice.
* [ ] Payment terhubung ke customer.
* [ ] Payment terhubung ke POP.
* [ ] Payment memiliki tanggal bayar.
* [ ] Payment memiliki metode bayar.
* [ ] Payment memiliki nominal.
* [ ] Bukti pembayaran bisa diupload jika task mencakup upload.
* [ ] Payment valid mengubah paid amount invoice.
* [ ] Payment valid mengubah remaining amount invoice.
* [ ] Payment penuh mengubah invoice menjadi lunas.
* [ ] Payment sebagian mengubah invoice menjadi sebagian.
* [ ] Payment ditolak tidak mengubah invoice menjadi lunas.
* [ ] Teknisi tidak bisa mencatat pembayaran.
* [ ] Customer Service tidak bisa memvalidasi pembayaran.
* [ ] Pembayaran dapat difilter berdasarkan tanggal, POP, metode, status.
* [ ] Perubahan payment masuk audit log.

Tidak boleh dianggap selesai jika:

* Payment bisa dibuat tanpa invoice.
* Payment bisa dicatat oleh teknisi.
* Payment ditolak tetap membuat invoice lunas.
* Status invoice tidak berubah setelah pembayaran valid.

---

# 14. Definition of Done untuk Dashboard

Task dashboard dianggap selesai jika:

* [ ] Dashboard menampilkan total pelanggan.
* [ ] Dashboard menampilkan total pelanggan aktif.
* [ ] Dashboard menampilkan total pelanggan belum lengkap.
* [ ] Dashboard menampilkan total pelanggan siap billing.
* [ ] Dashboard menampilkan total pelanggan per POP.
* [ ] Dashboard menampilkan total tagihan bulan ini.
* [ ] Dashboard menampilkan total pembayaran bulan ini.
* [ ] Dashboard menampilkan total tunggakan.
* [ ] Dashboard menampilkan tagihan jatuh tempo.
* [ ] Dashboard menampilkan pelanggan yang perlu dilengkapi.
* [ ] Owner melihat semua data.
* [ ] Admin Pusat melihat semua cabang.
* [ ] Admin Cabang hanya melihat cabangnya.
* [ ] Dashboard bisa difilter berdasarkan POP.
* [ ] Dashboard bisa difilter berdasarkan periode.

Tidak boleh dianggap selesai jika:

* Admin Cabang melihat data cabang lain.
* Statistik tidak berdasarkan data real.
* Dashboard menampilkan data global untuk semua role.

---

# 15. Definition of Done untuk Laporan

Task laporan dianggap selesai jika:

* [ ] Laporan pelanggan tersedia.
* [ ] Laporan pelanggan lengkap tersedia.
* [ ] Laporan pelanggan belum lengkap tersedia.
* [ ] Laporan pelanggan aktif tersedia.
* [ ] Laporan pelanggan isolir tersedia.
* [ ] Laporan pelanggan per POP tersedia.
* [ ] Laporan tagihan bulanan tersedia.
* [ ] Laporan pembayaran bulanan tersedia.
* [ ] Laporan tunggakan tersedia.
* [ ] Laporan pembayaran per metode tersedia.
* [ ] Laporan import data tersedia.
* [ ] Laporan dapat difilter berdasarkan tanggal.
* [ ] Laporan dapat difilter berdasarkan POP.
* [ ] Laporan dapat diexport ke Excel/CSV.
* [ ] Admin Cabang hanya export data cabangnya.

Tidak boleh dianggap selesai jika:

* Laporan tidak bisa difilter.
* Export mengabaikan scope POP.
* Admin Cabang bisa export semua cabang.

---

# 16. Definition of Done untuk Audit Log

Task audit log dianggap selesai jika:

* [ ] Tabel audit log tersedia.
* [ ] Audit log mencatat user.
* [ ] Audit log mencatat waktu.
* [ ] Audit log mencatat modul.
* [ ] Audit log mencatat aksi.
* [ ] Audit log mencatat data lama jika memungkinkan.
* [ ] Audit log mencatat data baru jika memungkinkan.
* [ ] Perubahan pelanggan tercatat.
* [ ] Perubahan POP tercatat.
* [ ] Perubahan paket tercatat.
* [ ] Perubahan invoice tercatat.
* [ ] Perubahan payment tercatat.
* [ ] Perubahan role/permission tercatat.
* [ ] Owner/Admin Pusat dapat melihat audit log.
* [ ] User biasa tidak dapat menghapus audit log.

Tidak boleh dianggap selesai jika:

* Audit log hanya mencatat sebagian data tanpa alasan.
* Audit log bisa diedit user biasa.
* Perubahan pembayaran tidak tercatat.

---

# 17. Format Review Akhir Task

Setiap selesai task, AI wajib memberikan output:

```md
## Task Selesai
Nama task:

## Scope Check
- Sesuai task aktif: Ya/Tidak
- Keluar MVP: Ya/Tidak
- Menyentuh sprint lain: Ya/Tidak

## File Diubah
- file 1
- file 2

## Acceptance Criteria
- [x] Kriteria 1
- [x] Kriteria 2
- [ ] Kriteria belum terpenuhi

## Cara Test
1. ...
2. ...
3. ...

## Risiko / Catatan
Tuliskan jika ada.

## Update TASKS.md
- Task yang dipindah ke Done:
- Task berikutnya yang menjadi In Progress:

## Rekomendasi
Lanjut / Perbaiki dulu
```

---

# 18. Larangan Menandai Done

Task tidak boleh ditandai Done jika:

1. Acceptance criteria belum terpenuhi.
2. Masih ada error utama.
3. Route belum aman.
4. Query POP belum dibatasi.
5. Data bisa bocor antar cabang.
6. Pelanggan belum lengkap bisa dibuatkan invoice.
7. Payment bisa dibuat tanpa invoice.
8. Status berubah tanpa validasi.
9. Ada fitur post-MVP masuk tanpa persetujuan.
10. Belum ada cara test.
11. `docs/TASKS.md` belum diupdate.

---

# 19. Definition of Done untuk Advanced Hierarchical RBAC

Task yang berkaitan dengan Advanced RBAC dianggap selesai jika memenuhi kriteria spesifik berikut:

### 19.1 Feature Tree & Action Permission
*   [ ] Tabel `features` dan `actions` terbuat dengan index dan unique constraints (`code`) yang benar.
*   [ ] Kombinasi model `Feature` dan `Action` mendukung format kode permission `{feature_code}.{action_code}`.
*   [ ] Command generator `php artisan rbac:generate-permissions` berjalan secara idempotent (bisa dijalankan berulang tanpa menduplikasi data).

### 19.2 Role Permission Matrix UI
*   [ ] UI menyajikan Feature Tree secara berjenjang (hierarki modular) dengan expand/collapse yang lancar.
*   [ ] Interaksi matriks checkbox mendukung cascade check/uncheck (mencentang parent otomatis mencentang semua child).
*   [ ] Perubahan pemetaan matrix permission disimpan ke backend dan tercatat di Audit Log secara akurat.

### 19.3 User Form & Scope Management
*   [ ] Form tambah/edit user mewajibkan pemilihan Role beserta Scope Type.
*   [ ] UI memaksa pemilihan POP Target secara dinamis jika scope `selected_pop` atau `pop_tree` dipilih.
*   [ ] Panel *Effective Permission Preview* menampilkan daftar izin bersih (izin akhir) user secara langsung sebelum disubmit.

### 19.4 Keamanan & Route Protection Middleware
*   [ ] Middleware backend menolak akses (return `403 Forbidden`) jika user tidak memiliki permission `{feature_code}.{action_code}` yang valid.
*   [ ] Otorisasi route murni berbasis permission middleware, dilarang keras melakukan pengecekan hardcode nama role (seperti `role:Admin`).
*   [ ] Penyamaran (sensor) field sensitif (password PPPoE/WiFi) terimplementasi di level API/Controller, bukan sekadar manipulasi CSS/JS frontend.

### 19.5 POP Scope Helper
*   [ ] Pembatasan wilayah data cabang (`all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`) diterapkan otomatis via Eloquent Global Scope (PopScope).
*   [ ] Seluruh query database pelanggan, invoice, payment, dashboard, dan laporan mematuhi POP scope user login.

### 19.6 Pengujian (Testing)
*   [ ] Unit test untuk logic `EffectiveAccessService` / `userCan()` lulus dengan cakupan skenario positif/negatif.
*   [ ] Integration test untuk memvalidasi proteksi route dan cegah kebocoran data POP scope berjalan sukses.

### 19.7 Larangan Mutlak Menandai Done pada Advanced RBAC
Dilarang keras menandai task Advanced RBAC sebagai "Done" jika:
1.  **Route Belum Aman:** Route backend masih bisa diakses langsung via URL / API bypass tanpa token/permission check.
2.  **POP Scope Bocor:** User Admin POP dengan scope `selected_pop` masih bisa memodifikasi atau melihat data pelanggan di POP lain melalui request manual (ID parameter tampering).
3.  **Data Hilang saat User Dihapus:** Penghapusan user (soft delete) ikut menghapus history data log audit atau invoice yang diinput oleh user tersebut.
