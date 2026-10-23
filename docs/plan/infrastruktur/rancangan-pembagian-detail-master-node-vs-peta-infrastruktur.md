# Rancangan: Detail Aset Master Node vs Peta Infrastruktur

Tanggal: 22 Oktober 2026
Status: **selesai dieksekusi** (22 Oktober 2026). Rencana eksekusi §4 (bagian Master Node) sudah diimplementasikan dan diverifikasi test. Peta Infrastruktur tidak diubah (sesuai §4 poin 2 — sudah benar). Rincian implementasi: lihat §8.

## Ringkasan eksekusi

1. **Master Node** (`network/nodes/index.blade.php` + `show.blade.php`) — blok "Aset Terpasang"/"Aset Gudang Terpasang" diganti jadi ringkasan count per item (`NetworkNode::assetSummary()`), tanpa SN/lot. Tombol/tautan "Lihat detail di Peta Infrastruktur" (`network.map.index?node_id=`) ditambahkan di kedua halaman.
2. **Peta Infrastruktur** — menerima `node_id` opsional (`InfrastructureMapController::index()`), divalidasi terhadap node yang SUDAH di-scope (404 kalau di luar scope/pop_id/tidak ada), lalu membuka tab Inventaris dengan node itu terpilih (`network/map/index.blade.php`, Alpine `initialNodeId`).
3. **Gap tak terduga yang ikut diperbaiki** (ditemukan saat verifikasi §3 lama): tautan "Riwayat Gudang" di Peta Infrastruktur (`InfrastructureMapService`) sebelumnya SELALU dibentuk tanpa cek `warehouse_traceability.view` — sekarang digate (`$canViewTraceability`), konsisten dengan §Ketepatan sumber data.
4. **Landmine Blade ditemukan & dihindari**: menyisipkan `@php...@endphp` di titik tertentu file `index.blade.php`/`show.blade.php` (keduanya sudah sangat besar/bernested akibat rewrite independen sebelumnya) membuat compiler Blade gagal (`unexpected end of file, expecting endif`) walau sintaksnya sendiri valid berdiri sendiri. Solusi: agregasi dipindah ke `NetworkNode::assetSummary()` (model), bukan `@php` inline — lebih sesuai konvensi (`business logic bukan di view`) sekaligus menghindari landmine itu.
5. **2 test pre-existing gagal** — **di luar scope rancangan ini**, bukan regresi dari eksekusi ini:
   - `InfrastructureMapTest::peta_menampilkan_pusat_cabang_dan_mini_pop_tanpa_node_perangkat` — breadcrumb "Pusat / Server Utama" tidak muncul di Master Node.
   - `InfrastructureHierarchyTest::panel_pon_menampilkan_...` — `_pon-inspector.blade.php` tidak merender SN splitter.
   Keduanya berasal dari rewrite independen file-file ini (di luar kendali rancangan ini, kemungkinan pekerjaan lain yang sedang berjalan paralel) — perlu ditangani terpisah, bukan diperbaiki diam-diam di sini.
6. Test terdampak: `NetworkNodeAssetViewTest` (ditulis ulang), `InfraAssetRegistrationTest` (1 assert disesuaikan), `InfrastructureMapTest` (4 test baru: deep link valid/404×3/permission gating).

## Tujuan dan batas

Master Node dipakai untuk mengelola struktur node dan port. Peta Infrastruktur dipakai untuk membaca topologi dan menelusuri aset Gudang yang terpasang. Master Node tetap perlu ringkasan aset untuk pekerjaan di node. Rancangan ini membahas **aset infrastruktur pada node**; SN ONT pelanggan pada hierarki/port adalah konteks pelanggan yang tetap mengikuti permission customers.detail.devices.view_sensitive.

Tidak ada perubahan kode, skema, data, atau status task dalam koreksi dokumen ini. Implementasi memerlukan task lanjutan yang disetujui.

## Temuan kode

1. Daftar Master Node (resources/views/network/nodes/index.blade.php) menampilkan SN dan lot aset infrastruktur. Tes NetworkNodeAssetViewTest saat ini mewajibkan SN muncul.
2. Detail Master Node (resources/views/network/nodes/show.blade.php) juga menampilkan SN dan lot. Mengubah daftar saja tidak memenuhi pembagian yang diusulkan.
3. InfrastructureMapService sudah menyusun rincian aset untuk drawer Peta: SN/lot, jumlah, waktu transaksi, teknisi, dan nomor task **jika ada event yang cocok**. Tabel Inventaris adalah daftar node; rincian aset ada di drawer node terpilih.
4. Peta menerima tab dan pop_id, tetapi belum menerima node_id. Tautan ke tab Inventaris saja akan membuka node awal, bukan node asal.
5. Route Peta dan Master Node memakai network_nodes.view. Route riwayat Gudang memakai warehouse_traceability.view; tautan ke sana harus mengikuti izin itu.

