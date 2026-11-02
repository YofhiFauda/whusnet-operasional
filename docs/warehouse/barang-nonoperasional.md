# Barang Nonoperasional di Gudang

Panduan ini menjelaskan stok barang nonoperasional, termasuk merchandise milik Marketing, yang disimpan di Gudang Pusat lalu diminta oleh Cabang. Contoh barang: kaos branding, mug, goodie bag, dan kaos polo.

## Aturan stok

| Jenis barang | Saat stok berkurang | Posisi yang dicatat |
| --- | --- | --- |
| Operasional | Saat dipakai dalam pekerjaan atau masuk custody teknisi, sesuai alur operasional | Gudang, teknisi, pemasangan, dan alur operasional lain yang relevan |
| Nonoperasional, termasuk merchandise Marketing | Saat permintaan Cabang berhasil disimpan | Gudang Pusat berkurang dan Gudang Cabang bertambah pada saat yang sama |

Barang nonoperasional tetap memiliki saldo stok di Cabang setelah dipindahkan. Riwayat dari sisi Marketing berhenti pada Cabang: sistem ini tidak mencatat pembagian hadiah, penerima akhir, pelanggan, teknisi, atau PIC kegiatan. Tidak ada alur pengembalian barang atau opsi selisih untuk barang nonoperasional.

## Menyiapkan kategori dan barang

1. Buka **Master Kategori Barang**, lalu buat kategori dengan **Jenis Penggunaan: Nonoperasional**. Contohnya **Merchandise Marketing**. Kategori nonoperasional baru dapat dibuat untuk kelompok barang lain.
2. Buka **Master Barang**, pilih kategori tersebut, lalu isi identitas barang, satuan, dan data lain yang ditampilkan formulir. Setiap jenis atau varian yang perlu dihitung terpisah dibuat sebagai barang tersendiri.
3. Barang nonoperasional menggunakan pencatatan berdasarkan **jumlah (quantity)**. Formulir tidak meminta **Kepemilikan**, **Tipe Node Tujuan**, atau **Posisi Pemasangan** karena atribut itu hanya relevan untuk barang operasional.
4. Pastikan barang dan kategorinya aktif agar dapat dipilih pada formulir Gudang.

Pilihan **Operasional/Nonoperasional** pada kategori terkunci setelah kategori memiliki barang. Kategori pada barang nonoperasional juga tidak dapat diganti setelah barang mempunyai riwayat stok. Karena itu, tentukan klasifikasi sebelum mulai mencatat stok.

Saat ini seluruh kategori nonoperasional memakai **aturan perpindahan yang sama: Pusat → Cabang**. Kategori dipakai untuk mengelompokkan barang dan memfilter laporan; aturan perpindahan khusus untuk tiap kategori belum tersedia.

## Barang masuk ke Gudang Pusat

1. Buka menu **Gudang → Barang Nonoperasional**.
2. Pilih **Barang Masuk Pusat**.
3. Pilih Gudang Pusat, barang, dan jumlah yang diterima. Catatan bersifat opsional.
4. Simpan. Saldo barang di Pusat langsung bertambah dan penerimaan masuk riwayat.

Jumlah harus lebih dari nol, dengan ketelitian paling banyak dua angka desimal. Alur ini mencatat kuantitas; tidak ada input harga perolehan untuk laporan nilai barang nonoperasional.

## Memindahkan stok ke Cabang

1. Dari **Gudang → Barang Nonoperasional**, pilih **Pindahkan ke Cabang**.
2. Pilih Gudang Pusat asal, Cabang tujuan, barang, dan jumlah. Catatan bersifat opsional.
3. Simpan permintaan. Jika stok Pusat cukup, sistem langsung mengurangi Pusat dan menambah Cabang. Permintaan dan mutasinya muncul di riwayat; tidak diperlukan konfirmasi penerimaan kedua oleh Cabang.

Contoh: Pusat memiliki 100 mug, lalu Cabang A meminta 30 mug. Setelah berhasil disimpan, Pusat memiliki **70 mug** dan Cabang A **30 mug**. Total di dua lokasi tetap 100 mug.

