# Rancangan: Manajemen Aset Infrastruktur per Lokasi (Pusat/Cabang/Mini POP/ODP)

Status: **DRAFT — brainstorming, belum diimplementasi.**
Dibuat: 2026-10-09

## 1. Latar Belakang

Modul Gudang sekarang (`docs/warehouse/`) cuma mengenal 2 level lokasi stok:
`pops.type = pusat` (gudang pusat) dan `cabang` (gudang cabang). Barang yang
keluar dari gudang cuma punya 2 nasib: **custody teknisi** (dipinjam, dikembalikan)
atau **terpasang ke pelanggan** (`SerialStatus::INSTALLED`, lewat `InventoryService::installSerial()`).

Kebutuhan baru: melacak aset infrastruktur jaringan yang **netap** di lokasi
selain gudang dan selain pelanggan — OLT/switch di Mini POP, ODP box + splitter
di ODP, UPS/aki di ruang server Cabang. Dipicu/dimutasi lewat task `O-REQ`
(Office Request) dan `INFR REQ` (Infrastruktur Request) — dua `TaskType` yang
sudah ada tapi belum nyambung ke Gudang sama sekali.

Kebutuhan turunan dari diskusi: bukan cuma "catat barangnya ada di mana" tapi
juga **audit 2 arah**:
- Dari SN/aset → lokasinya di mana sekarang (sudah mirip Lacak Barang yang ada).
- Dari lokasi (Mini POP/ODP tertentu) → aset apa saja yang terpasang di situ
  (tidak ada sekarang — Gudang 100% SN-centric, bukan lokasi-centric).

## 2. Keputusan dari Diskusi (jangan ditinjau ulang tanpa alasan baru)

1. **ODP adalah node baru**, anak dari Mini POP (`1 Mini POP → banyak ODP`) —
   **bukan** `Distribution` yang sudah ada. `Distribution` tetap murni label
   segmen CID, bisa di-reassign bebas, dan **secara konsep bukan** anak
   struktural Mini POP (walau kolom `pop_id`-nya ada) — dua konsep ini sengaja
   dipisah, jangan digabung.
2. **Dua kelas barang masuk cakupan**: (a) aset tetap baru — OLT, switch,
   ODP box, tower, UPS/aki — belum ada representasinya sama sekali; (b)
   perluasan barang Gudang yang sudah ada (splitter, kabel) supaya bisa
   dicatat "terpasang di ODP X", bukan cuma "stok di gudang".
3. **O-REQ dan INFR-REQ dua-duanya bisa**: pasang aset baru ATAU
   ganti/mutasi aset yang sudah ada di suatu lokasi.
4. **Alur pasang = pola custody→install yang sudah ada**, cuma target
   akhirnya digeneralisasi: teknisi `Issue` dari gudang Cabang → custody →
   submit laporan O-REQ/INFR-REQ → barang pindah dari custody jadi
   "terpasang" di lokasi (Mini POP/ODP), **bukan** hanya logika baru dari nol.
5. **Status "terpasang" (deployed) dibutuhkan di SEMUA level**, termasuk
   Pusat/Cabang — OLT yang sudah naik rak beda maknanya dari OLT yang masih
   jadi stok cadangan di gudang. Jadi bukan cuma Mini POP/ODP yang butuh
   status baru, Pusat/Cabang pun butuh pembeda "stok" vs "terpasang".