## Pembagian tampilan

| Kebutuhan | Master Node | Peta Infrastruktur |
|---|---|---|
| Kelola struktur dan port | Ya | Tautan ke Master Node bila berizin |
| Kapasitas port | Ya, untuk kerja port | Ya, untuk baca |
| Aset infrastruktur | Ringkasan jumlah dan satuan per item | Rincian SN atau saldo item/lot di drawer |
| SN dan lot aset infrastruktur | Tidak tampil pada ringkasan | Tampil bila tersedia |
| Teknisi, waktu, nomor task | Tidak tampil pada ringkasan | Tampil bila event cocok |
| SN ONT pelanggan di port | Mengikuti permission perangkat pelanggan | Mengikuti permission yang sama |
| Riwayat Gudang | Lewat Peta | Tautan bagi pengguna berizin |

Contoh ringkasan: “2 unit Splitter 1:8”, “3 pcs Box ODP”, “25,5 m Kabel”. Hitung serial berstatus DEPLOYED per item. Untuk barang qty/roll, jumlahkan infrastructure_balances.qty yang positif per item dan satuan. Jumlah baris saldo/roll bukan jumlah unit fisik. Pertahankan angka port terpakai dan tersedia di Master Node.

## Ketepatan sumber data

Untuk serial, Peta mengambil transaksi DEPLOY terakhir menurut ID per SN; jika tujuan transaksi tidak cocok dengan node saat ini, metadata dikosongkan. Untuk barang qty/roll, jumlah aktif berasal dari saldo, sedangkan event DEPLOY atau koreksi infra_record_correction terakhir hanya memasok metadata bila tersedia. Waktu event adalah waktu pencatatan transaksi, bukan bukti pasti waktu pemasangan fisik. Label yang tepat ialah “Nomor task” atau “Task FOP”, bukan “nomor tiket”. Jika metadata kosong, tampilkan “belum tercatat”; jangan menyimpulkan aset belum dipasang.

Node Peta dibatasi dengan akses POP sebelum aset dirakit. Parameter node_id baru wajib divalidasi **terhadap hasil yang sudah di-scope**, termasuk ketika pop_id dipakai. Node di luar scope dan ID yang tidak ada mendapat 404 yang sama; jangan tampilkan nama atau keberadaannya lewat pesan error.

## Rencana eksekusi lanjutan

1. Ubah ringkasan aset infrastruktur di daftar **dan detail** Master Node. Jangan hilangkan aksi port, kapasitas, atau SN ONT pelanggan yang sudah mengikuti permission. Tambahkan tautan ke Peta memakai tab=inventaris dan node_id node asal. Jangan sertakan filter pop_id yang dapat menyembunyikan node tujuan.
2. Terima node_id opsional berupa bilangan bulat di Peta. Pilih hanya network node yang ada dalam data Peta yang sudah dibatasi scope, buka tab Inventaris dan drawer node tersebut; selain itu balas 404. Pertahankan penyajian aset yang sudah ada. Tampilkan tautan riwayat Gudang hanya bila pengguna memiliki warehouse_traceability.view.
3. Perbarui NetworkNodeAssetViewTest untuk daftar dan detail: serial jamak, qty pecahan, beberapa lot, saldo nol, SN/lot infrastruktur tidak muncul pada ringkasan, kapasitas port tetap ada, dan POP lain tidak bocor. Tambah tes deep link Peta untuk node dalam scope, luar scope, tidak ada, serta tersaring pop_id. Verifikasi HTML/node awal, bukan hanya output service.
4. Saat implementasi disetujui, selaraskan bagian “Barang Terpasang di Node Ini” pada analisa-simplifikasi-master-node-dan-port-visual.md.

## Di luar rancangan

Tidak perlu migrasi atau perubahan sumber saldo/ledger untuk pembagian tampilan ini. Histori semua event per rentang waktu, rekonstruksi tiap unit qty/roll, penghapusan SN ONT pelanggan, dan pemindahan CRUD ke Peta memerlukan keputusan tersendiri. Bila tes menemukan kebocoran POP, perbaiki scope sebelum menambah tautan.

## Riwayat koreksi

Draf awal mengira rincian aset belum ada di Peta. Revisi berikutnya menganggap hanya daftar Master Node yang perlu diubah. Audit ini menemukan detail Master Node masih memuat SN/lot dan Peta belum mendukung deep link node. Rencana dibatasi pada ringkasan dua halaman Master Node, navigasi Peta, izin tautan Gudang, dan tes scope.
