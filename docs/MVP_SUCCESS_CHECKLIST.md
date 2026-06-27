# MVP_SUCCESS_CHECKLIST.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini digunakan untuk mengecek apakah MVP Website Billing ISP sudah benar-benar sesuai PRD.

MVP tidak dianggap selesai hanya karena semua halaman sudah dibuat.

MVP dianggap selesai jika:

1. Data pelanggan lama bisa masuk.
2. Data pelanggan baru bisa diinput manual.
3. Data pelanggan memiliki status kelengkapan.
4. Billing hanya berjalan dari pelanggan valid.
5. Tagihan dibuat berdasarkan paket aktif pelanggan.
6. Pembayaran mengubah status tagihan.
7. Data bisa difilter berdasarkan POP/Cabang.
8. Admin Cabang hanya melihat data cabangnya.
9. Laporan dasar dapat digunakan.
10. Audit log mencatat perubahan penting.

---

# 1. Checklist Scope MVP

MVP harus memiliki fitur berikut:

* [ ] Login.
* [ ] User Management.
* [ ] Role.
* [ ] Permission.
* [ ] RBAC dasar.
* [ ] POP/Cabang.
* [ ] Assign user ke POP.
* [ ] Paket Internet.
* [ ] Input Manual Pelanggan.
* [ ] Import Excel/CSV Pelanggan Lama.
* [ ] Validasi Kelengkapan Data Pelanggan.
* [ ] Detail Pelanggan Lengkap.
* [ ] Aktivasi Layanan Pelanggan.
* [ ] Tagihan Manual.
* [ ] Pembayaran.
* [ ] Dashboard.
* [ ] Laporan sederhana.
* [ ] Audit Log.

---

# 2. Checklist Fitur yang Tidak Boleh Ada di MVP

Fitur berikut tidak boleh dibuat pada MVP kecuali sudah diputuskan sebagai change request:

* [ ] Integrasi MikroTik tidak dibuat.
* [ ] Payment gateway tidak dibuat.
* [ ] Auto suspend tidak dibuat.
* [ ] WhatsApp notification tidak dibuat.
* [ ] Ticketing kompleks tidak dibuat.
* [ ] Monitoring OLT/SNMP tidak dibuat.
* [ ] Inventory kompleks tidak dibuat.
* [ ] Mobile app teknisi tidak dibuat.
* [ ] Multi-company tidak dibuat.
* [ ] Laporan akuntansi kompleks tidak dibuat.

Jika salah satu fitur di atas sudah dibuat tanpa persetujuan, berarti MVP keluar scope.

---

# 3. Checklist Login dan User

* [ ] User dapat login.
* [ ] User dapat logout.
* [ ] Halaman admin tidak bisa diakses tanpa login.
* [ ] User memiliki role.
* [ ] User dapat ditugaskan ke POP.
* [ ] User nonaktif tidak bisa login jika fitur status user diterapkan.
* [ ] Owner pertama tersedia.
* [ ] Password disimpan dengan hash.
* [ ] Tidak ada password plain text.

---

# 4. Checklist RBAC (Advanced Hierarchical RBAC)

*   [ ] Sembilan (9) Role Utama tersedia di database seeder: `Owner`, `Atasan`, `Admin`, `NOC`, `Helpdesk`, `FOP`, `Teknisi`, `Sales`, dan `Admin POP`.
*   [ ] Struktur Feature Tree (Modul, Sub-modul, Mini Fitur) tersedia di database.
*   [ ] Daftar aksi standar (Standard Actions) tersedia di database.
*   [ ] Kode Permission terbuat dengan format kombinasi `{feature_code}.{action_code}` (e.g., `customers.view`).
*   [ ] Tabel `user_role_scopes` dan `user_role_scope_targets` tersedia untuk memisahkan Role dari Scope wilayah POP.
*   [ ] NOC Pusat dikonfigurasi dengan scope `all_pop` (akses nasional).
*   [ ] Admin POP dikonfigurasi wajib dengan scope `selected_pop` (akses terbatas POP wilayah kerjanya).
*   [ ] Menu navigasi (Sidebar) dan rendering tombol aksi tampil secara dinamis berdasarkan permission user login.
*   [ ] Route backend terlindungi sepenuhnya menggunakan permission middleware, bukan pengecekan hardcode nama role.
*   [ ] Direct URL / API Access diuji aman dari pemintasan parameter (Tampering) untuk user yang tidak memiliki izin.
*   [ ] Keamanan data sensitif (password PPPoE/WiFi, detail perangkat teknis) tersensor default dan hanya bisa di-reveal jika memiliki permission `view_sensitive`.
*   [ ] Query database terisolasi otomatis berdasarkan POP Scope user login (mencegah kebocoran data antar-cabang).
*   [ ] **Larangan Fungsi Bisnis Terlindungi:**
    *   [ ] Teknisi tidak memiliki menu/akses untuk mencatat pembayaran atau mengunggah bukti bayar.
    *   [ ] Helpdesk tidak memiliki akses untuk mengubah nominal tagihan yang sudah terbit.
    *   [ ] Sales tidak memiliki akses ke laporan keuangan, laporan tagihan, atau laporan pembayaran.
    *   [ ] Admin POP tidak dapat melihat, mengubah, atau mengekspor data milik POP lain.
