# skema Upgrade dan Downgrade paket internet

## 1. Skema Upgrade Paket
Prinsip dasarnya: Pelanggan berpindah ke paket yang lebih mahal, sehingga ada tambahan biaya di periode berjalan.

**A. Postpaid (Pasca-bayar / Belum Dibayar)**

Tagihan dihitung berdasarkan total akumulasi pemakaian riil kedua paket dari tanggal cetak tagihan sampai akhir periode:

$$\text{Invoice Bulan Ini} = \text{Prorate Paket Lama} + \text{Prorate Paket Baru}$$

**Contoh:**: Pakai paket lama 10 hari + pakai paket baru 20 hari.

**B. Prepaid (Pra-bayar / Sudah Dibayar di Awal)**
Pelanggan sudah bayar penuh untuk 1 bulan paket lama, sehingga punya "tabungan/kredit" dari sisa hari paket lama yang belum dipakai. Tagihannya adalah selisihnya:

$$\text{Invoice Tagihan Upgrade} = \text{Prorate Paket Baru} - \text{Sisa Kredit Hari Paket Lama}$$

**Contoh:**: Paket lama dibeli tanggal 1, baru ganti ke paket baru tanggal 15. 


## 2. Skema Downgrade Paket

Prinsip dasarnya: Pelanggan berpindah ke paket yang lebih murah. Tidak menggunakan rumus minus (–) pada tagihan bulan berjalan agar tidak keliru menghitung pemakaian riil.

**A. Postpaid (Pasca-bayar / Belum Dibayar)**
Sama seperti upgrade, tagihan bulan berjalan dihitung dari penjumlahan pemakaian riil kedua paket sebelum dan sesudah downgrade:

$$\text{Invoice Bulan Ini} = \text{Prorate Paket Lama} + \text{Prorate Paket Baru}$$

(Hasil akhirnya akan lebih murah dibanding jika bertahan di paket lama, tetapi tetap menambah biaya dari hari-hari yang sudah berjalan).

**B. Prepaid (Pra-bayar / Sudah Dibayar di Awal)**
Pelanggan sudah bayar paket lama yang mahal untuk 1 bulan penuh. Karena paket baru lebih murah, sisa hari paket lama bernilai lebih besar daripada harga paket baru. Selisih kelebihannya dijadikan Deposit/Kredit pengurang tagihan bulan depan: 

$$\text{Deposit Bulan Depan} = \text{Sisa Kredit Hari Paket Lama} - \text{Prorate Paket Baru}$$

(Invoice bulan berjalan = Rp 0, dan sisa kelebihannya memotong tagihan di bulan berikutnya).


# Tabel Ringkasan Perbaikan Skema

| Metode Pembayaran | Rumus Perhitungan Invoice | Output / Catatan |
|---------------------|------------------------------|------------------|
| Postpaid | $\text{Prorate Paket Lama} + \text{Prorate Paket Baru}$ | Tagihan bulan ini bertambah |
| Prepaid | $\text{Prorate Paket Baru} - \text{Sisa Kredit Paket Lama}$ | Bayar selisih kekurangannya saja |
| Postpaid | $\text{Prorate Paket Lama} + \text{Prorate Paket Baru}$ | Tagihan bulan ini menyesuaikan total hari|
| Prepaid | $\text{Sisa Kredit Paket Lama} - \text{Prorate Paket Baru}$ | Invoice saat itu Rp 0, selisih masuk Deposit| 


# Contoh Implementasinya
## Asumsi Parameter:

- Jumlah Hari dalam 1 Bulan: 30 Hari
- Paket Lama (10 Mbps): Rp 300.000 / bulan (Rp 10.000 / hari)
- Paket Baru Upgrade (50 Mbps): Rp 600.000 / bulan (Rp 20.000 / hari)
- Paket Baru Downgrade (5 Mbps): Rp 150.000 / bulan (Rp 5.000 / hari)
- Perubahan Terjadi Pada: Hari ke-11 (Paket lama dipakai 10 hari, sisa paket baru/sisa hari = 20 hari).


## 1. Contoh Skema UPGRADE
### A. Postpaid (Pasca-bayar / Belum Dibayar)
Pelanggan memakai Paket Lama selama 10 hari, lalu upgrade ke Paket Baru untuk 20 hari sisanya. Tagihan dihitung di akhir bulan.
- Prorate Paket Lama (10 hari): $10 \times \text{Rp } 10.000 = \text{Rp } 100.000$
- Prorate Paket Baru (20 hari): $20 \times \text{Rp } 20.000 = \text{Rp } 400.000$
- Total Invoice Bulan Ini:$$\text{Rp } 100.000 + \text{Rp } 400.000 = \mathbf{\text{Rp } 500.000}$$

