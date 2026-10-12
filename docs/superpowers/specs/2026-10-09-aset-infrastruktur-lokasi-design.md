# Rancangan: Pohon Infrastruktur Jaringan Dinamis + Aset Gudang per Lokasi

Status: **DRAFT v2 — brainstorming, belum diimplementasi.**
Dibuat: 2026-10-09. **Revisi total 2026-10-12** — v1 (model `odps` fix 1 level
anak Mini POP) diganti total jadi pohon generik. Riwayat keputusan v1
dipertahankan di §9 (kenapa dibuang), jangan dibaca sebagai desain aktif.

## 1. Latar Belakang

Rantai infrastruktur jaringan riil **jauh lebih dalam dan tidak tetap**
jumlah levelnya (disediakan user, 2026-10-12):

```
Pusat (NOC) → Core Router/Switch → Backbone/Transport → Cabang (POP)
  → Router/Switch Agregasi POP → OLT (= "Mini POP" existing, OLT ke-2/3/dst
    di POP yang sama) → PON Port/Card → Kabel Feeder → ODC (+splitter) →
      Kabel Distribusi → ODP (+splitter) → Kabel Drop → ONT/ONU pelanggan
```

Modul Gudang sekarang cuma kenal 2 lokasi stok (`pops.type=pusat/cabang`) +
custody teknisi + terpasang ke pelanggan (`SerialStatus::INSTALLED`). Tidak
ada cara mencatat "OLT ini ada di mana", "ODC/ODP ini kapasitasnya berapa,
sisa berapa", atau "apa saja yang netap terpasang di titik X" — dan levelnya
**tidak boleh fix** (v1 rancangan ini sempat salah asumsi "ODP = anak tetap
Mini POP", ditolak user karena rantai nyata punya level yang berubah per
cabang dan bisa bertambah ke depannya — lihat §9).

**Tiga kebutuhan yang harus dijawab desain ini, di titik mana pun di rantai:**
1. Kapasitas & sisa kosong (berapa port/slot, berapa terpakai).
2. Alat apa saja yang netap terpasang di titik itu (dari Gudang).
3. Audit dua arah — dari alat → lokasinya, dari lokasi → isinya — DAN
   pohonnya harus bisa tumbuh/berubah tanpa migration baru tiap ada level
   baru.

## 2. Keputusan Inti (2026-10-12, jangan ditinjau ulang tanpa alasan baru)

1. **Satu tabel pohon generik `network_nodes`**, self-referencing
   (`parent_id`), kedalaman bebas — bukan satu tabel per level (ODC, OLT,
   ODP masing-masing tabel sendiri itu yang bikin kaku di v1).
2. **`node_type` lewat tabel lookup `network_node_types`** yang dikelola
   admin (Master Data biasa, CRUD) — **bukan** PHP enum. Nambah tipe
   infrastruktur baru (mis. "Repeater", "ODC Tier-2") cukup tambah baris,
   tanpa sentuh kode/migration/deploy. Ini jawaban langsung kebutuhan
   "sedinamis mungkin".
3. **Mini POP existing (`pops.type=mini_pop`) TIDAK dipindah/diubah.** Dia
   sudah representasi "OLT ke-2/3/dst di POP yang sama" (konfirmasi user)
   dan dibutuhkan mesin CID/RBAC/billing yang sudah jalan — jangan disentuh.
   `network_nodes` nyambung ke dunia POP lewat `root_pop_id` (FK `pops`,
   boleh Cabang ATAU Mini POP tergantung cabang itu punya Mini POP atau
   tidak) — node-node di bawahnya (PON Port, ODC, ODP, dst) jalan generik
   lewat `parent_id`, tidak peduli root-nya Cabang atau Mini POP langsung.
   Ini otomatis menjawab dilema lama "ODP anak Cabang vs Mini POP" — kedua
   topologi sah, tergantung cabang.
4. **Kapasitas generik, dua bentuk, node pilih yang relevan:**
   - `capacity` (int di `network_nodes`) — ringkasan angka doang, buat node
     yang tidak butuh identitas per-slot (mis. Backbone bandwidth).
   - `network_node_ports` (tabel anak, port bernomor + `is_used`) — buat
     node yang butuh slot diskrit bisa dicek satu-satu (PON Port di OLT,
     splitter port di ODC/ODP). **Ini generalisasi dari `odp_ports` yang
     sudah dibangun — bukan tabel baru yang beririsan, lihat §6.**
