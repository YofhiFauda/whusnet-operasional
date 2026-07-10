
---

# FILE: docs/ACCEPTANCE_CRITERIA.md

# Acceptance Criteria
# Website Billing ISP Berbasis Master Data Pelanggan

## Global Acceptance Criteria
Sistem dianggap sesuai PRD jika:

- [ ] Data pelanggan lama bisa diimport ke sistem baru.
- [ ] Data pelanggan bisa diinput manual.
- [ ] Setiap pelanggan memiliki status kelengkapan data.
- [ ] Admin tahu pelanggan mana yang datanya belum lengkap.
- [ ] Billing hanya berjalan untuk pelanggan yang datanya valid.
- [ ] Tagihan dibuat berdasarkan paket aktif pelanggan.
- [ ] Pembayaran bisa dicatat dan mengubah status tagihan.
- [ ] Pelanggan bisa difilter berdasarkan POP/Cabang.
- [ ] Admin cabang hanya melihat data cabangnya.
- [ ] Laporan pelanggan, tagihan, dan pembayaran bisa digunakan.

---

## Modul Login
Acceptance Criteria:

- [ ] User dapat login.
- [ ] User dapat logout.
- [ ] Halaman admin tidak bisa diakses tanpa login.
- [ ] User tidak aktif tidak bisa login.
- [ ] Setelah login, user masuk ke dashboard.

---

## Modul RBAC
Acceptance Criteria:

- [ ] Sistem memiliki role.
- [ ] Sistem memiliki permission.
- [ ] User dapat memiliki role.
- [ ] Role dapat memiliki banyak permission.
- [ ] User hanya melihat menu sesuai hak akses.
- [ ] User tidak bisa membuka URL fitur yang tidak diizinkan.
- [ ] Admin cabang tidak bisa melihat data cabang lain.
- [ ] Teknisi tidak bisa membuka menu pembayaran.
- [ ] Finance tidak bisa mengubah data modem.
- [ ] Customer Service tidak bisa mengubah nominal tagihan.

---

## Modul POP/Cabang
Acceptance Criteria:

- [ ] POP dapat dibuat.
- [ ] POP dapat diedit.
- [ ] POP dapat dinonaktifkan.
- [ ] POP dapat memiliki parent-child.
- [ ] Tipe POP tersedia: Pusat, Cabang, Mini POP.
- [ ] Pelanggan dapat difilter berdasarkan POP.
- [ ] User dapat di-assign ke POP.
- [ ] Admin Cabang hanya melihat pelanggan POP yang ditugaskan.

---

## Modul Paket Internet
Acceptance Criteria:

- [ ] Paket dapat dibuat.
- [ ] Paket dapat diedit.
- [ ] Paket dapat dinonaktifkan.
- [ ] Paket dapat dipilih saat input pelanggan.
- [ ] Harga paket dapat menjadi dasar tagihan.
- [ ] Paket nonaktif tidak bisa dipilih untuk pelanggan baru.

---

## Modul Input Manual Pelanggan
Acceptance Criteria:

- [ ] Admin dapat menyimpan data pelanggan walaupun belum lengkap.
- [ ] Sistem menandai field wajib yang belum diisi.
- [ ] Sistem memberi status kelengkapan data.
- [ ] Data pelanggan belum lengkap tidak bisa masuk billing aktif.
- [ ] Data pelanggan lengkap bisa diubah menjadi siap billing.
- [ ] Detail pelanggan menampilkan tab data.
- [ ] Data pelanggan dapat dicari.
- [ ] Data pelanggan dapat difilter berdasarkan POP.
- [ ] Data pelanggan dapat difilter berdasarkan status kelengkapan.
- [ ] Data pelanggan dapat difilter berdasarkan status layanan.

---

## Modul Import Excel/CSV
Acceptance Criteria:

- [ ] Admin dapat mendownload template Excel/CSV.
- [ ] Admin dapat mengupload file Excel/CSV.
- [ ] Sistem membaca data pelanggan.
- [ ] Sistem menampilkan preview sebelum import.
- [ ] Sistem menandai data valid dan invalid.
- [ ] Sistem menolak data yang tidak valid.
- [ ] Sistem menjelaskan alasan data gagal.
- [ ] Sistem mencegah duplikasi.
- [ ] Sistem menyimpan ID pelanggan lama.
- [ ] Sistem menyimpan log import.
- [ ] Data hasil import bisa diedit seperti data input manual.

