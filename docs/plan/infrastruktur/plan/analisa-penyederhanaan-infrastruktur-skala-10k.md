# Analisa: Penyederhanaan Penyusunan, Laporan, dan Barang Terpasang Infrastruktur (Skala 10k Node)

Tanggal: 17 November 2026
Status: **analisa — belum diimplementasikan**, kecuali dua bug di §3 yang sudah diperbaiki di ADHOC-302. Tiap fase di §6 jadi task terpisah setelah dipilih user (sprint aktif masih ADHOC-202).

Baca dulu: `diagnosa-awal-temuan-infrastruktur.md` (folder yang sama) untuk kondisi sistem sekarang dan akar masalahnya.

## 1. Latar belakang

Permintaan user (2026-11-17): cari cara supaya tiga fitur ini tidak ribet dan tidak susah dipakai:

1. **Penyusunan Infrastruktur** — membangun hierarki POP → Mini POP → OLT/Switch → Slot → PON/Port → ODP.
2. **Laporan Infrastruktur** — laporan teknisi pada Task INFR REQ / O-REQ (pasang, ganti, cabut, sambung port).
3. **Pencatatan Barang Gudang yang terpasang di Infrastruktur** — SN, qty, dan roll kabel yang menempel di node.

Syaratnya: solusi harus tetap cepat **dan** mudah dipakai saat data sudah besar, contohnya ~10.000 node (≈80–100 ribu port, ≈50 ribu baris barang terpasang).

Fondasi fiturnya sudah lengkap: Master Node, Generate Slot & PON, laporan INFR REQ dengan DEPLOY, Registrasi Aset lama per node, dan halaman Barang Terpasang. Masalahnya ada di dua hal:

- **Ribet (UX):** terlalu banyak langkah manual per titik.
- **Berat (skala):** banyak halaman memuat **seluruh** node dalam scope ke memori, jadi makin lambat seiring bertambahnya data.

## 2. Analisa awal (ringkasan putaran pertama)

Pilihan cara lain yang diusulkan sebelum penggalian kode, diurutkan dari dampak terbesar:

| # | Cara | Yang dipermudah | Effort |
|---|---|---|---|
| 1 | Import Excel struktur per cabang (preview + dry-run) | Penyusunan awal dan data lama | Sedang |
| 2 | Opname infrastruktur per POP | Pencatatan barang lama, audit berkala | Sedang |
| 3 | QR/label di ODP/OLT, scan lalu lapor | Laporan: node tujuan otomatis benar | Sedang–besar |
| 4 | Status node `rencana` → `aktif` lewat DEPLOY | Peta jujur, commissioning menyatu | Kecil–sedang |
| 5 | Paket barang standar (BOM) | Input barang di laporan | Sedang |
| 6 | Rekap laporan bulanan per POP + export | Laporan ke atasan | Kecil |

Penggalian di bawah ini memperluas daftar itu dan menambahkan fondasi skala yang dibutuhkan supaya semuanya tetap cepat di 10k node.

## 3. Temuan per fitur (hasil telusur kode)

### A. Penyusunan Infrastruktur

**Ribet:**
- Node dibuat satu per satu lewat form. Tidak ada import massal node (`NetworkNodeController::store`, `NetworkNodeController.php:334-436`). Kode ODP diketik manual.
- Langkah menambah satu ODP: buat OLT → Generate Slot & PON → buka PON → isi form Tambah ODP (tipe, PON induk, kode, nama, kapasitas, lokasi) → submit → sambung port satu per satu.
- Status node hanya `active`/`inactive`, dan `store` menulis `active` secara hardcode (:416). Belum ada status rencana/commissioning, jadi node langsung tampak aktif walau fisiknya belum terpasang.

