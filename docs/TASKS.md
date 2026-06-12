# Current Tasks
# Website Billing ISP Berbasis Master Data Pelanggan

## Status Project Saat Ini
Current Sprint: Sprint 5 — Billing Dasar
Current Module: Buat Tagihan Manual
Current Task: S5-T003 — Buat Tagihan Manual

---

# Sprint 5 — Billing Dasar

## Tujuan Sprint 5
Membuat aktivasi layanan dan tagihan manual berdasarkan pelanggan aktif.

---

### S5-T003 — Buat Tagihan Manual
Status: In Progress

Tujuan:
Membuat invoice manual dari pelanggan aktif.

Checklist:
- [ ] Buat tombol buat tagihan di detail pelanggan.
- [ ] Cek pelanggan aktif/siap billing.
- [ ] Ambil paket aktif pelanggan.
- [ ] Ambil harga layanan pelanggan.
- [ ] Ambil tanggal jatuh tempo.
- [ ] Tentukan periode tagihan.
- [ ] Cek invoice dobel untuk periode sama.
- [ ] Buat invoice.
- [ ] Status invoice default belum dibayar.

Acceptance Criteria:
- [ ] Tagihan hanya bisa dibuat untuk pelanggan aktif/siap billing.
- [ ] Tagihan mengambil harga dari layanan pelanggan.
- [ ] Tagihan memiliki periode.
- [ ] Tagihan tidak dobel untuk periode yang sama.
- [ ] Tagihan memiliki status belum dibayar.

---

# Sprint 5 — Billing Dasar

## Tujuan Sprint 5
Membuat aktivasi layanan dan tagihan manual berdasarkan pelanggan aktif.

---

### S5-T003 — Buat Tagihan Manual
Status: In Progress

Tujuan:
Membuat invoice manual dari pelanggan aktif.

Checklist:
- [ ] Buat tombol buat tagihan di detail pelanggan.
- [ ] Cek pelanggan aktif/siap billing.
- [ ] Ambil paket aktif pelanggan.
- [ ] Ambil harga layanan pelanggan.
- [ ] Ambil tanggal jatuh tempo.
- [ ] Tentukan periode tagihan.
- [ ] Cek invoice dobel untuk periode sama.
- [ ] Buat invoice.
- [ ] Status invoice default belum dibayar.

Acceptance Criteria:
- [ ] Tagihan hanya bisa dibuat untuk pelanggan aktif/siap billing.
- [ ] Tagihan mengambil harga dari layanan pelanggan.
- [ ] Tagihan memiliki periode.
- [ ] Tagihan tidak dobel untuk periode yang sama.
- [ ] Tagihan memiliki status belum dibayar.

---

### S5-T004 — Daftar dan Detail Tagihan
Status: Todo

Tujuan:
Membuat halaman daftar dan detail invoice.

Checklist:
- [ ] Buat halaman daftar invoice.
- [ ] Buat filter POP.
- [ ] Buat filter periode.
- [ ] Buat filter status.
- [ ] Buat search pelanggan/invoice.
- [ ] Buat halaman detail invoice.
- [ ] Tampilkan pelanggan.
- [ ] Tampilkan paket.
- [ ] Tampilkan total.
- [ ] Tampilkan status.

Acceptance Criteria:
- [ ] Tagihan dapat difilter berdasarkan POP.
- [ ] Tagihan dapat difilter berdasarkan periode.
- [ ] Tagihan dapat difilter berdasarkan status.
- [ ] Tagihan dapat difilter berdasarkan pelanggan.
- [ ] Admin Cabang hanya melihat tagihan POP yang ditugaskan.

---

# Sprint 6 — Pembayaran

## Tujuan Sprint 6
Membuat pencatatan pembayaran dan update status invoice.

---

### S6-T001 — Migration dan Model Payment
Status: Todo

Tujuan:
Membuat struktur pembayaran.

Checklist:
- [ ] Buat tabel `payments`.
- [ ] Tambahkan nomor pembayaran.
- [ ] Tambahkan invoice_id.
- [ ] Tambahkan customer_id.
- [ ] Tambahkan pop_id.
- [ ] Tambahkan tanggal bayar.
- [ ] Tambahkan metode bayar.
- [ ] Tambahkan nominal bayar.
- [ ] Tambahkan penerima.
- [ ] Tambahkan bukti pembayaran.
- [ ] Tambahkan status pembayaran.
- [ ] Tambahkan catatan.

Acceptance Criteria:
- [ ] Payment dapat disimpan.
- [ ] Payment terhubung ke invoice.
- [ ] Payment terhubung ke customer.
- [ ] Payment terhubung ke POP.
- [ ] Payment memiliki status.

---

### S6-T002 — Input Pembayaran
Status: Todo

Tujuan:
Membuat pencatatan pembayaran invoice.

Checklist:
- [ ] Buat tombol input pembayaran di invoice.
- [ ] Buat form pembayaran.
- [ ] Pilih metode pembayaran.
- [ ] Input nominal.
- [ ] Upload bukti jika ada.
- [ ] Simpan pembayaran.
- [ ] Update paid amount invoice.
- [ ] Update remaining amount invoice.
- [ ] Update status invoice.

Acceptance Criteria:
- [ ] Finance dapat mencatat pembayaran.
- [ ] Pembayaran muncul di detail pelanggan.
- [ ] Jika nominal penuh, invoice menjadi lunas.
- [ ] Jika nominal kurang, invoice menjadi sebagian.
- [ ] Bukti pembayaran dapat diupload.

---

### S6-T003 — Daftar dan Detail Pembayaran
Status: Todo

Tujuan:
Membuat halaman daftar dan detail pembayaran.

