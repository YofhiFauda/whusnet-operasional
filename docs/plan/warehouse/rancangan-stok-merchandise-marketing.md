# Rancangan Stok Merchandise Marketing Pusat ke Cabang

Tanggal: 2026-10-30  
Status: Diimplementasikan pada 2026-10-31; migration diterapkan pada database aplikasi lokal.

## Tujuan dan batas riwayat

Merchandise Marketing seperti kaos branding, mug, goodie bag, dan kaos polo disimpan sebagai stok di Gudang Pusat. Cabang dapat meminta barang dari Pusat. Ketika permintaan berhasil dicatat, stok Pusat otomatis berkurang dan stok Cabang tujuan otomatis bertambah. Riwayat merchandise Marketing berakhir pada Cabang penerima.

Sistem Marketing ini tidak mencatat pembagian hadiah, penerima akhir, pelanggan, teknisi, PIC kegiatan, pengembalian barang, atau opsi selisih stok. Cabang adalah titik akhir pencatatan distribusi Marketing.

## Aturan pengurangan stok menurut jenis barang

| Jenis barang | Peristiwa yang mengurangi stok Gudang asal | Posisi setelah peristiwa |
|---|---|---|
| Operasional | Dipakai dalam pekerjaan atau dikeluarkan sebagai custody teknisi sesuai alur Gudang operasional | Tercatat sebagai pemakaian atau berada pada custody teknisi |
| Merchandise Marketing | Permintaan perpindahan Pusat ke Cabang berhasil dicatat | Stok Pusat berkurang dan stok Cabang tujuan bertambah; riwayat Marketing berhenti di Cabang |

Untuk merchandise Marketing, perpindahan Pusat ke Cabang adalah titik keluar dari stok Pusat, bukan klaim bahwa barang telah diberikan kepada penerima akhir. Stok gabungan Pusat dan Cabang tidak berkurang hanya karena perpindahan antarlokasi. Modul Marketing tidak mengikuti barang setelah sampai di Cabang.
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

## Implementasi

- Master Barang memakai kategori sistem `merchandise_marketing`. Varian seperti ukuran kaos dibuat sebagai SKU terpisah. Tracking wajib kuantitas; kategori tidak dapat dipindah ke/dari Marketing setelah barang memiliki riwayat stok.
- Halaman `/warehouse/marketing` menampilkan posisi stok, riwayat permintaan, barang masuk Pusat, dan mutasi. Laporan dapat difilter menurut periode, barang, dan Cabang serta diekspor ke CSV tanpa harga.
- Barang Masuk Marketing dicatat dari form khusus tanpa harga dan hanya ke Gudang Pusat. Permintaan dari Cabang langsung menulis permintaan, transfer, pengurangan Pusat, serta penambahan Cabang dalam satu transaksi database. Kunci permintaan mencegah submit ulang mengurangi stok dua kali.
- Permintaan operasional, transfer biasa, issue teknisi, penyesuaian/opname, dan form Barang Masuk operasional tidak menerima barang berkategori Marketing. Hak akses dan filter data mengikuti POP scope Gudang.
- Kunci permintaan hanya dapat dipakai ulang untuk permintaan yang identik; benturan submit serentak mengambil hasil transaksi pertama. Laporan mutasi menampilkan saldo awal, masuk, keluar, dan akhir per lokasi/periode. Riwayat barang masuk Pusat memakai filter, pagination, referensi, dan pencatat. Transfer Marketing tidak menyalin snapshot harga dari stok lama.
- Migration `2026_10_30_161227_add_marketing_fields_to_stock_requests_table.php` telah diterapkan pada database aplikasi lokal. Tidak ada data stok lama yang diubah oleh migration.
## Ekstensi kategori nonoperasional

Kategori baru dapat dipilih sebagai nonoperasional pada Master Kategori Barang. Atribut kepemilikan dan pemasangan jaringan tidak ditampilkan untuk item kategori tersebut; tracking stok memakai kuantitas. Seluruh kategori nonoperasional memakai alur pencatatan Pusat ke Cabang yang sama, sedangkan laporan dan ekspor dapat difilter per kategori. Kategori item nonoperasional tidak dapat diganti setelah ada riwayat stok agar laporan lama tetap konsisten. Perpindahan dengan aturan khusus per kategori memerlukan rancangan tersendiri.

## Scope pencatatan

Implementasi mengikuti koreksi langsung pengguna atas rancangan sebelumnya. Task aktif ADHOC-202 pada docs/TASKS.md tetap aktif. Penerimaan Marketing memakai formulir tanpa harga; permintaan Cabang memindahkan stok secara atomik dan langsung selesai. Data stok lama tidak diubah oleh migration.

Rujukan: docs/warehouse/README.md, docs/warehouse/user-flow.md, docs/warehouse/business-logic.md.
