# User Flow — Modul Business Development

## Admin — Atur Kategori Paket Mana yang Butuh Gate BD

1. Buka **Master Kategori Paket** (`/master/package-categories`) — permission `packages.view`.
2. Klik kategori (mis. "Paket Bisnis Broadband") → Edit — permission `packages.update`.
3. Pilih **Role** di dropdown "Validasi Biaya Instalasi Oleh" (mis. "Business Development"). Kosongkan (pilih "— Tidak Perlu Validasi —") kalau kategori ini tidak butuh gate sama sekali.
4. Simpan. Efek langsung ke pelanggan BARU yang diverifikasi CS setelah titik ini — pelanggan yang sudah lanjut ke gate sebelum perubahan tidak berubah statusnya.
5. Buat kategori baru: tombol "Tambah Kategori" di halaman index — form sama (nama + role approval).

## CS — Verifikasi Pelanggan (Kategori Apa Saja)

1. Buka `/verifications/{id}/admin`, tab **Verifikasi**.
2. Isi Tanggal Aktivasi + biaya tambahan (kabel, tiang, materai). Kalau kategori paket pelanggan butuh gate BD, field "Biaya Pemasangan" otomatis readonly bertuliskan "0 — ditagih terpisah oleh BD" — CS tidak perlu (dan tidak bisa) mengisinya.
3. Klik tombol submit:
   - **"Aktivasi & Terbitkan Tagihan"** — kalau kategori TIDAK butuh gate. Pelanggan langsung ACTIVE, Invoice Awal langsung terbit, CS bisa cetak kwitansi saat itu juga.
   - **"Verifikasi & Terbitkan Tagihan"** — kalau kategori BUTUH gate (label beda, sengaja jujur soal apa yang sebenarnya terjadi). Pelanggan **belum** ACTIVE, **belum** ada invoice yang bisa dicetak — nyangkut di antrean BD.
4. Pesan sukses menjelaskan status: "Verifikasi CS tersimpan... tagihan pertama BELUM terbit, menunggu Busdev isi Biaya Instalasi & verifikasi."

## BD — Verifikasi & Aktivasi Pelanggan Bisnis

1. Buka **Menunggu Verifikasi BD** (`/business-development-verifications`) — permission `business_development_verification.view`. Daftar berisi semua pelanggan berstatus "Verifikasi BD" dalam POP scope BD.
2. Klik nama pelanggan → halaman detail (tampilan SAMA seperti halaman Verifikasi CS: tab Registrasi/Survey/Pemasangan/Pengujian/Verifikasi, badge status "Verifikasi BD").
3. Tab **Verifikasi** menampilkan kartu **"Hasil Verifikasi CS"**:
   - Kalau badge **"Belum Terbit"** (amber) — invoice belum ada, angka yang tampil (Tanggal Aktivasi, Prorata, Biaya Pemasangan CS = 0, Total Tagihan Awal) adalah estimasi dari hitungan CS, bukan tagihan resmi.
   - Kalau badge **Lunas/Belum Dibayar** — invoice sudah pernah terbit (kasus edit ulang / kategori tanpa gate diakses lewat route BD), ada link "Lihat Detail Tagihan Awal".
4. Isi field **Biaya Instalasi** (sudah terprefill dari harga bawaan paket — Master Paket Internet — boleh diubah kalau nego harga per klien).
5. Klik **"Verifikasi & Aktifkan"** — **satu tombol, tanpa jalur tolak**. Efek langsung: SATU Invoice Awal terbit — mencatat biaya yang sudah diverifikasi CS *dan* Biaya Instalasi yang baru diisi, bukan dua tagihan terpisah — pelanggan resmi ACTIVE.
6. Kalau BD bukan role yang dikonfigurasi kategori itu (dan tidak punya permission override) — halaman menampilkan pesan read-only "Anda tidak punya izin memverifikasi pelanggan kategori paket ini", tanpa form.

## BD — Monitoring "Pelanggan Aktif < 30 Hari"

1. Buka **Pelanggan Aktif < 30 Hari** (`/customer-acquisitions`) — daftar pelanggan yang baru ACTIVE bulan ini (reset otomatis tiap tanggal 1, bulan lalu bisa dibuka lewat filter Periode).
2. Filter tambahan: Role Penginput, Nama Penginput (khusus role ber-pembatasan paket seperti Sales/Teknisi).
3. Kolom "Harga Dikurangi PPN" dihitung otomatis (Biaya Langganan × 89%) — tidak ada input manual.
4. Baris pelanggan yang lewat gate BD di atas otomatis sudah terisi Biaya Instalasi & link invoice-nya — tidak pernah muncul "Menunggu Validasi".

## BD — List Pelanggan Bisnis, Dashboard Omset Sales, Restriksi Paket, Master Agent

0. **List Pelanggan Bisnis** (`/business-development/business-customers`) — cari nama/ID/nama alat, filter Tipe Paket & Status. Kolom: Harga Paket (setelah diskon, sebelum PPN), Harga Sesudah PPN (tagihan bulanan, ikut PPN per pelanggan), Status, Alat yang Ditinggalkan (unit gudang berstatus terpasang), Biaya Instalasi (dari baris Pelanggan Aktif < 30 Hari), Tanggal Aktivasi. Hanya pelanggan yang sudah lewat pemasangan (Menunggu Verifikasi BD/Aktif/Suspend/Putus); tidak ada tombol tambah — pelanggan masuk lewat Registrasi biasa.

1. **Dashboard Omset Sales** (`/business-development/sales-omset`) — pilih rentang tanggal/POP, lihat total Omset per Sales, klik nama untuk breakdown per pelanggan.
2. **Restriksi Paket per Role** (`/business-development/package-restrictions`) — centang paket yang BOLEH dipilih Sales/Teknisi saat registrasi (daftar berlaku global untuk semua role dengan pembatasan).
3. **Master Agent** (`/business-development/agents`) — tambah/ubah/nonaktifkan mitra referral.

## Sales/Teknisi — Registrasi Pelanggan dengan Paket Dibatasi

1. Buka form Registrasi Pelanggan — dropdown Paket Internet hanya menampilkan paket yang ada di daftar Restriksi Paket (kalau daftar itu masih kosong sama sekali, semua paket aktif tetap tampil — fail-open, tidak mengunci Sales sebelum BD sempat mengisi).
2. Field ID Sales otomatis terisi (dikunci) dari akun yang login — role di luar daftar restricted melihat pesan penjelas dan mengisi manual lewat dropdown.
3. Field Referral bisa dicari lewat autocomplete CID/nama pelanggan existing.

---

Lihat juga [docs/customer-lifecycle/user-flow.md](../customer-lifecycle/user-flow.md) untuk langkah sebelum tahap Verifikasi (Registrasi, Survey, Pemasangan).
