# Rancangan: Modem Pelanggan di Panel Port ODP + Master Node Jadi Pohon

Status: **diimplementasikan pada 2026-10-14** (lihat ringkasan hasil di bawah).
Bagian rancangan awal tetap disimpan sebagai riwayat; kondisi aktual mengikuti
ringkasan implementasi dan audit terbaru.

Dua gap terpisah, independen satu sama lain — bisa dikerjakan di urutan
manapun.

## Hasil implementasi (2026-10-14)

- Master Node memakai satu himpunan node berscope untuk pohon, inspector, dan
  KPI. Paginasi 10 node dan mode All Cards yang menduplikasi data dihapus.
  Root dikelompokkan lewat Cabang hasil resolusi POP; root Pusat tetap muncul.
  Filter tipe di pohon meredupkan noncocok tanpa memutus hierarki, dan semua
  kelompok terbuka pada awal. Pilih node dan buka/tutup turunannya adalah
  dua tindakan terpisah.
- Ringkasan port menyebut pelanggan, node tujuan, atau label manual; tindakan
  port kosong diberi nama yang menjelaskan bahwa statusnya menjadi terpakai
  secara manual.
- Detail ODP dan drawer Peta menampilkan seluruh SN modem/ONT terpasang dari
  Gudang (kategori `modem_ont` atau SN legacy yang cocok), ditambah SN
  terstruktur di `customer_devices`, detail teknis, dan kolom lama jika berbeda. SN sama
  digabung dengan label asal data; perangkat nonmodem dan serial yang sudah
  tidak terpasang tidak ikut. Jika tidak ada SN, tampil keterangan
  "Modem belum tercatat".
- SN hanya dikirim/ditampilkan bila pengguna memiliki izin
  `customers.detail.devices.view_sensitive` dan POP pelanggan termasuk
  scope yang diizinkan. Tanpa itu, tampil keterangan akses dibatasi.
- Verifikasi: tes peta, assignment port, dan manajemen ODP 37 lulus (257
  assertion); Pint dan Vite build lulus. Uji browser langsung belum selesai
  karena server lokal mengalihkan HTTP ke HTTPS pada port 8000 yang hanya
  melayani HTTP. Tidak ada migrasi database.

## Keputusan implementasi (2026-10-14)

1. **Skala dan paginasi:** database Docker lokal yang sedang berjalan berisi
   13 node: Jetis 9, Sandya 1, dan WHUSNET/Pusat 3. Untuk skala ini, pohon
   menampilkan semua node tanpa paginasi per node. Tiga node Pusat harus
   punya kelompok sendiri, jangan hilang dari pengelompokan Cabang. Angka
   ini bukan verifikasi database produksi; ukur ulang jika target implementasi
   memakai database lain atau volumenya bertambah besar.
2. **Filter tipe:** node yang tidak cocok diredupkan; node serta leluhurnya
   tetap ada supaya jalur hierarki tidak terputus. Hasil yang cocok harus
   mudah ditemukan, bukan sekadar perubahan warna yang nyaris tak terlihat.
3. **Keadaan awal pohon:** semua kelompok dan node terbuka. Pengguna dapat
   menutupnya sendiri lewat kontrol expand/collapse. Evaluasi ulang jika
   volume data menjadi jauh lebih besar.

**Koreksi Gap 1:** `Customer::inventorySerials()` adalah data penelusuran
inventaris, bukan sumber utama perangkat yang dipasang; model menyebut
`customerTechnicalDetail` sebagai sumber data teknis terpasang. Status
`INSTALLED` saja juga tidak membuktikan suatu serial adalah modem karena
inventaris serial mencakup jenis perangkat lain. Sebelum implementasi,
tentukan kriteria barang modem/ONT dan cara menangani perbedaan antara SN
inventaris dan SN detail teknis. Tampilan harus menamai sumber data secara
jujur. Pelanggan dengan lebih dari satu modem terpasang harus menampilkan
semua modem; contoh `firstWhere()` dan payload tunggal
`customer_modem_sn`/`customer_modem_name` di bawah adalah **usulan lama yang
tidak boleh diikuti**. Gunakan daftar modem/SN.