6. **Butuh halaman baru yang lokasi-centric** ("pilih lokasi, lihat semua
   aset di situ"), bukan hanya memperluas Lacak Barang yang SN-centric
   ("pilih SN, lihat riwayatnya"). Dua entry point, satu sumber data.

## 3. Entitas & Perubahan Data Model

### 3.1 Tabel baru `odps`

Pola sama seperti master POP (self-referencing dihindari, cukup FK ke Mini POP):

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `pop_id` | FK `pops`, restrict | **Wajib** Mini POP (`type=mini_pop`) — ditegakkan Service, bukan DB constraint (sama pola `distributions.pop_id`) |
| `code` | `string(30)` unique | Kode ODP, manual diisi admin (konsisten dengan filosofi POP: "semua kode ditentukan manual") |
| `name` | `string(150)` | |
| `capacity_port` | `integer` nullable | Opsional — jumlah port splitter, kalau admin mau catat |
| `latitude`/`longitude` | `decimal` nullable | Opsional, buat peta (kalau dibutuhkan UI peta belakangan) |
| `status` | `string(20)` default `active` | `active`/`inactive` — ODP yang dibongkar gak dihapus, dinonaktifkan (jejak aset lama tetap valid) |
| `notes` | `text` nullable | |
| timestamps | | |

CRUD di Master Data (`/master/odp`), permission `odps.view|create|update|delete`
(generate dari `features`×`actions`, bukan hardcode) — pola identik Master POP/Distribution.

**Kenapa bukan dalam tabel `pops`:** `pops` dipakai luas buat RBAC scope tree
(`EffectiveAccessService::resolvePopTree()`), CID generation, dan hierarki
billing. ODP tidak butuh ikut seluruh mesin itu — ODP murni lokasi fisik aset,
nebeng FK ke Mini POP sudah cukup. Menambah `type=odp` ke `pops` beresiko
mencampur konsep "node RBAC/billing" dengan "node lokasi aset" yang sifatnya beda.

### 3.2 `OwnershipMode` — case baru

```php
case INFRASTRUCTURE_ASSET = 'infrastructure_asset';
```

Beda dari `COMPANY_ASSET` yang sudah ada (alat kerja — OTDR/laptop, **cuma
looping** `ISSUED ⇄ RETURNED`, **tidak pernah netap**). `INFRASTRUCTURE_ASSET`
boleh transisi ke status baru `SerialStatus::DEPLOYED` dan **netap** di sana
(OLT, switch, ODP box, UPS) — bukan dipinjam-balikin, melainkan dipasang permanen
sampai diganti/dicabut lewat O-REQ/INFR-REQ lain.

`INSTALLABLE` (ke pelanggan) tetap terpisah — splitter/kabel yang dipasang di
ODP **bukan** `INSTALLABLE` ke pelanggan tunggal, tapi juga bukan `COMPANY_ASSET`
(bukan alat kerja). Barang ini butuh `INFRASTRUCTURE_ASSET` juga, walau fisiknya
mirip barang `INSTALLABLE` existing (splitter kadang terpasang di sisi pelanggan,
kadang di ODP) — axis `OwnershipMode` jadi property **transaksi** kapan perlu
dibedakan per penggunaan, bukan cuma property `Item` statis, kalau ternyata satu
Item (misal splitter 1x8) bisa dipasang baik ke pelanggan maupun ke ODP. **Ini
titik yang perlu digali lagi saat spek detail** — lihat §7 Open Items.

### 3.3 `SerialStatus` — case baru

```php
case DEPLOYED = 'deployed';
```

Label: "Terpasang di Infrastruktur". `badgeVariant()` → `success` (sehat, netap),
sejajar `AVAILABLE`/`INSTALLED`.

### 3.4 `inventory_serials` — kolom lokasi baru

| Kolom | Tipe | Keterangan |
|---|---|---|
| `current_odp_id` | FK `odps` nullable, null-on-delete | Diisi kalau `status=DEPLOYED` **dan** lokasinya ODP |

**`current_pop_id` yang sudah ada dipakai ulang** untuk kasus `status=DEPLOYED`
di Pusat/Cabang/Mini POP — kolom ini sekarang punya 2 makna tergantung `status`:
- `status ∈ {AVAILABLE, RECEIVED, ISSUED-pending, ...}` → "ada di gudang ini,
  masih stok" (makna lama, tidak berubah).
- `status = DEPLOYED` → "terpasang netap di lokasi ini" (makna baru).

**Ini AMAN dilakukan** — `inventory_serials.current_pop_id` **tidak pernah**
dibatasi `Pop::WAREHOUSE_TYPES` di level model/DB (beda dari `inventory_balances`,
lihat §3.5 di bawah). Pembatasan `WAREHOUSE_TYPES=['pusat','cabang']` cuma
ditegakkan Service-level untuk alur stok (`InventoryReceiveService`/
`InventoryTransferService`/`InventoryIssueService`), bukan untuk kolom ini
secara umum. Konsisten dengan prinsip Gudang §12 ("axis independen,
`current_pop_id`/`current_technician_id` sengaja dibiarkan sebagai jejak
forensik") — satu kolom, makna ditentukan `status` yang mendampinginya.

### 3.5 **KOREKSI** — barang QUANTITY butuh tabel baru, BUKAN kolom di `inventory_balances`

**Revisi dari draf awal** (ditemukan saat perencanaan detail, 2026-10-09):
rancangan awal mau menambah `odp_id` + izinkan `mini_pop` di `inventory_balances`.
Ini **menabrak invariant yang sudah ada dan sengaja**: `Pop::WAREHOUSE_TYPES =
['pusat','cabang']` (`app/Models/Pop.php:67`) — **Mini POP sengaja TIDAK
PERNAH dianggap gudang** (ADHOC-54: "titik distribusi fisik, bukan kantor
cabang berstaf"), dan `inventory_balances.pop_id` punya dokumentasi tegas
"TIDAK PERNAH mini_pop" yang ditegakkan di titik tulis Service. Menambah jalur
yang bikin `inventory_balances` punya baris ber-makna "mini_pop"/"odp"
mencampur konsep "stok gudang yang bisa di-Transfer/Issue" dengan "aset netap
infrastruktur" — dua lifecycle yang beda.

**Keputusan baru:** tabel proyeksi terpisah, `infrastructure_balances`
— pola sama `inventory_balances` (proyeksi, diturunkan dari ledger, bukan
sumber kebenaran) tapi khusus barang QUANTITY yang **terpasang netap**
(bukan stok yang masih bisa dipindah-pindah):

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `item_id` | FK `items`, restrict | |
| `lot_no` | `string(50)` default `''` | Sentinel kosong, sama pola `inventory_balances` |
| `pop_id` | FK `pops` nullable, restrict | Lokasi = Pusat/Cabang/**Mini POP** (3 level diperbolehkan di sini — beda dari `inventory_balances` yang cuma 2) |
| `odp_id` | FK `odps` nullable, restrict | Lokasi = ODP |
| `qty` | `decimal(12,2)` default 0 | |

Unique: `(pop_id, odp_id, item_id, lot_no)`. Constraint aplikasi (Service):
tepat satu dari `pop_id`/`odp_id` terisi, yang lain `null`.

**Kenapa tabel baru, bukan kolom di tabel lama:** konsisten dengan alasan
`task_materials` dulu dibuat baru alih-alih numpang kolom existing (lihat
`docs/fop-task/rancangan-request-pemasangan-dan-material-task.md` §2.2) —
"tabel baru kalau kolom tidak cukup" berlaku juga di sini karena makna
"gudang" dan "terpasang netap" sudah punya invariant yang saling bertentangan
secara sengaja, bukan cuma beda nama kolom.

### 3.5a `WarehouseStockAsOfService` — TIDAK disentuh

Karena `infrastructure_balances` adalah tabel proyeksi sendiri (bukan baris
baru di `inventory_balances`), replay logic `WarehouseStockAsOfService` yang
sudah ada **tidak perlu diubah**. Laporan Gudang (stok Pusat/Cabang) dan
laporan Aset Infrastruktur (Mini POP/ODP) tetap 2 sumber proyeksi yang jelas
terpisah — menghindari laporan stok gudang tiba-tiba kemasukan baris "stok"
Mini POP yang sebenarnya bukan stok.

### 3.6 `inventory_transactions` — kolom & tipe baru

| Kolom | Tipe | Keterangan |
|---|---|---|
| `to_odp_id` | FK `odps` nullable, null-on-delete | Mirror `to_pop_id` |
| `from_odp_id` | FK `odps` nullable, null-on-delete | Mirror `from_pop_id` — dipakai saat aset dicabut dari satu ODP (ganti/upgrade) |

`InventoryTransactionType` dapat 2 nilai baru:

| Type | Kombinasi kolom | Makna |
|---|---|---|
| `deploy` | `from_technician_id`, (`to_pop_id` **atau** `to_odp_id`) | Aset pindah dari custody teknisi jadi terpasang netap — titik terminal, sejajar `install` yang sudah ada tapi target lokasi bukan `customer_id` |
| `retrieve_infra` | (`from_pop_id` **atau** `from_odp_id`), `to_technician_id` | Aset dicabut balik dari lokasi ke custody teknisi (mis. OLT lama dicabut sebelum diganti, mau dibawa balik ke gudang/dibuang) |

Kedua tipe baru ini **mengikuti pola yang sama persis** dengan
`issue`/`return`/`install` yang sudah ada — ledger append-only, row locking,
reference number per pola `generateReferenceNumber()`. Satu ledger, tiga
pembaca proyeksi tergantung `tracking_type` item (**koreksi data 2026-10-09**:
splitter/ODP box = SERIALIZED, kabel = ROLL — bukan QUANTITY, lihat
`docs/superpowers/plans/2026-10-09-aset-infrastruktur-lokasi.md` Task 2a):
- **SERIALIZED** (splitter, ODP box, OLT, switch) → proyeksi dibaca dari
  `inventory_serials.status/current_pop_id/current_odp_id` (SATU baris = SATU
  unit, gak butuh tabel saldo — sama seperti `install` sekarang).
- **QUANTITY** (aksesoris pasang, bukan splitter/kabel) → proyeksi ditulis ke
  `infrastructure_balances` (§3.5) dengan `lot_no=''`, **bukan** `inventory_balances`.
- **ROLL** (kabel dropcore/fiber) → **tidak** lewat `TechnicianCustody`
  (roll dipegang langsung, bukan custody agregat — Gudang business-logic §1).
  Proyeksi ditulis ke `infrastructure_balances` juga, tapi `lot_no=roll_code`
  (bukan sentinel kosong) supaya meter dari roll berbeda tetap tertelusur per-roll.

Untuk QUANTITY & ROLL, `to_pop_id`/`to_odp_id` baris `deploy` dan
`from_pop_id`/`from_odp_id` baris `retrieve_infra` dibaca Service yang
meng-update `infrastructure_balances`, mirror cara `inventory_balances`
diupdate di `issue`/`transfer` sekarang (increment/decrement langsung di
Service yang sama dengan penulisan ledger, satu transaction).

## 4. Alur Pasang/Ganti Aset lewat O-REQ/INFR-REQ

Mengikuti pola **integrasi Task Teknisi ↔ Gudang** yang sudah ada
(`InventoryService`, lihat `docs/warehouse/business-logic.md` §6), bukan jalur baru:

```
1. Teknisi Issue dari Gudang Cabang (pola sama: InventoryIssueService)
   → custody teknisi (QUANTITY) atau SN status=ISSUED (SERIALIZED)

2. Teknisi kerjakan task O-REQ/INFR REQ, submit Laporan — **BUKAN form baru**,
   O-REQ & INFR REQ **sudah** lewat `TaskMaintenanceController::report()`/`store()`
   yang sama dengan MTN/C-REQ (satu controller, satu view
   `tasks/maintenance-report.blade.php`, one-shot `sync()+complete()` dalam
   satu request). Cukup tambah **seksi baru** "Pasang/Ganti Infrastruktur" di
   form itu, tampil kondisional cuma untuk `task_type ∈ {OREQ, INFR}`:
   → pilih target lokasi: Mini POP / ODP (dropdown berjenjang
     Cabang → Mini POP → ODP, pola sama modal "Atur Mini POP & Distribusi")
   → pilih SN yang dipasang (SERIALIZED, dari custody tim — sumber sama
     `eligibleSerialsForTeam()`, filter `ownership_mode=INFRASTRUCTURE_ASSET`
     bukan `INSTALLABLE`) dan/atau qty dari custody (QUANTITY)

3. InventoryService::deployToLocation() (method baru, sejajar installSerial())
   - SERIALIZED: SN → status=DEPLOYED, current_pop_id ATAU current_odp_id diisi
     (exclusive), current_technician_id dikosongkan. Ledger `deploy`.
   - QUANTITY: consumeFromCustody() (SUDAH ADA, tidak diubah) kurangi custody,
     lalu baris `deploy` ke to_pop_id/to_odp_id menambah **`infrastructure_balances`**
     (§3.5 — BUKAN `inventory_balances`) di lokasi tujuan. Guard
     OwnershipMode::INFRASTRUCTURE_ASSET ditegakkan di titik ini —
     satu-satunya pintu transisi, sama prinsip installSerial().

4. Mutasi/ganti aset existing (OLT lama diganti baru):
   - Laporan INFR-REQ dengan 2 bagian: SN lama "dicabut" (retrieve_infra,
     balik ke custody teknisi yang submit laporan) + SN baru "dipasang"
     (deploy, langkah 3). Satu transaksi DB, satu submit laporan — supaya
     tidak ada window "lokasi kosong" di antara cabut dan pasang.
```

**Kenapa custody dulu, bukan langsung dari gudang:** sama alasan Issue→Install
yang sudah ada — custody adalah jejak "siapa yang pegang barang ini sebelum
dipasang", penting buat audit kalau ada selisih fisik.

### 4.1 Jalur admin langsung (tanpa laporan teknisi)

**Di luar scope rancangan ini untuk saat ini** — diskusi awal (Q&A) menyebut
kemungkinan ini, tapi belum ada keputusan final. Dicatat di §7 Open Items.

## 5. Halaman Baru: Aset per Lokasi (lokasi-centric)

Gudang yang ada 100% **SN-centric** (Lacak Barang: pilih SN → lihat riwayat).
Kebutuhan baru murni **lokasi-centric**: pilih lokasi → lihat semua aset di situ.
Dua entry point berbeda, satu sumber data (ledger) — tidak duplikasi logic.

**Halaman:** `/warehouse/location-assets` (nama route tentatif,
`warehouse.location-assets.index`), permission baru `warehouse_location_asset.view`
(bukan menumpang `warehouse.view` — sesuai aturan CLAUDE.md "tiap halaman
baru punya permission sendiri" kalau beda fungsi, dan ini beda fungsi/akses
dari Dashboard Gudang biasa).

- **Selector berjenjang**: Cabang → Mini POP → (opsional) ODP. Pilih sampai
  Mini POP saja = lihat semua aset yang `current_pop_id` = Mini POP itu
  **plus** rollup semua ODP di bawahnya (buat audit cepat "Mini POP C1 lengkap
  infrastrukturnya apa, termasuk semua ODP-nya"). Pilih sampai ODP spesifik =
  cuma aset di ODP itu.
- **Isi tabel**: SN (SERIALIZED, status=DEPLOYED, dari `inventory_serials`) +
  nama item + tanggal dipasang (`deployed_at`, dari ledger `created_at` baris
  `deploy` terakhir) + saldo QUANTITY (splitter banyak, kabel sisa, dari
  **`infrastructure_balances`** §3.5) kalau ada di node itu.
- **Breadcrumb** di atas tabel: `Cabang Jetis → Mini POP C1 → ODP B` — jawab
  langsung pertanyaan "ODP B terpasang di Mini POP mana" tanpa klik tambahan.
- **Link "Riwayat"** per baris SN → lempar ke Lacak Barang (`warehouse.traceability.show`)
  yang sudah ada, reuse penuh, tidak reimplementasi tampilan riwayat ledger.
- **Scope POP**: sama trait `AuthorizesWarehousePop` — `pop_admin` cuma lihat
  node (Mini POP/ODP) di bawah Cabang scope-nya.

### 5.1 Master ODP juga dapat breadcrumb

Halaman Master ODP (`/master/odp`) list & detail menampilkan kolom/baris
"Mini POP" + "Cabang" (join `pop_id` naik 2 level) — ini **statis dari
struktur data**, tidak butuh ledger, beda sumber dari §5 di atas yang
menjawab "isinya apa", bukan "dia anak siapa".

## 6. RBAC & Scope

- Permission baru: `odps.{view,create,update,delete}` (Master ODP, generate
  dari `features`×`actions`), `warehouse_location_asset.view` (halaman §5).
- Tidak ada permission baru untuk `deploy`/`retrieve_infra` — reuse
  `warehouse_issue.create`/`warehouse_reassign.create` sesuai konteks laporan
  (mengikuti pola reuse permission yang konsisten di seluruh modul Gudang,
  lihat `docs/warehouse/business-logic.md` §11).
- Scope Mini POP/ODP **ikut Cabang induknya** — `pop_admin` dengan scope
  Cabang Jetis otomatis boleh lihat/kelola semua Mini POP + ODP di bawah
  Jetis, konsisten `EffectiveAccessService::resolvePopTree()` yang sudah ada
  (ODP tidak masuk pohon RBAC langsung karena bukan baris `pops`, tapi
  resolusinya didapat cukup lewat `odp.pop_id` → `mini_pop.parent_id` →
  Cabang, 2 lookup, tidak butuh `resolvePopTree()` diubah).

## 7. Open Items (belum diputuskan, perlu digali lagi sebelum implementasi)

Status per 2026-10-09 (sesi lanjutan sebelum writing-plans) — 5 item di atas
**sudah diputuskan**, dipertahankan di sini sebagai jejak keputusan:

1. **RESOLVED — `OwnershipMode` tetap property `Item` statis.** Keputusan
   user: kalau fisik barang sama (splitter 1x8) tapi dipakai di 2 konteks
   (ke pelanggan vs ke ODP), admin bikin **2 kode Item master beda**
   (misal `SPLITTER-1X8-PEL` = `INSTALLABLE`, `SPLITTER-1X8-ODP` =
   `INFRASTRUCTURE_ASSET`) — bukan ubah axis jadi property transaksi. Tidak
   ada perubahan struktur `Item`/enum di luar penambahan case §3.2.
2. **DEFERRED — jalur admin assign langsung tanpa laporan teknisi.** Di luar
   scope plan ini. Semua `deploy`/`retrieve_infra` lewat laporan O-REQ/INFR-REQ
   teknisi (§4). Kalau nanti dibutuhkan, itu rancangan/plan terpisah.
3. **DECIDED — `capacity_port` murni informasional.** Ikut prinsip
   Distribution (`docs/master/distribution/business-logic.md` §3): dicatat,
   tidak ada guard "ODP penuh". Kolom tetap ada (nullable) buat catatan
   admin, tidak ditegakkan Service.
4. **DEFERRED — field survey `nearest_odp` (teks bebas) tidak disentuh.**
   Tetap teks bebas, terpisah dari master `odps` baru. Penyatuan (migrasi
   ke dropdown `Odp`) di luar scope plan ini.
5. **IN SCOPE — penyesuaian `adjustSerialStatus()`.** Masuk sebagai task
   di plan: SN `status=DEPLOYED` yang diadjust ke `LOST`/`DAMAGED`/`SCRAPPED`/
   `QUARANTINE` wajib mengosongkan `current_pop_id`/`current_odp_id` juga
   (pola sama pengosongan `customer_id` saat SN `INSTALLED` diadjust, Gudang
   business-logic §7/§12).

## 8. Dokumen Terkait

- `docs/warehouse/README.md`, `business-logic.md`, `database-schema.md` —
  konvensi ledger yang diikuti (bukan diubah struktural).
- `docs/master/pop/business-logic.md` — pola hierarki & scope yang ditiru
  untuk `odps`.
- `docs/master/distribution/business-logic.md` — contoh kenapa Distribution
  sengaja dipisah dari ODP (§2 keputusan di atas).
- `docs/fop-task/rancangan-request-pemasangan-dan-material-task.md` — pola
  estimasi vs terpakai yang relevan buat laporan O-REQ/INFR-REQ.