Checklist:
- [ ] Buat halaman daftar pembayaran.
- [ ] Buat filter tanggal.
- [ ] Buat filter metode.
- [ ] Buat filter POP.
- [ ] Buat filter status.
- [ ] Buat search pelanggan/invoice.
- [ ] Buat detail pembayaran.
- [ ] Tampilkan bukti pembayaran.

Acceptance Criteria:
- [ ] Pembayaran dapat difilter berdasarkan tanggal.
- [ ] Pembayaran dapat difilter berdasarkan POP.
- [ ] Pembayaran dapat difilter berdasarkan metode.
- [ ] Pembayaran dapat difilter berdasarkan status.
- [ ] Admin Cabang hanya melihat pembayaran POP yang ditugaskan.

---

### S6-T004 — Audit Log Pembayaran
Status: Todo

Tujuan:
Mencatat perubahan pembayaran.

Checklist:
- [ ] Catat create pembayaran.
- [ ] Catat update pembayaran.
- [ ] Catat pembatalan pembayaran jika ada.
- [ ] Catat user yang melakukan perubahan.
- [ ] Catat waktu perubahan.
- [ ] Catat data sebelum dan sesudah.

Acceptance Criteria:
- [ ] Perubahan pembayaran masuk audit log.
- [ ] Owner/Admin Pusat dapat melihat log pembayaran.
- [ ] Perubahan pembayaran tidak hilang dari riwayat.

---

# Sprint 7 — Dashboard dan Laporan

## Tujuan Sprint 7
Membuat dashboard dan laporan operasional sederhana.

---

### S7-T001 — Dashboard Ringkasan
Status: Todo

Tujuan:
Membuat dashboard ringkasan pelanggan dan billing.

Checklist:
- [ ] Total pelanggan.
- [ ] Total pelanggan aktif.
- [ ] Total pelanggan belum lengkap.
- [ ] Total pelanggan siap billing.
- [ ] Total pelanggan per POP.
- [ ] Total tagihan bulan ini.
- [ ] Total pembayaran bulan ini.
- [ ] Total tunggakan.
- [ ] Tagihan jatuh tempo.
- [ ] Data pelanggan yang perlu dilengkapi.
- [ ] Filter POP.
- [ ] Filter periode.

Acceptance Criteria:
- [ ] Owner melihat semua data.
- [ ] Admin Pusat melihat semua cabang.
- [ ] Admin Cabang hanya melihat cabangnya.
- [ ] Dashboard dapat difilter berdasarkan POP.
- [ ] Dashboard dapat difilter berdasarkan periode.

---

### S7-T002 — Laporan Pelanggan
Status: Todo

Tujuan:
Membuat laporan pelanggan.

Checklist:
- [ ] Laporan pelanggan lengkap.
- [ ] Laporan pelanggan belum lengkap.
- [ ] Laporan pelanggan aktif.
- [ ] Laporan pelanggan isolir.
- [ ] Laporan pelanggan per POP.
- [ ] Filter tanggal.
- [ ] Filter POP.
- [ ] Export Excel/CSV.

Acceptance Criteria:
- [ ] Laporan pelanggan dapat difilter.
- [ ] Laporan pelanggan dapat diexport.
- [ ] Admin Cabang hanya export data cabangnya.

---

### S7-T003 — Laporan Tagihan
Status: Todo

Tujuan:
Membuat laporan tagihan.

Checklist:
- [ ] Laporan tagihan bulanan.
- [ ] Laporan tagihan per POP.
- [ ] Laporan tagihan per status.
- [ ] Laporan tunggakan.
- [ ] Filter tanggal.
- [ ] Filter POP.
- [ ] Export Excel/CSV.

Acceptance Criteria:
- [ ] Laporan tagihan dapat difilter.
- [ ] Laporan tunggakan tersedia.
- [ ] Laporan tagihan dapat diexport.
- [ ] Admin Cabang hanya export data cabangnya.

---

### S7-T004 — Laporan Pembayaran
Status: Todo

Tujuan:
Membuat laporan pembayaran.

Checklist:
- [ ] Laporan pembayaran bulanan.
- [ ] Laporan pembayaran per POP.
- [ ] Laporan pembayaran per metode.
- [ ] Filter tanggal.
- [ ] Filter POP.
- [ ] Filter metode.
- [ ] Export Excel/CSV.

Acceptance Criteria:
- [ ] Laporan pembayaran dapat difilter.
- [ ] Laporan pembayaran per metode tersedia.
- [ ] Laporan pembayaran dapat diexport.
- [ ] Admin Cabang hanya export data cabangnya.

---

### S7-T005 — Laporan Import Data
Status: Todo

Tujuan:
Membuat laporan hasil import data pelanggan lama.

Checklist:
- [ ] Tampilkan riwayat import.
- [ ] Tampilkan total rows.
- [ ] Tampilkan valid rows.
- [ ] Tampilkan invalid rows.
- [ ] Tampilkan imported rows.
- [ ] Tampilkan error import.
- [ ] Export laporan import jika dibutuhkan.

Acceptance Criteria:
- [ ] Admin dapat melihat riwayat import.
- [ ] Admin dapat melihat data error import.
- [ ] Admin dapat mengetahui data yang berhasil masuk.

---

# Sprint 8 — Data Teknis Pelanggan

## Tujuan Sprint 8
Melengkapi data teknis pelanggan setelah billing dasar stabil.

---

### S8-T001 — Data Survey Pelanggan
Status: Todo

Tujuan:
Membuat data survey pelanggan.

Checklist:
- [ ] Buat tabel `customer_surveys`.
- [ ] Tambahkan status survey.
- [ ] Tambahkan tanggal survey.
- [ ] Tambahkan jam mulai.
- [ ] Tambahkan jam selesai.
- [ ] Tambahkan petugas survey.
- [ ] Tambahkan kebutuhan alat.
- [ ] Tambahkan estimasi kabel.
- [ ] Tambahkan ODP terdekat.
- [ ] Tambahkan foto survey.
- [ ] Tambahkan catatan survey.
- [ ] Tampilkan di detail pelanggan.