**Koreksi Gap 2:** kode halaman saat audit sudah mempunyai `Topology
Explorer` (pohon + inspector) dan `All Cards View`; jadi langkah "ganti
tabel flat menjadi pohon" di bawah **bukan lagi pekerjaan yang tepat**.
Masalah utama sekarang ialah konsistensi sumber data, cara menemukan node,
dan hierarki informasi. Lihat audit setelah bagian Gap 2 sebelum membuat
plan eksekusi.

## Gap 1 — Modem pelanggan belum kelihatan di panel port ODP

### Kondisi sekarang

Data modem **sudah ada** di sistem, cuma belum ditampilkan di titik yang
paling masuk akal buat tim teknis:

- `resources/views/network/nodes/show.blade.php:171-182` — kolom "Penghuni"
  tiap port cuma nampilin `$assignment->customer->full_name`. Gak ada SN
  modem, model, atau status-nya.
- `resources/views/network/map/index.blade.php` (drawer detail, bagian
  "Port Color Legend" & "Selected Port Detail Card", ~baris 858-884) — sama,
  cuma `customer_name`/`customer_cid`, gak ada info modem.
- Data modem sendiri sebenarnya satu query away: `Customer::inventorySerials()`
  (HasMany, `app/Models/Customer.php:450`) — SN yang `status=INSTALLED` milik
  pelanggan itu, relasi `item` buat nama/model barang.

### Kenapa ini berguna (alasan user)

Tim teknis lagi liat ODP → langsung tau: port ini kepake pelanggan siapa,
DAN modemnya tipe apa/SN berapa — gak perlu loncat ke halaman Detail
Pelanggan buat ngecek device. Mempercepat analisa gangguan/survey.

### Desain

**Satu-satunya data baru yang perlu di-load:** SN modem pelanggan yang
`status=INSTALLED`, lewat relasi yang sudah ada (`Customer::inventorySerials`).
Tidak ada skema baru, tidak ada migrasi.

Tempat yang disentuh:

1. **`app/Http/Controllers/Network/NetworkNodeController.php::show()`**
   — tambah eager-load `ports.activeAssignment.customer.inventorySerials.item`
   (hati-hati: `inventorySerials` HasMany bisa lebih dari satu baris kalau
   pelanggan punya C-REQ "Tambah Modem" — filter ke `status=INSTALLED`
   lewat closure, bukan ambil semua).
2. **`resources/views/network/nodes/show.blade.php`** — di baris port yang
   `$assignment?->customer`, tambah baris kedua kecil di bawah nama
   pelanggan: SN + nama barang (`$assignment->customer->inventorySerials
   ->firstWhere('status', 'installed')?->item?->name`), kalau kosong tampil
   "Modem belum tercatat" (bukan disembunyikan — biar ketauan kalau emang
   ada gap data, bukan dikira sengaja kosong).
3. **`app/Services/InfrastructureMapService.php::build()`** — port data
   array yang dikirim ke Alpine (`customer_name`/`customer_cid`) tambah 2
   field baru: `customer_modem_sn`, `customer_modem_name`. Sumbernya sama,
   eager-load `ports.activeAssignment.customer.inventorySerials.item` di
   query `$nodes` (`$nodes = NetworkNode::query()->...->with([...])`).
4. **`resources/views/network/map/index.blade.php`** — di "SELECTED PORT
   DETAIL CARD" (~baris 858-884), di bawah baris `customer_cid`, tambah
   `<template x-if="currentSelectedPortData.customer_modem_sn">` nampilin
   SN + nama modem.

### Batas yang disengaja (jangan diubah tanpa alasan baru)

- **Modem TETAP bukan node.** Ini bukan "masukin modem ke pohon
  `network_nodes`" — itu sudah diputuskan salah di diskusi sebelumnya
  (siklus hidup beda, bakal bengkakin pohon ribuan baris). Ini cuma
  nampilin data yang SUDAH ada, di tempat yang lebih kelihatan.
- Kalau pelanggan punya >1 SN `INSTALLED` (kasus "Tambah Modem" C-REQ),
  tampilkan SEMUA, jangan cuma yang pertama — satu baris per SN, biar gak
  ada modem kedua yang "hilang" dari pandangan teknisi.
