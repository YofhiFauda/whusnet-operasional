# Rancangan UI dan UX Modul Gudang (Warehouse)

## 1. Pemetaan Fitur & Hak Akses

┌───────────────────────────┬───────────────┬────────────────────────────────────────────────────────────────────────┐
│         Feature           │    Halaman    │                                Fungsi                                  │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse                 │ Dashboard &   │ Stat kartu stok, KPI, Riwayat Mutasi (semua transaksi lintas gudang)   │
│                           │ Ledger        │                                                                        │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_transfer        │ Transfer      │ Pusat kirim barang ke Cabang (dispatch) + Cabang konfirmasi terima —   │
│                           │ Antar Gudang  │ scan validasi / konfirmasi fisik                                       │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_issue           │ Serah Terima  │ Cabang kasih barang ke teknisi (Issue) — scan validasi                 │
│                           │ ke Teknisi    │                                                                        │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_custody         │ Custody       │ Admin/gudang lihat barang di tangan SEMUA teknisi (Custody sendiri     │
│                           │ Teknisi       │ teknisi ada di halaman Task-nya, tanpa permission terpisah)            │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_traceability    │ Lacak         │ Cari 1 SN → riwayat lengkap dari masuk sampai ke pelanggan —           │
│                           │ Barang/SN     │ Single/Batch Assign via scan                                           │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_adjustment      │ Adjustment    │ Lapor Rusak/Hilang/Scrapped (wajib bukti foto/BAP) + Stock Opname      │
│                           │ Stok          │                                                                        │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_reassign        │ Reassign      │ Pindahkan barang dari teknisi resign/cuti ke teknisi lain atau balikin │
│                           │ Custody       │ ke gudang cabang                                                       │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_report          │ Laporan       │ Agregat periodik — Pergerakan Barang & Kerugian per gudang / bulan     │
│                           │ Gudang        │                                                                        │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_stock_request   │ Permintaan    │ Cabang ajukan permintaan stok ke Pusat (tiket komunikasi/request —     │
│                           │ Stok          │ pemenuhan tetap diteruskan ke Transfer Antar Gudang)                   │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_transfer.create │ Barang Masuk  │ Proses penerimaan barang masuk dari distributor/vendor ke Pusat/Cabang │
│ (Receive)                 │ (Receive)     │ validasi SN/MAC, penomoran lot kabel fiber, dan referensi nota/DO      │
├───────────────────────────┼───────────────┼────────────────────────────────────────────────────────────────────────┤
│ warehouse_adjustment.     │ Ambang Stok   │ Pengaturan batas minimum stok per item & per gudang (Threshold)        │
│ create (Threshold)        │ Rendah        │ untuk memicu peringatan restock dini                                   │
└───────────────────────────┴───────────────┴────────────────────────────────────────────────────────────────────────┘

---

## 2. Rancangan & Spesifikasi Dashboard Gudang

### 2.1 Komponen & Metrik Dashboard
1. **Stat Card Total Barang**: Total SKU aktif dan estimasi kuantitas fisik yang tercatat di sistem.
2. **Stat Card ONT / Router Siap Pasang**: Unit perangkat serial dengan status `AVAILABLE` di gudang yang siap diinstalasi pelanggan baru.
3. **Stat Card Stok Transit (Total Barang di Jalan)**: Total unit/material yang sedang dalam perjalanan transfer antar gudang (status dikirim dari Pusat namun belum di-konfirmasi terima oleh Cabang).
4. **Stat Card Barang di Tangan Teknisi (Custody)**: Total modem dan sisa meter kabel yang sedang dibawa teknisi lapangan.
5. **Stat Card Kepatuhan Stock Opname**: Status kepatuhan audit fisik (Persentase/Jumlah item Sudah Opname vs Belum Opname pada periode berjalan).
6. **Arus Barang Hari Ini per POP**: Throughput harian (Jumlah barang masuk/inbound vs barang keluar/issue ke teknisi hari ini).
7. **Peringatan Stok Rendah**: Daftar item dengan stok kritis ($Qty \le MinimumStock$) dilengkapi progress bar visual dan tombol aksi pengadaan/transfer.
8. **List Stok Opname Jatuh Tempo**: Daftar item yang paling lama tidak diaudit fisik (>30/60 hari) untuk prioritas jadwal audit.
9. **Ringkasan Karantina / Rusak**: Unit berstatus `DAMAGED` atau `QUARANTINE` (misal tarikan bekas pelanggan) yang menunggu proses BAP/Scrap/Retur.
10. **Riwayat Mutasi Terbaru (Ledger Log)**: 10–25 transaksi terakhir append-only dengan badge aksi berwarna dan link ke dokumen sumber.

---

### 2.2 Wireframe Layout Dashboard (Responsive 4-Zone)