5. **Aset Gudang (SN/qty) nempel ke `network_nodes` lewat SATU kolom**
   polimorfik-tunggal, bukan pasangan kolom per level seperti v1 (dulu
   `current_pop_id`+`current_odp_id` dua kolom) — karena sekarang cuma ada
   SATU tabel tujuan (`network_nodes`), tidak perlu pasangan exclusive lagi.
6. **Dua data berbeda di satu node, jangan dicampur:**
   - **Kapasitas/port** = soal "berapa slot, berapa kosong" (`capacity`/`network_node_ports`) — murni angka/okupansi, tidak perlu tahu SN spesifik.
   - **Aset terpasang** = soal "barang APA dari Gudang yang netap di sini" (`inventory_serials`/`infrastructure_balances`, ber-SN/qty, berasal dari ledger).
   Satu node bisa punya dua-duanya sekaligus (OLT: kapasitas PON port +
   chassis OLT itu sendiri sebagai aset bernomor SN).

## 3. Entitas & Skema

### 3.1 `network_node_types` (master lookup, dikelola admin)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `code` | `string(30)` unique | mis. `olt`, `pon_port`, `odc`, `odp`, `backbone`, `aggregation_switch`, `core_router` |
| `name` | `string(100)` | Label tampilan |
| `is_active` | boolean default true | |
| `sort_order` | int default 0 | |
| timestamps | | |

CRUD di `/master/network-node-types`, permission `master_network_node_types.{view,create,update,delete}`
(generate dari `features`×`actions`). Guard hapus: tolak kalau masih dipakai
`network_nodes.node_type_id` manapun (pola `MasterRecordRemovalService` yang
sudah ada di repo untuk master lain).

**Seed awal** (idempotent, `updateOrCreate` by `code`): `core_router`,
`backbone`, `aggregation_switch`, `olt`, `pon_port`, `odc`, `odp` — tujuh
tipe dari rantai yang disebut user §1. Admin boleh tambah lebih banyak kapan
pun lewat UI, tanpa seed ulang.

### 3.2 `network_nodes` (pohon, kedalaman bebas)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `parent_id` | FK `network_nodes` nullable, restrict | Null = root (langsung anak `root_pop_id`) |
| `root_pop_id` | FK `pops`, restrict | Anchor Cabang/Mini POP — **tidak berubah** sepanjang hidup node (beda dari `parent_id` yang bisa dipindah kalau topologi direstruktur), dipakai scope RBAC + breadcrumb cepat tanpa manjat seluruh `parent_id` tiap kali |
| `node_type_id` | FK `network_node_types`, restrict | |
| `code` | `string(50)` unique | Manual diisi admin, sama filosofi POP |
| `name` | `string(150)` | |
| `capacity` | `integer` nullable | Ringkasan, dipakai kalau node TIDAK punya baris `network_node_ports` |
| `address` | `text` nullable | Mirror `pops.address` |
| `village`/`district`/`city` | `string(100)` nullable | Mirror `pops` |
| `latitude`/`longitude` | `decimal(10,7)` nullable | Mirror `pops` — buat maps nanti, **tidak** auto-geocode sekarang (manual dulu, field-nya disiapkan supaya nanti gampang disambung API maps tanpa migration lagi) |
| `status` | `string(20)` default `active` | `active`/`inactive` |
| `notes` | `text` nullable | |
| timestamps | | |

Index: `parent_id`, `root_pop_id`, `node_type_id`.

**Cegah circular parent** — sama pola `PopController` (`exclude semua
descendant dari pilihan parent_id`), ditegakkan Service, bukan DB.

**Kenapa bukan reuse `pops`:** `pops` sarat makna RBAC-tree/CID/billing
(`EffectiveAccessService::resolvePopTree()`, `generateComplexCid()`).
Mencampur "node topologi fisik di bawah Mini POP" ke tabel yang juga
menentukan CID pelanggan berisiko besar salah pakai tanpa sengaja. Tabel
terpisah, nyambung lewat `root_pop_id` (satu arah, bukan dua arah).

### 3.3 `network_node_ports` (generalisasi `odp_ports`)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `network_node_id` | FK `network_nodes`, cascade-on-delete | |
| `number` | `unsignedSmallInteger` | |
| `is_used` | boolean default false | |
| timestamps | | |