- Jangan tarik data ini kalau port-nya BUKAN tipe `odp` (port OLT/ODC/switch
  gak punya pelanggan langsung — assignment ke situ selalu `target_node`
  atau `manual_label`, cek `$assignment?->customer` null-safe sudah cukup,
  gak perlu guard tipe tambahan).

### Test yang perlu ditambah

- `NetworkNodeController::show()` nampilin SN modem di port ODP yang
  pelanggannya punya modem `INSTALLED`.
- Port ODP terpakai tapi pelanggannya BELUM punya SN `INSTALLED` (data
  gak lengkap) → tampil "Modem belum tercatat", bukan error/kosong diam.
- Peta Infrastruktur: port detail card di drawer nampilin SN modem yang sama.
- Pelanggan dengan 2 SN `INSTALLED` (Tambah Modem) → keduanya tampil.

---

## Gap 2 — Master Node: rancangan awal yang perlu direvisi

### Kondisi sekarang

`app/Http/Controllers/Network/NetworkNodeController.php::index()` punya
DUA set data node yang beda tujuan:

- `$nodes` (baris 39-48): query **flat**, `orderBy('code')->paginate(10)`.
  Ini yang ditampilin sebagai tabel utama di halaman — makanya OLT, ODC,
  ODP, switch semua ketemu campur sesuai urutan alfabet kode, bukan posisi
  di pohon.
- `$parentOptions` + `$treeChildren` (baris 51-56): **SUDAH** dihitung
  bentuk pohonnya (`groupBy(parent_id)`) + `$breadcrumbLabels` (lokasi
  lengkap tiap node) — tapi cuma dipakai buat ngisi dropdown "pilih parent"
  di form create/move, BUKAN buat tampilan listing utama.

Jadi bahan buat bikin tampilan pohon **sudah ada di backend**, cuma belum
dipakein ke tampilan listing. Partial rekursif buat gambar pohon juga
sudah ada: `resources/views/network/map/_tree-node.blade.php` (dipakai tab
"Per POP (Pohon)" di Peta Infrastruktur) — tapi partial itu nempel ke
Alpine state punya halaman Peta (`visibleNode()`, `selectedNodeId` dari
`x-data` root `network/map/index.blade.php`), jadi **tidak bisa dipakai
langsung** di halaman Master Node tanpa adaptasi (beda `x-data` scope).

### Desain

**Ganti tabel flat di `network/nodes/index.blade.php` jadi tampilan pohon**,
dikelompokkan per Cabang (sama seperti Master POP/Cabang), pakai data yang
sudah dihitung controller (`$parentOptions`/`$treeChildren`/`$breadcrumbLabels`)
— tidak perlu query baru.

1. **Controller** (`NetworkNodeController::index()`): HAPUS `$nodes` (query
   flat + paginate) — sudah gak perlu kalau listing utamanya jadi pohon.
   `$parentOptions` jalan terus dipakai (sekarang jadi sumber TAMPILAN
   utama, bukan cuma buat dropdown). Filter `node_type_id` yang sekarang
   nempel di `$nodes` (baris 45) perlu dipindah jadi filter di level
   tampilan (highlight/dim node yang gak match tipe, bukan ngilangin dari
   query — kalau difilter dari query, pohonnya jadi bolong/putus di tengah
   kalau parent-nya gak match filter tapi anaknya match).
2. **Partial pohon baru, KHUSUS Master Node** — buat
   `resources/views/network/nodes/_tree-node.blade.php` (nama beda dari
   punya Peta, biar gak ketuker), adaptasi dari `network/map/_tree-node.blade.php`
   TAPI:
   - Ganti `selectNode(...)`/`visibleNode(...)` Alpine → jadi link biasa
     `<a href="{{ route('network.nodes.show', $treeNode) }}">` (Master Node
     gak butuh kanvas/drawer, klik node = pindah ke halaman detailnya,
     pola PRG yang sudah dipakai di tempat lain).
   - `<details open>` tetap dipakai buat expand/collapse per cabang (native
     HTML, gak butuh Alpine sama sekali untuk Gap 2 ini — lebih enteng).