```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [Header] Sapaan & Status POP Aktif   │ [Filter Scope POP ▼] (HQ) │ [+ Quick Actions Bar]         │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 1: METRIC KPI CARDS (Grid 5 Kolom)                                                          │
│ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌──────────────────────┐ │
│ │ Total Stok    │ │ ONT / Router  │ │ Stok Transit  │ │ Custody       │ │ Kepatuhan Opname     │ │
│ │ SKU & Fisik   │ │ Siap Pasang   │ │ (Di Jalan)    │ │ di Teknisi    │ │ Sudah vs Belum Audit │ │
│ └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘ └──────────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 2: OPERASIONAL HARIAN & PERINGATAN KRITIS (Grid 2 Kolom: 7 / 5)                             │
│ ┌──────────────────────────────────────────────┐ ┌─────────────────────────────────────────────┐ │
│ │ 📥 Arus Barang Hari Ini (Inbound vs Outbound)│ │ ⚠️ Peringatan Stok Rendah (Kritis)          │ │
│ │ • Masuk: +50 ONT, +2 Drum Kabel              │ │ • Dropcore 1 Core (Cabang Siman: Sisa 150m) │ │
│ │ • Keluar: -12 ONT ke Teknisi                 │ │ • ONT Huawei (Cabang Babadan: Sisa 2 unit)  │ │
│ │ • Transfer Aktif: TRF-2026-0012 (In-Transit) │ │ [Progress Bar Sisa vs Min] [Aksi Restock]   │ │
│ └──────────────────────────────────────────────┘ └─────────────────────────────────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 3: AUDIT & KONTROL INTERNAL (Grid 2 Kolom: 6 / 6)                                           │
│ ┌──────────────────────────────────────────────┐ ┌─────────────────────────────────────────────┐ │
│ │ 📋 Item Jatuh Tempo Stock Opname             │ │ 🧰 Ringkasan Custody Teknisi & Karantina    │ │
│ │ (Item terlama belum diaudit fisik)           │ │ • Top teknisi dengan custody aktif terbanyak│ │
│ │ • Patchcord SC (Terakhir opname: 45 hari yl) │ │ • 3 ONT status QUARANTINE (Menunggu BAP)    │ │
│ │ [Tombol: Mulai Opname Sekarang]              │ │ [Lihat Detail Custody →]                    │ │
│ └──────────────────────────────────────────────┘ └─────────────────────────────────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 4: BUKU BESAR MUTASI TERBARU (Full Width)                                                  │
│ ┌──────────────────────────────────────────────────────────────────────────────────────────────┐ │
│ │ 📜 Riwayat Mutasi Terbaru (Ledger 10-25 Transaksi Terakhir)                                  │ │
│ │ Waktu │ Tipe Aksi │ Barang / Serial Number │ Dari ➔ Ke │ Qty Mutasi │ Dokumen Sumber         │ │
│ └──────────────────────────────────────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 2.3 Rincian Perilaku Interaktif & Hak Akses per Zona

#### A. Header & Filter Bar
* **Scope Selector**:
  * Role `owner` & `admin` (HQ): Bebas memilih filter *"Semua POP (Nasional)"* atau memilih salah satu cabang.
  * Role `pop_admin` (Admin Cabang): Otomatis terkunci pada data cabangnya sendiri via `EffectiveAccessService`.
* **Quick Actions Bar**:
  * Tombol pintasan: `[+ Terima Barang]`, `[+ Buat Transfer]`, `[+ Serah ke Teknisi]`, `[+ Stock Opname]`.

#### B. Zona 1: Elevated Stat Cards (Atas)
* **Highlight Visual**:
  * *Stok Transit*: Badge oranye/kuning berkedip jika ada barang yang belum dikonfirmasi terima.
  * *ONT Siap Pasang*: Angka tebal warna hijau (emerald) menunjukkan kesiapan pasang baru.

#### C. Zona 2: Operasional Harian & Alert Kritis
* **Throughput Hari Ini**: Menghitung delta barang masuk (`RECEIVE`), transfer keluar/masuk (`TRANSFER`), dan serah terima teknisi (`ISSUE`) sejak pukul 00:00 hari berjalan.
* **Tabel Stok Rendah**: 
  * Menampilkan bar persentase sisa stok terhadap nilai threshold minimum.
  * Tombol aksi cepat: Cabang ➔ `[Ajukan Permintaan Stok]`, Pusat ➔ `[Kirim Transfer ke Cabang]`.

#### D. Zona 3: Audit & Kontrol Internal
* **Opname Jatuh Tempo**: Query item berdasarkan `max(created_at)` dari transaksi berjenis `STOCK_OPNAME`. Item dengan selisih hari terbesar muncul di urutan teratas.
* **Karantina & Pengawasan Custody**: Menampilkan rekap unit rusak/karantina serta memantau teknisi yang membawa material tanpa ada laporan instalasi baru.

#### E. Zona 4: Riwayat Mutasi Terbaru
* **Audit Trail**: Menggunakan tabel ledger append-only `inventory_transactions`.
* **Navigasi Langsung**: Klik pada baris mutasi membuka dokumen terkait (`TRF-XXXX`, `ISS-XXXX`, atau halaman `Traceability` serial number terkait).

---

## 3. Rancangan & Spesifikasi Management Stock (Kelola Stok Gudang)

Halaman **Management Stock (Kelola Stok)** berfungsi sebagai **Hub Operasional Utama**. Pengguna dapat melihat saldo fisik stok di setiap gudang serta langsung mengeksekusi aksi mutasi tanpa perlu berpindah-pindah menu.

### 3.1 Perbedaan Kapabilitas: Gudang Pusat vs Gudang Cabang

| Alur & Kapabilitas | 🏢 Gudang Pusat (HQ) | 🏬 Gudang Cabang (POP) |
|---|:---:|:---:|
| **Penerimaan Barang Baru (Inbound)** | ✅ Penerimaan utama dari supplier/distributor | ⚠️ Terbatas (Hanya pengadaan darurat lokal) |
| **Transfer Stok Keluar (Dispatch)** | ✅ Distribusi stok Pusat ➔ Gudang Cabang | ❌ Tidak diizinkan antar-cabang sembarangan |
| **Konfirmasi Terima Transfer** | — | ✅ Wajib cek fisik & verifikasi SN in-transit |
| **Permintaan Stok (Stock Request)** | ✅ Evaluasi & Fulfill permintaan Cabang | ✅ Mengajukan tiket permintaan ke Pusat |
| **Serah Terima ke Teknisi (Issue)** | ✅ Khusus teknisi Pusat / Backbone | ✅ Pengeluaran ke teknisi pasang baru/MT cabang |
| **Monitoring Custody & Tarik Barang** | ✅ Seluruh teknisi nasional | ✅ Khusus teknisi yang bertugas di cabang tersebut |
| **Audit Fisik & Ambang Stok Rendah** | ✅ Stock Opname & Threshold Pusat + Cabang | ✅ Stock Opname & Threshold khusus cabangnya |
| **Scope Tampilan Data** | Multi-POP (Pusat & Seluruh Cabang) | Terkunci otomatis pada Gudang Cabang terkait |

---

### 3.2 4 Pilar Fungsional Management Stock

1. **Pencarian & Visibilitas Cerdas (Discovery)**:
   * Pencarian fleksibel: Nama item, SKU / kode barang, nomor lot drum kabel, dan pencarian serial number (SN).
   * Filter terpadu: Gudang POP, Kategori Barang, Jenis Tracking (*Serialized*, *Batch/Lot*, *Quantity*).
   * Quick filter status kesehatan stok: *Semua Stok*, *Stok Aman*, *Menipis/Kritis ($Qty \le Min$)*.
2. **Operasional Keluar & Masuk (Movement Hub)**:
   * Pintasan aksi penerimaan barang (`Receive`), transfer antar gudang (`Transfer`), serah terima teknisi (`Issue`), dan pengajuan stok (`Request`).
3. **Pengendalian & Audit (Control & Integrity)**:
   * Akses cepat `Stock Opname` berkala per item/gudang.
   * Penyesuaian stok selisih/rusak/hilang (`Adjustment`) dengan kewajiban upload bukti foto/BAP.
   * Pengaturan batas minimum stok (`Threshold`) per item per gudang.
4. **Detail Stok & Pelacakan (Item Stock Details)**:
   * Menampilkan rincian komposisi stok fisik: Daftar SN unit yang tersedia, sisa meter per drum lot, dan riwayat mutasi per item.

---

### 3.3 Wireframe Layout Halaman Kelola Stok (`/warehouse/stock`)

```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [Header] Kelola Stok Gudang  │  [Indikator Mode: Gudang Pusat / Cabang Siman]                    │
│                                                                                                  │
│ [Tombol Aksi Cepat Kontekstual Berdasarkan Role & Scope]:                                        │
│   • Jika PUSAT  : [+ Terima Barang Baru]  [+ Kirim Transfer ke Cabang]  [+ Serahkan ke Teknisi]  │
│   • Jika CABANG : [+ Ajukan Permintaan Stok]  [+ Konfirmasi Terima Transfer]  [+ Serah Teknisi]   │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 🔍 TOOLBAR FILTER & PENCARIAN (Multi-Dimension Filter)                                           │
│ ┌───────────────────────────┬──────────────────────┬──────────────────────┬────────────────────┐ │
│ │ Cari Nama / SKU / Lot / SN│ Pilih Gudang POP ▼   │ Filter Jenis Tracking│ Filter Status Stok │ │
│ │ [Ketik untuk mencari...]  │ [Semua / Cabang X]   │ [Semua / Serial/Lot] │ [Semua / Kritis ⚠️]│ │
│ └───────────────────────────┴──────────────────────┴──────────────────────┴────────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 📦 TABEL DATA STOK BARANG (Interactive Inventory Grid)                                           │
│ ┌──────────────┬──────────────────┬──────────────┬──────────────┬──────────────┬───────────────┐ │
│ │ Gudang POP   │ Detail Barang    │ Jenis & Lot  │ Status Stok  │ Qty Tersedia │ Aksi Cepat    │ │
│ ├──────────────┼──────────────────┼──────────────┼──────────────┼──────────────┼───────────────┤ │
│ │ Pusat        │ ONT Huawei HG8245│ SERIALIZED   │ 🟢 Aman      │ 85 Unit      │ [⋮ Aksi ▼]    │ │
│ │ (Pusat)      │ SKU: ONT-HW-01   │ 85 SN Siap   │ Min: 20 Unit │ (Tersedia)   │ • Kirim Trf   │ │
│ │              │ Kategori: ONT    │              │ Opname: 3 hr │              │ • Serah Tek   │ │
│ │              │                  │              │              │              │ • Opname/Adj  │ │
│ ├──────────────┼──────────────────┼──────────────┼──────────────┼──────────────┼───────────────┤ │
│ │ Cabang Siman │ Dropcore 1C 1000M│ BATCH / LOT  │ 🔴 Kritis    │ 120 Meter    │ [⋮ Aksi ▼]    │ │
│ │ (Cabang)     │ SKU: DC-1C-01    │ Lot: LOT-002 │ Min: 500 Mtr │ (Sisa drum)  │ • Minta Stok  │ │
│ │              │ Kategori: Kabel  │ (1 Drum)     │ Opname: -    │              │ • Serah Tek   │ │
│ │              │                  │              │              │              │ • Atur Ambang │ │
│ ├──────────────┼──────────────────┼──────────────┼──────────────┼──────────────┼───────────────┤ │
│ │ Cabang Bbdn  │ RJ45 Cat5e Pack  │ QUANTITY     │ 🟢 Aman      │ 12 Pack      │ [⋮ Aksi ▼]    │ │
│ │ (Cabang)     │ SKU: RJ45-5E-01  │ Non-Serial   │ Min: 5 Pack  │              │ • Serah Tek   │ │
│ │              │ Kategori: Aksesori│             │ Opname: 12 hr│              │ • Opname/Adj  │ │
│ └──────────────┴──────────────────┴──────────────┴──────────────┴──────────────┴───────────────┘ │
│                                                                                                  │
│ [Pagination] Menampilkan 1 - 25 dari 148 Data Stok                        Halaman 1 dari 6 < 1 2 >│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 3.4 Interaksi & Aksi Baris Data Stok

