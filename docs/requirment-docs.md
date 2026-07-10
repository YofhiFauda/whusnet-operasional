#Sekarang Kita Pecah Registrasi Pelanggan - Survey - Verifikasi Admin - Pemasangan - Aktifasi - Pelanggan Baru

## ALUR FLOW PELANGGAN:
1. Melakukan Registrasi Pelanggan dengan mengisi 1. Data Diri Pelanggan, 2. Dokumen Lengkapdan, 3. LAYANAN & PAKET LAYANAN INTERNET

2. Setelah itu Akan masuk kedalam Sub Menu Survey dengan Status "Waiting Survey", Ketika Action di tekan "Survey Data" maka akan menjalankan Countdown yang berjalan selama proses survey dan status dari "Waitig Survey" menjadi "Proses Survey". Ketika di tekan action "Lapor Data" maka Countdown akan berhenti dan akan masuk kedalam FORM ANTRIAN SURVEI yang isinya seperti pada Input Hasil Survey Lapangan setelah di tekan save maka akan masuk kedalam sub menu "Verifikasi Admin & Teknisi". countdown tadi nantinya akan masuk kedalam Data Survey Pelanggan di Detail Pelanggan dari Waktu Mulai sampai Waktu Selesai. 

3. Setelah Survey berhasil maka pada Sub Menu "Verifikasi Admin & Teknisi" status "Menunggu ACC" dengan terdapat action "Proses ke Tim" (Pada Proses ini Admin/CS memverifikasi kepada pelanggan apakah data yang di masukan sudah sesuai/ paket yang di inginkan sudah sesuai"). Setelah action "proses ke tim" ditekan maka status akan berganti "Menunggu Pemasangan" dan action "proses ke tim" berganti menjadi "start proses", ketika di tekan maka akan menghitung countdown dan status akan berganti "Mulai Pasang" lalu action juga ganti menjado "Lapor Pemasangan". ketika "Lapor Pemasangan" ditekan akan menampilkan form dari hasil Pendaftaran Pelanggan, Survey Pelanggan, lalu akan mengisi data yang sama seperti 2. Modal Input Data Perangkat Pelanggan dan 3. Modal Laporan Hasil Pengujian Layanan (Speedtest) setelah mengisi dan memperbarui data pelanggan dan save. maka status pada List Pelanggan di Halaman Verifikasi Admin akan berubah menjadi "Verifikasi Admin" dan action berganti menjadi "Verifikasi", ketika action "Verifikasi" ditekan maka akan menampilkan Form lagi yang dimana admin harus memasukan data yang sama seperti pada 4. Modal Buat Tagihan Manual. ketika sudah save maka akan jadi pelanggan dan masuk kedalam list Pelanggan


##ALUR PENDAFTARAN PELANGGAN:
### 1. DATA DIRI PELANGGAN
 - NAMA LENGKAP
 - NOMOR IDENTITAS (NIK) *
 - JENIS KELAMIN *
 - Pilih Jenis Kelamin
 - NOMOR HP UTAMA *
 - NOMOR HP ALTERNATIF
 - NPWP
 - ALAMAT EMAIL
 - ALAMAT INSTALASI LENGKAP *
 - KOTA *
 - KECAMATAN *
 - DESA / KELURAHAN *
 - LATITUDE
 - LONGITUDE
 - TANGGAL REGISTRASI *
 - POP CABANG *
 - STATUS AWAL ALUR KERJA *
 - JENIS KONTRAK {sewa, beli}

### 2. Dokumen Lampiran
 - Foto KTP

### 3. LAYANAN & PAKET LAYANAN INTERNET
 - PAKET INTERNET *
 - MASA KONTRAK (BULAN) *
 - DISKON PROMOSI (RP) *
 - PPN (%) *
 - BIAYA LAIN DI LUAR STANDAR (RP)
 - Rincian Estimasi Biaya Bulanan


## ALUR SURVEY PELANGGAN
### 1. Kolom List Antrean Survey:
No	ID		NAMA		HP		DESA	S	TATUS		Inserted At	ACTION
1	 C00RQ002027	deni purwanto	 6281335339622	JABUNG		2026-06-18 	13:17:22	Detail, Survey Data, Delete

### 2. FORM SURVEY
A . Data Diri yang di daftarkan tadi 
B.  Modal Input Hasil Survey Lapangan


## ALUR VERIFIKASI PELANGGAN
### 1. Kolom List Antrean Proses
No	    ID		    NAMA		    HP		        DESA		    STATUS		        inserted_at		    ACTION
1	 C00RQ002028	test 1		    08912312735	    WONODADI	    Menunggu Acc	    2026-06-20 09:25:32	Detail Pelanggan, Proses ke Tim, Delete, dan SCAN QR

### 2. Modal Input Data Perangkat Pelanggan
### 3. Modal Laporan Hasil Pengujian Layanan (Speedtest)
### 4. Modal Buat Tagihan Manual