3. **Pengelompokan per Cabang**: bungkus daftar pohon dengan loop per
   Cabang (sama konsepnya kayak `rootsByPop` di `InfrastructureMapService`),
   biar strukturnya kebaca sama kayak Master POP/Cabang: Cabang → (Mini POP
   kalau ada) → node root → turunannya.
4. **Paginasi**: pohon gak bisa di-paginate per-baris kayak tabel flat
   (bisa mutilasi cabang pohon di tengah halaman). Ganti jadi: paginasi
   per CABANG (misal 5 cabang per halaman), bukan per node — atau, kalau
   jumlah total node di scope user wajar (puluhan-ratusan, bukan ribuan),
   tampil semua tanpa paginasi dan andelin `<details>` collapse buat
   ringkesin tampilan. Keputusan ini butuh cek jumlah node riil di data
   produksi dulu sebelum milih (lihat "Perlu Dikonfirmasi" di bawah).

### Yang TETAP ada, jangan dihapus

- Form "Tambah Node" + dropdown parent (pakai `$parentOptions`/
  `$breadcrumbLabels` yang sudah ada) — cuma pindah posisi/tampilan, logic
  validasi (`assertPlacement`, `assertTypePlacement`) gak disentuh.
- Statistik ringkasan atas (`$totalNodesCount`, `$totalPortsCount`, dst) —
  tetap dihitung dari `$parentOptions`, cuma sekarang itu juga satu-satunya
  sumber listing (dulu dihitung terpisah dari `$nodes`).

### Keputusan yang sudah tercatat

Lihat bagian "Keputusan implementasi" dan "Audit penyajian
Master Node Jaringan & Port" di dokumen ini. Pertanyaan paginasi, filter tipe,
dan keadaan awal pohon sudah dijawab. Langkah implementasi lama di bagian
ini jangan langsung dieksekusi karena halaman saat ini sudah memiliki pohon.

### Test yang perlu ditambah/diubah

- Listing Master Node nampilin struktur pohon (parent→child keliatan
  nested), bukan flat urutan kode.
- Node tanpa parent (root, anchor langsung ke POP) tetap nongol sebagai
  root pohon di Cabang yang benar.
- Filter tipe node (sesuai keputusan poin 2 di atas) teruji perilakunya.
- Scope POP tetap ditegakkan — user `SELECTED_POP` cuma liat Cabang-nya
  sendiri di pohon (regresi dari behavior `$parentOptions` yang sudah ada,
  jangan sampai pohon malah bocorin node cabang lain).


---

## Audit penyajian Master Node Jaringan & Port (2026-10-14)

Audit ini membaca kode halaman saat ini; belum ada perubahan UI. Rancangan
awal Gap 2 di atas perlu disusun ulang sebelum implementasi karena halaman
sudah memakai pohon dan inspector.

### Temuan yang dapat dibuktikan dari kode

1. **Pohon lengkap, inspector tidak lengkap (prioritas kritis).**
   `NetworkNodeController::index()` memuat seluruh node dalam scope ke
   `$parentOptions`/`$treeChildren`, tetapi `$nodes` untuk inspector dan
   All Cards masih `paginate(10)`. Pohon kiri merender semua node, sedangkan
   inspector kanan hanya merender node halaman aktif. Dengan 13 node pada
   database Docker lokal, memilih salah satu dari tiga node di luar halaman
   pertama dapat menghasilkan panel kanan kosong. Paginasi hanya tampak
   pada mode All Cards, bukan Topology Explorer. KPI menghitung semua node,
   sehingga angka dan daftar detail yang dapat dibuka juga tidak sejalan.
2. **Kelompok pohon memakai anchor POP langsung, bukan Cabang.**
   `$treeChildren->get(0)->groupBy('anchor_pop_id')` memecah root menurut
   anchor. Bila Cabang dan Mini POP sama-sama punya root, keduanya tampil
   sebagai kelompok terpisah; hubungan Pusat → Cabang → Mini POP tidak
   tersaji secara utuh. Root WHUSNET/Pusat perlu kelompok tersendiri.
   Pengelompokan baru harus tetap memakai node dalam scope user agar tidak
   mengungkap node atau nama wilayah yang tak berhak diakses.
