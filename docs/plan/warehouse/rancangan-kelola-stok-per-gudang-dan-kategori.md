# Rancangan Kelola Stok per Gudang dan Kategori Barang

> **Digantikan** oleh `docs/superpowers/specs/2026-10-29-kelola-stok-papan-gudang-design.md` (papan jadi tampilan default; Per Lot lama di `?view=rincian`). Dokumen ini hanya arsip rancangan awal.

Tanggal: 2026-10-28
Status: Rancangan untuk ditinjau; belum diimplementasikan.

## Tujuan dan keputusan produk

Petugas harus dapat menemukan Barang A di Gudang C dan Barang B di Gudang A tanpa menyisir daftar stok campuran. Gudang Pusat dan Gudang Cabang adalah lokasi stok. Dalam analogi pengguna, tiap cabang adalah ruangan; "rak" adalah **kelompok visual berdasarkan Kategori Barang** dari `item_categories`, misalnya Modem, AP, Splitter, dan ODP. Kategori baru mengikuti master data otomatis.

Rak bukan posisi fisik bernomor. Tidak ada tabel rak, kode rak, kapasitas rak, atau transaksi perpindahan antar-rak. Barang tetap mempunyai satu kategori master; perubahan kategori hanya mengubah kelompok tampilannya.

## Kondisi saat ini

`warehouse.stock.index` sudah mempunyai filter gudang, kategori, barang, jenis tracking, pencarian, serta mode Per Lot (default) dan Ringkasan per Item. Stok QUANTITY, SERIALIZED, dan ROLL sudah digabung. `WarehouseStockPositionService` sudah menghitung posisi per barang/POP, custody teknisi, masalah, dan transit. Kendala utamanya adalah hasil masih sulit dibaca sebagai struktur Gudang → Kategori → Barang.

## Pendekatan

| Pilihan | Kelebihan | Kekurangan |
|---|---|---|
| Tabel datar dengan filter | Perubahan kecil | Cabang dan kategori tetap bercampur |
| Pohon Gudang → Kategori → Barang saja | Enak memantau cabang | Mencari barang lintas cabang lambat |
| **Jelajah gudang + pencarian lintas gudang** | Mendukung dua cara mencari | Konteks filter perlu dijaga konsisten |

Rekomendasi: pilihan ketiga, meneruskan arah Visual Bay Explorer dari analisis UI lama dengan kategori nyata. Contoh tray, suhu, dan kapasitas rak fiktif dalam dokumen lama tidak berlaku.

## Susunan halaman

```text
Kelola Stok
├─ Cari barang [nama / kode / SN / nomor roll / lot]
├─ Pilih Gudang [Semua yang dapat diakses] [Pusat] [Cabang A] [Cabang C]
├─ Filter aktif [kategori] [jenis tracking] [status] dan Hapus semua
├─ Satu gudang dipilih: kategori → barang → jumlah tersedia → rincian
└─ Semua gudang/pencarian lintas gudang: hasil per Gudang + Kategori + Barang
```

Kategori menjadi judul kelompok atau filter cepat. Hasil pencarian terlihat langsung, tanpa beberapa lapis accordion. Urutan informasi hasil: nama dan kode barang → gudang → kategori → tersedia beserta satuan → status/peringatan → rincian. Pada daftar satu gudang, nama gudang cukup terlihat di header. Mode Per Lot tetap tersedia untuk rincian operasional.

## Alur utama

**Mulai dari gudang:** pilih Gudang C sesuai POP scope → pilih Kategori Modem → lihat tiap model dan jumlah tersedia → buka daftar SN atau jejaknya. Angka pada kategori berarti jumlah jenis barang/SKU, bukan jumlah fisik yang mencampur satuan.

**Mulai dari barang:** cari nama, kode, SN, nomor roll, atau lot tanpa memilih gudang → hasil menunjukkan setiap gudang dalam scope beserta posisi barang → pilih hasil untuk melihat rincian di gudang itu. Hasil kecocokan spesifik diberi label, misalnya `Cocok: SN ...`, agar angka yang tersaring tidak disalahpahami sebagai total stok.

Jika Gudang A sudah dipilih, pencarian dibatasi ke Gudang A. Aksi `Cari di semua gudang terjangkau` hanya menghapus filter gudang; POP scope server tetap berlaku.

## Angka dan status

| Label | Makna | Sumber saat ini |
|---|---|---|
| Tersedia di gudang | Barang yang siap berada di gudang terpilih | Saldo QUANTITY; SN AVAILABLE; meter tersisa ROLL yang tersedia/diterima |
| Di teknisi | Barang masih dalam custody teknisi | `technician_custody`, SN dan roll terkait |
| Dalam perjalanan | Sisa transfer yang belum diterima | Transfer terbuka dan ledger |
| Perlu perhatian | Stok di bawah ambang, kondisi SN bermasalah, atau opname sesuai data yang ada | Ambang saldo, kondisi SN, transaksi opname |

