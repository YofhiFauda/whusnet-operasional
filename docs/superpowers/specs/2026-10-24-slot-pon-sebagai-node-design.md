# Slot, PON, dan Port Switch Menjadi Node Jaringan

Tanggal: 2026-10-24. Status: diimplementasikan (ADHOC-213); lihat "Koreksi D1-D5" di akhir dokumen untuk penyimpangan dari teks di atas. Jalur: arsitektural.

## 1. Tujuan dan keputusan

Hierarki `Mini POP → OLT/Switch → Slot → PON/Port → ODP → ONT` dimodelkan sebagai
**satu pohon `network_nodes`**. Slot, PON, dan Port Switch menjadi node sungguhan
dengan `parent_id`, bukan baris di tabel terpisah.

Ini **membalik** keputusan `rancangan-hierarki-infrastruktur-server-pop-mini-pop.md`
§2 dan §17 yang menyatakan Slot/PON bukan baris `network_nodes`. Dokumen itu wajib
diperbarui bersamaan dengan implementasi.

Keputusan user (2026-10-24):

| # | Keputusan |
|---|---|
| 1 | OLT **dan** Switch sama-sama memakai model node (Slot → PON / Slot → Port Switch). |
| 2 | Data `infrastructure_slots`, `infrastructure_ports`, koneksi, dan ODP lama adalah **dummy, boleh di-reset**. Tidak ada migrasi pemetaan. |
| 3 | ODP **wajib** punya PON sebagai induk (`parent_id`). Tidak ada ODP tanpa PON. |
| 4 | Pendekatan A: satu tabel `network_nodes`, kolom khusus nullable. Bukan tabel detail 1:1. |
| 5 | Konsep "uplink ODP→PON terverifikasi" dibuang. Uplink = `parent_id`. |
| 6 | Tidak ada kartu OLT. Chassis/rack/kartu tetap aset Gudang yang dipasang ke Slot, bukan node. |

Alasan A, bukan B: B mempertahankan dua tabel per entitas yang harus dijaga
sinkron, yaitu dualisme yang ingin dihapus.

## 2. Skema

### 2.1 Tipe node (`network_node_types`)

| Code | Nama | Induk sah |
|---|---|---|
| `olt_chassis` (ada) | OLT | anchor Mini POP / Cabang |
| `switch_mini_pop` (ada) | Switch Mini POP | anchor Mini POP |
| `olt_slot` | Slot OLT | `olt_chassis` |
| `olt_pon` | PON | `olt_slot` |
| `switch_slot` | Slot Switch | `switch_mini_pop` |
| `switch_port` | Port Switch | `switch_slot` |
| `odp` (ada) | ODP | `olt_pon` |

`olt_chassis` hanya nama code historis, artinya OLT (tidak ada pembungkus kartu).
Tipe legacy `odc`, `pon_port`, `olt_card`, `aggregation_switch`, `core_router`
sudah dihapus dan **tidak** dihidupkan lagi.

### 2.2 Kolom baru `network_nodes` (semua nullable)

- `number` unsigned small integer
- `capacity_unit` string(30), `pelanggan_aktif` untuk PON
- `is_logical` boolean default false, untuk "Slot 1 internal" Switch port tetap
- `red_zone_threshold` unsigned tiny integer, per PON

`capacity` dan `status` yang sudah ada dipakai ulang. `status` memakai nilai
`InfrastructureRecordStatus` yang sudah ada.

### 2.3 Indeks

- `unique(parent_id, number)` memberi nomor unik per induk, bukan global.
- `network_nodes.code` tetap unique global. Kode Slot/PON/Port **diturunkan otomatis**
  dari jalur induk (`OLT-A-S3`, `OLT-A-S3-P5`) dan tidak bisa diketik bebas.

### 2.4 Aturan penempatan

- Slot/PON/Port tidak punya `anchor_pop_id`. POP diturunkan dari rantai induk lewat
  `NetworkNode::resolvePop()` seperti sekarang.
- Aturan induk-per-tipe ditegakkan di `NetworkNodeService`, dan dapat dikelola lewat
  Master Tipe Node (`_placement-rules`) dengan rule induk node.
- ODP yang lama (anchor POP) dibuang bersama reset dummy.

## 3. Yang dibuang

- Tabel `infrastructure_slots`, `infrastructure_ports`, `infrastructure_legacy_mappings`.
- Kolom `legacy_olt_slot_id`, `legacy_network_node_id`.
- Konsep `unverified` di `PonCapacityService::summary()`.
- Cabang `slot:` / `port:` di `InfrastructureHierarchyService::build()`.