**Berat:**
- Port di-INSERT satu baris per query di dalam loop (`NetworkNodeController.php:420-422`; `InfrastructurePositionService::generateStructure` :218-250).
- **Master Node index** memuat semua node non-posisi tanpa paginasi, eager load `ports.activeAssignment.customer`, `deployedSerials`, `infrastructureBalances` (:64-75), lalu memanggil `InfrastructureHierarchyService::build()` lagi (:175).
- **Peta** `InfrastructureMapService::build()` memuat semua node dalam scope **dua kali** (:32-337 dan :269-273). Endpoint lazy `children`/`childrenBatch` memicu full `build()` saat cache 5 menit habis.
- `NetworkNode::scopeInPops` (:457-485) mengembalikan **semua ID** scope lewat recursive CTE hanya untuk mengecek satu node, dan ini dijalankan di tiap request panel/children untuk user non-admin.
- `subtreeNodeIds` berkompleksitas O(N²) karena `in_array` di atas daftar yang terus tumbuh (`InfrastructureMapService.php:867`).
- Tidak ada kolom `path`/`depth`. Breadcrumb dan leluhur dihitung ulang tiap request (`breadcrumbLabel`, `resolvePop`, `isSelfOrDescendant` = satu query per level).
- Index yang kurang: `network_node_ports(network_node_id, is_used)` dan `network_nodes.status`.

### B. Laporan Infrastruktur

**Ribet:**
- Lokasi (cascade 4–6 dropdown POP → Mini POP → OLT → Slot → PON → ODP) harus diisi ulang **di setiap baris aksi**. Tidak ada "sama dengan baris sebelumnya".
- Contoh pasang ODP baru + splitter + kabel + sambung port ≈ **39 input**, 4 klik tambah aksi, dan ±20 fetch dropdown.
- Cascade berhenti di ODP, tidak ada cari-ketik, dan node baru tidak bisa dibuat atau dijangkau dari laporan. Akibatnya teknisi terdorong ke mode lama "Titik jaringan lama".
- Ada dua jalur paralel: aksi vs koreksi, dan mode Hierarki vs mode lama.
- Koordinat diketik manual (belum ada tombol GPS), tiap baris wajib diisi "Hasil tindakan", dan belum ada QR untuk mengenali node.
- **Bug (sudah diperbaiki, ADHOC-302):** `port_connect` di mode Hierarki. `targetNodes()` (`resources/js/task-infrastructure-report.js`) dulu memfilter `row.network_node_id`, padahal kolom itu kosong di mode Hierarki, sehingga daftar "Node tujuan" selalu kosong. Sekarang memakai `portNodeId(row)`. Dijaga test JS `tests/task-infrastructure-report.test.mjs` dan test server `TaskInfrastructureHierarchicalLocationTest::sambung_port_mode_hierarki_*`.

**Berat:**
- `TaskMaintenanceController::report` menanam JSON berisi semua node Cabang (:453), semua port node tersebut (:468), dan semua transaksi DEPLOY tanpa batas tanggal (:456) ke halaman.
- `NetworkNode::breadcrumbLabel()` (:494-531) memanggil `self::find()` untuk tiap leluhur yang belum dimuat, ±2–3 query per node. Di 10k node itu berarti puluhan ribu query.
- Daftar SN yang bisa dicabut di mode lama dihitung atas seluruh Cabang (:355).
- `assetsAt` menjalankan recursive query atas seluruh tabel node tiap kali lokasi dipilih (`TaskInfrastructureActionService.php:168`).
- Belum ada rekap laporan infrastruktur per POP/bulan. `WarehouseUsageController` (Rekap Pemakaian Material) memfilter dan memaginasi di memori PHP.

### C. Pencatatan Barang Terpasang

**Ribet:**
- Registrasi barang lama hanya bisa **satu SN per submit**, di level node saja (`InventoryService::registerExistingInfraSerial`, :747-795). **Barang qty/roll lama belum punya jalur sama sekali.** Tidak ada batch atau import.
- Tidak ada opname untuk barang terpasang. `recordSerialStockOpname()` hanya untuk stok gudang. Tabel `infrastructure_reconciliation_runs` dan `infrastructure_legacy_mappings` sudah ada tapi belum dipakai kode mana pun.
- `/warehouse/infra-stock` tidak punya filter atau pencarian (SN, barang, node, Cabang).
- Tidak ada export Excel/CSV untuk kondisi terpasang saat ini dari sisi gudang.

