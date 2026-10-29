# Analisis Bayar Dimuka pada Laporan Bulanan Admin

**Tanggal:** 2026-10-29  
**Status:** Analisis disepakati secara konsep; belum diimplementasikan.  
**Cakupan:** Laporan Bulanan Admin (/reports/collector-monthly), blok Tagihan, Pelanggan, dan penjelasan Piutang Bulan Lalu. Task aktif ADHOC-202 tetap berjalan.

## Definisi yang diminta

- **Tagihan — Dimuka:** nominal penuh uang yang dibayarkan dalam satu transaksi untuk tagihan bulan ini sekaligus beberapa bulan berikutnya (contoh 3–4 bulan). Ini uang yang diterima pada bulan transaksi, bukan saldo lama yang dipakai untuk melunasi invoice bulan ini.
- **Pelanggan — Bayar Dimuka:** jumlah pelanggan unik yang melakukan pembayaran beberapa bulan sekaligus pada bulan laporan. Dua transaksi oleh satu pelanggan dalam bulan yang sama tetap berarti satu orang.
- **Piutang tak Tertagih:** sisa invoice bulan lampau yang dihapus buku secara resmi karena dinilai tidak dapat ditagih. Invoice tetap sah dan tersimpan; sisa tersebut keluar dari piutang aktif. Ini bukan pembayaran.
- **Tak Tertagih Dipulihkan:** hapus buku periode terdahulu yang dibatalkan pada bulan laporan, sehingga sisa tagihan kembali menjadi piutang aktif. Ini juga bukan penerimaan kas.

Untuk menghindari dua arti “Dimuka”, nama metrik yang disarankan adalah **Bayar Dimuka Diterima** untuk uang baru dan **Dibayar dari Saldo** untuk saldo lama yang dipakai pada invoice. Label akhir UI ditentukan saat implementasi.

## Contoh Oktober 2026

Tagihan Oktober Rp100.000. Satu pelanggan membayar Rp400.000 sekali pada Oktober untuk Oktober–Januari.

| Metrik | Nilai Oktober |
| --- | ---: |
| Bayar Dimuka Diterima (nominal transaksi penuh) | Rp400.000 |
| Pelanggan Bayar Dimuka (orang unik) | 1 |
| Bagian yang melunasi invoice Oktober | Rp100.000 |
| Kelebihan yang dikreditkan ke saldo pelanggan | Rp300.000 |

Laporan kas yang ada tetap menguraikan Rp400.000 sebagai **Bulanan Rp100.000 + Lebih Bayar Rp300.000**. Angka Bayar Dimuka Diterima Rp400.000 adalah informasi atas transaksi yang sama dan **tidak boleh ditambahkan lagi** ke Total Uang Diterima atau Total Pembayaran invoice. Saat invoice November–Januari terbit, saldo Rp100.000 dipakai per bulan tanpa uang fisik baru dari transaksi ini.

## Kesenjangan implementasi saat ini

- CollectorMonthlyReportService::fillTagihanDanPelanggan() menghitung Tagihan Dimuka dari pembayaran invoice periode laporan dengan metode saldo. Itu **pemakaian saldo**, bukan nominal penuh yang diterima saat pelanggan membayar beberapa bulan sekaligus.
- Pelanggan Bayar Dimuka saat ini menghitung pelanggan yang invoice bulan laporan lunas seluruhnya dari saldo. Itu **status pelunasan invoice**, bukan jumlah pelanggan unik yang melakukan transaksi bayar dimuka pada bulan tersebut.
- Payment menyimpan bagian untuk invoice pada amount, kelebihan pada overpay_amount, dan saldo yang dipakai pada balance_used_amount. Payment::cashReceivedAmount() merupakan acuan uang fisik yang diterima.
- Sumber kredit saldo bayar_di_muka saat ini terkait kelebihan pembayaran invoice AWAL; kelebihan invoice lain berlabel kelebihan_bayar. Kedua sumber belum cukup untuk memastikan niat membayar 3–4 bulan sekaligus pada invoice bulanan. Semua overpay tidak boleh otomatis dianggap bayar dimuka.
- Rumus Tagihan saat ini adalah Total Pembayaran = Bulanan + Dimuka (saldo dipakai) + Diskon. Mengganti Dimuka dengan nominal transaksi penuh tanpa mengubah struktur akan menghitung uang dua kali dan merusak rekonsiliasi Tagihan Terbit = Total Pembayaran + Piutang.

