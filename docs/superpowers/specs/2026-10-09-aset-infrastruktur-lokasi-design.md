# Rancangan: Pohon Infrastruktur Jaringan Dinamis + Aset Gudang per Lokasi

Status: **v3 diimplementasikan; perluasan operasional 2026-10-13 di §10; revisi
Fase 0 (Mini POP = lokasi, bukan 1 OLT) di §11, 2026-10-14.**
Dibuat: 2026-10-09. Revisi v2: 2026-10-12. Revisi v3: 2026-10-12 — OLT = Mini POP,
penempatan model folder-di-drive (`anchor_pop_id` hanya di node puncak), cabang
diturunkan (tidak disimpan), snapshot cabang di ledger, aturan tipe-di-bawah-tipe
ditunda. Revisi v4 (§11, 2026-10-14): Mini POP = lokasi fisik yang bisa menaungi
N chassis OLT, bukan identitas 1 OLT. Riwayat keputusan v1 & v2 di §8, bukan
desain aktif.

## 1. Latar Belakang

Rantai infrastruktur jaringan riil **jauh lebih dalam dan tidak tetap**
jumlah levelnya (disediakan user, 2026-10-12):

```
Pusat (NOC) → Core Router/Switch → Backbone/Transport → Cabang (POP)
  → Router/Switch Agregasi POP → OLT (= Mini POP, OLT ke-2/3/dst di POP yang sama)
    → PON Port/Card → Kabel Feeder → ODC (+splitter) →
      Kabel Distribusi → ODP (+splitter) → Kabel Drop → ONT/ONU pelanggan
```

Modul Gudang sekarang cuma kenal 2 lokasi stok (`pops.type=pusat/cabang`) +
custody teknisi + terpasang ke pelanggan (`SerialStatus::INSTALLED`). Tidak
ada cara mencatat "ODC ini kapasitasnya berapa, sisa berapa", atau "apa saja
yang netap terpasang di titik X" — dan levelnya **tidak boleh fix**.

**Tiga kebutuhan yang harus dijawab desain ini, di titik mana pun di rantai:**
1. Kapasitas & sisa kosong (berapa port/slot, berapa terpakai).
2. Alat apa saja yang netap terpasang di titik itu (dari Gudang).
3. Audit dua arah — dari alat → lokasinya, dari lokasi → isinya — DAN
   pohonnya bisa **berubah susunan** (node dipindah) tanpa migration dan tanpa
   merusak data historis.

> **Open item, bukan blocker:** chassis OLT belum bisa dicatat sebagai aset
> terpasang, karena Mini POP bukan `network_node`. Lihat §7 nomor 1. Implementasi
> v3 tetap jalan tanpa ini.

## 2. Keputusan Inti (v3, jangan ditinjau ulang tanpa alasan baru)

1. **Satu tabel pohon generik `network_nodes`**, self-referencing (`parent_id`),
   kedalaman bebas. Bukan satu tabel per level.
2. **Tipe node lewat tabel lookup `network_node_types`** yang dikelola admin
   (Master Data, CRUD). Bukan PHP enum. Seed awal **enam tipe**: `core_router`,
   `backbone`, `aggregation_switch`, `pon_port`, `odc`, `odp`. Tipe `olt` **tidak
   ada** (lihat poin 3).
3. **OLT = Mini POP.** Mini POP (`pops.type=mini_pop`) tetap merepresentasikan
   OLT ke-2/3/dst di sebuah Cabang. Tidak dipindah dan tidak diduplikasi ke
   `network_nodes`, karena CID, RBAC, dan billing sudah memakainya.
4. **Penempatan model folder-di-drive.** Setiap node berada di **tepat satu
   tempat**:
   - di dalam node lain: `parent_id` terisi, `anchor_pop_id` null; atau
   - langsung di sebuah POP: `anchor_pop_id` terisi, `parent_id` null.

   Hanya node puncak yang punya `anchor_pop_id`. Turunannya tidak menyimpan
   cabang sama sekali. Akibatnya, memindahkan subtree selalu satu baris: pindah
   ODC-1 ke OLT lain = ganti `anchor_pop_id` atau `parent_id` satu node.