Acceptance Criteria:
- [ ] Teknisi dapat mengisi data survey.
- [ ] Data survey tampil di detail pelanggan.
- [ ] User tanpa permission tidak dapat mengisi survey.

---

### S8-T002 — Data Pemasangan Pelanggan
Status: Todo

Tujuan:
Membuat data pemasangan pelanggan.

Checklist:
- [ ] Buat tabel `customer_installations`.
- [ ] Tambahkan status pemasangan.
- [ ] Tambahkan tanggal jadwal.
- [ ] Tambahkan jam jadwal.
- [ ] Tambahkan teknisi pemasangan.
- [ ] Tambahkan tanggal selesai.
- [ ] Tambahkan foto pemasangan.
- [ ] Tambahkan catatan pemasangan.
- [ ] Tampilkan di detail pelanggan.

Acceptance Criteria:
- [ ] Teknisi dapat mengisi data pemasangan.
- [ ] Data pemasangan tampil di detail pelanggan.
- [ ] User tanpa permission tidak dapat mengisi pemasangan.

---

### S8-T003 — Data Modem/ONT/Router Pelanggan
Status: Todo

Tujuan:
Membuat data perangkat pelanggan.

Checklist:
- [ ] Buat tabel `customer_devices`.
- [ ] Tambahkan jenis perangkat.
- [ ] Tambahkan merk.
- [ ] Tambahkan tipe.
- [ ] Tambahkan serial number.
- [ ] Tambahkan MAC address.
- [ ] Tambahkan username PPPoE.
- [ ] Tambahkan password PPPoE.
- [ ] Tambahkan SSID WiFi.
- [ ] Tambahkan password WiFi.
- [ ] Tambahkan IP address.
- [ ] Tambahkan VLAN ID.
- [ ] Tambahkan ODP.
- [ ] Tambahkan port ODP.
- [ ] Tambahkan redaman.
- [ ] Tambahkan mode koneksi.
- [ ] Tambahkan catatan teknis.
- [ ] Batasi akses field sensitif.

Acceptance Criteria:
- [ ] Teknisi dapat mengisi data perangkat.
- [ ] Data perangkat tampil di detail pelanggan.
- [ ] Password PPPoE dan WiFi dibatasi aksesnya.
- [ ] Finance tidak dapat mengubah data modem.
- [ ] CS tidak dapat melihat field sensitif jika tidak diizinkan.

---

### S8-T004 — Data Dokumen Pelanggan
Status: Todo

Tujuan:
Membuat penyimpanan dokumen pelanggan.

Checklist:
- [ ] Buat tabel `customer_documents`.
- [ ] Upload dokumen KTP.
- [ ] Upload foto rumah.
- [ ] Upload kontrak.
- [ ] Upload foto survey.
- [ ] Upload foto pemasangan.
- [ ] Tampilkan dokumen di detail pelanggan.
- [ ] Batasi akses dokumen berdasarkan permission.

Acceptance Criteria:
- [ ] Dokumen pelanggan dapat diupload.
- [ ] Dokumen tampil di detail pelanggan.
- [ ] User tanpa permission tidak dapat mengakses dokumen tertentu.

---

### S8-T005 — Audit Log Umum
Status: Todo

Tujuan:
Membuat audit log untuk perubahan data penting.

Checklist:
- [ ] Buat tabel `audit_logs`.
- [ ] Catat perubahan pelanggan.
- [ ] Catat perubahan paket.
- [ ] Catat perubahan POP.
- [ ] Catat perubahan tagihan.
- [ ] Catat perubahan pembayaran.
- [ ] Catat perubahan user.
- [ ] Catat perubahan role.
- [ ] Catat perubahan data teknis.
- [ ] Buat halaman audit log untuk Owner/Admin Pusat.

Acceptance Criteria:
- [ ] Perubahan pelanggan tercatat.
- [ ] Perubahan pembayaran tercatat.
- [ ] Perubahan tagihan tercatat.
- [ ] Perubahan role tercatat.
- [ ] Owner/Admin Pusat dapat melihat audit log.

---

## Done

## Sprint 1 - Foundation

### S1-T001 — Setup Project
Status: Done

Tujuan:
Membuat pondasi project agar siap dikembangkan.

Checklist:
- [x] Setup project Laravel / framework yang dipakai.
- [x] Setup database.
- [x] Setup environment.
- [x] Setup struktur folder.
- [x] Pastikan aplikasi bisa jalan lokal.
- [x] Tambahkan dokumen `docs/`.
- [x] Tambahkan `AGENTS.md`.

Acceptance Criteria:
- [x] Project bisa dijalankan lokal.
- [x] Database terkoneksi.
- [x] Struktur dokumen tersedia.
- [x] AI memahami aturan project dari dokumen.

Catatan:
Jika project sudah ada, cukup verifikasi setup dan lanjut ke S1-T002.

---


### S1-T002 — Authentication Dasar
Status: Done

Tujuan:
Membuat login user internal.

Checklist:
- [x] Buat fitur login.
- [x] Buat fitur logout.
- [x] Proteksi halaman admin.
- [x] Redirect user setelah login.
- [x] Seed user owner pertama.

Acceptance Criteria:
- [x] User dapat login.
- [x] User dapat logout.
- [x] Halaman admin tidak bisa diakses tanpa login.
- [x] Owner pertama tersedia.

---


### S1-T003 — Model dan Tabel Role
Status: Done

Tujuan:
Membuat struktur role utama sistem.