Permintaan melebihi saldo Pusat ditolak tanpa mengubah kedua saldo. Pengiriman ulang formulir dengan kunci permintaan yang sama tidak membuat perpindahan ganda.

## Posisi stok dan laporan

Halaman **Barang Nonoperasional** menampilkan:

| Bagian | Yang ditunjukkan |
| --- | --- |
| Posisi stok | Saldo terkini per lokasi dan barang. Angka ini adalah posisi saat halaman dibuka, bukan saldo pada akhir periode filter. |
| Riwayat permintaan | Permintaan Cabang yang berhasil dicatat, termasuk barang, jumlah, asal, tujuan, dan pembuat. |
| Riwayat penerimaan | Barang nonoperasional yang masuk ke Gudang Pusat. |
| Ringkasan stok | Saldo awal, masuk, keluar, dan saldo akhir **untuk periode yang dipilih**, per lokasi dan barang. |
| Riwayat mutasi | Rincian penerimaan dan perpindahan yang mendasari laporan. |
| Ekspor CSV | Baris mutasi sesuai filter, berisi tanggal, referensi, lokasi, barang, arah, dan jumlah. Ekspor ini bukan ekspor ringkasan saldo dan tidak memuat nilai rupiah. |

Filter yang tersedia: tanggal awal, tanggal akhir, kategori, barang, dan Cabang. Filter kategori memungkinkan laporan **Merchandise Marketing** terpisah dari kategori nonoperasional lain. Filter Cabang menampilkan lokasi dan mutasi terkait Cabang yang dipilih; penerimaan Pusat tidak muncul dalam riwayat penerimaan ketika filter Cabang aktif.

Akses data mengikuti scope POP pengguna. Pengguna hanya dapat melihat atau memilih lokasi yang diizinkan untuk perannya. Jika perlu membandingkan saldo Pusat dan Cabang sekaligus, gunakan akun yang memiliki akses ke kedua lokasi.

## Izin akses

| Tindakan | Permission |
| --- | --- |
| Melihat kategori/barang | `item_categories.view` / `items.view` |
| Membuat kategori/barang | `item_categories.create` atau `item_categories.update` / `items.create` atau `items.update` |
| Melihat laporan dan ekspor CSV nonoperasional | `warehouse_stock_request.view` |
| Mencatat barang masuk Pusat | `warehouse_transfer.create` |
| Meminta barang untuk Cabang | `warehouse_stock_request.create` |

Selain permission, sistem tetap memeriksa scope POP pada tindakan Gudang.

## Batasan alur saat ini

- Barang nonoperasional tidak masuk alur permintaan stok operasional, pemakaian teknisi, pemasangan, adjustment, atau stock opname operasional.
- Tidak ada pencatatan pembagian hadiah ke penerima akhir, pengembalian sisa, rusak/hilang/selisih, atau harga dan nilai perolehan dalam laporan nonoperasional.
- Cabang memiliki saldo yang terlihat, tetapi alur distribusi nonoperasional setelah Cabang belum dicatat.
- Nama route dan jenis permintaan `marketing` masih dipakai secara teknis untuk menjaga kompatibilitas. Di UI, alur ini berlaku untuk seluruh kategori nonoperasional.

## Uji manual singkat

1. Buat kategori **Nonoperasional** dan satu barang aktif; pastikan atribut pemasangan/kepemilikan tidak diminta.
2. Terima 100 unit di Pusat. Periksa posisi Pusat dan riwayat penerimaan.
3. Minta 30 unit untuk Cabang A. Periksa posisi Pusat 70, Cabang A 30, riwayat permintaan, dan ringkasan stok.
4. Coba minta 71 unit lagi dari Pusat yang sama. Pastikan permintaan ditolak dan saldo tidak berubah.
5. Buat barang di kategori nonoperasional lain. Filter laporan per kategori, lalu ekspor CSV dan periksa bahwa hanya mutasi sesuai filter yang muncul.
6. Masuk dengan pengguna berscope satu Cabang. Pastikan laporan tidak menampilkan Cabang di luar scope.