5. **Cabang tidak disimpan, diturunkan.** Cabang sebuah node didapat dengan naik
   rantai `parent_id` sampai node puncak, lalu mengambil `anchor_pop_id`. Kalau
   anchor-nya Mini POP, cabangnya adalah `parent` Pop tersebut (relasi `pops`
   yang sudah ada).
6. **Kapasitas dua bentuk, node pilih yang relevan:**
   - `capacity` (int di `network_nodes`): ringkasan angka, untuk node tanpa slot
     diskrit (mis. Backbone bandwidth).
   - `network_node_ports` (port bernomor + `is_used`): untuk node yang slotnya
     bisa dicek satu-satu (PON Port, splitter ODC/ODP). Ini generalisasi dari
     `odp_ports` yang sudah dibangun.
7. **Kapasitas ada guard:** `addPorts()` menolak jumlah port melebihi
   `max_ports` milik tipe node (bukan angka 128 hardcode).
8. **Aset Gudang nempel ke `network_nodes` lewat satu kolom**
   (`current_network_node_id`), bukan pasangan kolom per level.
9. **Dua data berbeda di satu node, jangan dicampur:** kapasitas/port (berapa
   slot, berapa kosong) dan aset terpasang (SN/qty dari ledger). Satu node bisa
   punya keduanya.
10. **Ledger menyimpan snapshot cabang saat kejadian.** Saat `deploy` dan
    `retrieve_infra`, kolom `to_pop_id`/`from_pop_id` diisi dengan cabang yang
    diturunkan **pada saat itu**. Laporan "terpasang di cabang X bulan lalu"
    tidak berubah walau node dipindah kemudian.
11. **Aturan tipe-di-bawah-tipe DITUNDA.** Semua kombinasi parent-child
    dibolehkan dulu. Tabel aturan ditambah hanya kalau salah input susunan
    sudah jadi masalah nyata.

## 3. Entitas & Skema

### 3.1 `network_node_types` (master lookup, dikelola admin)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `code` | `string(30)` unique | mis. `odc`, `odp`, `pon_port` |
| `name` | `string(100)` | Label tampilan |
| `has_ports` | boolean default false | Tipe ini punya slot port diskrit (`network_node_ports`) |
| `monitors_capacity` | boolean default false | Masuk perhitungan Red Zone |
| `max_ports` | `unsignedSmallInteger` nullable | Batas port per node tipe ini |
| `red_zone_threshold` | `unsignedTinyInteger` nullable | Persen terpakai untuk status Red Zone |
| `is_active` | boolean default true | |
| `sort_order` | integer default 0 | |
| timestamps | | |

CRUD di `/master/network-node-types`, permission
`master_network_node_types.{view,create,update,delete}`. Guard hapus: tolak
kalau masih dipakai `network_nodes.node_type_id` manapun.

**Seed awal** (idempotent, `updateOrCreate` by `code`): `core_router`,
`backbone`, `aggregation_switch`, `pon_port`, `odc`, `odp`.
- `odc` dan `odp`: `has_ports=true`, `monitors_capacity=true`, `max_ports=128`,
  `red_zone_threshold=80` (nilai awal, bisa diubah admin).
- Lainnya: `has_ports=false`.

Admin boleh menambah tipe kapan pun lewat UI, tanpa deploy.

### 3.2 `network_nodes` (pohon, kedalaman bebas)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `parent_id` | FK `network_nodes` nullable, restrict | Terisi kalau node berada di dalam node lain |
| `anchor_pop_id` | FK `pops` nullable, restrict | Terisi kalau node puncak, langsung di Cabang atau Mini POP |
| `node_type_id` | FK `network_node_types`, restrict | |
| `code` | `string(50)` unique | Manual diisi admin |
| `name` | `string(150)` | |
| `capacity` | `integer` nullable | Ringkasan, dipakai kalau node tidak punya baris `network_node_ports` |
| `address` | `text` nullable | |
| `village`/`district`/`city` | `string(100)` nullable | |
| `latitude`/`longitude` | `decimal(10,7)` nullable | Disiapkan untuk peta, belum auto-geocode |
| `status` | `string(20)` default `active` | `active` / `inactive` |
| `notes` | `text` nullable | |
| timestamps | | |

