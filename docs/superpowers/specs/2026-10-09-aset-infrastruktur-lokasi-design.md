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

Konsisten dengan prinsip yang sudah ada di dokumen Gudang §12 ("axis independen,
`current_pop_id`/`current_technician_id` sengaja dibiarkan sebagai jejak
forensik") — satu kolom, makna ditentukan `status` yang mendampinginya, bukan
constraint DB. Hanya `pusat`/`cabang`/`mini_pop` yang valid untuk kombinasi ini
(Service yang menegakkan, sama pola `AuthorizesWarehousePop`).

### 3.5 `inventory_balances` — kolom lokasi baru (barang QUANTITY)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `odp_id` | FK `odps` nullable, null-on-delete | Mirror `pop_id` — proyeksi saldo QUANTITY yang netap di ODP (contoh: 40 meter slack dropcore tersisa di ODP B) |

Unique index diperluas: `(pop_id, odp_id, item_id, lot_no)` — salah satu dari
`pop_id`/`odp_id` terisi, yang lain `null` (exclusive, ditegakkan Service).

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
`issue`/`return`/`install` yang sudah ada — tidak ada mekanisme ledger baru,
cuma kombinasi kolom baru untuk `type` baru. Ledger append-only, row locking,
dan `WarehouseStockAsOfService` replay logic tetap berlaku tanpa modifikasi
struktural (cuma tambah cabang `to_odp_id`/`from_odp_id` di query SUM).

## 4. Alur Pasang/Ganti Aset lewat O-REQ/INFR-REQ

Mengikuti pola **integrasi Task Teknisi ↔ Gudang** yang sudah ada
(`InventoryService`, lihat `docs/warehouse/business-logic.md` §6), bukan jalur baru:

```
1. Teknisi Issue dari Gudang Cabang (pola sama: InventoryIssueService)
   → custody teknisi (QUANTITY) atau SN status=ISSUED (SERIALIZED)

2. Teknisi kerjakan task O-REQ/INFR REQ, submit Laporan
   (form baru, pola sama "Laporan Pemasangan"/"Laporan Device Retrieval")
   → pilih target lokasi: Mini POP / ODP (dropdown berjenjang
     Cabang → Mini POP → ODP, pola sama modal "Atur Mini POP & Distribusi")
   → pilih SN yang dipasang (SERIALIZED) dan/atau qty dari custody (QUANTITY)

3. InventoryService::deployToLocation() (method baru, sejajar installSerial())
   - SERIALIZED: SN → status=DEPLOYED, current_pop_id ATAU current_odp_id diisi
     (exclusive), current_technician_id dikosongkan. Ledger `deploy`.
   - QUANTITY: consumeFromCustody() (SUDAH ADA, tidak diubah) kurangi custody,
     lalu baris `deploy` ke to_pop_id/to_odp_id menambah inventory_balances
     di lokasi tujuan. Guard OwnershipMode::INFRASTRUCTURE_ASSET ditegakkan
     di titik ini — satu-satunya pintu transisi, sama prinsip installSerial().

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
- **Isi tabel**: SN (SERIALIZED, status=DEPLOYED) + nama item + tanggal
  dipasang (`deployed_at`, dari ledger `created_at` baris `deploy` terakhir) +
  saldo QUANTITY (splitter banyak, kabel sisa) kalau ada di node itu.
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

1. **`OwnershipMode` per-Item vs per-transaksi** (§3.2) — kalau satu Item
   (misal splitter 1x8) bisa jadi `INSTALLABLE` (ke pelanggan) DAN
   `INFRASTRUCTURE_ASSET` (ke ODP) tergantung konteks pemakaian, axis ini
   gak bisa lagi murni property `Item` statis seperti sekarang. Butuh
   keputusan: pisah jadi 2 Item master (`SPLITTER-1X8-PELANGGAN` vs
   `SPLITTER-1X8-ODP`) walau fisiknya identik, atau `OwnershipMode` jadi
   kolom di `inventory_transactions`/laporan (dipilih saat deploy), bukan
   di `Item`.
2. **Jalur admin assign langsung tanpa laporan teknisi** (§4.1) — perlu
   atau tidak, dan kalau perlu, permission apa yang menggerakkannya.
3. **Kapasitas ODP** (`capacity_port`) — cuma informasional, atau nanti ada
   guard "ODP penuh, gak bisa deploy splitter baru"? Keputusan Distribution
   (§3 `docs/master/distribution/business-logic.md`) eksplisit menolak
   tracking kapasitas — perlu dikonfirmasi apakah ODP baru ini mau beda
   prinsip atau ikut yang sama (murni informasional).
4. **Field survey existing `nearest_odp`** (teks bebas) — apakah nanti
   diganti jadi dropdown pilih `Odp` master yang sebenarnya, atau dibiarkan
   teks bebas terpisah dari master ODP baru ini (dua sumber kebenaran ODP
   kalau tidak disatukan).
5. **Laporan Rusak/Hilang** — adjustment existing (`InventoryAdjustmentService`)
   untuk SN `status=DEPLOYED` di lokasi: perlu dicek apakah
   `adjustSerialStatus()` yang sudah ada otomatis cukup (kemungkinan iya,
   karena method itu generic per-SN tidak peduli status asal) atau perlu
   penyesuaian kecil untuk mengosongkan `current_odp_id` juga (seperti pola
   mengosongkan `customer_id` di §12 Gudang business-logic saat SN
   `INSTALLED` diadjust).

## 8. Dokumen Terkait

- `docs/warehouse/README.md`, `business-logic.md`, `database-schema.md` —
  konvensi ledger yang diikuti (bukan diubah struktural).
- `docs/master/pop/business-logic.md` — pola hierarki & scope yang ditiru
  untuk `odps`.
- `docs/master/distribution/business-logic.md` — contoh kenapa Distribution
  sengaja dipisah dari ODP (§2 keputusan di atas).
- `docs/fop-task/rancangan-request-pemasangan-dan-material-task.md` — pola
  estimasi vs terpakai yang relevan buat laporan O-REQ/INFR-REQ.
