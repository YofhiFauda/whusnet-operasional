# Biling data Pelanggan Sederhana
## Fitur Unggulan: Input masal Excel (Import data dari Excel)

### DATA DIRI PELANGGAN:
- ID REG (permanen dari aplikasi)
- Nama Lengkap
- Nomor Identitas
- Alamat
- Desa
- Kecamatan
- Kota
- Jenis Kelamin
- Email
- Nomor hp
- Koordinat latitude longtitute
- foto ktp
- foto rumah
- Foto Kontrak
- Tanggal Registrasi



### INFO REFERAL:
- ID Sales (dari Sales)
- ID Agent (dari Agent )
- ID Referal (dari Pelanggan)

### DATA layanan:
- Nama Paket
- Harga Paket
- Kecepatan upload
- Kecepatan Download
- Profile
- Jenis kontrak
- Diskon
- PPN
- Total Biaya Layanan


### DATA SURVEY :
- Tanggal Mulai Suvey
- Waktu Mulai Survey
- Tanggal Selesai Survey
- Waktu Selesai Survey
- Foto Rumah
- Kebutuhan alat
- Durasi Survey
- Petugas Survey (siapa yang melakukan Survey 1-3 orang)


### Data FOP:
- Waktu Penugasan Survey
- Waktu Penugasan Pemasangan
- ID FOP


### Data Laporan Pemasangan
- Tanggal Pemasangan
- Waktu Mulai Pemasangan
- Waktu Selesai Pemasangan
- Teknisi yang bertugas (2 -3 orang)

### LAPORAN Aktifasi:
- Tanggal Aktifasi
- Waktu Aktifasi
- Petugas Aktifasi

### Data TEKNIS:
- CID (dapat berubah Ketika putus langganan maka CID hilang/di Tarik ke stock Master CID)
- Ip address = Dial up
- SN 
- Perangkat Pasif 
- Nomer Cabang 
- Nomer POP 
- Nomor OLT 
- Nomor port OLT 
- Nomer ODP 
- Nomor Port ODP
- Nomer Router 
- Redaman Awal Pemasangan 
- Redaman Aktual 
- Vlan
- Catatan Teknis 

### LAPORAN UJI:
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


### Laporan Pembayaran Awal:
- Biaya Pemasangan
- Tagihan Prorate (mengukur tanggal akktifasi dan tanggal akhir bulan yang sama X (biaya bulanan / 30 (sesuai Bulan))
- Tagihan lain di luar standart
	- Kabel
	- jasa instalasi perangkat tambahan
	- Tambahan Tiang
