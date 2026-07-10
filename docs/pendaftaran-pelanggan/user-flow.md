# User Flow: Pendaftaran Pelanggan

Berikut adalah interaksi antara pengguna (Admin / CS / Teknisi) dengan sistem dari antarmuka aplikasi.

## Tahap 1: Registrasi
1. **User (CS/Admin)** membuka menu *Pelanggan > Tambah Pelanggan*.
2. Mengisi formulir Registrasi (Data Personal, Alamat, Paket yang dipilih).
3. Klik tombol *Simpan & Lanjut Survey*.
4. Pelanggan muncul di antrean *Pelanggan > Antrean Survey*.

## Tahap 2: Survey Lapangan
1. **User (Teknisi/Admin)** membuka menu *Antrean Survey*.
2. Memilih pelanggan yang mengantre, klik tombol *Mulai Survey*.
3. Sistem memunculkan **Countdown Live** dari waktu pengerjaan.
4. Setelah selesai di lapangan, klik *Lapor Survey*.
5. Mengisi data hasil survey (Jarak FAT, ODP, Koordinat).
6. Pelanggan hilang dari antrean survey dan berpindah ke menu *Verifikasi & Pemasangan*.

## Tahap 3: Verifikasi & Pemasangan
1. **User (Admin)** membuka menu *Verifikasi & Pemasangan*.
2. Memeriksa hasil survey, lalu menekan tombol *Proses ke Tim* (Status menjadi `waiting_installation`).
3. **User (Teknisi)** menekan tombol *Mulai Pasang*. Countdown SLA pemasangan berjalan.
4. Setelah instalasi perangkat selesai, Teknisi menekan *Lapor Pemasangan*.
5. Teknisi mengisi formulir Data Teknis (SN Perangkat, OLT Port, VLAN, Hasil Speedtest).
6. Status pelanggan berubah menjadi *Installed* atau *Verification Admin*.

## Tahap 4: Verifikasi Akhir & Aktivasi Tagihan
1. **User (Admin / Finance)** memeriksa data perangkat dari menu *Verifikasi & Pemasangan*.
2. Menekan tombol *Verifikasi & Aktivasi* pada pelanggan berstatus *Installed*.
3. Muncul Modal **Aktivasi**.
4. User memverifikasi/mengubah Periode Tagihan, Subtotal, Diskon, dan PPN.
5. Klik *Aktivasi & Terbitkan Tagihan*.
6. Pelanggan berpindah ke menu *Pelanggan > Semua Pelanggan* dengan status **Aktif**.
7. Tagihan dapat dilihat pada menu *Keuangan > Tagihan (Invoice)*.