---

## Modul Validasi Kelengkapan Data
Acceptance Criteria:

- [ ] Sistem dapat menampilkan persentase kelengkapan data.
- [ ] Sistem menampilkan daftar field yang belum lengkap.
- [ ] Sistem mencegah pelanggan belum lengkap masuk billing aktif.
- [ ] Admin dapat melihat daftar pelanggan yang perlu dilengkapi.
- [ ] Status data berubah otomatis berdasarkan field wajib.

Field wajib agar siap billing:

- [ ] Nama lengkap
- [ ] Nomor HP
- [ ] Alamat lengkap
- [ ] Desa/Kelurahan
- [ ] Kecamatan
- [ ] Kota/Kabupaten
- [ ] POP/Cabang
- [ ] Paket internet
- [ ] Harga bulanan
- [ ] Tanggal aktivasi
- [ ] Tanggal jatuh tempo
- [ ] Status layanan

---

## Modul Aktivasi Layanan
Acceptance Criteria:

- [ ] Pelanggan tidak bisa diaktifkan jika data wajib belum lengkap.
- [ ] Pelanggan aktif harus memiliki paket.
- [ ] Pelanggan aktif harus memiliki nominal tagihan.
- [ ] Tanggal jatuh tempo wajib ada.
- [ ] Sistem menyimpan riwayat aktivasi.
- [ ] Status pelanggan berubah menjadi aktif/siap billing.

---

## Modul Tagihan
Acceptance Criteria:

- [ ] Tagihan hanya bisa dibuat untuk pelanggan aktif/siap billing.
- [ ] Tagihan mengambil harga dari layanan pelanggan.
- [ ] Tagihan memiliki periode.
- [ ] Tagihan memiliki status.
- [ ] Tagihan dapat difilter berdasarkan POP.
- [ ] Tagihan dapat difilter berdasarkan periode.
- [ ] Tagihan dapat difilter berdasarkan status.
- [ ] Tagihan dapat difilter berdasarkan pelanggan.
- [ ] Tagihan tidak boleh dobel untuk periode yang sama.
- [ ] Tagihan lunas tidak boleh dihapus sembarangan.

---

## Modul Pembayaran
Acceptance Criteria:

- [ ] Finance dapat mencatat pembayaran.
- [ ] Pembayaran muncul di detail pelanggan.
- [ ] Status tagihan berubah sesuai nominal pembayaran.
- [ ] Bukti pembayaran dapat diupload.
- [ ] Pembayaran dapat difilter berdasarkan tanggal.
- [ ] Pembayaran dapat difilter berdasarkan POP.
- [ ] Pembayaran dapat difilter berdasarkan metode.
- [ ] Pembayaran dapat difilter berdasarkan status.
- [ ] Perubahan pembayaran masuk audit log.

---

## Modul Dashboard
Acceptance Criteria:

- [ ] Owner melihat semua data.
- [ ] Admin Pusat melihat semua cabang.
- [ ] Admin Cabang hanya melihat cabangnya.
- [ ] Dashboard dapat difilter berdasarkan POP.
- [ ] Dashboard dapat difilter berdasarkan periode.
- [ ] Dashboard menampilkan pelanggan belum lengkap.
- [ ] Dashboard menampilkan tagihan jatuh tempo.

---

## Modul Laporan
Acceptance Criteria:

- [ ] Laporan dapat difilter berdasarkan tanggal.
- [ ] Laporan dapat difilter berdasarkan POP.
- [ ] Laporan dapat diexport ke Excel/CSV.
- [ ] Admin cabang hanya bisa export data cabangnya.
- [ ] Laporan pelanggan tersedia.
- [ ] Laporan tagihan tersedia.
- [ ] Laporan pembayaran tersedia.
- [ ] Laporan tunggakan tersedia.
- [ ] Laporan import tersedia.

---

## Modul Audit Log
Acceptance Criteria:

- [ ] Perubahan pelanggan tercatat.
- [ ] Perubahan pembayaran tercatat.
- [ ] Perubahan tagihan tercatat.
- [ ] Perubahan role tercatat.
- [ ] Perubahan POP tercatat.
- [ ] Perubahan paket tercatat.
- [ ] Owner/Admin Pusat dapat melihat audit log.