*   [ ] Setiap modifikasi role, permission, dan penugasan scope user tercatat instan di Audit Log.

---

# 5. Checklist POP/Cabang

* [ ] POP dapat dibuat.
* [ ] POP dapat diedit.
* [ ] POP dapat dinonaktifkan.
* [ ] POP memiliki kode POP.
* [ ] POP memiliki tipe: pusat, cabang, mini_pop.
* [ ] POP dapat memiliki parent-child.
* [ ] POP memiliki alamat.
* [ ] POP memiliki PIC.
* [ ] POP memiliki status aktif/nonaktif.
* [ ] User dapat diassign ke POP.
* [ ] Pelanggan wajib terhubung ke POP.
* [ ] Laporan dapat difilter berdasarkan POP.
* [ ] Admin Cabang hanya melihat data POP yang ditugaskan.

---

# 6. Checklist ID Numbering Berdasarkan POP

* [ ] Setiap POP memiliki `pop_code`.
* [ ] Setiap POP memiliki `registration_prefix`.
* [ ] Setiap POP memiliki `cid_prefix`.
* [ ] Sequence registration tersedia per POP.
* [ ] Sequence CID tersedia per POP.
* [ ] ID Request dibuat otomatis saat pelanggan pertama disimpan.
* [ ] CID dibuat otomatis saat pelanggan diaktifkan.
* [ ] Format ID Request sesuai aturan.
* [ ] Format CID sesuai aturan.
* [ ] ID Request tidak duplikat.
* [ ] CID tidak duplikat.
* [ ] ID Request berjalan per POP.
* [ ] CID berjalan per POP.
* [ ] ID tidak dibuat menggunakan `count(customers) + 1`.
* [ ] Import pelanggan menghasilkan ID Request.
* [ ] CID tidak dibuat sebelum pelanggan siap billing/aktif.

---

# 7. Checklist Paket Internet

* [ ] Paket dapat dibuat.
* [ ] Paket dapat diedit.
* [ ] Paket dapat dinonaktifkan.
* [ ] Paket memiliki nama.
* [ ] Paket memiliki kategori.
* [ ] Paket memiliki kecepatan download.
* [ ] Paket memiliki kecepatan upload.
* [ ] Paket memiliki harga bulanan.
* [ ] Paket memiliki PPN jika digunakan.
* [ ] Paket memiliki diskon jika digunakan.
* [ ] Paket memiliki total harga.
* [ ] Paket aktif dapat dipilih saat input pelanggan.
* [ ] Paket nonaktif tidak dapat dipilih untuk pelanggan baru.
* [ ] Harga paket dapat menjadi dasar tagihan.
* [ ] Harga layanan pelanggan disimpan sebagai snapshot.

---

# 8. Checklist Input Manual Pelanggan

* [ ] Admin dapat membuat pelanggan manual.
* [ ] Pelanggan dapat disimpan walaupun belum lengkap.
* [ ] Pelanggan memiliki ID Request.
* [ ] Pelanggan memiliki POP.
* [ ] Pelanggan memiliki data identitas.
* [ ] Pelanggan memiliki alamat.
* [ ] Pelanggan dapat memilih paket.
* [ ] Pelanggan memiliki data layanan.
* [ ] Pelanggan memiliki status kelengkapan data.
* [ ] Pelanggan memiliki status layanan.
* [ ] Sistem menampilkan field yang belum lengkap.
* [ ] Sistem menghitung persentase kelengkapan jika diterapkan.
* [ ] Pelanggan belum lengkap tidak bisa masuk billing aktif.
* [ ] Pelanggan belum lengkap tidak bisa dibuatkan invoice.
* [ ] Admin Cabang hanya melihat pelanggan POP yang ditugaskan.