**Aturan XOR:** tepat satu dari `parent_id` atau `anchor_pop_id` terisi.
Ditegakkan `NetworkNodeService` dan dites. Index: `parent_id`, `anchor_pop_id`,
`node_type_id`.

**Cegah siklus:** node tidak boleh dipindah ke dalam dirinya atau turunannya.

**Kenapa bukan reuse `pops`:** `pops` menentukan RBAC-tree, CID pelanggan, dan
billing. Mencampur node topologi fisik ke tabel itu berisiko salah pakai. Tabel
terpisah, tersambung ke POP lewat `anchor_pop_id`.

### 3.3 `network_node_ports` (generalisasi `odp_ports`)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `network_node_id` | FK `network_nodes`, cascade-on-delete | |
| `number` | `unsignedSmallInteger` | |
| `is_used` | boolean default false | |
| timestamps | | |

Unique `(network_node_id, number)`. Bentuk identik dengan `odp_ports`.

### 3.4 Enum Gudang

- `OwnershipMode::INFRASTRUCTURE_ASSET`: barang yang boleh transisi ke
  `SerialStatus::DEPLOYED` dan netap. Beda dari `COMPANY_ASSET` yang hanya loop
  ISSUED⇄RETURNED.
- `SerialStatus::DEPLOYED`: "terpasang netap di infrastruktur", badge `success`.
- `InventoryTransactionType::DEPLOY` / `RETRIEVE_INFRA`: ledger, sejajar
  `install`/`return` yang sudah ada.

### 3.5 `inventory_serials`: satu kolom lokasi

| Kolom | Tipe | Keterangan |
|---|---|---|
| `current_network_node_id` | FK `network_nodes` nullable, null-on-delete | Diisi kalau `status=DEPLOYED` |

`current_pop_id` yang sudah ada tetap berarti "ada di gudang Pusat/Cabang,
masih stok". Tidak dipakai untuk kasus deploy.

### 3.6 `infrastructure_balances`: proyeksi saldo QUANTITY/ROLL yang di-deploy

Tabel terpisah dari `inventory_balances` karena `inventory_balances.pop_id`
sengaja dibatasi `Pop::WAREHOUSE_TYPES` (ADHOC-54).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `item_id` | FK `items`, restrict | |
| `lot_no` | `string(50)` default `''` | `''` = QUANTITY generik; `roll_code` = asal roll kabel tertentu |
| `network_node_id` | FK `network_nodes`, restrict | |
| `qty` | `decimal(12,2)` default 0 | |
| timestamps | | |

Index `(item_id, network_node_id)`. Tanpa unique index; keunikan ditegakkan
Service (`lockForUpdate()` + `firstOrCreate`), konsisten dengan
`inventory_serials`.

Tiga pembaca proyeksi tergantung `tracking_type`:
- **SERIALIZED** (OLT chassis, switch, ODP box, splitter): dibaca dari
  `inventory_serials.status` / `current_network_node_id`.
- **QUANTITY** (aksesoris): dibaca dari `infrastructure_balances`, `lot_no=''`.
- **ROLL** (kabel): dibaca dari `infrastructure_balances`, `lot_no=roll_code`.
  Tidak lewat `TechnicianCustody`.

### 3.7 `inventory_transactions`: kolom lokasi

| Kolom | Tipe | Keterangan |
|---|---|---|
| `to_network_node_id` | FK `network_nodes` nullable, null-on-delete | Dipakai `deploy` |
| `from_network_node_id` | FK `network_nodes` nullable, null-on-delete | Dipakai `retrieve_infra` |