3. **Dua mode mengulang informasi tanpa tujuan yang jelas.** Topology
   Explorer sudah memuat pohon, inspector, port, aset, lokasi, turunan,
   dan relokasi. All Cards mengulang identitas, port, dan aset yang sama
   dengan urutan datar per kode. Pergantian mode mengubah bentuk dan
   jangkauan navigasi; pengguna harus memahami dua cara kerja untuk satu
   daftar. Tentukan satu tampilan utama untuk menemukan node, lalu detail
   node untuk pekerjaan operasional; pertahankan mode kedua hanya bila ada
   kebutuhan audit massal yang nyata.
4. **Port ditampilkan sebagai tombol status, bukan koneksi.** Di matriks
   port, label `Port # · Terpakai/Kosong` tidak menunjukkan tujuan
   `targetNode`, pelanggan, atau label manual. Untuk melihat penghuni
   port terpakai, pengguna harus membuka halaman detail. Port kosong
   langsung mengirim perubahan menjadi `Terpakai (belum diverifikasi)`
   ketika diklik; petunjuk "toggle status koneksi" tidak menjelaskan
   akibat ini. Ini sangat mungkin sumber rasa "aneh": halaman bernama
   jaringan dan port, tetapi jalur fisik dan alasan status tersembunyi
   sementara aksi ubah status terlalu dekat dengan aksi lihat.
5. **Filter tipe dalam rencana awal belum ada di UI saat audit.**
   Controller menerima `node_type_id` untuk query `$nodes`, tetapi
   halaman tidak menyediakan kontrol filter tipe. Jika parameter query
   dikirim manual, inspector/cards terfilter sedangkan pohon tetap
   menampilkan semua node tanpa penanda kecocokan. Ini perlu disatukan
   dengan keputusan filter redupkan node yang tidak cocok.
6. **Istilah dan metrik kurang presisi.** KPI "Kapasitas Port Fisik"
   dijelaskan sebagai "Total slot splitter terpasang", padahal hitungan
   menjumlahkan port semua tipe node. Judul "PORT SPLITTER / PATCH PANEL
   MATRIX" juga dipakai untuk setiap tipe yang memiliki port. Status
   `Terpakai` menandai pemakaian port, belum tentu koneksi terverifikasi.
   Label perlu mengikuti tipe node dan jenis assignment yang nyata.

### Arah perbaikan sebelum membuat task eksekusi

- Pakai satu himpunan node berscope untuk pohon, pemilihan node, inspector,
  dan KPI. Pada skala saat ini, hilangkan paginasi per node dari tampilan
  utama; pastikan setiap node yang dapat dipilih punya panel detail atau
  menuju halaman detail yang tersedia.
- Pertahankan pohon sebagai navigasi lokasi. Kelompokkan menurut Pusat,
  Cabang, dan Mini POP yang relevan, lalu tampilkan node root dan turunannya.
  Jangan menjadikan POP sebagai `network_node` fiktif.
- Di ringkasan node, tampilkan informasi berurutan: identitas dan jalur,
  kapasitas/status, tujuan tiap port, baru aset dan lokasi. Letakkan aksi
  ubah status, koneksi, relokasi, dan edit sebagai kontrol yang jelas
  terpisah dari aksi inspeksi.
- Tampilkan keadaan port yang berbeda dengan jelas: kosong, menuju node,
  pelanggan, manual/belum terverifikasi. Untuk ODP, tampilkan informasi
  modem pelanggan sesuai keputusan Gap 1 setelah aturan sumber data jelas.
- Saat filter tipe aktif, penanda kecocokan pada pohon dan inspector
  harus konsisten; leluhur tetap terlihat. Uji peran terbatas POP,
  node ke-11 dan seterusnya, node ber-anchor Mini POP, serta root Pusat.

Audit ini mendasari implementasi yang dirangkum di awal dokumen. Temuan
historis di atas dipertahankan untuk menjelaskan alasan perubahan.