1. **Badge Interaktif**:
   * Badge `SERIALIZED` dapat diklik untuk membuka modal / panel daftar seluruh Serial Number yang ada di gudang tersebut.
   * Badge `BATCH / LOT` menampilkan info nomor drum dan sisa meter kabel secara riil.
2. **Dropdown Menu Aksi Cepat (`[⋮ Aksi]`)**:
   * **`Kirim Transfer`**: Langsung mengisi form transfer dengan item dan gudang asal terpilih (khusus Pusat).
   * **`Minta Stok`**: Langsung membuka form stock request dengan item terpilih (khusus Cabang).
   * **`Serahkan ke Teknisi`**: Membuka form issue barang ke teknisi cabang.
   * **`Opname / Sesuaikan`**: Membuka form penyesuaian saldo fisik vs sistem.
   * **`Atur Ambang (Threshold)`**: Mengatur minimum stok untuk memicu peringatan restock otomatis.

---

## 4. Rancangan & Spesifikasi Riwayat Mutasi dan Audit Ledger

Halaman **Riwayat Mutasi (`/warehouse/history`)** adalah **Buku Besar Inventori (*Immutable Inventory Ledger*)** yang mencatat seluruh jejak audit pergerakan barang secara permanen (*append-only*). Catatan pada ledger ini tidak dapat diubah (*update*) maupun dihapus (*delete*).