**Wajib juga mengisi `to_pop_id` / `from_pop_id`** (kolom sudah ada) dengan
cabang yang diturunkan pada saat transaksi. Ini snapshot untuk laporan historis
(lihat keputusan §2 poin 10).

### 3.8 Pindah node (`NetworkNodeService::move()`)

```php
// tepat satu dari $newParent / $newPop; tolak jika $newParent adalah turunan $node
NetworkNodeService::move(NetworkNode $node, ?NetworkNode $newParent, ?Pop $newPop): void
```

- Efek: ubah `parent_id` atau `anchor_pop_id` **satu baris**. Turunan ikut
  otomatis tanpa di-rewrite.
- Ditolak: siklus; `$newParent` dan `$newPop` sama-sama terisi atau sama-sama kosong.
- Scope: aktor harus punya scope di **cabang asal dan cabang tujuan**.
- Jejak: dicatat lewat `App\Models\AuditLog::log()` yang sudah ada (user, IP,
  waktu, nilai lama/baru). Nilai lama/baru memuat `parent_id`, `anchor_pop_id`,
  `from_pop_id`, `to_pop_id`. Tidak ada tabel `network_node_moves`. Laporan
  "semua pindah ke cabang X" belum bisa di-query langsung (nilai JSON), diterima
  untuk sekarang.
- Ledger tidak ikut berubah. Riwayat "terpasang di mana" dijaga snapshot §3.7.

### 3.9 RBAC & Scope

- `master_network_node_types.{view,create,update,delete}`: Master Data tipe node.
- `network_nodes.{view,create,update,delete}`: menggantikan `network_odps`
  (rename lewat `PermissionGeneratorService` + update seeder).
- Pindah node: pakai `network_nodes.update` + scope asal dan tujuan. Permission
  terpisah untuk lintas cabang belum diputuskan (§7 nomor 4).
- Tidak ada permission baru untuk `deploy`/`retrieve_infra`. Reuse
  `warehouse_issue.create` / `warehouse_reassign.create`.
- **Scope cabang dihitung dari anchor, lewat dua API di model:**
  - `NetworkNode::resolvePop()`: naik ke node puncak, ambil cabang. Dipakai
    untuk cek satu node (`assertNetworkNodeInScope()`), dengan cache per request.
  - `NetworkNode::scopeInPops(array $popIds)`: turun dari anchor, ambil semua
    node di cabang yang boleh diakses. Dipakai untuk daftar, Red Zone, dan tree
    picker. Satu query recursive CTE, bukan resolver per baris (hindari N+1).
- Jangan tambah kolom cache cabang. Itu mengembalikan masalah yang ingin
  dihilangkan.
- Scope tetap lewat `EffectiveAccessService` / `AuthorizesWarehousePop`.

## 4. Alur Pasang/Ganti Aset lewat O-REQ/INFR-REQ

Alur tidak berubah: Issue dari Gudang Cabang → custody → submit laporan
O-REQ/INFR REQ (seksi baru di `TaskMaintenanceController`) →
`InventoryService::deploySerial()` / `deployQuantity()` / `deployRoll()`.
Parameter lokasi satu: `NetworkNode $node`.

Dropdown lokasi di form laporan: **tree picker**. Mulai dari cabang task, lalu
turun lewat `parent_id` sampai node yang dimaksud. Kedalaman bebas.

Mutasi/ganti aset (OLT lama diganti baru): `retrieveSerialFromLocation()`
(cabut, balik ke custody actor) + `deploySerial()` (pasang baru), satu transaksi.

## 5. Halaman: Aset & Kapasitas per Lokasi

Generalisasi halaman `/network/odps` yang sudah dibangun menjadi `/network/nodes`
untuk **node apa pun** di pohon, menyatukan:

- **Kapasitas:** jumlah port, dipakai, kosong, badge Red Zone (ambang dari tipe node).
- **Aset terpasang:** SN `status=DEPLOYED` + saldo `infrastructure_balances`
  dengan `network_node_id` = node ini.

