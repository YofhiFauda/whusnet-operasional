# Slot, PON, dan Port Switch Menjadi Node Jaringan

Tanggal: 2026-10-24. Status: menunggu review. Jalur: arsitektural.

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
  `positions/port` disesuaikan ke `NetworkNode`; tidak dibuat ulang.
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
