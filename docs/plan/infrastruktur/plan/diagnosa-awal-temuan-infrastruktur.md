# Diagnosa Awal: Kondisi Penyusunan, Laporan, dan Barang Terpasang Infrastruktur

Tanggal: 17 November 2026
Status: **diagnosa — dasar untuk `analisa-penyederhanaan-infrastruktur-skala-10k.md`** (folder yang sama).
Urutan baca: **dokumen ini dulu**, baru dokumen analisa. Dokumen ini menjelaskan keadaan sistem *sekarang* dan kenapa masalahnya muncul. Dokumen analisa berisi solusi dan urutan fasenya.

## 1. Kenapa dokumen ini ada

User (2026-11-17) bertanya apakah ada cara lain supaya tiga pekerjaan ini lebih mudah:

1. **Penyusunan Infrastruktur** — mencatat hierarki jaringan di aplikasi.
2. **Laporan Infrastruktur** — teknisi melaporkan pekerjaan jaringan.
3. **Pencatatan Barang Gudang yang terpasang di Infrastruktur** — tahu barang apa menempel di titik mana.

Syarat tambahan dari user: solusinya harus tetap mudah **dan** cepat saat data infrastruktur sudah besar, contohnya **±10.000 node** (≈80–100 ribu port, ≈50 ribu baris barang terpasang).

Agent atau session berikutnya yang membaca dokumen analisa tanpa konteks percakapan akan bingung kenapa solusi tertentu dipilih. Dokumen ini menutup celah itu.

## 2. Gambaran modul saat ini

### 2.1 Hierarki yang berlaku

```
Server Utama (POP Pusat)
 └─ POP Cabang
     └─ Mini POP (opsional)
         ├─ OLT ─ Slot ─ PON ─ ODP ─ (port ODP) ─ ONT pelanggan
         └─ Switch Mini POP ─ Slot ─ Port Switch
```

- Semua titik di bawah POP adalah baris `network_nodes` (tipe: `server`, `olt_chassis`, `switch_mini_pop`, `olt_slot`, `olt_pon`, `switch_slot`, `switch_port`, `odp`). Slot/PON/Port Switch juga node sejak ADHOC-213.
- Relasi induk hanya lewat `parent_id` (+ `anchor_pop_id` untuk node yang menempel langsung ke POP). Belum ada kolom jalur (`path`) atau kedalaman (`depth`).
- Port ODP ada di tabel `network_node_ports`. Sambungan port ke pelanggan atau anak node ada di `network_port_assignments`.
- Scope akses mengikuti POP user (`EffectiveAccessService`). Node mewarisi POP dari induknya.

### 2.2 Layar yang dipakai sekarang

| Pekerjaan | Layar | Pengguna |
|---|---|---|
| Menyusun titik & port | Master Node Jaringan & Port (`/network/nodes`), Generate Slot & PON | Operator/NOC |
| Melihat & memverifikasi | Peta Infrastruktur (`/network/peta`), tab Topologi/Pohon/Inventaris/Barang Terpasang | FOP/NOC |
| Menyerahkan barang | Issue Gudang → custody teknisi | Gudang |
| Melaporkan pekerjaan | Task INFR REQ / O-REQ → Laporan Maintenance bagian Infrastruktur | Teknisi |
| Mencatat barang lama | Registrasi Aset per node (`network.nodes.register-asset.create`) | Operator |
| Melihat barang terpasang | Gudang → Barang Terpasang di Infrastruktur (`/warehouse/infra-stock`, tab SN/Qty/Roll) | Gudang |

Panduan langkah demi langkahnya ada di `docs/plan/infrastruktur/manual-book-penataan-infrastruktur.md` (versi 2026-10-24).

### 2.3 Sumber data barang terpasang