Checklist:
- [x] Buat tabel roles.
- [x] Buat model Role.
- [x] Buat seeder role.
- [x] Isi role: Owner, Admin Pusat, Admin Cabang, Finance/Kasir, Teknisi, Customer Service.

Acceptance Criteria:
- [x] Role dapat disimpan di database.
- [x] Role utama tersedia dari seeder.
- [x] Tidak ada role di luar kebutuhan MVP.

---

### S1-T004 — Model dan Tabel Permission
Status: Done

Tujuan:
Membuat struktur permission untuk membatasi akses fitur.

Checklist:
- [x] Buat tabel permissions.
- [x] Buat model Permission.
- [x] Buat seeder permission awal.
- [x] Kelompokkan permission berdasarkan modul.

Acceptance Criteria:
- [x] Permission tersimpan di database.
- [x] Permission dikelompokkan sesuai modul.
- [x] Permission tidak mencakup fitur post-MVP.

---

### S1-T005 — Relasi User, Role, dan Permission
Status: Done

Tujuan:
Membuat user dapat memiliki role dan role dapat memiliki banyak permission.

Checklist:
- [x] Relasi user ke role.
- [x] Relasi role ke permission.
- [x] Seeder mapping permission ke role.
- [x] Helper pengecekan permission.

Acceptance Criteria:
- [x] User dapat memiliki role.
- [x] Role dapat memiliki banyak permission.
- [x] Permission dapat dicek dari user login.

---

### S1-T006 — Middleware Permission
Status: Done

Tujuan:
Melindungi route berdasarkan permission.

Checklist:
- [x] Buat middleware permission.
- [x] Terapkan middleware pada route admin.
- [x] Jika tidak punya permission, user mendapat response forbidden.
- [x] Pastikan URL langsung tetap terlindungi.

Acceptance Criteria:
- [x] User tidak bisa membuka URL fitur yang tidak diizinkan.
- [x] Teknisi tidak bisa membuka pembayaran.
- [x] Finance tidak bisa membuka data modem.
- [x] CS tidak bisa mengubah nominal tagihan.

---

### S1-T007 — Layout Dashboard Admin
Status: Done

Tujuan:
Membuat layout dashboard admin dasar berdasarkan role.

Checklist:
- [x] Buat layout admin.
- [x] Buat sidebar.
- [x] Menu tampil berdasarkan permission.
- [x] Buat halaman dashboard kosong/sementara.
- [x] Tambahkan placeholder statistik untuk sprint berikutnya.

Acceptance Criteria:
- [x] User login dapat melihat dashboard.
- [x] Menu tampil sesuai permission.
- [x] Menu yang tidak diizinkan tidak tampil.
- [x] Route tetap aman walaupun menu disembunyikan.

---

# Sprint 2 — POP dan Paket
## Tujuan Sprint 2
Membuat master wilayah operasional ISP dan master paket internet sebagai dasar pengelompokan pelanggan.

### S2-T001 - Master POP/Cabang Migration dan Model
Status: Done

Tujuan:
Membuat struktur database dan model untuk POP/Cabang.

Checklist:
- [x] Buat tabel `pops`.
- [x] Tambahkan field kode POP.
- [x] Tambahkan field nama POP.
- [x] Tambahkan field tipe POP: pusat, cabang, mini_pop.
- [x] Tambahkan field parent_id untuk parent-child POP.
- [x] Tambahkan alamat POP.
- [x] Tambahkan desa/kelurahan.
- [x] Tambahkan kecamatan.
- [x] Tambahkan kota/kabupaten.
- [x] Tambahkan latitude dan longitude.
- [x] Tambahkan PIC POP.
- [x] Tambahkan nomor HP PIC.
- [x] Tambahkan status aktif/nonaktif.
- [x] Buat relasi parent-child pada model POP.

Acceptance Criteria:
- [x] POP dapat disimpan di database.
- [x] POP dapat memiliki parent POP.
- [x] POP dapat memiliki child POP.
- [x] POP memiliki tipe pusat/cabang/mini_pop.
- [x] POP memiliki status aktif/nonaktif.

---

### S2-T002 — CRUD Master POP/Cabang
Status: Done

Tujuan:
Membuat halaman CRUD POP/Cabang.

Checklist:
- [x] Buat halaman daftar POP.
- [x] Buat halaman tambah POP.
- [x] Buat halaman edit POP.
- [x] Buat halaman detail POP.
- [x] Buat filter berdasarkan tipe POP.
- [x] Buat filter berdasarkan status.
- [x] Validasi field wajib POP.
- [x] Pastikan POP bisa dinonaktifkan.

Acceptance Criteria:
- [x] Admin dapat membuat POP Pusat.
- [x] Admin dapat membuat POP Cabang.
- [x] Admin dapat membuat Mini POP.
- [x] POP dapat diedit.
- [x] POP dapat dinonaktifkan.
- [x] POP dapat memiliki parent-child.

---

### S2-T003 — Assign User ke POP
Status: Done

Tujuan:
Membatasi akses user berdasarkan POP yang ditugaskan.

Checklist:
- [x] Buat tabel `user_pops`.
- [x] Buat relasi user ke banyak POP.
- [x] Buat form assign POP ke user.
- [x] Admin Pusat dapat assign user ke POP.
- [x] Admin Cabang hanya bisa melihat data POP yang ditugaskan.
- [x] Buat helper scope query berdasarkan POP user.

Acceptance Criteria:
- [x] User dapat memiliki akses ke satu atau banyak POP.
- [x] Admin Cabang hanya melihat POP yang ditugaskan.
- [x] Data cabang lain tidak terlihat oleh Admin Cabang.
- [x] Pembatasan berlaku di query, bukan hanya tampilan menu.

---

### S2-T004 — Master Paket Internet Migration dan Model
Status: Done

Tujuan:
Membuat struktur database dan model untuk paket internet.