**Navigasi:** tree browser. Breadcrumb dari `resolvePop()` lalu turun lewat
`parent_id`.

**Link "Riwayat"** per SN tetap ke `warehouse.traceability.index` (SN-centric),
2 entry point 1 sumber data (ledger).

**Performa:** query daftar lintas cabang belum diukur. Untuk sekarang wajar.
Cek ulang saat data node sudah ribuan. Kalau lambat, isi `scopeInPops()` bisa
diganti ke materialized path atau closure table tanpa mengubah pemanggil.

## 6. Rekonsiliasi dengan Kerja Codex (`odps`/`odp_ports`, uncommitted)

Ditemukan 2026-10-09: `odps`/`odp_ports`, `Network\OdpController`,
`FopUnifiedDashboardService::getOdpSummary()`, dan `OdpNetworkManagementTest`
sudah ada di working tree, belum di-commit. **Tidak dibuang**, direshape jadi
instance pertama pohon ini:

- `odps` → baris `network_nodes`: `node_type_id` = tipe `odp`,
  `anchor_pop_id = odps.pop_id`, `parent_id = null`.
- `odp_ports` → `network_node_ports` (bentuk kolom identik, ganti FK).
- `Network\OdpController` → `NetworkNodeController` (CRUD node apa pun).
- `getOdpSummary()`: filter pindah dari `node_type.code='odp'` ke flag
  `has_ports=true AND monitors_capacity=true`. Ambang dan batas port dari tipe
  node. Output shape tidak berubah, jadi Red Zone tetap jalan.
- `OdpNetworkManagementTest`: 5 test disesuaikan ke API baru (`network.nodes.*`),
  assertion inti (capacity, Red Zone, scope POP) dipertahankan.

Tidak ada data produksi yang hilang: semuanya masih uncommitted di working tree.

## 7. Open Item

1. **Chassis OLT belum bisa jadi tujuan deploy (bukan blocker).** Mini POP bukan
   `network_node`, jadi `deploySerial()` tidak bisa menaruh SN chassis OLT di
   sana. Arah solusi: **node pembungkus**, yaitu tipe baru (misal `olt_chassis`)
   dengan `anchor_pop_id` ke Mini POP. Dikerjakan setelah v3. Node di bawah Mini
   POP (ODC, ODP, PON port, dll.) tetap bisa menerima deploy di v3.
2. **Aturan tipe-di-bawah-tipe:** ditunda. Tambah tabel `network_node_type_rules`
   hanya kalau salah input susunan sudah jadi masalah nyata.
3. **Item mana boleh dipasang di tipe node mana:** ditunda. Sekarang hanya dijaga
   `OwnershipMode`.
4. **Pindah lintas cabang: permission terpisah atau cukup scope?** Belum diputuskan.
   Default sementara: scope asal + tujuan. Frekuensi nyata menentukan perlu tidaknya
   permission khusus.
5. **Task O-REQ/INFR yang masih terbuka saat node pindah cabang:** tetap di cabang
   asal, atau ikut pindah? Belum diputuskan.
6. **Log pindah:** sudah diputuskan pakai `AuditLog` (§3.8). Tabel khusus
   ditunda sampai ada kebutuhan laporan yang tidak bisa dijawab dari JSON.
7. **Seed data riil** (OLT mana, ODC mana, ODP mana, ratusan/ribuan baris):
   di luar scope coding. Input data operasional admin.
8. **Performa query lintas cabang** belum diukur (§5).

## 8. Riwayat Keputusan

- **v1 (2026-10-09), dibuang:** `odp` sebagai tabel tunggal wajib anak Mini POP.
  Ditolak karena rantai nyata punya banyak level yang tidak tercakup, dan
  "ODP wajib anak Mini POP" tidak selalu benar secara topologi.
- **v2 (2026-10-12), diubah:** pohon generik `network_nodes` dengan `root_pop_id`
  dikunci seumur hidup node. Diubah di v3 karena pindah OLT (Mini POP lain) harus
  mengubah anchor, dan klaim "tidak berubah" bertabrakan dengan itu.
