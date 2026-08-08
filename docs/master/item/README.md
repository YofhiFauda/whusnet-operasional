# Master Barang / Material

Daftar barang yang boleh dicatat teknisi di **Estimasi Kebutuhan Alat** (Laporan Survey) dan **Perangkat Pasif Terpakai** (Laporan Pemasangan).

Ditambahkan 2026-07-31 bersama fitur pencatatan material — lihat [docs/plan/rancangan-request-pemasangan-dan-material-task.md](../../plan/rancangan-request-pemasangan-dan-material-task.md).

## Kenapa master ini ada

Bukan karena butuh manajemen stok — stok memang **belum ada**. Master ini ada supaya penamaan barang seragam sejak baris pertama dicatat.

Tanpa master, nama barang diketik bebas dan data enam bulan ke depan akan berisi `"Dropcore 1 core"`, `"dropcore 1core"`, `"DC 1C"`, `"kabel dropcore"` untuk barang yang sama. Modul Inventory kemudian harus membersihkannya manual, dan pekerjaan itu tidak bisa diotomasi — persis kerja ekstra yang ingin dihindari.

## Yang SENGAJA tidak ada di sini

Stok, harga, lokasi gudang, minimum stock, pergerakan barang. Semua itu wilayah modul Inventory (lihat [docs/post-mvp/inventory-fop.md](../../post-mvp/inventory-fop.md)). Modul Inventory nanti **menambah** kolom/tabel di atas `items`, bukan menggantinya.

## Halaman

| Aksi | Route | Permission |
|---|---|---|
| Daftar barang | `GET /master/items` | `items.view` |
| Tambah | `GET/POST /master/items/create` | `items.create` |
| Ubah | `GET/PUT /master/items/{item}` | `items.update` |
| Aktif/Nonaktif | `POST /master/items/{item}/toggle` | `items.update` |

**Tidak ada aksi hapus.** Baris `task_materials` yang sudah tersimpan menunjuk ke `items`; menghapus master bikin laporan lama kehilangan rujukan. Barang yang tidak dipakai lagi **dinonaktifkan** (`is_active = false`) — pola yang sama dengan master lain di repo ini.

Permission digenerate `PermissionGeneratorService` dari feature `items` (`ItemFeatureSeeder`), bukan hardcode. Awalnya cuma owner yang punya (lewat wildcard `*`); role lain ditambahkan lewat UI Role Management.

## Skema

Lihat [docs/customer-lifecycle/database-schema.md](../../customer-lifecycle/database-schema.md#tabel-items-master-barangmaterial) — tabel `items` didokumentasikan di sana bersama `task_materials` yang memakainya.

| Kolom | Keterangan |
|---|---|
| `code` | unique, identitas tetap (mis. `DC-1C`) |
| `name` | nama tampilan |
| `type` | `App\Enums\MaterialType` |
| `unit` | satuan resmi — **menang atas isian form** waktu barang ini dipilih |
| `is_active` | barang nonaktif tak muncul di dropdown laporan |

## Perilaku yang perlu diketahui

1. **Satuan master menang.** Saat teknisi memilih barang dari master, nama/tipe/satuan disalin dari master dan tidak bisa dikarang. Form mengunci kolomnya, dan `TaskMaterialService` juga menolak nilai kiriman — kalau tidak, satu POST yang dirakit tangan bisa memasukkan "120 pcs dropcore" dan agregasi pemakaian jadi ngawur.
2. **Snapshot, bukan join.** `task_materials` menyimpan salinan nama & tipe. Rename barang di sini **tidak** mengubah laporan yang sudah tersimpan — sengaja, supaya histori tidak berubah retroaktif.
3. **Barang "Lainnya" tetap boleh.** Teknisi di lapangan tidak boleh terhambat karena barangnya belum terdaftar. Baris seperti itu tersimpan dengan `item_id` null dan jadi kandidat penambahan master.

## Isi awal

`ItemSeeder` mengisi 11 barang standar (dropcore 1C/2C, splitter 1:8 & 1:16, ODP 8 port, patch cord, media converter, tray, klem, tiang). Sengaja pendek — barang riil ditambahkan admin lewat halaman Master Data. Kalau seeder ini kepanjangan, yang terjadi justru dua daftar (seeder vs realita gudang) yang tidak pernah sinkron.