Checklist:
- [x] Gunakan tabel `internet_packages` sebagai sumber data Paket Internet.
- [x] Tambahkan nama paket.
- [x] Tambahkan kategori paket.
- [x] Tambahkan kecepatan download.
- [x] Tambahkan kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan PPN.
- [x] Tambahkan diskon default.
- [x] Tambahkan total harga.
- [x] Tambahkan profile teknis.
- [x] Tambahkan deskripsi.
- [x] Tambahkan status aktif/nonaktif.

Acceptance Criteria:
- [x] Paket dapat disimpan di database.
- [x] Paket memiliki harga bulanan.
- [x] Paket memiliki kecepatan download dan upload.
- [x] Paket memiliki status aktif/nonaktif.

---

### S2-T005 — CRUD Master Paket Internet
Status: Done

Tujuan:
Membuat halaman CRUD paket internet.

Checklist:
- [x] Buat halaman daftar paket.
- [x] Buat halaman tambah paket.
- [x] Buat halaman edit paket.
- [x] Buat validasi field wajib.
- [x] Buat fitur aktif/nonaktif paket.
- [x] Pastikan paket aktif dapat dipilih di modul pelanggan nantinya.
- [x] Pastikan paket nonaktif tidak dipilih untuk pelanggan baru.

Acceptance Criteria:
- [x] Paket dapat dibuat.
- [x] Paket dapat diedit.
- [x] Paket dapat dinonaktifkan.
- [x] Harga paket dapat menjadi dasar tagihan.
- [x] Paket aktif siap digunakan pada input pelanggan.

---

### S2-T006 - POP Identifier Setting
Status: Done

Tujuan:
Menambahkan aturan ID khusus berdasarkan POP.

Checklist:
- [x] Tambahkan field `pop_code` pada POP.
- [x] Tambahkan field `registration_prefix` pada POP.
- [x] Tambahkan field `cid_prefix` pada POP.
- [x] Buat tabel sequence nomor per POP.
- [x] Buat sequence untuk registration number.
- [x] Buat sequence untuk CID.
- [x] Pastikan nomor urut berjalan per POP.
- [x] Pastikan nomor urut berjalan per jenis ID.
- [x] Pastikan format ID sesuai aturan.

Format:
- ID Request: `{registration_prefix}-{pop_code}-{running_number}`
- CID: `{cid_prefix}-{pop_code}-{running_number}`

Contoh:
- ID Request: `C-SMN-000001`
- CID: `D-SMN-000001`

Acceptance Criteria:
- [x] Setiap POP memiliki kode POP.
- [x] Setiap POP memiliki prefix ID Request.
- [x] Setiap POP memiliki prefix CID.
- [x] Sistem dapat membuat ID Request otomatis.
- [x] Sistem dapat membuat CID otomatis.
- [x] ID tidak boleh duplikat.
- [x] Tambahkan nama paket.
- [x] Tambahkan kategori paket.
- [x] Tambahkan kecepatan download.
- [x] Tambahkan kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan PPN.
- [x] Tambahkan diskon default.
- [x] Tambahkan total harga.
- [x] Tambahkan profile teknis.
- [x] Tambahkan deskripsi.
- [x] Tambahkan status aktif/nonaktif.

Acceptance Criteria:
- [x] Paket dapat disimpan di database.
- [x] Paket memiliki harga bulanan.
- [x] Paket memiliki kecepatan download dan upload.
- [x] Paket memiliki status aktif/nonaktif.

---

### S2-T005 — CRUD Master Paket Internet
Status: Done

Tujuan:
Membuat halaman CRUD paket internet.

Checklist:
- [x] Buat halaman daftar paket.
- [x] Buat halaman tambah paket.
- [x] Buat halaman edit paket.
- [x] Buat validasi field wajib.
- [x] Buat fitur aktif/nonaktif paket.
- [x] Pastikan paket aktif dapat dipilih di modul pelanggan nantinya.
- [x] Pastikan paket nonaktif tidak dipilih untuk pelanggan baru.

Acceptance Criteria:
- [x] Paket dapat dibuat.
- [x] Paket dapat diedit.
- [x] Paket dapat dinonaktifkan.
- [x] Harga paket dapat menjadi dasar tagihan.
- [x] Paket aktif siap digunakan pada input pelanggan.

---

### S2-T006 - POP Identifier Setting
Status: Done

Tujuan:
Menambahkan aturan ID khusus berdasarkan POP.

Checklist:
- [x] Tambahkan field `pop_code` pada POP.
- [x] Tambahkan field `registration_prefix` pada POP.
- [x] Tambahkan field `cid_prefix` pada POP.
- [x] Buat tabel sequence nomor per POP.
- [x] Buat sequence untuk registration number.
- [x] Buat sequence untuk CID.
- [x] Pastikan nomor urut berjalan per POP.
- [x] Pastikan nomor urut berjalan per jenis ID.
- [x] Pastikan format ID sesuai aturan.

Format:
- ID Request: `{registration_prefix}-{pop_code}-{running_number}`
- CID: `{cid_prefix}-{pop_code}-{running_number}`

Contoh:
- ID Request: `C-SMN-000001`
- CID: `D-SMN-000001`

Acceptance Criteria:
- [x] Setiap POP memiliki kode POP.
- [x] Setiap POP memiliki prefix ID Request.
- [x] Setiap POP memiliki prefix CID.
- [x] Sistem dapat membuat ID Request otomatis.
- [x] Sistem dapat membuat CID otomatis.
- [x] ID tidak boleh duplikat.
- [x] CID tidak dibuat sebelum pelanggan aktif/siap billing.

Catatan Test:
- `php artisan test --filter=Pop` lulus: 14 tests, 80 assertions.

---

# Sprint 3 - Master Data Pelanggan Manual