- **v3 (2026-10-12):** OLT = Mini POP, tipe `olt` dicabut dari seed, anchor
  model folder-di-drive, cabang diturunkan, snapshot cabang di ledger, flag
  tipe menggantikan hardcode `odp`, aturan tipe ditunda.

## 9. Dokumen Terkait

- `docs/superpowers/plans/Analisa Desain Pohon Infrastruktur Dinamis (network_nodes).md`:
  analisa yang menjadi dasar v3.
- `docs/superpowers/plans/2026-10-09-aset-infrastruktur-lokasi.md`: plan turunan
  (perlu disesuaikan ke v3).
- `docs/warehouse/README.md`, `business-logic.md`, `database-schema.md`:
  konvensi ledger yang diikuti.
- `docs/master/pop/business-logic.md`: pola hierarki dan scope POP.
- `docs/superpowers/plans/2026-10-10-redesign-fop-unified-dashboard.md`: asal
  kerja `odps`/`odp_ports` yang direkonsiliasi di §6.
- `docs/fop-task/rancangan-request-pemasangan-dan-material-task.md`: pola
  estimasi vs terpakai untuk laporan O-REQ/INFR-REQ.

## 10. Perluasan Operasional 2026-10-13

Keputusan pada bagian ini menggantikan penundaan aturan tipe, chassis, dan pengelolaan port pada v3:

- Mini POP tetap identitas OLT. Slot OLT bernomor dikelola di Mini POP; node `olt_card` mengisi slot hanya setelah aset serial Gudang benar-benar di-deploy. Node `olt_chassis` menjadi lokasi serial chassis, tanpa menggandakan Mini POP.
- Alamat, wilayah administratif, dan koordinat dapat dicatat per node dan diwariskan per field dari induk/POP. Perpindahan subtree lintas cabang ditolak jika masih ada assignment port aktif, SN terpasang, atau saldo infrastruktur di subtree.
- Aturan tipe anak ke tipe induk atau tipe POP dikelola admin melalui `network_node_type_rules`. Tipe baru tanpa aturan tetap fleksibel sampai admin menetapkan aturannya.
- Port memiliki riwayat assignment ke pelanggan, anak node langsung, atau label manual. Pengelola dapat menghubungkan anak node ke port melalui detail node. Pemasangan pelanggan memakai port ODP yang tersedia; port yang sama tidak dapat dipakai ganda. Port lama `is_used` direkonsiliasi sebagai manual, lalu dicocokkan dengan data teknis pelanggan hanya bila kombinasi ODP, nomor port, cabang, dan pelanggan unik. Kasus ambigu tetap menunggu verifikasi di detail node.
- Pelepasan port dilakukan eksplisit oleh pengguna berizin dengan konfirmasi pelepasan fisik dan catatan alasan. Isolasi atau status putus pelanggan sendiri tidak otomatis mengosongkan port.
- Dua SKU katalog OLT lama diubah ke `infrastructure_asset` agar bisa mengikuti ledger deploy/retrieve. Komponen chassis atau kartu OLT hanya menerima satu SN aktif dari kategori `olt_module`; SKU harus menunjuk tipe node tujuan melalui `items.infrastructure_node_type_id` yang dapat dikelola di Master Barang.

Batas operasional yang masih perlu keputusan lanjutan: laporan tugas DEAC belum mencatat pelepasan kabel/port secara khusus, dan kapasitas performa pohon besar belum diukur. Input topologi dan verifikasi kasus data lama yang ambigu tetap pekerjaan operasional.

## 11. Revisi 2026-10-14 (Fase 0 — jawaban `analisa-gap-peta-infrastruktur.md` §6)