### B. Prepaid (Pra-bayar / Sudah Dibayar di Awal)
Pelanggan sudah bayar Rp 300.000 di awal bulan. Di hari ke-11 dia upgrade. Dia punya sisa deposit 20 hari paket lama yang belum terpakai.
- Sisa Kredit Paket Lama (20 hari): $20 \times \text{Rp } 10.000 = \text{Rp } 200.000$
- Biaya Prorate Paket Baru (20 hari): $20 \times \text{Rp } 20.000 = \text{Rp } 400.000$
- Total Invoice Bulan Ini:$$\text{Rp } 400.000 - \text{Rp } 200.000 = \mathbf{\text{Rp } 200.000}$$
(Pelanggan cukup membayar kekurangannya sebesar Rp 200.000 agar paket baru langsung aktif).

## 2. Contoh Skema DOWNGRADE
### A. Postpaid (Pasca-bayar / Belum Dibayar)
Pelanggan memakai Paket Lama selama 10 hari, lalu downgrade ke Paket Baru (lebih murah) untuk 20 hari sisanya. Tagihan dihitung di akhir bulan.
- Prorate Paket Lama (10 hari): $10 \times \text{Rp } 10.000 = \text{Rp } 100.000$
- Prorate Paket Baru (20 hari): $20 \times \text{Rp } 5.000 = \text{Rp } 100.000$
- Total Invoice Bulan Ini:$$\text{Rp } 100.000 + \text{Rp } 100.000 = \mathbf{\text{Rp } 200.000}$$
(Lebih murah daripada jika tetap di paket lama yang biayanya Rp 300.000).

### B. Prepaid (Pra-bayar / Sudah Dibayar di Awal)
Pelanggan sudah bayar Rp 300.000 di awal bulan. Di hari ke-11 dia downgrade ke paket Rp 150.000/bulan. Sisa hari paket lama bernilai lebih mahal dari paket baru.

- Sisa Kredit Paket Lama (20 hari): $20 \times \text{Rp } 10.000 = \text{Rp } 200.000$
- Biaya Prorate Paket Baru (20 hari): $20 \times \text{Rp } 5.000 = \text{Rp } 100.000$
- Perhitungan Selisih Deposit:$$\text{Rp } 200.000 - \text{Rp } 100.000 = \mathbf{\text{Rp } 100.000 \text{ (Sisa Deposit)}}$$
- Hasil Invoice saat Downgrade: Rp 0 (Tidak perlu bayar apa-apa).

- Catatan Bulan Depan: Tagihan bulan depan (Rp 150.000) dipotong deposit Rp 100.000, jadi pelanggan cukup bayar Rp 50.000 di bulan berikutnya.


# Tabel Ringkasan Simulasi Angka

| Kondisi | Status Bayar | Paket Lama Digunakan | Prorata Paket Lama | Paket Baru Digunakan | Nominal Prorata Paket Baru | Biaya Harus Dibayar Saat Transaksi |
|---|---|---|---|---|---|---|
| **Upgrade Postpaid** | Belum Bayar | 10 Hari (10 Mbps) | Rp 100.000 | 20 Hari (50 Mbps) | Rp 400.000 | **Rp 500.000** (Total akumulasi pemakaian di akhir periode) |
| **Upgrade Prepaid** | Sudah Bayar | 10 Hari (10 Mbps) | Rp 100.000 *(Sisa kredit: Rp 200.000)* | 20 Hari (50 Mbps) | Rp 400.000 | **Rp 200.000** (Bayar kekurangan: Prorata Baru − Sisa Kredit) |
| **Downgrade Postpaid** | Belum Bayar | 10 Hari (10 Mbps) | Rp 100.000 | 20 Hari (5 Mbps) | Rp 100.000 | **Rp 200.000** (Total akumulasi pemakaian di akhir periode) |
| **Downgrade Prepaid** | Sudah Bayar | 10 Hari (10 Mbps) | Rp 100.000 *(Sisa kredit: Rp 200.000)* | 20 Hari (5 Mbps) | Rp 100.000 | **Rp 0** *(Kelebihan Rp 100.000 dicatat sebagai Deposit bulan depan)* |