---

# 9. Checklist Detail Pelanggan

* [ ] Detail pelanggan memiliki tab Ringkasan.
* [ ] Detail pelanggan memiliki tab Identitas.
* [ ] Detail pelanggan memiliki tab Alamat.
* [ ] Detail pelanggan memiliki tab POP/Cabang.
* [ ] Detail pelanggan memiliki tab Paket & Layanan.
* [ ] Detail pelanggan memiliki tab Survey.
* [ ] Detail pelanggan memiliki tab Pemasangan.
* [ ] Detail pelanggan memiliki tab Modem/Perangkat.
* [ ] Detail pelanggan memiliki tab Billing.
* [ ] Detail pelanggan memiliki tab Tagihan.
* [ ] Detail pelanggan memiliki tab Pembayaran.
* [ ] Detail pelanggan memiliki tab Dokumen.
* [ ] Detail pelanggan memiliki tab Riwayat Perubahan.
* [ ] Status kelengkapan terlihat jelas.
* [ ] Field yang belum lengkap terlihat jelas.
* [ ] Tombol aksi mengikuti permission.
* [ ] Field sensitif mengikuti permission.
* [ ] Admin Cabang tidak bisa membuka pelanggan cabang lain.

---

# 10. Checklist Import Excel/CSV

* [ ] Admin dapat download template.
* [ ] Admin dapat upload file Excel/CSV.
* [ ] Sistem membaca file.
* [ ] Sistem membaca header.
* [ ] Sistem menyediakan mapping kolom jika dibutuhkan.
* [ ] Sistem memvalidasi field wajib.
* [ ] Sistem mengecek ID pelanggan lama.
* [ ] Sistem mengecek duplikasi.
* [ ] Sistem mengecek POP.
* [ ] Sistem mengecek paket.
* [ ] Sistem mengecek harga.
* [ ] Sistem mengecek tanggal.
* [ ] Sistem mengecek status layanan.
* [ ] Sistem menampilkan preview.
* [ ] Data valid dan invalid dipisahkan.
* [ ] Alasan data gagal ditampilkan.
* [ ] Data invalid tidak masuk master pelanggan.
* [ ] Data valid masuk master pelanggan setelah konfirmasi.
* [ ] Import batch tersimpan.
* [ ] Import error tersimpan.
* [ ] Data hasil import bisa diedit manual.
* [ ] Import tidak membuat invoice otomatis pada MVP.
* [ ] Import tidak membuat payment otomatis.

---

# 11. Checklist Validasi Kelengkapan Data

* [ ] Sistem mengecek nama lengkap.
* [ ] Sistem mengecek nomor HP.
* [ ] Sistem mengecek alamat lengkap.
* [ ] Sistem mengecek desa/kelurahan.
* [ ] Sistem mengecek kecamatan.
* [ ] Sistem mengecek kota/kabupaten.
* [ ] Sistem mengecek POP/Cabang.
* [ ] Sistem mengecek paket internet.
* [ ] Sistem mengecek harga bulanan.
* [ ] Sistem mengecek tanggal aktivasi.
* [ ] Sistem mengecek tanggal jatuh tempo.
* [ ] Sistem mengecek status layanan.
* [ ] Sistem menampilkan field yang belum lengkap.
* [ ] Sistem mencegah pelanggan belum lengkap masuk billing aktif.
* [ ] Sistem mengubah status kelengkapan otomatis.
* [ ] Admin dapat melihat daftar pelanggan yang perlu dilengkapi.

---

# 12. Checklist Aktivasi Layanan

* [ ] Pelanggan belum lengkap tidak bisa diaktifkan.
* [ ] Pelanggan harus memiliki paket aktif.
* [ ] Pelanggan harus memiliki nominal tagihan.
* [ ] Pelanggan harus memiliki tanggal aktivasi.
* [ ] Pelanggan harus memiliki tanggal jatuh tempo.
* [ ] Aktivasi mengubah status pelanggan menjadi aktif.
* [ ] Aktivasi mengubah status kelengkapan menjadi siap billing.
* [ ] Aktivasi membuat CID jika aturan CID diterapkan.
* [ ] Aktivasi menyimpan riwayat.
* [ ] Aktivasi masuk audit log jika audit aktif.

---

# 13. Checklist Tagihan / Invoice