### 4.1 Prinsip Dasar & Integritas Ledger
1. **Append-Only & Immutability**: Dilindungi oleh Observer di backend (`InventoryTransactionObserver`), setiap perubahan stok wajib menghasilkan baris transaksi baru.
2. **Directional Tracking (Asal ➔ Tujuan)**: Setiap mutasi wajib memiliki entitas pengirim/asal (`from_pop_id` / `from_technician_id` / Supplier) dan entitas penerima/tujuan (`to_pop_id` / `to_technician_id` / Pelanggan).
3. **Dokumentasi Terkait (*Source Reference*)**: Setiap baris mutasi terikat pada nomor dokumen sah (`TRF-XXXX`, `ISS-XXXX`, `DO/Invoice`).

---

### 4.2 Detail Data Wajib Tercatat pada Ledger
Setiap baris transaksi mutasi mencantumkan:
* **Waktu & Tanggal Presisi**: Timestamp sistem `created_at` (Format `DD MMM YYYY, HH:mm`).
* **Tipe Mutasi**: Menggunakan enum `InventoryTransactionType` (`RECEIVE`, `TRANSFER`, `ISSUE`, `RETURN`, `ADJUSTMENT`, `STOCK_OPNAME`, `INSTALL`).
* **Identitas Barang**: Nama item, kode SKU, dan nomor drum/lot untuk kabel fiber.
* **Nomor Seri (Serial Number)**: Jika barang bertipe *Serialized* (ONT/Router/Switch), nomor seri ditampilkan sebagai tautan ke *Traceability*.
* **Aliran Fisik (Dari ➔ Ke)**:
  * Asal: Gudang POP Asal / Teknisi Pengembali / Supplier Eksternal.
  * Tujuan: Gudang POP Tujuan / Teknisi Penerima / Pelanggan / Karantina Rusak.
* **Jumlah & Satuan**: Kuantitas mutasi beserta unit satuannya (Pcs, Meter, Roll, Pack).
* **Aktor Pencatat**: Nama lengkap pengguna/petugas yang menginput transaksi ke sistem (`createdBy`).
* **Nomor Referensi Dokumen**: Nomor dokumen acuan yang dapat diklik membuka halaman detail dokumen digital.
* **Keterangan / Catatan**: Catatan peruntukan (misal: "Pasang Baru WO-102", "Restock Bulanan").
* **Lampiran Bukti Fisik (*Evidence*)**: File foto barang/surat BAP untuk transaksi kerugian/penyesuaian stok.

---

### 4.3 Wireframe Layout Riwayat Mutasi (`/warehouse/history`)