Alasan baru: user melaporkan kasus nyata **satu Mini POP bisa punya 3-4 chassis
OLT fisik**, membuktikan premis v3 poin 3 ("Mini POP = 1 OLT, merepresentasikan
OLT ke-2/3/dst") salah. §10 poin 1 ("Mini POP tetap identitas OLT") ikut
**digantikan**, bukan dihapus dari riwayat.

1. **Lapisan Pusat: tidak pakai layer "Metro" terpisah.** Node Core/Backbone/
   Server cukup ditambatkan langsung ke POP `pusat` (WHUSNET), sama seperti
   yang sudah digariskan §3.1 analisa gap — tanpa tipe/istilah tambahan.
2. **Mini POP = lokasi fisik, bukan identitas satu OLT.** Chassis OLT
   (`olt_chassis`) jadi node anak — boleh anak **Mini POP** (lokasi lapangan
   terpisah) atau anak **POP Cabang langsung** (kalau OLT nempel di gedung
   Cabang, tidak perlu Mini POP tersendiri). **Satu lokasi (Cabang atau Mini
   POP) boleh menaungi berapa pun chassis** — nambah unit OLT di titik yang
   sama = tambah node `olt_chassis`, BUKAN bikin Mini POP baru.
3. **Mini POP baru dibuat hanya kalau ada lokasi fisik baru** (alamat/
   koordinat beda dari Cabang induknya), tidak lagi berdasar jumlah OLT.
4. **Registrasi aset lama (chassis/kartu OLT): manual satu-satu**, pola sama
   dengan `odp_port` di `CustomerInstallationController` — field teks bebas
   kalau node/slot belum terdaftar sebagai `network_node` resmi, berubah jadi
   pilihan dari daftar resmi begitu node itu terdaftar. Tidak perlu seeder;
   tipe `olt_chassis` didaftarkan sekali di Master Tipe Node, instance-nya
   dibuat manual per lokasi nyata lewat `/network/nodes`.

Kalimat yang **tidak berlaku lagi** (biarkan di §2 dan §10 sebagai riwayat,
jangan dihapus): "Mini POP tetap merepresentasikan OLT ke-2/3/dst di sebuah
Cabang" dan "Mini POP tetap identitas OLT".

5. **Feeder: ikut lacak stok fisik (Opsi B), bukan sekadar node topologi.**
   Feeder jadi node (tipe `feeder`, parent OLT/Mini POP, `capacity` = jumlah
   core, port assignment ke ODC tujuan) **dan** dipasang lewat jalur Gudang
   yang sama dengan kabel Drop: `consumeFromRoll()` saat pemasangan (laporan
   O-REQ/INFR) mengurangi `infrastructure_balances` di lot ROLL asal dan
   mencatat ledger `DEPLOY`. Pencabutan ikut jalur retrieve yang sama (meter
   sisa balik ke ROLL / jadi scrap). Feeder = barang terpasang, sama kelasnya
   dengan ODC/ODP/Drop — bukan jalur pasif tanpa jejak material.
6. **Keterbatasan yang diketahui, SENGAJA tidak diotomasi:** migration
   `2026_10_14_000001_add_feeder_node_type_and_fix_olt_rules.php` menyusulkan
   baris `network_node_type_rules` `olt_chassis`/`olt_card` yang baru (anchor
   `olt_chassis` → Cabang, parent `olt_card` → `olt_chassis`) secara idempotent
   — ini aman diotomasi karena cuma tambah/hapus baris aturan, bukan data. Yang
   **TIDAK** diotomasi: rename kolom `olt_slots.pop_id` → `chassis_node_id`.
   Database yang sempat menjalankan migration `olt_slots` versi LAMA (ADHOC-166/
   168, sebelum revisi ini) sebelum Fase 0 selesai akan punya baris `olt_slots`
   yang masih berisi `pop_id` alih-alih `chassis_node_id` — memetakan slot lama
   ke chassis node yang benar butuh keputusan data-dependent (slot ini
   sebelumnya milik chassis mana) yang tidak bisa ditebak otomatis dengan aman.
   Database semacam itu wajib `migrate:fresh` (kalau datanya cuma data uji)
   atau migration reconciliation tersendiri yang ditulis manual sambil melihat
   data riil — jangan dibungkus diam-diam ke migration Fase 0 manapun.