Dipertahankan: `network_node_ports` (port fisik ODP yang diisi pelanggan; bukan PON).
`infrastructure_connections` dipertahankan hanya untuk sambungan non-pohon bila
masih dibutuhkan; ODP→PON tidak lagi memakainya.

## 4. Referensi lokasi

Sekitar 17 kolom FK ke slot atau port diarahkan ke `network_nodes.id`:

`inventory_serials`, `inventory_transactions`, `infrastructure_balances`,
`task_infrastructure_actions`, `fop_tasks`, `infrastructure_connections`.

Pasangan kolom "slot" dan "port" **dipertahankan** (YAGNI): hanya target FK yang
berubah, supaya logika "lokasi tepat" di service tidak ditulis ulang. Penyederhanaan
jadi satu kolom per grup bukan bagian pekerjaan ini.

## 5. Kapasitas PON

- `PonCapacityService` menerima node `olt_pon`.
- Pemakaian = pelanggan aktif pada ODP anak langsung PON (`parent_id`), tanpa join
  tabel koneksi. Satu pelanggan = satu unit, berapa pun ONT-nya.
- Kapasitas ODP (port pelanggan) tetap terpisah dan tidak dicampur.
- Red Zone hanya diklaim bila `capacity` dan `red_zone_threshold` sama-sama valid;
  `null` berarti belum bisa dinilai, bukan aman.

## 6. Generate massal

- Aksi di detail OLT: jumlah Slot, jumlah PON per Slot, kapasitas default.
- Aksi di detail Switch: jumlah port. Port tetap memakai satu Slot 1 logis otomatis.
- Satu transaksi. Aman dijalankan ulang: hanya menambah nomor yang belum ada,
  tidak menimpa nilai yang sudah diisi.
- Satu entri audit per aksi.
- Mutasi data, jadi **halaman create tersendiri** (pola 2 CLAUDE.md), bukan modal.

## 7. Pohon dan tampilan

- `InfrastructureHierarchyService::build()` hanya menyusun item `node:` dengan
  `parent_id`. Cabang slot/port/koneksi dihapus.
- Tingkat Slot ke bawah dimuat **lazy**: endpoint JSON mengembalikan anak langsung satu
  node, scope POP tetap dicek lewat `EffectiveAccessService`. Tingkat atas (POP,
  Mini POP, OLT/Switch) dimuat seperti sekarang.
- View `_slot-inspector`, `_pon-inspector`, `_device-slots`, `positions/slot`,
  `positions/port` disesuaikan ke `NetworkNode`; tidak dibuat ulang. (Belakangan
  `_slot-inspector`/`_pon-inspector`/`_device-slots` dihapus, diganti panel per level —
  lihat §12.)
- Target aksi POST/PUT/DELETE tetap dirender server-side.

## 8. Skala

Perkiraan 10 Cabang × 3 Mini POP × 2 OLT, 16 slot, 8 PON per slot: sekitar 60 OLT,
960 Slot, 7.700 PON, sekitar 10 ribu baris. Aman dengan indeks `parent_id` dan
`(parent_id, number)`. `scopeInPops` sudah satu recursive CTE.

## 9. Pengujian

PHPUnit, hanya file terdampak:

- Penempatan: induk salah ditolak, nomor ganda per induk ditolak, kode otomatis
  tidak bentrok antar Cabang.
- Generate massal: jumlah node benar, idempotent, satu entri audit.
- Kapasitas PON: hitung pelanggan aktif lewat ODP anak.
- Scope POP: user cabang A tidak melihat PON cabang B, termasuk endpoint lazy load.
- Perpindahan OLT memindahkan seluruh subtree.
- Tes lama yang memakai `InfrastructureSlot`/`InfrastructurePort` diubah ke node;
  tidak ada tes yang dihapus tanpa persetujuan.

## 10. Risiko dan hal yang perlu persetujuan eksplisit

- **Migrasi destruktif** (drop tabel, hapus ODP dummy). Hanya jalan setelah user
  mengonfirmasi database target adalah dummy. Tidak boleh jalan otomatis di data nyata.
- Sekitar 50 file tersentuh (service, model, controller, view, tes).
- Mengubah `rancangan-hierarki-infrastruktur-server-pop-mini-pop.md` §2, §3, §17 dan
  `docs/database-schema.md`.
- Hilangnya konsep uplink terverifikasi mengubah perilaku ringkasan kapasitas.

## 11. Di luar cakupan