```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [Header] Riwayat Mutasi & Audit Ledger Gudang    │ [Tombol: 📥 Export Excel / CSV]               │
│ Subtitle: Buku besar append-only seluruh pergerakan material & perangkat antar gudang/teknisi    │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 🔍 TOOLBAR FILTER KOMPREHENSIF                                                                   │
│ ┌───────────────────────────┬──────────────────────┬──────────────────────┬────────────────────┐ │
│ │ Cari Barang / SN / No Ref │ Tipe Transaksi ▼     │ Gudang POP Asal/Tuj  │ Rentang Tanggal    │ │
│ │ [Ketik SN / SKU / Ref...] │ [Semua Tipe Mutasi]  │ [Semua Gudang]       │ [01/09/2026-05/09] │ │
│ └───────────────────────────┴──────────────────────┴──────────────────────┴────────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 📜 TABEL BUKU BESAR AUDIT (Immutable Ledger Grid)                                                │
│ ┌──────────────┬──────────────┬──────────────────┬──────────────────┬──────────┬───────────────┐ │
│ │ Waktu        │ Tipe Aksi    │ Barang / SN      │ Aliran (Dari ➔ Ke│ Jumlah   │ Aktor & Bukti │ │
│ ├──────────────┼──────────────┼──────────────────┼──────────────────┼──────────┼───────────────┤ │
│ │ 05 Sep 2026  │ [TRANSFER]   │ ONT Huawei HG8245│ Gudang Pusat ➔   │ 10 Unit  │ Budi (Admin)  │ │
│ │ 10:45 WIB    │ Dikirim      │ SN: 10 Unit Terp.│ Cabang Siman     │          │ Ref: TRF-0012 │ │
│ │              │ (Biru)       │ (Klik lihat SN)  │                  │          │ Ket: Pasang br│ │
│ ├──────────────┼──────────────┼──────────────────┼──────────────────┼──────────┼───────────────┤ │
│ │ 05 Sep 2026  │ [ISSUE]      │ Dropcore 1 Core  │ Cabang Siman ➔   │ 150 Mtr  │ Agus (Gudang) │ │
│ │ 09:15 WIB    │ Serah Tek    │ Lot: LOT-2026-001│ Teknisi Rian     │          │ Ref: ISS-0045 │ │
│ │              │ (Indigo)     │ (Kabel Fiber)    │                  │          │ Ket: WO-Siman │ │
│ ├──────────────┼──────────────┼──────────────────┼──────────────────┼──────────┼───────────────┤ │
│ │ 04 Sep 2026  │ [ADJUSTMENT] │ ONT ZTE F609     │ Cabang Babadan ➔ │ -1 Unit  │ Doni (Admin)  │ │
│ │ 16:20 WIB    │ DAMAGED      │ SN: ZTEG12345678 │ Karantina Rusak  │          │ Ref: BAP-003  │ │
│ │              │ (Amber)      │ (Petir Lapangan) │                  │          │ 📷 [Lihat BAP]│ │
│ ├──────────────┼──────────────┼──────────────────┼──────────────────┼──────────┼───────────────┤ │
│ │ 04 Sep 2026  │ [RECEIVE]    │ Patchcord SC 3M  │ Supplier Mitra ➔ │ 200 Pcs  │ Budi (Admin)  │ │
│ │ 14:00 WIB    │ Inbound      │ SKU: PC-SC-03    │ Gudang Pusat     │          │ Ref: DO-88912 │ │
│ │              │ (Hijau)      │ Non-Serial       │                  │          │ Ket: PO Bulann│ │
│ └──────────────┴──────────────┴──────────────────┴──────────────────┴──────────┴───────────────┘ │
│                                                                                                  │
│ [Pagination] Menampilkan 1 - 30 dari 1.420 Catatan Mutasi                 Halaman 1 dari 48 < >   │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 4.4 Detail Interaksi & Visualisasi Ledger

1. **Badge Tipe Aksi Berwarna**:
   * 🟢 `RECEIVE`: Penerimaan barang masuk dari supplier.
   * 🔵 `TRANSFER`: Perpindahan antar gudang (*Transfer Dikirim* / *Transfer Diterima*).
   * 🟣 `ISSUE`: Serah terima dari gudang ke teknisi.
   * 🩵 `RETURN`: Pengembalian sisa material/alat dari teknisi ke gudang.
   * 🟡 `ADJUSTMENT / OPNAME`: Koreksi audit fisik atau penyesuaian barang rusak/hilang.
   * 🟣 `INSTALL`: Perangkat terpasang di rumah pelanggan.
2. **Deep Linking Dokumen Sumber**:
   * Mengklik baris atau nomor referensi langsung membuka dokumen asli (`/warehouse/receive/{ref}`, `/warehouse/transfers/{id}`, `/warehouse/issues/{ref}`).
   * Mengklik nomor SN langsung membuka riwayat lengkap unit di halaman `/warehouse/traceability?sn=...`.
3. **Bukti Foto / BAP Kerugian**:
   * Kolom aksi/bukti menampilkan thumbnail foto yang dapat diklik untuk memperbesar (*modal preview lightbox*) foto kerusakan perangkat.

---

## 5. Rancangan & Spesifikasi Permintaan Stok (Stock Request)

Halaman **Permintaan Stok (`/warehouse/stock-requests`)** adalah saluran komunikasi formal berbasis tiket bagi Gudang Cabang untuk mengajukan penambahan stok material/perangkat ke Gudang Pusat ketika persediaan menipis atau untuk persiapan pekerjaan skala besar.

### 5.1 Alur Kerja & Siklus Status Permintaan Stok
Permintaan stok **bukan mutasi saldo langsung**, melainkan permohonan yang harus disetujui dan dieksekusi melalui **Transfer Barang (`TRF-XXXX`)** oleh Gudang Pusat.

* **Penomoran Global**: Format `REQ-YYYY-XXXX` (misal: `REQ-2026-0012`).
* **Siklus Status (*State Machine*)**:
  ```text
  [PENDING] ──(Pusat Setujui / Kirim Transfer)──> [FULFILLED] (Selesai)
      │
      ├──(Pusat Tolak + Alasan Wajib)──────────> [REJECTED]  (Ditolak)
      │
      └──(Pengaju Cabang Batal Sendiri)────────> [CANCELLED] (Dibatalkan)
  ```

---

### 5.2 Otorisasi & Hak Akses (RBAC)

| Peran Aktor | Hak Akses & Kewenangan |
|---|---|
| **Gudang Cabang (`pop_admin`)** | • Membuat pengajuan baru (`warehouse_stock_request.create`)<br>• Membatalkan pengajuan miliknya sendiri yang masih pending (`warehouse_stock_request.cancel`) |
| **Gudang Pusat / Admin / Owner** | • Melihat antrean permintaan dari semua cabang (`warehouse_stock_request.view`)<br>• Menandai/Memenuhi permintaan (`warehouse_stock_request.approve`)<br>• Menolak permintaan dengan menyertakan alasan (`warehouse_stock_request.reject`) |

---

### 5.3 Wireframe Layout Permintaan Stok (3 Layar Terpadu)

#### A. Layar 1: Daftar Antrean Permintaan (`/warehouse/stock-requests`)
```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [Header] Permintaan Stok Cabang  │  [Tombol: [+ Ajukan Permintaan Stok]]                         │
│ Subtitle: Antrean permintaan barang dari Gudang Cabang untuk diproses Gudang Pusat               │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 🔍 FILTER STATUS & PENCARIAN                                                                     │
│ ┌──────────────────────────────────────┬───────────────────────────────┬───────────────────────┐ │
│ │ Status: Menunggu Diproses (Pending) ▼│ Cabang: Semua Gudang Cabang ▼ │ [Cari No Ref / Item..]│ │
│ └──────────────────────────────────────┴───────────────────────────────┴───────────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 📋 TABEL ANTREAN PERMINTAAN STOK                                                                 │
│ ┌──────────────┬──────────────┬──────────────┬────────────────────────┬──────────────┬─────────┐ │
│ │ No. Request  │ Cabang       │ Pengaju      │ Rincian Barang Diminta │ Status       │ Waktu   │ │
│ ├──────────────┼──────────────┼──────────────┼────────────────────────┼──────────────┼─────────┤ │
│ │ REQ-2026-0012│ Cabang Siman │ Agus (Admin) │ • 10 Unit ONT Huawei   │ 🟡 PENDING   │ 05 Sep  │ │
│ │              │              │              │ • 2 Drum Dropcore 1C   │ (Menunggu)   │ 09:30   │ │
│ ├──────────────┼──────────────┼──────────────┼────────────────────────┼──────────────┼─────────┤ │
│ │ REQ-2026-0011│ Cabang Bbdn  │ Rian (Gudang)│ • 50 Pcs Patchcord SC  │ 🟢 FULFILLED │ 04 Sep  │ │
│ │              │              │              │                        │ (Dipenuhi)   │ 14:15   │ │
│ ├──────────────┼──────────────┼──────────────┼────────────────────────┼──────────────┼─────────┤ │
│ │ REQ-2026-0010│ Cabang Kauman│ Doni (Admin) │ • 5 Unit Switch 8P     │ 🔴 REJECTED  │ 03 Sep  │ │
│ │              │              │              │                        │ (Stok Pusat 0│ 11:00   │ │
│ └──────────────┴──────────────┴──────────────┴────────────────────────┴──────────────┴─────────┘ │
│                                                                                                  │
│ [Pagination] Menampilkan 1 - 20 dari 64 Permintaan                        Halaman 1 dari 4 < 1 2 >│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

#### B. Layar 2: Form Pengajuan Permintaan (`/warehouse/stock-requests/create`)
```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [← Kembali ke Antrean]  Form Pengajuan Permintaan Stok ke Pusat                                  │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. INFORMASI CABANG PEMOHON                                                                      │
│    Gudang Cabang : [ Cabang Siman (Terkunci otomatis sesuai login) ▼ ]                           │
│    Catatan/Alasan: [ Contoh: Restock kabel & ONT menipis untuk jadwal pasang baru WO Desa Siman ]│
│                                                                                                  │
│ 2. DAFTAR BARANG YANG DIMINTA (Multi-Item Dynamic Rows)                                          │
│    ┌──────────────────────────────────────┬─────────────┬─────────────┬────────────────────────┐ │
│    │ Pilih Item / SKU                     │ Lot (Ops)   │ Qty Diminta │ Aksi                   │ │
│    ├──────────────────────────────────────┼─────────────┼─────────────┼────────────────────────┤ │
│    │ [ ONT Huawei HG8245H5 (Unit)      ▼] │ [ -       ] │ [ 10      ] │ [ 🗑️ Hapus ]          │ │
│    │ [ Dropcore 1 Core 1000M (Meter)   ▼] │ [ LOT-002 ] │ [ 1000    ] │ [ 🗑️ Hapus ]          │ │
│    └──────────────────────────────────────┴─────────────┴─────────────┴────────────────────────┘ │
│    [+ Tambah Baris Barang Lain]                                                                  │
│                                                                                                  │
│ [ Tombol: 🚀 Kirim Permintaan ke Gudang Pusat ]                                                  │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

#### C. Layar 3: Detail & Eksekusi Permintaan (`/warehouse/stock-requests/{id}`)
```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [← Kembali ke Antrean]  Detail Permintaan #REQ-2026-0012                                         │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Status  : 🟡 MENUNGGU DIPROSES (PENDING)                                                         │
│ Pengaju : Agus (Admin Cabang Siman) — Diajukan: 05 Sep 2026, 09:30 WIB                           │
│ Catatan : Restock kabel & ONT menipis di cabang siman untuk pasang baru minggu ini.              │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 📦 RINCIAN BARANG YANG DIMINTA:                                                                  │
│  1. ONT Huawei HG8245H5 : 10 Unit                                                                │
│  2. Dropcore 1 Core     : 1.000 Meter (Lot: LOT-002)                                             │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ⚡ AKSI TINDAKAN (Sesuai Role Aktor):                                                             │
│                                                                                                  │
│ [UNTUK ADMIN PUSAT / OWNER]:                                                                     │
│   1. [ Tombol: 🚚 Buat Transfer Pengiriman ] ➔ Otomatis mengisi form Transfer Pusat ➔ Cabang     │
│   2. [ Tombol: ✅ Tandai Sudah Dipenuhi ]    ➔ Menyelesaikan tiket status FULFILLED              │
│   3. [ Tombol: ❌ Tolak Permintaan ]         ➔ Menampilkan input wajib alasan penolakan          │
│                                                                                                  │
│ [UNTUK PENGAJU CABANG]:                                                                          │
│   • [ Tombol: ⛔ Batalkan Permintaan ]       ➔ Hanya jika status masih PENDING                   │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 5.4 Integrasi dengan Transfer Antar Gudang
Ketika Admin Pusat meninjau permintaan stok yang valid:
1. Admin Pusat menekan tombol **`[Buat Transfer Pengiriman]`**.
2. Sistem otomatis membuka form `/warehouse/transfers/create` dengan data item, jumlah, dan gudang tujuan (Cabang) yang sudah terisi otomatis dari tiket `REQ-XXXX`.
3. Setelah pengiriman fisik selesai dan transfer dibuat, status tiket otomatis ditandai `FULFILLED`.

---

## 6. Rancangan & Spesifikasi Laporan Gudang (Warehouse Reports)

Halaman **Laporan Gudang (`/warehouse/reports`)** menyajikan **Agregasi Periodik (Bulanan/Triwulanan)** yang merangkum tren keluar-masuk barang dan total kerugian untuk evaluasi pimpinan dan audit internal logistik ISP.

### 6.1 Struktur 2 Tab Utama Laporan
Laporan disusun dalam 1 halaman dengan 2 tab agregasi terpadu:

1. **Tab 1: Laporan Pergerakan Barang (*Movement Summary*)**:
   * Menampilkan volume perputaran barang di setiap cabang per bulan.
   * Rincian kolom:
     * **Barang Masuk (Receive)**: Total barang baru yang diterima dari supplier ke Pusat.
     * **Transfer Masuk**: Total barang yang diterima cabang dari Pusat.
     * **Transfer Keluar**: Total barang yang dikirim Pusat ke cabang.
     * **Keluar ke Teknisi (Issue)**: Total material & perangkat yang diserahkan ke teknisi lapangan.
2. **Tab 2: Rekapitulasi Kerugian & Penyesuaian (*Loss & Adjustment Summary*)**:
   * Kontrol audit untuk memantau kerugian material per cabang / teknisi.
   * Kategori kerugian: `DAMAGED` (rusak/petir), `LOST` (hilang), `SCRAPPED` (usang), dan `STOCK_OPNAME_DIFF` (selisih audit fisik).
   * Atribusi sumber: Memisahkan kerugian yang terjadi di **Gudang POP** vs saat berada di **Custody Teknisi**.

---

### 6.2 Otorisasi & Hak Akses (RBAC)

| Peran Aktor | Hak Akses & Scope Laporan |
|---|---|
| **Owner / Admin (HQ)** | Akses penuh melihat laporan seluruh cabang (Nasional) atau filter per cabang (`warehouse_report.view`) |
| **Admin Cabang (`pop_admin`)** | Akses terbatas hanya melihat data laporan di cabangnya sendiri (`warehouse_report.view` scoped) |

---

### 6.3 Wireframe Layout Laporan Gudang (`/warehouse/reports`)

```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [Header] Laporan Agregat Gudang & Logistik       │ [Tombol: 🖨️ Cetak PDF]  [📥 Export Excel]    │
│ Subtitle: Rekapitulasi pergerakan barang dan catatan kerugian per periode bulanan                 │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 🔍 FILTER PERIODE & GUDANG                                                                       │
│ ┌──────────────────────────────┬───────────────────────────────┬───────────────────────────────┐ │
│ │ Periode Bulan: [ 2026-09  ▼] │ Gudang: [ Semua POP (Nasional) ▼ ] │ [Tombol: 🔍 Terapkan]    │ │
│ └──────────────────────────────┴───────────────────────────────┴───────────────────────────────┘ │
│                                                                                                  │
│ [Segmented Tab Switcher]:                                                                        │
│   [ 📦 1. Pergerakan Barang (Movement) ]   [ ⚠️ 2. Rekapitulasi Kerugian (Loss & Adjustment) ]   │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ KARTU KPI RINGKASAN PERIODE (SEPTEMBER 2026):                                                    │
│ ┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────────┐ ┌───────────────────┐ │
│ │ Total Inbound Masuk   │ │ Total Kirim ke Cabang │ │ Total ke Teknisi  │ │ Total Kerugian (BAP)│ │
│ │ +350 Unit / 4 Drum    │ │ 210 Unit (Transfer)   │ │ 180 Unit Terpasang│ │ 3 Unit (Rusak/Petir)│ │
│ └───────────────────────┘ └───────────────────────┘ └───────────────────┘ └───────────────────┘ │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 📊 TABEL TAB 1: PERGERAKAN BARANG PER GUDANG (Jika Tab 1 Aktif)                                  │
│ ┌──────────────┬──────────────┬──────────────┬──────────────┬──────────────┬───────────────────┐ │
│ │ Gudang POP   │ Tipe Gudang  │ Inbound Baru │ Trf Masuk    │ Trf Keluar   │ Keluar ke Teknisi │ │
│ ├──────────────┼──────────────┼──────────────┼──────────────┼──────────────┼───────────────────┤ │
│ │ Gudang Pusat │ PUSAT (HQ)   │ 350 Unit     │ -            │ 210 Unit     │ 25 Unit (Internal)│ │
│ ├──────────────┼──────────────┼──────────────┼──────────────┼──────────────┼───────────────────┤ │
│ │ Cabang Siman │ CABANG       │ -            │ 120 Unit     │ -            │ 95 Unit (Pasang)  │ │
│ ├──────────────┼──────────────┼──────────────┼──────────────┼──────────────┼───────────────────┤ │
│ │ Cabang Bbdn  │ CABANG       │ -            │ 90 Unit      │ -            │ 60 Unit (Pasang)  │ │
│ └──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴───────────────────┘ │
│                                                                                                  │
│ ⚠️ TABEL TAB 2: REKAPITULASI KERUGIAN & PENYESUAIAN (Jika Tab 2 Aktif)                            │
│ ┌──────────────────────────┬───────────────────────────────┬──────────────┬────────────────────┐ │
│ │ Kategori Kerugian / BAP  │ Lokasi / Sumber               │ Jml Kejadian │ Total Unit / Qty   │ │
│ ├──────────────────────────┼───────────────────────────────┼──────────────┼────────────────────┤ │
│ │ Rusak (DAMAGED - Petir)  │ Cabang Siman (Gudang)         │ 2 Transaksi  │ 2 Unit ONT         │ │
│ ├──────────────────────────┼───────────────────────────────┼──────────────┼────────────────────┤ │
│ │ Hilang (LOST di Lapangan)│ — (Custody Teknisi)           │ 1 Transaksi  │ 1 Unit ONT         │ │
│ ├──────────────────────────┼───────────────────────────────┼──────────────┼────────────────────┤ │
│ │ Selisih Stock Opname     │ Cabang Babadan                │ 1 Transaksi  │ -15 Meter Dropcore │ │
│ └──────────────────────────┴───────────────────────────────┴──────────────┴────────────────────┘ │
│ *Catatan: Kerugian di tangan teknisi ditandai "— (Custody Teknisi)" karena terjadi di luar gudang.│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 6.4 Prinsip Penyajian Data Laporan (*Noise-Free*)
1. **Penyembunyian Baris Nol (*No Zero Rows*)**: Gudang atau item yang tidak memiliki transaksi dalam periode yang dipilih otomatis disembunyikan agar tabel tetap padat, relevan, dan mudah dianalisa.
2. **Format Standar Angka**: Kuantitas diformat rapi tanpa angka nol di belakang koma yang tidak perlu (misal `150` bukan `150.00`).

---

## 7. Rancangan & Spesifikasi Serah Terima Barang ke Teknisi (Issue to Technician — Mobile & Scanner First)

Halaman **Serah Terima ke Teknisi (`/warehouse/issues/create`)** dirancang dengan pendekatan **Mobile-First** agar admin gudang dapat melakukan pencatatan dan pemindaian barcode langsung di depan rak barang menggunakan smartphone tanpa perlu perangkat laptop/PC.

### 7.1 Fitur Utama Pengalaman Mobile
1. **Continuous Camera Scan (Scan Beruntun)**:
   * Kamera HP tetap menyala setelah pemindaian pertama berhasil. Admin dapat memindai 5–10 unit modem berturut-turut tanpa perlu menekan tombol buka kamera berulang kali.
2. **Audio & Haptic Feedback (Getar & Beep)**:
   * HP bergetar pendek dan mengeluarkan suara "beep" konfirmasi setiap kali barcode nomor seri berhasil dibaca.
3. **Bingkai Pemandu Presisi (*Narrow Window Cropping*)**:
   * Jendela bidik kamera berbentuk pita horizontal sempit untuk membatasi area pembacaan barcode pada label modem (ZTE/Huawei/Fiberhome) yang padat, mencegah pembacaan barcode MAC atau Model secara tidak sengaja.
4. **Validasi Stok Real-Time Cabang**:
   * Jika nomor seri yang dipindai tidak tersedia di gudang cabang tersebut (atau statusnya bukan `AVAILABLE`), sistem langsung memberikan getaran panjang dan peringatan merah: *"SN tidak ada di stok cabang ini!"*.
5. **Pintasan Cepat Kuantitas Kabel & Material Pasif**:
   * Pemilihan lot drum aktif kabel dropcore dengan tombol pintas cepat: `[+50m]`, `[+100m]`, `[+150m]`, atau stepper `[ - ] [ Qty ] [ + ]` untuk aksesoris pasif (RJ45, Patchcord).
6. **Sticky Bottom Action Bar**:
   * Tombol simpan mengambang di bagian bawah layar agar selalu mudah dijangkau dengan jempol satu tangan.

---

### 7.2 Alur Operasional Serah Terima (3 Langkah Cepat)

```text
[1. Pilih Teknisi Lapangan Penerima]
       ↓
[2. Scan Barcode SN Modem via Kamera HP / Pilih Meter Kabel Drum]
       ↓ (HP Bergetar "Beep" ➔ Item otomatis masuk ke daftar serah terima)
[3. Tekan Tombol Simpan ➔ Bon Serah Terima Resmi (ISS-YYYY-XXXX) Terbit]
```

---

### 7.3 Wireframe Layout Mobile: Serah Terima Barang (`/warehouse/issues/create`)

```text
┌────────────────────────────────────────────────────────┐
│ 📱 TAMPILAN SMARTPHONE (1 Kolom Ergonomis)             │
├────────────────────────────────────────────────────────┤
│ [← Kembali]   Serah Terima ke Teknisi                  │
├────────────────────────────────────────────────────────┤
│ 👤 1. TUJUAN PENYERAHAN                                │
│ Gudang Asal : [ Cabang Siman (Terkunci Otomatis) ]     │
│ Teknisi     : [ 👷 Pilih Teknisi Lapangan ▼ ]          │
│               (Contoh: Rian Pratama - Tim Pasang Baru) │
├────────────────────────────────────────────────────────┤
│ 📷 2. SCANNER KAMERA HP (Terintegrasi)                 │
│ ┌────────────────────────────────────────────────────┐ │
│ │ 🔲 VIEWFINDER KAMERA (16:9)                        │ │
│ │                                                    │ │
│ │  ┌──────────────────────────────────────────────┐  │ │
│ │  │ [━━━━━ Garis Laser Hijau Berjalan ━━━━━]     │  │ │
│ │  └──────────────────────────────────────────────┘  │ │
│ │     Posisikan Barcode SN Modem di dalam kotak      │ │
│ │                                                    │ │
│ │ [🔄 Ganti Lensa (1x/Wide)]    [💡 Nyalakan Flash]   │ │
│ └────────────────────────────────────────────────────┘ │
│ Status: 🟢 Siap Scan Barcode SN berikutnya...         │
├────────────────────────────────────────────────────────┤
│ 📦 3. BARANG YANG AKAN DISERAHKAN (Auto-List)          │
│                                                        │
│ [Item Serial: ONT / Modem]                             │
│ • ONT Huawei HG8245H5                                  │
│   SN: HWTC12345678  [ 🗑️ Hapus ]                       │
│ • ONT Huawei HG8245H5                                  │
│   SN: HWTC87654321  [ 🗑️ Hapus ]                       │
│                                                        │
│ [Item Batch: Kabel Dropcore (Pilih Drum)]              │
│ • Dropcore 1 Core (Lot: LOT-2026-001 - Sisa 350m)      │
│   Jumlah: [ - ]  [ 150 Meter ]  [ + ]                  │
│   Pintasan: [+50m]  [+100m]  [+150m]                   │
│                                                        │
│ [Item Pasif: Aksesoris]                                │
│ • Patchcord SC-UPC 3M                                  │
│   Jumlah: [ - ]  [ 2 Pcs ]  [ + ]                      │
├────────────────────────────────────────────────────────┤
│ 📝 CATATAN / NO WO:                                    │
│ [ Contoh: Material pasang baru WO Desa Siman Indah... ]│
├────────────────────────────────────────────────────────┤
│ 🟢 [STICKY BOTTOM BAR - MUDAH DIJANGKAU JEMPOL]        │
│ ┌────────────────────────────────────────────────────┐ │
│ │ 💾 Konfirmasi Serah Terima (4 Item)                │ │
│ └────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────┘
```

---

### 7.4 Bukti Serah Terima & Digital Acknowledgment
Setelah tombol simpan ditekan:
1. Terbit nomor bukti resmi **`ISS-YYYY-XXXX`** (misal `ISS-2026-0042`).
2. Saldo stok di Gudang Cabang otomatis terpotong pada ledger `inventory_transactions`.
3. Status Serial Number berubah menjadi `ISSUED` dengan teknisi terkait sebagai *custodian*.
4. Sistem menampilkan halaman bukti serah terima digital yang siap dicetak / dibagikan.