Barang di teknisi, transit, retur menunggu verifikasi, karantina/rusak, terpasang di pelanggan, atau aset pada node jaringan tidak dihitung sebagai **tersedia di rak gudang**. ROLL menampilkan meter tersisa sebagai jumlah utama dan banyak roll sebagai rincian; SERIALIZED menampilkan unit/SN; QUANTITY mengikuti satuan barang. Jangan menjumlah meter, roll, dan unit menjadi satu angka fisik kategori.

Kondisi SN memakai label Gudang yang sudah ada: Baru, Bekas Belum Dicek, Bekas Sudah Dicek, dan Bekas Rusak. Kondisi tidak otomatis berarti siap di-issue; status dan aturan pemeriksaan tetap menentukan kesiapan.

## Pencarian dan filter

- Pencarian di server mencakup nama/kode barang, SN, nomor roll, dan lot yang tercakup halaman stok.
- Kategori memakai ID master barang; perubahan nama kategori tidak memutus filter.
- Filter gudang, kategori, jenis tracking, dan status tampil sebagai chip. Mengubah satu filter mempertahankan yang lain dan mereset halaman paginasi.
- Hasil kosong dibedakan: tidak ada kecocokan, gudang belum punya stok tersedia, dan pengguna tidak mempunyai gudang yang boleh diakses.
- SN atau roll yang hanya ada di custody/transit dapat ditautkan ke pelacakan yang sudah ada sesuai izin, dengan status sebenarnya.
- Urutan dan paginasi harus stabil; judul kategori tidak boleh hilang karena barisnya ada di halaman berikutnya.

## RBAC dan tampilan

Semua opsi gudang, ringkasan kategori, pencarian, dan rincian dibatasi oleh `EffectiveAccessService`. Memasukkan ID POP atau SN dari luar scope melalui URL tidak boleh membocorkan nama, jumlah, atau keberadaan barang. Tombol mutasi tetap memakai permission aksinya sendiri; rancangan ini tidak menambah alur mutasi.

Desktop memakai pilihan gudang dan kategori di dekat daftar barang. Mobile memakai pilihan ringkas dan kartu barang; pencarian tetap mudah dijangkau. Status mempunyai teks, bukan hanya warna. Hasil dan rincian dapat dibuka dengan keyboard.

## Batas implementasi berikutnya

Rancangan pertama memakai `warehouse.stock.index`, `ItemCategory`, dan `WarehouseStockPositionService`. Berkas yang mungkin terkait: `WarehouseStockController.php`, `WarehouseStockPositionService.php`, `resources/views/warehouse/stock/index.blade.php`, partial ringkasan stok, dan tes fitur Gudang. Daftar final ditentukan setelah audit kode. Tidak ada migrasi atau tabel baru yang direncanakan.

Kriteria penerimaan implementasi:

1. Barang A dapat dicari dan hasil menunjukkan Gudang C bila posisinya ada dalam POP scope pengguna.
2. Barang B di Gudang A dapat ditemukan lewat Gudang → Kategori → Barang tanpa menyisir daftar campuran.
3. Kategori berasal dari master data tanpa rak fisik atau kapasitas fiktif.
4. Jumlah tersedia benar untuk QUANTITY, SERIALIZED, dan ROLL; custody, transit, dan stok bermasalah tidak bercampur.
5. Pencarian SN/roll/lot, filter, paginasi, dan rincian tidak membocorkan data POP lain.
6. Desktop dan mobile tetap mudah dipakai, termasuk ketika hasil kosong.

## Keputusan terbuka

Tampilan awal bagi pengguna yang dapat mengakses banyak gudang belum diputuskan pengguna. **Usulan:** buka ringkasan Semua Gudang Terjangkau agar pencarian lintas gudang langsung tersedia; pengguna dengan satu gudang langsung melihat gudangnya. Alternatif: buka gudang terakhir yang dipakai. Keputusan ini perlu ditetapkan sebelum implementasi.

## Scope dan rujukan

Ini dokumentasi atas permintaan langsung pengguna. Task aktif di `docs/TASKS.md` adalah ADHOC-202 (cutover hierarki infrastruktur); rancangan Gudang ini tidak mengubah task aktif atau kode.

Rujukan: `docs/warehouse/README.md`, `docs/warehouse/user-flow.md`, `docs/warehouse/business-logic.md`, `docs/plan/warehouse/analisa-ui-ux-warehouse.md`, `docs/plan/warehouse/analisa-komparasi-ui-kelola-stok-gudang.md`, `app/Http/Controllers/Warehouse/WarehouseStockController.php`, `app/Services/WarehouseStockPositionService.php`, dan `resources/views/warehouse/stock/index.blade.php`.
