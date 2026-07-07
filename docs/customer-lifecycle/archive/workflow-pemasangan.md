# 🔄 Ringkasan Alur Status & Action (Dinamis pada List Antrean)
Tombol ACTION pada Kolom List Antrean Proses akan berubah secara dinamis mengikuti STATUS pelanggan:

## Fase 1: Verifikasi Awal (Oleh Admin/CS)
1. Status: Menunggu ACC
- Action: Proses ke Tim
- Proses: CS/Admin menghubungi pelanggan untuk konfirmasi data dan paket.
2. Setelah "Proses ke Tim" diklik & disetujui:
- Status berubah menjadi: Menunggu Pemasangan
- Action berubah menjadi: Start Proses
## Fase 2: Eksekusi Pemasangan (Oleh Teknisi)
3. Status: Menunggu Pemasangan
- Action: Start Proses
- Proses: Teknisi menekan tombol, sistem menghitung countdown (mencatat waktu pengerjaan).
4. Setelah "Start Proses" diklik:
- Status berubah menjadi: Mulai Pasang
- Action berubah menjadi: Lapor Pemasangan
5. Status: Mulai Pasang
- Action: Lapor Pemasangan
- Proses: Teknisi mengisi form yang menggabungkan data Pendaftaran, Survey, Modal 2 (Data Perangkat), dan Modal 3 (Speedtest).
6. Setelah "Lapor Pemasangan" di-save:
- Status berubah menjadi: Verifikasi Admin
- Action berubah menjadi: Verifikasi
## Fase 3: Finalisasi & Billing (Oleh Admin/Finance)
7. Status: Verifikasi Admin
- Action: Verifikasi
- Proses: Admin memverifikasi laporan teknisi dan membuat tagihan menggunakan Modal 4 (Buat Tagihan Manual).
8. Setelah "Verifikasi" di-save:
- Status Akhir: Pelanggan resmi aktif.
- Hasil: Data pelanggan dipindahkan/dimasukkan ke dalam List Pelanggan Utama (keluar dari antrean proses).


## 📋 Pemetaan UI / Tampilan
Berdasarkan poin yang Anda berikan, tampilan Kolom List Antrean Proses akan seperti ini:
No | ID | NAMA | HP | DESA | STATUS | inserted_at | ACTION

Catatan untuk ACTION: Karena action bersifat dinamis, maka pada kolom ACTION akan memuat tombol default seperti Detail Pelanggan, Delete, dan SCAN QR, ditambah 1 tombol utama yang berubah sesuai status (Proses ke Tim / Start Proses / Lapor Pemasangan / Verifikasi).


## FORM LAPORAN PEMASANGAN DAN PENGUJIAN:
### 1. Modal Input Data Perangkat Pelanggan
Sudah bagus, bisakah jika tampilannya itu seperti pada Registrasi Pelanggan, 
#### STEP 1 Jadi Data diri yang sudah di isi pada Registrasi dapat terlihat pada Survey
#### STEP 2 Tab Dokumen Lampiran Terdapat Foto KTP yang sudah di upload pada saat Regisdtrasi di tambahkan Foto RUmah + Foto ODP dengan status Belum Lengkap dan Note di bawahnya Wajib diisi: Foto Rumah dan Foto ODP Terdekat. 
#### STEP 3 LAYANAN & PAKET LAYANAN INTERNET yang sudah di isi pada tahap Registrasi
#### STEP 4 LAPORAN SURVEY itu terdapat ODP Terdekat *, Estimasi Kabel (Meter) *, Tingkat Kesulitan
 {SULIT, SEDANG, MUDAH}, Alat Tambahan / Khusus, Catatan Teknis Survey  dengan status Belum Lengkap
