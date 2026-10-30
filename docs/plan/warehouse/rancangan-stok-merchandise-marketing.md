# Rancangan Stok Merchandise Marketing Pusat ke Cabang

Tanggal: 2026-10-30  
Status: Rancangan yang dikoreksi sesuai arahan pengguna; belum diimplementasikan.

## Tujuan dan batas riwayat

Merchandise Marketing seperti kaos branding, mug, goodie bag, dan kaos polo disimpan sebagai stok di Gudang Pusat. Cabang dapat meminta barang dari Pusat. Ketika permintaan berhasil dicatat, stok Pusat otomatis berkurang dan stok Cabang tujuan otomatis bertambah. Riwayat merchandise Marketing berakhir pada Cabang penerima.

Sistem Marketing ini tidak mencatat pembagian hadiah, penerima akhir, pelanggan, teknisi, PIC kegiatan, pengembalian barang, atau opsi selisih stok. Cabang adalah titik akhir pencatatan distribusi Marketing.

## Data yang dicatat

- Master barang: kode, nama, kategori merchandise, satuan, dan varian yang perlu dihitung terpisah, misalnya Polo L dan Polo XL.
- Stok per lokasi: Pusat dan masing-masing Cabang. Pemilik barang tetap Marketing; lokasi menunjukkan tempat stok berada.
- Permintaan Cabang: nomor referensi unik, tanggal, Cabang tujuan, peminta, barang, varian, jumlah, dan catatan tujuan permintaan bila diperlukan.
- Riwayat perpindahan: nomor referensi permintaan, barang, jumlah, Pusat asal, Cabang tujuan, waktu, dan pengguna yang mencatat.
- Tidak ada data harga perolehan atau laporan nilai dalam rancangan ini.

## Alur stok

1. Petugas mencatat barang masuk di Gudang Pusat. Stok Pusat bertambah sesuai jumlah yang diterima.
2. Cabang A mengajukan permintaan barang Marketing dari stok Pusat.
3. Pada satu proses pencatatan permintaan yang berhasil, sistem memeriksa ketersediaan stok Pusat, mengurangi stok Pusat, menambah stok Cabang A, dan menyimpan riwayat perpindahan. Seluruh perubahan berhasil bersama-sama atau batal bersama-sama.
4. Riwayat Marketing berhenti pada Cabang A. Tidak ada proses lanjutan untuk membagikan barang kepada pihak lain di modul ini.

Tidak ada tahap persetujuan, status barang di tangan PIC, atau konfirmasi pembagian hadiah dalam alur yang dirancang ini. Jika permintaan gagal karena stok Pusat tidak cukup atau data tidak valid, stok kedua lokasi tidak berubah. Pengiriman ulang permintaan yang sama tidak boleh memotong stok dua kali.

### Contoh

| Peristiwa | Stok Pusat | Stok Cabang A |
|---|---:|---:|
| Awal | 100 mug | 0 mug |
| Cabang A berhasil meminta 30 mug | 70 mug | 30 mug |

Riwayat yang terlihat: 30 mug berpindah dari Pusat ke Cabang A dengan nomor permintaan dan waktu pencatatan. Tidak ada baris pembagian hadiah atau penerima akhir.

## Laporan

| Laporan | Isi |
|---|---|
| Posisi stok Marketing | Jumlah tersedia per barang/varian di Pusat dan masing-masing Cabang |
| Barang masuk Pusat | Tanggal, referensi, barang/varian, jumlah masuk, pengguna pencatat |
| Permintaan dan distribusi ke Cabang | Tanggal, nomor permintaan, Cabang tujuan, peminta, barang/varian, jumlah yang memotong stok Pusat |
| Mutasi stok per lokasi | Saldo awal, barang masuk atau diterima dari Pusat, barang keluar ke Cabang, saldo akhir per periode |

Filter laporan: periode, barang/varian, dan Cabang yang boleh diakses. Total stok Marketing lintas lokasi dihitung sebagai stok Pusat ditambah stok semua Cabang, tanpa menghitung perpindahan sebagai barang baru. Tidak ada laporan hadiah dibagikan, barang di tangan PIC, barang kembali, selisih, atau nilai barang.

## Akses dan pengaman

- Pengguna Cabang hanya melihat permintaan dan stok Cabang dalam POP scope yang diizinkan. Pusat dapat melihat stok Pusat dan perpindahan ke Cabang sesuai izinnya.
- Akses daftar, detail, ekspor, dan pencatatan permintaan memakai permission yang sesuai; ID Cabang di luar scope tidak boleh membuka data Cabang lain.
- Jumlah permintaan harus positif dan tidak boleh melebihi stok Pusat yang tersedia.
- Satu permintaan menghasilkan satu perpindahan stok. Kegagalan proses tidak boleh meninggalkan Pusat berkurang tanpa Cabang bertambah, atau sebaliknya.
- Bukti permintaan dan riwayat perpindahan tetap dapat ditelusuri; dokumen ini tidak menambahkan alur koreksi stok atau opsi selisih.

## Kriteria penerimaan implementasi kelak

1. Penerimaan 100 mug membuat stok Pusat menjadi 100.
2. Permintaan 30 mug oleh Cabang A yang berhasil membuat Pusat 70 dan Cabang A 30 dalam satu proses.
3. Permintaan melebihi stok Pusat atau permintaan duplikat tidak mengubah stok.
4. Riwayat Marketing menampilkan perpindahan Pusat ke Cabang A dan berhenti di Cabang.
5. Laporan posisi, permintaan/distribusi, dan mutasi per lokasi menampilkan angka yang cocok dengan perpindahan yang dicatat.
6. Pengguna tidak dapat melihat atau membuat permintaan untuk Cabang di luar POP scope-nya.

## Scope pencatatan

Revisi ini mengikuti koreksi langsung pengguna atas rancangan sebelumnya. Task aktif ADHOC-202 pada docs/TASKS.md tetap aktif. Tidak ada perubahan kode, skema, transaksi, atau data stok.

Rujukan: docs/warehouse/README.md, docs/warehouse/user-flow.md, docs/warehouse/business-logic.md.