- **SN:** `inventory_serials` dengan status `deployed` dan kolom lokasi `current_*` (node/slot/port/mini POP/POP).
- **Qty non-kabel:** `infrastructure_balances` (saldo per barang + lokasi).
- **Kabel (roll):** **tidak** punya saldo per lokasi (§16.4 #2). Posisi kabel diturunkan dari ledger `inventory_transactions` (DEPLOY, RETRIEVE_INFRA, ADJUSTMENT `infra_record_correction`).
- **Ledger** `inventory_transactions` mencatat seluruh riwayat dengan kolom `from_*`/`to_*` per tingkat lokasi.

## 3. Alur kerja nyata hari ini

### 3.1 Menambah satu ODP baru

1. Pastikan OLT sudah ada. Kalau belum, buat lewat form Tambah Node.
2. Jalankan Generate Slot & PON di OLT itu (sekali per OLT).
3. Buka PON tujuan, lalu isi form Tambah ODP: tipe, PON induk, **kode diketik manual**, nama, kapasitas port, lokasi.
4. Simpan. Node langsung berstatus `active`, walau fisiknya belum terpasang.
5. Gudang Issue barang ke teknisi.
6. Teknisi melapor DEPLOY lewat laporan INFR REQ.
7. Port disambung satu per satu.

Tidak ada import massal. 1.000 ODP berarti ±1.000 kali isi form.

### 3.2 Teknisi melapor pemasangan

Contoh pasang ODP + splitter + kabel + sambung port:

- Tiap baris aksi wajib mengisi ulang lokasi lewat 4–6 dropdown bertingkat (POP → Mini POP → OLT → Slot → PON → ODP).
- Total ±39 isian, 4 klik "tambah aksi", dan ±20 kali memuat dropdown.
- Halaman laporan memuat **semua node, semua port, dan semua riwayat DEPLOY** satu Cabang sekaligus ke dalam HTML.

### 3.3 Mencatat barang yang sudah terpasang sebelum sistem ada

- Hanya barang ber-SN, satu SN per submit, satu node per halaman.
- Barang qty dan kabel lama **belum punya jalur sama sekali**.
- Belum ada opname untuk barang yang terpasang di jaringan (opname yang ada hanya untuk stok gudang).

## 4. Akar masalah

Masalahnya bukan fitur yang hilang. Ada dua akar masalah:

### 4.1 Ribet — langkahnya manual dan berulang

| Gejala | Penyebab di kode |
|---|---|
| Node dibuat satu per satu | Hanya ada `NetworkNodeController::store` (form tunggal), tidak ada jalur import |
| Kode ODP diketik manual | Tidak ada saran kode otomatis untuk ODP (beda dengan Slot/PON yang diturunkan otomatis) |
| Peta "bohong" saat rencana | `store` menulis status `active` hardcode, tidak ada status rencana |
| Lokasi diisi ulang tiap baris | Form laporan menyimpan lokasi per baris, tidak ada lokasi bersama di header |
| Tidak bisa cari titik | Pemilih lokasi hanya cascade dropdown, tanpa pencarian |
| Barang lama tidak tercatat | `registerExistingInfraSerial` hanya untuk SN tunggal, tidak ada versi qty/roll atau batch |

### 4.2 Berat — biaya per halaman naik seiring jumlah data

Pola yang berulang: **memuat seluruh node dalam scope ke memori, lalu menyaring di PHP.**

| Tempat | Apa yang dimuat |
|---|---|
| Master Node index | Semua node non-posisi + port + pelanggan, tanpa paginasi |
| Peta `InfrastructureMapService::build()` | Semua node dalam scope, dua kali |
| Laporan Infrastruktur | Semua node + port + riwayat DEPLOY satu Cabang, ditanam sebagai JSON |
| Tab Barang Terpasang Peta & export CSV | Semua node + semua barang, lalu dipotong di PHP |
| Cek scope satu node (`NetworkNode::scopeInPops`) | Semua ID node scope lewat recursive CTE |

Tanpa kolom `path`, pertanyaan seperti "node ini di bawah siapa?" atau "apa saja turunan node ini?" dijawab dengan rekursi di setiap request. Di ratusan node hal ini tidak terasa. Di 10k node hal ini jadi lambat dan berisiko kehabisan memori.

## 5. Bug yang ditemukan saat diagnosa

Keduanya **sudah dibuktikan lewat test dan diperbaiki di ADHOC-302** (2026-11-17):

1. **Tab Roll menghitung meter yang pernah dipasang, bukan yang terpasang sekarang.**
   - Dulu: `WarehouseInfraStockController::paginateRoll()` hanya menjumlah DEPLOY.
   - Sekarang: saldo bersih per barang + lokasi (+DEPLOY, ±koreksi, −cabut). Test `tests/Feature/Warehouse/InfraStockRollShowsCurrentlyInstalledNotEverDeployedTest.php`.
2. **Sambung port tidak bisa dipakai di mode Hierarki.**
   - Dulu: `targetNodes()` di `resources/js/task-infrastructure-report.js` memfilter `row.network_node_id` yang selalu kosong di mode Hierarki, jadi daftar Node tujuan kosong.
   - Sekarang: pakai `portNodeId(row)`. Test JS `tests/task-infrastructure-report.test.mjs` dan test server `TaskInfrastructureHierarchicalLocationTest::sambung_port_mode_hierarki_*`.

Catatan: perbaikan Roll belum menyelesaikan masalah skalanya. Query masih menjumlah seluruh ledger kabel tiap halaman. Itu masuk Fase 0 di dokumen analisa.

## 6. Yang tidak termasuk diagnosa ini

- Konfigurasi perangkat (OLT/switch) lewat API. Peta tidak melakukan konfigurasi.
- Alur pelanggan/ONT di luar pemilihan port ODP.
- Billing dan modul lain.

## 7. Langkah berikutnya

Baca `analisa-penyederhanaan-infrastruktur-skala-10k.md`: prinsip desain skala 10k (§4), solusi per fitur (§5), urutan fase (§6), dan keputusan yang masih menunggu user (§8).