Unique `(network_node_id, number)`. **Sama bentuk** dengan `odp_ports`
existing (lihat §6) — cuma ganti FK target.

### 3.4 Enum Gudang — tidak berubah dari v1

- `OwnershipMode::INFRASTRUCTURE_ASSET` — barang yang boleh transisi ke
  `SerialStatus::DEPLOYED` dan netap (beda `COMPANY_ASSET` yang cuma
  loop ISSUED⇄RETURNED).
- `SerialStatus::DEPLOYED` — "terpasang netap di infrastruktur", badge
  `success`.
- `InventoryTransactionType::DEPLOY` / `RETRIEVE_INFRA` — ledger, sejajar
  `install`/`return` yang sudah ada.

### 3.5 `inventory_serials` — SATU kolom lokasi (bukan pasangan seperti v1)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `current_network_node_id` | FK `network_nodes` nullable, null-on-delete | Diisi kalau `status=DEPLOYED`. Pengganti `current_odp_id` v1 — v1 butuh 2 kolom (`current_pop_id`+`current_odp_id`) karena ada 2 tabel tujuan; sekarang cuma 1 tabel tujuan jadi 1 kolom cukup |

`current_pop_id` yang sudah ada **tidak dipakai lagi** untuk kasus deploy —
beda dari v1. `current_pop_id` balik ke makna aslinya 100% ("ada di gudang
Pusat/Cabang, masih stok"), tidak dicampur lagi.

### 3.6 `infrastructure_balances` — proyeksi saldo QUANTITY/ROLL yang di-deploy

Alasan tabel terpisah dari `inventory_balances` **tidak berubah dari v1** —
`inventory_balances.pop_id` dibatasi `Pop::WAREHOUSE_TYPES=['pusat','cabang']`
secara sengaja (ADHOC-54), jadi barang QUANTITY/ROLL yang di-deploy ke
`network_nodes` butuh proyeksi sendiri.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `item_id` | FK `items`, restrict | |
| `lot_no` | `string(50)` default `''` | `''` = QUANTITY generik, `roll_code` = asal dari roll kabel tertentu (lihat v1 §3.5 lama, prinsip sama) |
| `network_node_id` | FK `network_nodes`, restrict | **Satu kolom lokasi**, bukan pasangan `pop_id`/`odp_id` seperti v1 |
| `qty` | `decimal(12,2)` default 0 | |
| timestamps | | |

Index: `(item_id, network_node_id)`. **Tidak ada unique index** — keunikan
"satu baris per lokasi+item+lot" ditegakkan Service (`lockForUpdate()` +
`firstOrCreate`), bukan DB constraint (alasan sama v1: kombinasi nullable
exclusive bikin unique index standar tidak berfungsi — di sini malah lebih
simpel karena cuma 1 kolom lokasi, tidak ada exclusivity, tapi tetap pakai
pola Service-enforced konsisten dengan `inventory_serials`).

### 3.7 `inventory_transactions` — kolom & tipe (disederhanakan dari v1)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `to_network_node_id` | FK `network_nodes` nullable, null-on-delete | Mirror `to_pop_id`, dipakai `deploy` |
| `from_network_node_id` | FK `network_nodes` nullable, null-on-delete | Mirror `from_pop_id`, dipakai `retrieve_infra` |

`InventoryTransactionType::DEPLOY`/`RETRIEVE_INFRA` — kombinasi kolom sama
semantik v1, cuma target lokasi sekarang **satu kolom** (`to_network_node_id`/
`from_network_node_id`), bukan pasangan `to_pop_id`/`to_odp_id`.

Tiga pembaca proyeksi tergantung `tracking_type` (sama prinsip v1 §3.6 lama,
disalin karena masih berlaku):
- **SERIALIZED** (OLT, switch, ODP box, splitter — lihat koreksi seeder di
  plan Task) → proyeksi dibaca `inventory_serials.status/current_network_node_id`.
- **QUANTITY** (aksesoris) → `infrastructure_balances`, `lot_no=''`.
- **ROLL** (kabel) → `infrastructure_balances`, `lot_no=roll_code`, **tidak**
  lewat `TechnicianCustody` (roll dipegang langsung, lihat Gudang
  business-logic §1).

## 4. Alur Pasang/Ganti Aset lewat O-REQ/INFR-REQ

**Tidak berubah dari v1 secara alur** — tetap: Issue dari Gudang Cabang →
custody → submit Laporan O-REQ/INFR REQ (`TaskMaintenanceController`,
seksi baru) → `InventoryService::deploySerial()`/`deployQuantity()`/
`deployRoll()`. **Yang berubah cuma target parameter**: dulu `(?Pop $pop,
?Odp $odp)` sepasang exclusive, sekarang **satu** `NetworkNode $node` —
method-method ini jadi lebih simpel signature-nya.

Dropdown lokasi di form laporan: **tree picker** (pilih Cabang/Mini POP dulu
→ `root_pop_id`, lalu navigasi turun `parent_id` sampai node yang dimaksud)
— bukan dropdown flat Mini POP+ODP terpisah seperti v1, karena kedalaman
sekarang bisa berapa pun.

Mutasi/ganti aset existing (OLT lama diganti baru) — **tidak berubah dari
v1**: `retrieveSerialFromLocation()` (cabut, balik ke custody actor) +
`deploySerial()` (pasang baru), satu transaksi.

## 5. Halaman: Aset & Kapasitas per Lokasi (gabung, bukan dua halaman)

**Beda dari v1** — v1 merancang halaman baru terpisah dari apa pun yang ada.
Sekarang: **generalisasi halaman `/network/odps` yang sudah dibangun**
(lihat §6) jadi `/network/nodes` yang bisa menampilkan **node apa pun** di
pohon, tidak cuma ODP, dan menyatukan 2 hal di satu halaman:

- **Kapasitas** (yang sudah ada di `/network/odps`): jumlah port, dipakai,
  kosong, badge "Red Zone" kalau mendekati penuh.
- **Aset terpasang** (baru, dari §3.5/3.6): daftar SN `status=DEPLOYED` +
  saldo `infrastructure_balances` yang `network_node_id`=node ini.

**Navigasi**: breadcrumb dari `root_pop_id` (Cabang → Mini POP kalau ada)
lalu turun lewat `parent_id` sampai node yang dipilih — tree browser, bukan
3 dropdown independen seperti v1.

**Link "Riwayat"** per SN tetap ke `warehouse.traceability.index` yang
sudah ada (SN-centric) — tidak berubah dari v1, tetap 2 entry point 1 sumber
data (ledger).

Scope POP: `assertNetworkNodeInScope()` (baru, trait `AuthorizesWarehousePop`)
— resolve lewat `node->root_pop_id` langsung (1 lookup, lebih simpel dari
v1 yang perlu manjat `odp->miniPop->parent_id`).

## 6. Rekonsiliasi dengan Kerja Codex (`odps`/`odp_ports`, uncommitted)

Ditemukan 2026-10-09 lewat audit working tree: task lain (`docs/superpowers/plans/2026-10-10-redesign-fop-unified-dashboard.md`
Task 7, "Kapasitas ODP/ODC port hampir penuh") sudah bikin `odps`/`odp_ports`
+ `App\Http\Controllers\Network\OdpController` + `FopUnifiedDashboardService::getOdpSummary()`
("Red Zone" alert) + 5 test `OdpNetworkManagementTest` — **uncommitted**,
ditemukan sebelum semuanya di-commit.

**Tidak dibuang** — direshape jadi instance pertama pohon ini:
- `odps` → data pindah jadi baris `network_nodes` dengan `node_type_id`
  = lookup `'odp'`, `root_pop_id` = `odps.pop_id` lama, `parent_id = null`
  (root, karena `odps` lama tidak punya struktur bertingkat).
- `odp_ports` → data pindah jadi `network_node_ports` (bentuk kolom identik,
  cuma ganti FK target).
- `App\Http\Controllers\Network\OdpController` → generalisasi jadi
  `NetworkNodeController` (bisa CRUD node apa pun, tree-aware — tombol
  "Tambah ODP" di UI existing jadi "Tambah Node" dengan `node_type_id`
  dipilih = `odp` secara default kalau dipanggil dari konteks yang sama).
- `FopUnifiedDashboardService::getOdpSummary()` — query diupdate baca
  `network_nodes`/`network_node_ports` filter `node_type.code='odp'`,
  **perilaku/output tidak berubah** (Red Zone tetap jalan sama).
- `OdpNetworkManagementTest` — 5 test disesuaikan ke API baru
  (`network.nodes.*` routes), assertion inti (capacity, red zone, scope
  POP) dipertahankan.

**Tidak ada data produksi yang hilang** — semuanya uncommitted di working
tree yang sama, direshape sebelum pernah di-commit/deploy.

## 7. RBAC & Permission

- `master_network_node_types.{view,create,update,delete}` — Master Data
  tipe node (§3.1).
- `network_nodes.{view,create,update,delete}` — menggantikan `network_odps`
  yang sudah ada (reshape permission, bukan permission baru independen —
  satu rename lewat `PermissionGeneratorService` + update seeder).
- Tidak ada permission baru untuk `deploy`/`retrieve_infra` — reuse
  `warehouse_issue.create`/`warehouse_reassign.create` (sama prinsip v1).
- Scope: `root_pop_id` → `EffectiveAccessService::getAllowedPopIds()`
  langsung (root_pop_id SELALU Cabang atau Mini POP, dua-duanya sudah
  masuk hasil `resolvePopTree()` existing) — satu lookup, tidak perlu jalan
  lewat hierarki `parent_id` buat scope check (`parent_id` cuma buat
  breadcrumb/struktur tampilan, bukan basis scope).

## 8. Open Items (belum diputuskan)

1. **Kapasitas: info doang atau ada guard "penuh, tidak bisa tambah"?**
   Punya codex (`odp_ports`) SUDAH menegakkan guard (`addPorts()` menolak
   >128 total) — beda dari keputusan v1 lama ("murni informasional, ikut
   Distribution"). **Keputusan baru: ikut yang sudah dibangun codex** (ada
   guard, port ditambah bukan dikurangi) — v1's Open Item #3 dicabut,
   prinsip Distribution tidak lagi jadi acuan di sini karena kasusnya beda
   (Distribution = label CID, ODP/node = kapasitas fisik port riil yang
   SUDAH ada kebutuhan nyata ditegakkan, dibuktikan `OdpNetworkManagementTest`).
2. **Jalur admin assign aset langsung tanpa laporan teknisi** — tetap
   DEFERRED (sama v1 §7 lama), di luar scope plan ini.
3. **Field survey `nearest_odp` (teks bebas)** — tetap DEFERRED, tidak
   disatukan ke `network_nodes` di plan ini.
4. **Berapa dalam seed awal rantai dibuat sekaligus?** — plan ini cuma
   bikin struktur generik + 7 `network_node_types` awal (§3.1). Mengisi
   data riil tiap cabang (OLT mana, ODC mana, ODP mana — ratusan/ribuan
   baris) **di luar scope coding plan ini**, itu kerja input data
   operasional admin pasca-fitur jadi.

## 9. Riwayat — Kenapa v1 Dibuang (2026-10-12)

v1 (odp sebagai tabel tunggal, wajib anak Mini POP) ditolak user karena:
1. Rantai nyata (§1) punya banyak level (Core Router, Backbone, Agregasi,
   OLT, PON Port, ODC, ODP, Kabel) yang v1 sama sekali tidak punya tempat
   — v1 cuma punya 1 level baru (ODP) di atas yang sudah ada.
2. "ODP wajib anak Mini POP" ternyata tidak selalu benar secara topologi
   (ada cabang yang OLT-nya langsung di Cabang tanpa Mini POP perantara) —
   dan lebih dalam dari itu, pertanyaan yang tepat bukan "ODP anak siapa"
   tapi "bagaimana pohon ini tetap bisa tumbuh tanpa migration tiap level
   baru muncul".
3. Keputusan v1 §7 Open Item #1 (pisah 2 Item master per OwnershipMode)
   dan §3.5 (tabel `infrastructure_balances` dengan pasangan kolom
   `pop_id`/`odp_id`) **tetap berlaku** di v2 — itu bukan bagian yang
   salah, cuma strukturnya disederhanakan jadi 1 kolom lokasi karena v2
   cuma punya 1 tabel tujuan.

## 10. Dokumen Terkait

- `docs/warehouse/README.md`, `business-logic.md`, `database-schema.md` —
  konvensi ledger yang diikuti.
- `docs/master/pop/business-logic.md` — pola hierarki/scope POP yang
  ditiru untuk `network_nodes`.
- `docs/superpowers/plans/2026-10-10-redesign-fop-unified-dashboard.md` —
  asal kerja `odps`/`odp_ports` yang direkonsiliasi di §6.
- `docs/fop-task/rancangan-request-pemasangan-dan-material-task.md` — pola
  estimasi vs terpakai yang relevan buat laporan O-REQ/INFR-REQ.