#### STEP 5 LAPORAN PEMASANGAN:
 - Status Pemasangan { Terjadwal, Proses, Selesai, Gagal } *
 - Jenis Perangkat { Modem, ONT, ONU, Router, Lainnya }
 - Mode Koneksi { Bridge, PPPOE, Static, DHCP, Lainnya } 
 - Merk
 - Tipe
 - Serial Number * 
 - MAC Address
 - Username PPPoE
 - Password PPPoE
 - SSID WiFi *
 - Password WiFi *
 - IP Address
 - VLAN ID
 - Nomor OLT 
 - Slot OLT 
 - Nomor port OLT 
 - Nomer ODP 
 - Nomor Port ODP
 - Nomer Router (sementara di isi manual dahulu, karena ini nanti akan berhubungan dengan Master Distribusi barang, yang akan di jelaskan kemudian)
 - VLAN (Jaringan)
 - Redaman Awal Pemasangan (dBm) 
 - Redaman Aktual (dBm) 
 - Catatan Pemasangan 

### STEP 6 LAPORAN UJI:
 - LAPORAN UJI:
 - Tanggal Uji
 - Waktu Uji
 - Sinyal Redaman awal pemasangan
 - Foto Speedtest
 - Jitter
 - Latency
 - Speed Upoad
 - Speed Download
 - Persentase Packet loss
 - Persentase sesuai Paket (Perhitungan missal paket 10mbs laporan 9mbps = 90% )
 - Skor Kualitas (hasil dari foto uji speedtest)


## 🔄 Update Alur & Logika Sistem
### 1. Fitur QR Code (Disederhanakan)
- Tombol SCAN QR pada list antrean diubah fungsinya menjadi Generate/Lihat QR (hanya memunculkan QR Code yang berisi ID Pelanggan/Data Survey untuk memudahkan teknisi di lapangan, tanpa fitur scan dari sistem dulu).
### 2. Countdown (SLA Pemasangan)
- Countdown bukan untuk membatalkan, melainkan Timer SLA (Service Level Agreement).
- Secara teknis di database, saat tombol Start Proses ditekan, sistem akan merekam waktu_mulai. Saat Lapor Pemasangan di-save, sistem merekam waktu_selesai. Selisih keduanya akan ditampilkan sebagai durasi pemasangan.
## 3. Penanganan Gagal & Revisi (Percabangan Status)
- Jalur A (Gagal di CS): Jika saat Proses ke Tim pelanggan batal/data salah, Admin bisa menekan tombol Batalkan/Gagal. Status berubah menjadi Gagal dan data otomatis pindah dari "List Antrean Proses" ke "List Pelanggan Gagal".
- Jalur B (Revisi di Teknisi): Jika saat Lapor Pemasangan hasil speedtest buruk atau ada kendala, teknisi bisa menekan tombol Revisi. Status akan kembali ke Menunggu Pemasangan (atau status khusus Revisi Pemasangan), sehingga teknisi bisa mengulang proses dari awal (menekan Start Proses lagi).
## 4. Pendekatan Role (MVP / Single Role)
- Untuk tahap awal (MVP - Minimum Viable Product), kita asumsikan 1 User Super/Admin yang bisa melihat semua tombol dan menjalankan semua alur dari Fase 1 sampai Fase 3. Nanti setelah workflow utama berjalan mulus, kita baru akan memecahnya ke dalam Role-Based Access Control (CS, Teknisi, Billing).



# 🗺️ Peta Status Final (State Machine)
Berdasarkan semua diskusi kita, berikut adalah siklus hidup status pelanggan di sistem Anda:
### 1. Menunggu ACC ➔ (CS Verifikasi) ➔ Menunggu Pemasangan
### 2. Menunggu Pemasangan ➔ (Teknisi Start) ➔ Mulai Pasang (Countdown berjalan)
### 3. Mulai Pasang ➔ (Teknisi Lapor) ➔ Verifikasi Admin
### 4. Verifikasi Admin ➔ (Admin Buat Tagihan) ➔ Aktif / Pelanggan Resmi (Masuk ke List Pelanggan Utama)
### 5. Percabangan Khusus:
- Dari Menunggu ACC ➔ (CS Batal) ➔ Gagal (Masuk ke List Pelanggan Gagal)
- Dari Mulai Pasang ➔ (Teknisi Revisi) ➔ Kembali ke Menunggu Pemasangan