**Berat / data:**
- **Bug (sudah diperbaiki, ADHOC-302):** tab Roll dulu menjumlah transaksi DEPLOY saja dan mengabaikan RETRIEVE_INFRA serta koreksi, jadi yang tampil "meter yang pernah dipasang". Sekarang `WarehouseInfraStockController::paginateRoll()` menghitung saldo bersih (+DEPLOY, ±koreksi, −cabut) per barang + lokasi. Dijaga test `InfraStockRollShowsCurrentlyInstalledNotEverDeployedTest`. **Sisa masalah:** query masih menjumlah seluruh ledger kabel tiap halaman (berat di 10k node), dan kabel tetap tanpa saldo per lokasi (§16.4 #2). Keduanya masuk B1.
- Scope tab SN memakai OR 6 kolom + `whereIn` ribuan ID node, sehingga optimizer cenderung full scan. Tab Qty menjalankan satu query per baris.
- Tab "Barang Terpasang" di Peta dan export CSV Peta memanggil `build()` untuk semua node, lalu memaginasi dengan `array_slice` di PHP (`InfrastructureMapController.php:342-419` dan :21-75). Ini berisiko kehabisan memori.
- `infrastructure_balances` tidak punya unique key, jadi baris dobel bisa muncul saat ada transaksi bersamaan.

## 4. Prinsip desain untuk skala 10k

Aturan yang berlaku untuk semua solusi di §5:

1. **Tidak ada request yang memuat seluruh node dalam scope.** Biaya per request = O(ukuran halaman), bukan O(jumlah node).
2. Daftar besar ditampilkan lewat **cari-ketik di server (limit 20)** atau **paginasi di database**, bukan dropdown penuh atau JSON yang ditanam di halaman.
3. Pertanyaan leluhur/subtree dijawab dengan **satu query ber-index**, bukan rekursi per request.
4. Pekerjaan berat (import, export, rekap) dijalankan sebagai **job antrian Horizon**, hasilnya disimpan di disk privat, lalu user diberi notifikasi in-app.
5. Satu sumber kebenaran untuk "terpasang sekarang": `inventory_serials` (SN) + `infrastructure_balances` (qty/roll). Ledger = riwayat, bukan saldo.

## 5. Solusi

### F. Fondasi — wajib duluan, menopang ketiga fitur

- **F1. Materialized path.** Tambah kolom `network_nodes.path` (mis. `/12/45/301/`), `depth`, dan `cabang_id` (Cabang efektif, didenormalisasi).
  - Subtree = `path LIKE '/12/45/%'` (ber-index).
  - Scope = `cabang_id IN (...)` / Mini POP.
  - Breadcrumb = satu query `whereIn` atas ID yang ada di `path`.
  - Dirawat di satu tempat: `NetworkNodeService::move` + observer saat create/ganti induk + `rederiveCodes`. Disertai command backfill dan test konsistensi.
- **F2. `path_label`** — teks breadcrumb yang disimpan, untuk tampilan dan pencarian.
- **F3. Komponen "Cari Titik" universal.** Combobox Alpine + endpoint pencarian kode/nama/`path_label`, limit 20, ter-scope POP, dan bisa dibatasi ke subtree tertentu. Dipakai di Master Node, laporan, scope Task FOP, dan filter barang terpasang. Cascade tetap tersedia sebagai mode jelajah.
- **F4. Index:** `network_node_ports(network_node_id, is_used)`, `network_nodes(status)`, `network_nodes(cabang_id, node_type_id)`, `network_nodes(path)`. Unique key `infrastructure_balances(item_id, lot_no, network_node_id, …)` dipasang setelah data dobel dibersihkan.
- **F5.** Ganti `scopeInPops`, `scopedNodeIds`, dan `subtreeNodeIds` supaya memakai F1.

### P. Penyusunan Infrastruktur

- **P1. Import Excel struktur per Cabang** (`spatie/simple-excel`, sudah terpasang).
  - Template: tipe, kode, kode induk, nama, kapasitas/jumlah port, lat/lng, alamat, (opsional) SN/qty terpasang.
  - Alur: upload → validasi per baris + preview diff (baru / berubah / error) → konfirmasi → job antrian. Upsert berdasarkan `code` (aman diulang), port di-insert massal per batch. File error bisa diunduh.
  - Halaman create tersendiri (pola #2 di CLAUDE.md, karena ini mutasi data).
- **P2. Tambah ODP banyak** di panel PON. Grid N baris dengan kode saran otomatis (`{kode PON}-ODP{n}`), kapasitas default, dan koordinat, lalu satu kali submit.
- **P3. Status `rencana` → `aktif`.** FOP membuat node berstatus rencana saat menyusun INFR REQ. DEPLOY pertama di laporan otomatis mengaktifkan node. Peta diberi filter/warna khusus untuk node rencana.
- **P4. Master Node ringan.** Tampilkan pohon level atas saja + lazy children (sudah ada) + kotak Cari Titik (F3) yang langsung membuka jalur ke node lewat `path`. Hapus eager load semua port/pelanggan di index.

### L. Laporan Infrastruktur

- **L1. Lokasi kerja diisi sekali di header laporan**, default dari scope root FopTask. Baris aksi mewarisi lokasi itu dan hanya di-override kalau memang berbeda.
- **L2. Pemilih lokasi = Cari Titik (F3)**, dibatasi ke subtree task, bisa menjangkau kedalaman berapa pun.
- **L3. Hapus JSON yang ditanam** (nodes/ports/sources). Port, SN terpasang, dan sumber DEPLOY dimuat **per lokasi yang dipilih** lewat endpoint (pola `location-assets` sudah ada). Sumber DEPLOY dibatasi lokasi + rentang tanggal.
- **L4. Paket aksi (template/BOM).** Contoh: "Pasang ODP baru" otomatis membuat baris box ODP + splitter + kabel dengan barang sudah terisi. Teknisi tinggal mengoreksi SN/qty.
- **L5. Tombol GPS** (`navigator.geolocation`) untuk koordinat. Catatan hasil cukup satu per laporan, catatan per baris jadi opsional.
- **L6. ~~Perbaiki bug `port_connect`~~ di mode Hierarki** — **selesai di ADHOC-302.**
- **L7. Rekap Laporan Infrastruktur** per Cabang/bulan. Agregasi SQL: jumlah task, node baru/aktif, barang dipasang/dicabut, PON Red Zone, ODP penuh. Paginasi di database, export lewat job (dipakai bersama B4).
- **L8. (Fase akhir) QR label node.** Token per node dengan pola `CustomerQrTokenService`, discan lewat `QrInAppScanController`, lalu laporan terbuka dengan lokasi sudah terisi.

### B. Pencatatan Barang Terpasang

- **B1. Satukan sumber data.** (Angka tab Roll sudah benar sejak ADHOC-302; yang tersisa adalah sumber dan beban query-nya.) Tab Roll dan Qty membaca `infrastructure_balances` (saldo saat ini). Pastikan `deployRoll`, retrieve, dan koreksi konsisten menulis balance. Tambah command rekonsiliasi balance vs ledger yang mengisi `infrastructure_reconciliation_runs`.
- **B2. Filter & cari di `/warehouse/infra-stock`:** Cabang, barang, SN/lot, lokasi (F3 → `path LIKE`). Query di database dengan paginasi keyset/simple.
- **B3. Tab Barang Terpasang di Peta** memakai query database terpaginasi lewat `path`, bukan `build()`.
- **B4. Export streaming lewat job** (cursor/lazy + SimpleExcel writer) untuk infra-stock, tab Barang Terpasang, dan CSV Peta.
- **B5. Registrasi massal barang lama** (SN + qty + roll) lewat import Excel yang sama dengan P1. Butuh method baru untuk registrasi qty/roll lama (ledger `REGISTER_INFRA`, tanpa Issue/DEPLOY palsu).
- **B6. Opname infrastruktur per lokasi/subtree.** Buka sesi opname → tampil daftar barang yang tercatat → teknisi centang ada/hilang/rusak dan menambah temuan → selisihnya jadi usulan adjustment yang harus di-approve (pola `InventoryAdjustmentService`). Temuan SN yang belum tercatat diproses sebagai registrasi barang lama.

## 6. Urutan fase (rekomendasi)

| Fase | Isi | Alasan |
|---|---|---|
| 0 | F1–F5, B1 (L6 sudah selesai di ADHOC-302) | Fondasi skala + satukan sumber saldo kabel/qty. Tanpa ini, fitur lain tetap lambat di 10k node. |
| 1 | L1, L2, L3, L5, B2, B3, P4 | Paling terasa di pekerjaan harian, effort sedang. |
| 2 | P1 + B5 (import bersama), P2, P3 | Mempercepat penyusunan dan mengisi data lama. |
| 3 | B6, L4, L7, B4 | Opname, paket aksi, rekap, export. |
| 4 | L8 | QR label, butuh cetak label fisik di lapangan. |

## 7. Target terukur di 10k node

Diuji dengan seeder dummy 10k node (pola `docs/plan/rancangan-index-performa-15k-seeder.md`):

- Buka Master Node / Peta / laporan / infra-stock: ≤ ±30 query, memori stabil (tidak naik seiring jumlah node), < 1 detik di server.
- Cari Titik: < 200 ms, maksimal 20 hasil.
- Import/export tidak berjalan di request web. Job selesai lalu user mendapat notifikasi.

## 8. Risiko & keputusan terbuka

- **Backfill `path`/`cabang_id`** harus konsisten. Kalau salah, data bocor lintas scope, jadi test scope wajib ada.
- **Unique key `infrastructure_balances`** butuh pembersihan data dobel lebih dulu. Ini migrasi berisiko data, jadi butuh konfirmasi user.
- **Status `rencana`** berdampak ke Peta, kapasitas PON, dan pemilih ODP pelanggan (node rencana tidak boleh dipilih untuk pelanggan).
- **Import upsert berdasarkan `code`:** perlu diputuskan apakah import boleh **mengubah** node lama atau hanya **menambah**.
- **Dua bug** (`port_connect` mode Hierarki, total tab Roll) **sudah dibuktikan dengan test dan diperbaiki di ADHOC-302.** Skenario ganti roll lewat `InfrastructureMaterialService::correctRoll` (roll pengganti) belum punya test sendiri.

## 9. Rujukan kode

- Penyusunan: `app/Http/Controllers/Network/NetworkNodeController.php`, `app/Services/NetworkNodeService.php`, `app/Services/InfrastructurePositionService.php`, `app/Services/InfrastructureHierarchyService.php`, `app/Models/NetworkNode.php` (`scopeInPops`, `breadcrumbLabel`, `resolvePop`).
- Peta: `app/Http/Controllers/Network/InfrastructureMapController.php`, `app/Services/InfrastructureMapService.php`.
- Laporan: `app/Http/Controllers/TaskMaintenanceController.php`, `app/Services/TaskInfrastructureActionService.php`, `app/Services/InfrastructureReportLocationParser.php`, `app/Services/InfrastructureLocationOptionsService.php`, `resources/js/task-infrastructure-report.js`, `resources/views/tasks/infrastructure-report.blade.php`.
- Barang terpasang: `app/Services/InventoryService.php`, `app/Services/InfrastructureMaterialService.php`, `app/Http/Controllers/Warehouse/WarehouseInfraStockController.php`, `app/Http/Controllers/Network/InfraAssetRegistrationController.php`, `app/Services/InventoryAdjustmentService.php`.
- Pola yang bisa dipakai ulang: `app/Services/CustomerQrTokenService.php`, `QrInAppScanController`, import pelanggan berbasis `spatie/simple-excel`.
- Dokumen terkait: `manual-book-penataan-infrastruktur.md`, `rancangan-tab-barang-terpasang.md`, `analisa-inventaris-infrastruktur.md`.