## Tujuan Sprint 3
Membuat master data pelanggan lengkap dengan input manual dan status kelengkapan data. (Sprint Selesai)

---
### S3-T001 - Migration dan Model Customer
Status: Done

Tujuan:
Membuat struktur utama master pelanggan.

Checklist:
- [x] Buat tabel `customers`.
- [x] Tambahkan ID pelanggan baru / registration number.
- [x] Tambahkan ID pelanggan lama.
- [x] Tambahkan CID.
- [x] Tambahkan nama lengkap.
- [x] Tambahkan NIK/nomor identitas.
- [x] Tambahkan jenis kelamin.
- [x] Tambahkan nomor HP utama.
- [x] Tambahkan nomor HP alternatif.
- [x] Tambahkan email.
- [x] Tambahkan tanggal registrasi.
- [x] Tambahkan status kelengkapan data.
- [x] Tambahkan status pelanggan.
- [x] Tambahkan relasi ke POP.
- [x] Tambahkan created_by dan updated_by.

Acceptance Criteria:
- [x] Customer dapat disimpan.
- [x] Customer memiliki relasi POP.
- [x] Customer memiliki status kelengkapan.
- [x] Customer memiliki status pelanggan.
- [x] Customer dapat menyimpan ID lama.
- [x] Customer dapat menyimpan ID Request dan CID.

Catatan Test:
- `php artisan test --filter=CustomerModelTest` lulus: 1 test, 11 assertions.
- Seluruh test suite `php artisan test` lulus: 66 tests, 355 assertions.

---

### S3-T002 — Migration dan Model Customer Address
Status: Done

Tujuan:
Membuat data alamat pelanggan.

Checklist:
- [x] Buat tabel `customer_addresses`.
- [x] Tambahkan customer_id.
- [x] Tambahkan alamat lengkap.
- [x] Tambahkan desa/kelurahan.
- [x] Tambahkan kecamatan.
- [x] Tambahkan kota/kabupaten.
- [x] Tambahkan provinsi.
- [x] Tambahkan latitude.
- [x] Tambahkan longitude.
- [x] Tambahkan foto rumah.
- [x] Tambahkan foto KTP.
- [x] Tambahkan foto kontrak.

Acceptance Criteria:
- [x] Customer memiliki alamat.
- [x] Alamat dapat disimpan.
- [x] Field wajib alamat dapat divalidasi.
- [x] Foto bersifat opsional untuk MVP.

Catatan Test:
- `php artisan test --filter=CustomerAddressModelTest` lulus: 2 tests, 12 assertions.
- Seluruh test suite `php artisan test` lulus: 68 tests, 367 assertions.

---

### S3-T003 — Migration dan Model Customer Service
Status: Done

Tujuan:
Membuat data paket/layanan pelanggan.

Checklist:
- [x] Buat tabel `customer_services`.
- [x] Tambahkan customer_id.
- [x] Tambahkan internet_package_id.
- [x] Tambahkan snapshot nama paket.
- [x] Tambahkan snapshot kecepatan download.
- [x] Tambahkan snapshot kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan diskon.
- [x] Tambahkan PPN.
- [x] Tambahkan total tagihan bulanan.
- [x] Tambahkan tanggal aktivasi.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan siklus tagihan.
- [x] Tambahkan status layanan.
- [x] Tambahkan status billing.

Acceptance Criteria:
- [x] Customer memiliki data layanan.
- [x] Layanan mengambil data dari master paket.
- [x] Harga paket disimpan sebagai snapshot.
- [x] Data layanan menjadi dasar invoice.

Catatan Test:
- `php artisan test --filter=CustomerServiceModelTest` lulus: 2 tests, 8 assertions.
- Seluruh test suite `php artisan test` lulus: 70 tests, 375 assertions.

---

### S3-T004 — Form Input Manual Pelanggan
Status: Done

Tujuan:
Membuat form input pelanggan manual.

Checklist:
- [x] Buat halaman tambah pelanggan.
- [x] Buat form data identitas.
- [x] Buat form data alamat.
- [x] Buat form pilihan POP/Cabang.
- [x] Buat form pilihan paket internet.
- [x] Buat form billing dasar.
- [x] Simpan data pelanggan walaupun belum lengkap.
- [x] Generate ID Request berdasarkan POP.
- [x] Validasi field wajib.
- [x] Tampilkan pesan field yang belum lengkap.

Acceptance Criteria:
- [x] Admin dapat input pelanggan manual.
- [x] Pelanggan belum lengkap tetap bisa disimpan.
- [x] Sistem membuat ID Request otomatis.
- [x] Sistem menandai data lengkap/belum lengkap.
- [x] Pelanggan belum lengkap tidak bisa masuk billing aktif.

Catatan Test:
- Seluruh test suite `php artisan test` lulus: 70 tests, 373 assertions.

---

### S3-T005 — Daftar Pelanggan
Status: Done

Tujuan:
Membuat halaman daftar pelanggan.

Checklist:
- [x] Buat tabel daftar pelanggan.
- [x] Tampilkan ID Request.
- [x] Tampilkan CID jika ada.
- [x] Tampilkan nama pelanggan.
- [x] Tampilkan nomor HP.
- [x] Tampilkan POP.
- [x] Tampilkan paket.
- [x] Tampilkan status kelengkapan.
- [x] Tampilkan status layanan.
- [x] Buat search nama/ID/nomor HP.
- [x] Buat filter POP.
- [x] Buat filter status kelengkapan.
- [x] Buat filter status layanan.

Acceptance Criteria:
- [x] Pelanggan dapat dicari.
- [x] Pelanggan dapat difilter berdasarkan POP.
- [x] Pelanggan dapat difilter berdasarkan status kelengkapan.
- [x] Pelanggan dapat difilter berdasarkan status layanan.
- [x] Admin Cabang hanya melihat pelanggan POP yang ditugaskan.