Peta (selain penyesuaian sumber data), pelaporan INFR-REQ/O-REQ selain FK baru,
penyederhanaan pasangan kolom slot/port, dan perubahan alur Gudang.

## 12. Koreksi D1-D5 (ditemukan saat implementasi, disetujui user)

Lima penyimpangan dari teks di atas. Bagian lain spec tetap berlaku. Jika ada
pertentangan, bagian ini yang menang.

| # | Temuan | Keputusan yang berlaku | Mengoreksi |
|---|---|---|---|
| D1 | `network_nodes.status` sudah berarti `active`/`inactive` (siklus hidup node). Spec §2.2 menyuruh memakai nilai `InfrastructureRecordStatus` (`belum_didata`/`terverifikasi`/`rusak_nonaktif`) di kolom yang sama: dua makna dalam satu kolom. | Kolom baru `record_status` (nullable string 30). `status` tetap `active`/`inactive`. `NULL` pada `record_status` dibaca sebagai `belum_didata`. | §2.2 |
| D2 | `InfrastructureDummyResetService` mencatat database saat ini **bukan dummy murni** (ODP-JETIS-01 memegang pelanggan). Spec §2.4 menyuruh membuang ODP lama. Menghapus ODP berpelanggan melanggar aturan `restrictOnDelete`. | Migrasi **tidak pernah menghapus** `network_nodes`/ODP. Yang dibersihkan hanya baris slot/port/koneksi/mapping. ODP lama (anchor POP) dibiarkan; aturan anchor ODP ke Cabang/Mini POP dicabut hanya untuk penempatan baru. ODP lama masih boleh dipindah ke PON lewat `move()`, dan baru wajib berinduk PON saat dibuat atau dipindah. | §2.4, §3, §10 |
| D3 | `code` `string(50)` tidak muat kode turunan (`{kode OLT}-S3-P5`). | `network_nodes.code` dilebarkan jadi `string(120)` (unique global tetap). Kode turunan > 120 karakter ditolak. Lebar ini hanya bisa diverifikasi di MySQL; tes lebar kolom di-skip pada sqlite. | §2.3 |
| D4 | Spec diam soal `olt_slots`, `OltSlot`, `OltSlotController` (`attachCard`, kartu sudah tidak ada), dan alat legacy (`InfrastructureLegacyBackfillService`, `InfrastructureLegacyDecisionService`, `InfrastructureLegacyMappingsCommand`, `InfrastructureReconciliationService`, `InfrastructureDummyResetService`, `infrastructure_legacy_mappings`). Semuanya bergantung pada tabel yang dibuang. | Semua dipensiunkan atas **persetujuan eksplisit user** (kode dan tes legacy-nya dihapus); tabel `olt_slots` ikut di-drop. Dua perintah tambahan ikut dihapus karena meng-inject service yang dihapus: `InfrastructureReconcileCommand` dan `InfrastructureCloseLegacyWritesCommand` (+ jadwal rekonsiliasi di `routes/console.php`). Saklar `InfrastructureCutover` tetap ada. | §3, §11 |
| D5 | Slot/PON/Port yang dipindah antar-induk merusak identitas (nomor, kode turunan). | `NetworkNodeService::move()` menolak node posisi (`olt_slot`, `olt_pon`, `switch_slot`, `switch_port`) dengan pesan "Slot/PON/Port tidak dapat dipindah — identitasnya mengikuti induk." Yang boleh dipindah: OLT/Switch (seluruh subtree ikut) dan ODP (antar-PON dalam Cabang yang sama). | §2.4, §9 |

Rujukan tambahan hasil implementasi (bukan koreksi, tetapi perlu diketahui):

- Kode turunan memakai satu helper `InfrastructurePositionService::derivedCode()`;
  nama relasi anak PON/Port Switch pada model adalah `NetworkNode::positionPorts()`
  (bukan `ports()`, yang sudah dipakai untuk `NetworkNodePort` milik ODP).
- `InfrastructureHierarchyService::build()` menerima `bool $withPositions = false`.
  Pohon Master Node memakai `false`: Slot/PON/Port **dan ODP di bawah PON** tidak dimuat
  (node non-posisi di luar posisi, mis. ODP lama anchor POP, tetap dimuat bertingkat);
  Peta memakai `true`. Rancangan awal (ODP ikut dimuat dengan jalur "OLT / Slot n / PON m",
  ruling R5) ditinggalkan pada final fix round karena memuat puluhan ribu ODP di tiap
  halaman. Seluruh rantai OLT → Slot → PON → ODP dibuka bertingkat lewat endpoint
  `network.nodes.children` (tiap anak membawa `children_url`/`url` dari server). Label
  "OLT / Slot n / PON m" kini dipakai endpoint `network.nodes.pon-options` (pilihan PON
  per OLT untuk relokasi ODP) dan `network.nodes.pon-search` (field PON bercari di modal
  Tambah ODP, lihat di bawah).