## Rancangan saat pekerjaan laporan dijadwalkan

1. Petugas menandai transaksi secara eksplisit sebagai “Bayar beberapa bulan di muka” pada alur pembayaran yang mengizinkannya. Simpan penanda pada pembayaran sumber dan audit perubahannya. Mekanisme pemisahan pembayaran invoice serta kredit saldo tetap dipakai.
2. Agregasi **Bayar Dimuka Diterima** memakai payment_date dalam periode laporan, payment yang sah, POP yang boleh diakses, penanda bayar dimuka, dan nominal uang fisik penuh yang setara dengan Payment::cashReceivedAmount(). Jumlahkan setiap transaksi sekali.
3. Agregasi **Pelanggan Bayar Dimuka** memakai customer_id unik dari transaksi yang sama. Hitungan baris rincian bukan hitungan orang.
4. Tampilkan kedua metrik tanpa menjumlahkan nominal transaksi penuh lagi ke Total Pembayaran invoice atau Total Uang Diterima. Perjelas metrik lama sebagai **Dibayar dari Saldo**; rincian per sel dan ekspor harus mengikuti definisi yang sama.
5. Pembayaran historis hanya boleh diberi penanda jika niat bayar dimuka dapat diverifikasi. Jangan menyimpulkan niat dari overpay_amount saja. Snapshot period_closings bulan yang sudah ditutup tidak ditulis ulang; koreksi dan pengembalian dicatat menurut aturan periode tindakan.
6. Terapkan POP scope dan pelipatan Mini POP ke cabang induk yang berlaku pada laporan.

## Mengapa dua kolom piutang tetap diperlukan

Piutang tak Tertagih mencatat pengurangan piutang aktif karena **hapus buku**, bukan karena uang diterima. Tak Tertagih Dipulihkan mencatat pengembalian piutang aktif ketika hapus buku lama dibatalkan. Keduanya menjelaskan perubahan posisi piutang dan menjaga jejak audit serta angka bulan tertutup. Rumus tampilan saat ini: **Sisa Piutang = Belum Dibayar − Piutang tak Tertagih + Tak Tertagih Dipulihkan**. Perubahan bayar dimuka tidak mengubah kedua definisi ini.

## Kriteria penerimaan implementasi mendatang

- Pembayaran bertanda Rp400.000 atas invoice Rp100.000 menghasilkan Bayar Dimuka Diterima Rp400.000 dan Pelanggan Bayar Dimuka 1 pada bulan pembayaran.
- Pembayaran yang sama tetap menghasilkan Rp100.000 pelunasan invoice dan Rp300.000 kredit saldo; uang tidak terhitung dua kali.
- Dua transaksi bayar dimuka pelanggan yang sama pada satu bulan menjumlahkan dua nominal tetapi menghitung satu orang.
- Kelebihan bayar biasa tanpa penanda tidak masuk metrik bayar dimuka.
- Pemakaian saldo bulan berikutnya tidak dicatat sebagai kas baru atau orang baru yang membayar dimuka pada bulan berikutnya.
- Pembayaran yang dikembalikan, batas periode, POP scope, Mini POP, snapshot tertutup, rincian, dan ekspor mengikuti definisi agregat yang sama.
- Dua kolom piutang tetap menjelaskan perubahan piutang tanpa masuk penerimaan kas.

## Rujukan

- app/Services/CollectorMonthlyReportService.php
- app/Models/Payment.php
- app/Services/PaymentService.php
- app/Services/InvoiceWriteOffService.php
- docs/billing-pembayaran/README.md