Catatan Test:
- Seluruh test suite `php artisan test` lulus: 75 tests, 395 assertions (termasuk unit/feature test filter, search, & POP restriction).

---

### S3-T006 — Detail Pelanggan dengan Tab
Status: Done

Tujuan:
Membuat halaman detail pelanggan lengkap.

Checklist:
- [x] Buat tab Ringkasan.
- [x] Buat tab Identitas.
- [x] Buat tab Alamat.
- [x] Buat tab POP/Cabang.
- [x] Buat tab Paket & Layanan.
- [x] Buat tab Billing.
- [x] Buat tab Tagihan.
- [x] Buat tab Pembayaran.
- [x] Buat tab Dokumen.
- [x] Buat tab Riwayat Perubahan.

Acceptance Criteria:
- [x] Detail pelanggan menampilkan semua data utama.
- [x] Data pelanggan dapat diedit sesuai permission.
- [x] Field yang belum lengkap terlihat.
- [x] Status kelengkapan terlihat jelas.

Catatan Test:
- Halaman detail berhasil memuat data dengan 10 tab interaktif.
- Tercover dalam `CustomerDetailTest.php`.

---

### S3-T007 — Validasi Kelengkapan Data Pelanggan
Status: Done

Tujuan:
Membuat sistem validasi kelengkapan data pelanggan.

Checklist:
- [x] Buat service/helper validasi kelengkapan.
- [x] Cek field wajib identitas.
- [x] Cek field wajib alamat.
- [x] Cek POP/Cabang.
- [x] Cek paket internet.
- [x] Cek harga bulanan.
- [x] Cek tanggal aktivasi.
- [x] Cek tanggal jatuh tempo.
- [x] Cek status layanan.
- [x] Hitung persentase kelengkapan.
- [x] Tampilkan daftar field yang belum lengkap.
- [x] Update status kelengkapan otomatis.

Acceptance Criteria:
- [x] Sistem menampilkan persentase kelengkapan data.
- [x] Sistem menampilkan field yang belum lengkap.
- [x] Pelanggan belum lengkap tidak bisa masuk billing aktif.
- [x] Admin dapat melihat daftar pelanggan yang perlu dilengkapi.

Catatan Test:
- `CustomerValidationTest.php` menguji kalkulasi persentase kelengkapan, perubahan status otomatis, dan penolakan transisi status `siap_billing` bila data tidak lengkap.
- `CustomerListTest.php` disesuaikan agar customer data seeder valid untuk pengujian filter kelengkapan.
- Seluruh 80 unit/feature test lulus (100% pass rate).

---

# Sprint 4 — Import Excel/CSV

## Tujuan Sprint 4
Membuat modul import pelanggan lama ke master pelanggan baru.

---

### S4-T001 — Template Import Pelanggan
Status: Done

Tujuan:
Membuat template Excel/CSV untuk import pelanggan lama.

Checklist:
- [x] Buat format kolom import.
- [x] Tambahkan ID pelanggan lama.
- [x] Tambahkan nama lengkap.
- [x] Tambahkan nomor HP.
- [x] Tambahkan alamat.
- [x] Tambahkan POP/Cabang.
- [x] Tambahkan nama paket.
- [x] Tambahkan harga paket.
- [x] Tambahkan tanggal aktivasi.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan status layanan.
- [x] Tambahkan field teknis opsional.

Acceptance Criteria:
- [x] Admin dapat download template.
- [x] Template memiliki field wajib.
- [x] Template memiliki field opsional teknis.
- [x] Format siap digunakan untuk import.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 4 tests, 35 assertions.
- `php artisan test` lulus: 81 tests, 408 assertions.

---

### S4-T002 — Upload dan Preview Import
Status: Done

Tujuan:
Membuat upload file dan preview data sebelum import.

Checklist:
- [x] Buat halaman import pelanggan.
- [x] Buat upload Excel/CSV.
- [x] Baca isi file.
- [x] Tampilkan preview data.
- [x] Tampilkan jumlah baris.
- [x] Tampilkan data valid dan invalid.

Acceptance Criteria:
- [x] Admin dapat upload file.
- [x] Sistem membaca data.
- [x] Sistem menampilkan preview sebelum import.
- [x] Sistem belum menyimpan data sebelum admin konfirmasi.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 4 tests, 35 assertions.
- Halaman `/customers/import` tampil dengan benar.
- Upload file Excel/CSV dibaca oleh SheetJS (client-side), lalu divalidasi via API `/customers/import/validate`.
- Preview tabel menampilkan status per baris (valid/warning/error) dengan metric cards jumlah baris.
- Data hanya tersimpan ke database setelah admin klik tombol konfirmasi import.

---

# Sprint 4 — Import Excel/CSV

## Tujuan Sprint 4
Membuat modul import pelanggan lama ke master pelanggan baru.

---

### S4-T003 — Validasi Import
Status: Done

Tujuan:
Memvalidasi data import sebelum masuk master pelanggan.

Checklist:
- [x] Cek ID pelanggan lama tidak duplikat.
- [x] Cek nama pelanggan tidak kosong.
- [x] Cek nomor HP tidak kosong.
- [x] Cek POP tersedia di master POP.
- [x] Cek paket tersedia di master paket.
- [x] Cek harga paket berupa angka.
- [x] Cek tanggal valid.
- [x] Cek status layanan sesuai pilihan sistem.
- [x] Tandai data teknis kosong sebagai belum lengkap.

Acceptance Criteria:
- [x] Data invalid ditolak.
- [x] Alasan error ditampilkan.
- [x] Data duplikat ditandai.
- [x] Data valid siap dikonfirmasi import.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 5 tests, 54 assertions.
- `php artisan test` lulus: 82 tests, 427 assertions.