- Master Node `index` hanya memuat node non-posisi (bukan Slot/PON/Port dan bukan anak
  posisi) untuk daftar inspector; jalur UI membuat ODP: form **Tambah ODP** di detail PON
  (POST `network.nodes.store` + `parent_id`, PRG ke detail ODP) dan modal Tambah Titik
  dengan **satu field PON bercari** (koreksi pasca-merge 2026-10-26, keputusan user;
  menggantikan OLT → PON dua langkah): `GET network.nodes.pon-search?q=` (izin
  `network_nodes.view`) mengembalikan ≤ 20 PON `{id, label}` ter-scope `inPops()`
  (scope kosong = kosong), cocok `q` ke kode PON turunan serta kode/nama OLT, urut
  kode OLT → Slot → PON, Slot & OLT lewat JOIN (jumlah query konstan). Hasilnya mengisi
  `parent_id` tersembunyi; URL pencarian dirender server di `data-pon-search-url`.
- **Panel "Manajemen Node — <tipe>" per level di inspector Master Node** (koreksi
  pasca-merge 2026-10-26 putaran 1 + putaran UI 2, spesifikasi user): tiap level HANYA
  menampilkan anak langsungnya beserta kapasitasnya, dimuat **lazy** untuk node terpilih
  saja lewat `GET network.nodes.panel` (`/network/nodes/{node}/panel`, izin
  `network_nodes.view`; 403 di luar scope POP via `authorizeNodeScope`, lalu 404 untuk
  tipe di luar {`olt_chassis`, `switch_mini_pop`, `olt_slot`, `switch_slot`, `olt_pon`}).
  Menggantikan `network.nodes.device-panel` + partial `_device-slots` (kartu PON bersarang
  di tiap Slot — terlalu banyak; partial dihapus, halaman Detail perangkat memakai partial
  level perangkat yang sama).
  - OLT/Switch → `_panel-device-slots`: daftar Slot; perangkat terpakai = Slot terdata vs
    kapasitas perangkat; tiap Slot terpakai = PON/Port terdata vs kapasitas Slot, sisa =
    kapasitas − terpakai. Aksi: kapasitas perangkat, Tambah Slot, Generate struktur
    (tautan di header inspector).
  - Slot → `_panel-slot-ports`: daftar PON (Slot OLT) / Port Switch (Slot Switch) slot itu;
    PON terpakai = pelanggan aktif (`PonCapacityService::summaries`, satu query), kapasitas
    PON, sisa, Red Zone, status, jumlah ODP; Port Switch = status keterisian
    (`InfrastructureConnectionService::occupanciesOf`, satu query). Aksi: kapasitas Slot,
    Tambah PON/Port.
  - PON → `_panel-pon-odps`: total PON (pelanggan aktif/kapasitas/sisa/Red Zone/ambang/
    status) + daftar ODP anak langsung; ODP kapasitas = jumlah port ODP, terpakai = port
    `is_used`, sisa, Red Zone = `isInRedZone()`, plus pelanggan aktif per ODP
    (`PonCapacityService::customersPerOdp`, aturan hitung sama; jumlahnya = terpakai PON).
    Aksi: kapasitas & ambang PON, Tambah ODP (`network.nodes.store`, `parent_id` = PON).
  - Kapasitas null (atau ODP tanpa port) ditulis "Kapasitas belum didata", tidak pernah
    "?"/NaN/sisa palsu (`App\Support\MasterNodeInspector::capacity`).
  - Jumlah query tiap panel konstan terhadap jumlah anak (dites 1 vs 16 Slot, 1 vs 16 PON,
    1 vs 24 Port Switch, 1 vs 30 ODP).
  - Slot/PON di pohon lazy kini **bisa dipilih**: JSON `network.nodes.children` membawa
    `panel_url` (route server; null untuk ODP/Port Switch yang tetap tautan Detail) dan
    `path` ("OLT / Slot n / PON m"); inspector posisi generik (satu blok, bukan per posisi)
    menampilkan badge, label, jalur, "Buka Detail", dan panel lazy. Tombol "Kelola …" di
    panel OLT/Slot (`data-select-position`, JSON server ter-escape, listener delegasi)
    turun ke level berikutnya. Token permintaan mencegah respons pilihan lama tampil.
  - Form panel membawa `master_node` (id node yang dibuka) → PRG sukses ke
    `network.nodes.index?node=<id>` (trait `RedirectsToMasterNode`; hanya integer, rute
    tetap); dari halaman Detail tetap PRG ke Detail. `?node=` yang menunjuk Slot/PON dalam
    scope diisi controller sebagai `selectedPosition` (tanpa memuat posisi ke `$nodes`);
    di luar scope diabaikan.
  Panel tidak dirender saat load index (menjaga fix O(N²) final review C2). Pilihan node
  disimpan di `?node=` (history.replaceState) sehingga `back()` setelah gagal validasi
  memilih ulang node yang sama. Tree tetap: ODP hanya tampil di bawah PON.
  **Putaran UI 3 (2026-10-26, umpan balik user):** ODP di bawah PON wajib tampil dengan
  inspector ODP yang sama dengan ODP lain (grid port, modal port, Lokasi, Relokasi). Pilihan
  ODP = muat halaman `network.nodes.index?node=<ODP>`: controller memuat TEPAT SATU ODP itu
  (scope `inPops`, eager-load sama dengan `$nodes`, rantai induk disambung ke OLT yang sudah
  dimuat) ke `$inspectorNodes` — `$nodes` (pohon/penghitung) tetap tanpa ODP anak PON, query
  tambahan konstan. URL datang dari server: `select_url` di JSON `children` (pohon lazy,
  `openOdp`) dan tombol "Kelola Port" di panel PON. `treeOpenIds` (leluhur dalam scope)
  membuka cabang pohon lazy otomatis, juga untuk `?node=` Slot/PON. Relokasi ODP memakai
  field PON bercari (`network.nodes.pon-search`), bukan select OLT → `pon-options`; move,
  Tambah Port, dan Tandai Manual menerima `master_node` → PRG ke node yang sama. Target form
  modal port dari `data-*` kartu port (route()), dan Lepas Port kini mengirim
  `physical_removal_confirmed` + `release_note` (sebelumnya selalu gagal validasi).
  **Koreksi putaran UI 4 (2026-10-26):** memilih ODP dari pohon/tombol "Kelola Port" **tidak
  lagi memuat ulang halaman** — reload membangun ulang pohon dengan cabang tertutup sehingga
  PON tampak collapse sampai Slot. Klik kini `fetch` ke `network.nodes.inspector`
  (`GET /network/nodes/{node}/inspector`, loader ter-scope `selectedOdpUnderPon` yang sama,
  404 bila di luar scope/bukan anak PON) dan menyuntik parsial `_inspector-node` ke
  `#lazy-odp-inspector`; URL diperbarui via `history.replaceState`. `inspector_url` dirender
  server (JSON `children` + `data-inspector-url`), klien tidak merakit path. `select_url` /
  `?node=` tetap berlaku untuk muat langsung halaman. Anak pohon lazy dirender `x-init`
  sekali-jalan, bukan `x-html` reaktif (yang merender ulang cabang terbuka tiap seleksi berganti).
  View `_slot-inspector` dan
  `_pon-inspector` (§7) **dihapus**: keduanya membaca Slot/PON dari `$hierarchy` yang di
  Master Node tidak memuat posisi, sehingga tak pernah merender apa pun. `network.nodes.store` menolak tipe posisi dan selalu
  redirect ke detail node baru. Kode/tipe node posisi read-only di server; rename kode
  perangkat menurunkan ulang kode Slot/PON/Port di bawahnya dalam satu transaksi.
- Migrasi `2026_10_24_000002` menolak jalan tanpa konfirmasi
  (`INFRA_CONFIRM_DUMMY_RESET=true`) bila ada baris posisi lama yang akan dibuang, dan
  tidak dapat di-rollback (`down()` melempar exception). Sebelum kolom slot/port di-NULL,
  serial (`current_network_node_id`), sisi ledger (`from_/to_network_node_id`), dan saldo
  yatim (`network_node_id`, digabung per barang+lot) dipindah ke **perangkat induk**
  (dibaca dari `infrastructure_slots.network_node_id`); hanya yang tanpa perangkat induk
  dikosongkan/dihapus dan dihitung di pesan guard. `2026_10_25_000001` (drop `olt_slots`)
  memakai saklar yang sama bila tabel masih berisi baris.
- "Slot OLT kosong" di Peta kini berarti Slot tanpa PON (key ringkasan `free_olt_slots`).