* [ ] Invoice hanya dibuat untuk pelanggan aktif/siap billing.
* [ ] Invoice mengambil harga dari layanan pelanggan.
* [ ] Invoice menyimpan snapshot harga.
* [ ] Invoice memiliki nomor invoice.
* [ ] Invoice memiliki customer.
* [ ] Invoice memiliki POP.
* [ ] Invoice memiliki periode.
* [ ] Invoice memiliki tanggal terbit.
* [ ] Invoice memiliki tanggal jatuh tempo.
* [ ] Invoice memiliki subtotal.
* [ ] Invoice memiliki total tagihan.
* [ ] Invoice memiliki status.
* [ ] Invoice tidak dobel untuk pelanggan dan periode yang sama.
* [ ] Invoice dapat difilter berdasarkan POP.
* [ ] Invoice dapat difilter berdasarkan periode.
* [ ] Invoice dapat difilter berdasarkan status.
* [ ] Invoice dapat difilter berdasarkan pelanggan.
* [ ] Admin Cabang hanya melihat invoice POP yang ditugaskan.

---

# 14. Checklist Pembayaran

* [ ] Payment dapat dibuat dari invoice.
* [ ] Payment terhubung ke invoice.
* [ ] Payment terhubung ke customer.
* [ ] Payment terhubung ke POP.
* [ ] Payment memiliki nomor pembayaran.
* [ ] Payment memiliki tanggal bayar.
* [ ] Payment memiliki metode bayar.
* [ ] Payment memiliki nominal bayar.
* [ ] Payment memiliki penerima.
* [ ] Payment dapat menyimpan bukti pembayaran.
* [ ] Payment memiliki status.
* [ ] Payment valid mengubah paid amount invoice.
* [ ] Payment valid mengubah remaining amount invoice.
* [ ] Payment penuh mengubah invoice menjadi lunas.
* [ ] Payment sebagian mengubah invoice menjadi sebagian.
* [ ] Payment ditolak tidak membuat invoice lunas.
* [ ] Teknisi tidak bisa mencatat pembayaran.
* [ ] CS tidak bisa validasi pembayaran.
* [ ] Perubahan payment masuk audit log.

---

# 15. Checklist Dashboard

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
* [ ] Dashboard dapat difilter berdasarkan POP.
* [ ] Dashboard dapat difilter berdasarkan periode.
* [ ] Owner melihat semua data.
* [ ] Admin Pusat melihat semua cabang.
* [ ] Admin Cabang hanya melihat cabangnya.

---

# 16. Checklist Laporan

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

---

# 17. Checklist Audit Log

* [ ] Audit log mencatat perubahan pelanggan.
* [ ] Audit log mencatat perubahan POP.
* [ ] Audit log mencatat perubahan paket.
* [ ] Audit log mencatat perubahan invoice.
* [ ] Audit log mencatat perubahan payment.
* [ ] Audit log mencatat perubahan user.
* [ ] Audit log mencatat perubahan role.
* [ ] Audit log mencatat perubahan permission.
* [ ] Audit log mencatat import.
* [ ] Audit log mencatat user pelaku.
* [ ] Audit log mencatat waktu.
* [ ] Audit log mencatat aksi.
* [ ] Audit log mencatat data lama jika memungkinkan.
* [ ] Audit log mencatat data baru jika memungkinkan.
* [ ] Owner/Admin Pusat dapat melihat audit log.
* [ ] User biasa tidak dapat menghapus audit log.

---

# 18. Final MVP Decision

MVP boleh dianggap selesai jika:

* [ ] Semua checklist scope MVP terpenuhi.
* [ ] Tidak ada fitur post-MVP yang masuk tanpa persetujuan.
* [ ] Data pelanggan lama bisa diimport.
* [ ] Data pelanggan baru bisa diinput manual.
* [ ] Status kelengkapan data berjalan.
* [ ] Pelanggan belum lengkap tidak bisa dibuatkan invoice.
* [ ] Pelanggan aktif bisa dibuatkan invoice.
* [ ] Pembayaran bisa mencatat pelunasan.
* [ ] Status invoice berubah sesuai pembayaran.
* [ ] Data cabang aman berdasarkan POP.
* [ ] Dashboard dapat digunakan.
* [ ] Laporan dasar dapat digunakan.
* [ ] Audit log mencatat perubahan penting.

Jika salah satu poin utama gagal, MVP belum layak dinyatakan selesai.