---

### S4-T004 — Import Batch dan Import Error
Status: Done

Tujuan:
Menyimpan log import dan error import.

Checklist:
- [x] Buat tabel `import_batches`.
- [x] Buat tabel `import_errors`.
- [x] Simpan nama file.
- [x] Simpan user pengupload.
- [x] Simpan total rows.
- [x] Simpan valid rows.
- [x] Simpan invalid rows.
- [x] Simpan imported rows.
- [x] Simpan error per baris.
- [x] Simpan raw data error.

Acceptance Criteria:
- [x] Setiap import memiliki batch log.
- [x] Error import tersimpan.
- [x] Admin dapat melihat riwayat import.
- [x] Admin dapat melihat alasan data gagal.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportLoggingTest.php` lulus: 2 tests, 11 assertions.
- Tabel `import_batches` dan `import_errors` dibuat.
- Halaman riwayat dan detail batch tersedia.

---

### S4-T005 — Konfirmasi Import ke Master Pelanggan
Status: Done

Tujuan:
Menyimpan data valid hasil import ke master pelanggan.

Checklist:
- [x] Buat tombol konfirmasi import.
- [x] Simpan data valid ke customers.
- [x] Simpan alamat ke customer_addresses.
- [x] Simpan layanan ke customer_services.
- [x] Simpan ID pelanggan lama.
- [x] Generate ID Request berdasarkan POP.
- [x] Jangan generate CID jika pelanggan belum aktif/siap billing.
- [x] Update status kelengkapan data.
- [x] Simpan log audit import.

Acceptance Criteria:
- [x] Data valid masuk master pelanggan.
- [x] Data invalid tidak masuk master pelanggan.
- [x] Data hasil import bisa diedit manual.
- [x] ID pelanggan lama tersimpan.
- [x] ID Request sistem baru dibuat.
- [x] Log import tersimpan.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportLoggingTest.php` lulus (verifikasi data masuk ke 3 tabel).
- `php artisan test tests/Feature/CustomerImportTest.php` lulus (verifikasi regresi).

---

## Sprint 5 — Billing Dasar

### S5-T001 — Aktivasi Layanan Pelanggan
Status: Done

Tujuan:
Mengubah pelanggan lengkap menjadi aktif/siap billing.

Checklist:
- [x] Buat tombol aktivasi layanan.
- [x] Cek kelengkapan data pelanggan.
- [x] Cek paket aktif.
- [x] Cek nominal tagihan.
- [x] Cek tanggal aktivasi.
- [x] Cek tanggal jatuh tempo.
- [x] Generate CID berdasarkan POP.
- [x] Ubah status pelanggan menjadi aktif.
- [x] Ubah status kelengkapan menjadi siap billing.
- [x] Simpan riwayat aktivasi.

Acceptance Criteria:
- [x] Pelanggan belum lengkap tidak bisa diaktifkan.
- [x] Pelanggan aktif memiliki paket.
- [x] Pelanggan aktif memiliki nominal tagihan.
- [x] Pelanggan aktif memiliki CID.
- [x] Tanggal jatuh tempo wajib ada.
- [x] Sistem menyimpan riwayat aktivasi.

Catatan Test:
- `php artisan test --filter=CustomerActivationTest` lulus: 3 tests, 22 assertions.
- Seluruh test suite `php artisan test` lulus: 87 tests, 468 assertions.

---

### S5-T002 — Migration dan Model Invoice
Status: Done

Tujuan:
Membuat struktur tagihan pelanggan.

Checklist:
- [x] Buat tabel `invoices`.
- [x] Tambahkan nomor invoice.
- [x] Tambahkan customer_id.
- [x] Tambahkan pop_id.
- [x] Tambahkan customer_service_id.
- [x] Tambahkan internet_package_id.
- [x] Tambahkan periode tagihan.
- [x] Tambahkan tanggal terbit.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan subtotal.
- [x] Tambahkan diskon.
- [x] Tambahkan PPN.
- [x] Tambahkan total tagihan.
- [x] Tambahkan paid amount.
- [x] Tambahkan remaining amount.
- [x] Tambahkan status tagihan.

Acceptance Criteria:
- [x] Invoice dapat disimpan.
- [x] Invoice terhubung ke customer.
- [x] Invoice terhubung ke POP.
- [x] Invoice memiliki periode.
- [x] Invoice memiliki status.

Catatan Test:
- `php artisan test --filter=InvoiceModelTest` lulus: 1 test, 17 assertions.
- Seluruh test suite `php artisan test` lulus: 88 tests, 485 assertions.

---
## Blocked
Belum ada.

## Notes
AI hanya boleh mengerjakan task dengan status `In Progress`.

Catatan hasil S2-T006:
- POP existing yang sudah ada sebelum migration identifier wajib dilengkapi `pop_code`, `registration_prefix`, dan `cid_prefix` melalui edit POP sebelum generator ID Request/CID digunakan.

Catatan refactor S2-T004/S2-T005:
- Duplikasi `service_packages`/`ServicePackage` dihapus dari kode aplikasi.
- Master Paket Internet sekarang memakai tabel/model/controller `internet_packages`/`InternetPackage`, dengan struktur data dan UI hasil gabungan dari Service Package dan Internet Package.
- Database development sudah di-reset dengan `php artisan migrate:fresh --seed`; hasil akhir: tabel `internet_packages` ada, tabel `service_packages` tidak ada, dan 27 paket ter-seed.
- Test refactor lulus: `InternetPackageSeederTest`, `CustomerCreateTest`, `CustomerEditTest`, `CustomerImportTest`, dan `npm run build`.

Setelah task selesai:
1. Pindahkan task ke Done.
2. Ubah task berikutnya menjadi In Progress.
3. Tambahkan catatan hasil test.

---
