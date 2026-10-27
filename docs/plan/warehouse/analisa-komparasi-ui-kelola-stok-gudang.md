# Analisis Komparasi & Penyempurnaan UI Kelola Stok Gudang ISP

Dokumen ini mendokumentasikan hasil evaluasi komparatif dari 3 opsi desain antarmuka (*wireframe UI*) untuk halaman **Kelola Stok & Inventori Gudang** (`warehouse.stock.index`) pada sistem operasional ISP Whusnet, serta spesifikasi penyempurnaan desain terpilih (**Desain 3**).

---

## 1. Konteks & Fakta Operasional Lapangan

Berdasarkan klarifikasi alur fisik di lapangan:
1. **Tidak Ada Sistem Racking Mikro / Slot Dimensi di Lapangan:**
   Gudang ISP operasional tidak menggunakan sistem penomoran rak/bin mikro dengan kapasitas dimensi tetap (seperti *Amazon WMS* atau gudang logistik kontainer raksasa).
2. **"Rak" Berfungsi Sebagai Pengelompokan Kategori / Zona Fisik:**
   Penamaan rak pada dasarnya adalah pengelompokan penataan barang agar tidak tercampur:
   - **Area/Lemari Modem & CPE:** Perangkat aktif bernomor seri (*serialized*).
   - **Area Kabel Dropcore:** Drum kabel gulungan besar (*linear meter*).
   - **Area Pasif / ODP & Splitter:** Kotak distribusi dan splitter optik.
   - **Area Aksesoris / Curah Fastener:** Patchcord, sleeve sambungan, dan klem tiang (*batch/quantity*).
3. **Pemisahan Kepemilikan Fisik:**
   Barang terbagi menjadi dua status lokasi nyata:
   - **Fisik di Gudang:** Berada di rak/lemari area gudang.
   - **Custody Teknisi:** Sedang dibawa oleh teknisi untuk penugasan instalasi atau perbaikan di lapangan.

---

## 2. Evaluasi Tiga Opsi Desain

### A. Desain 1: Flat Grouped Table by POP
* **Karakteristik:** Tabel data grid ERP konvensional yang dikelompokkan berdasarkan header Cabang/POP (Pusat vs Cabang Jetis).
* **Kelebihan:**
  - Padat data (*compact*), cepat untuk melihat nama barang dan SKU.
  - Memiliki tombol filter cepat (*Pills*) di atas tabel: `[Semua Stok]`, `[Stok Menipis]`, `[Serial Number]`, `[Curah & Roll]`.
  - Kolom alokasi teknisi transparan menampilkan PIC dan durasi pinjam.
* **Kelemahan:**
  - **Buta visual tata letak:** Semua jenis barang (modem, kabel drum, klem kecil) bercampur aduk dalam satu daftar panjang.
  - Kurang ergonomis untuk *stock opname* bertahap per jenis barang karena staf harus mencari manual di antara puluhan baris.

### B. Desain 2: Live Matrix Hierarchical Tree (WMS Advance)
* **Karakteristik:** Struktur pohon berjenjang (*nested accordion*): Ruangan → Rak → Kompartemen Tray/Bin → Baris SKU.
* **Kelebihan:**
  - Pembedaan status Serial Number sangat detail (`SN Ready` vs `Assigned ke SPK`).
  - Pemisahan status drum kabel yang realistis (`Drum Segel` vs `Drum Terbuka sisa tarikan`).
  - Dilengkapi icon visual dan sub-spesifikasi barang yang informatif.
* **Kelemahan & Alasan Eliminasi:**
  - **Over-Engineered:** Menampilkan data kapasitas fiktif (`Kapasitas 83% (59/60 pcs)`, `Tray-A1-02`) yang tidak ada di dunia nyata.
  - **Cognitive Overload:** Terlalu banyak baris judul, badge, progress bar berulang, dan tombol ciut/bentang yang memicu kelelahan mata (*visual fatigue*).
  - Tidak efisien untuk pencarian cepat karena tertutup di dalam *accordion*.

### C. Desain 3: Visual Bay Explorer + Scoped Data Table (TERPILIH)
* **Karakteristik:** Menggunakan pendekatan *hybrid* (Segmented Tab Ruangan di atas, Kartu Visual Pengelompokan di tengah, dan Tabel Data Terfokus di bawah).
* **Mengapa Menang:**
  - **Mental Model Tepat:** Memisahkan gudang cabang via Tab yang bersih, menghindari salah ambil barang antar-POP.
  - **Visual Hierarchy Seimbang:** Memberikan ringkasan kesehatan barang sekilas pandang melalui kartu zona, lalu menyediakan tabel detail yang mudah dibaca.
  - **Spasi & Kontras Nyaman:** Tipografi dan padding lega, sangat ergonomis digunakan pada laptop maupun tablet staf gudang.

---

## 3. Rencana Adopsi Fitur (Sintesis Penyempurnaan Desain 3)

Untuk menjadikan Desain 3 solusi terbaik tanpa beban fiktif, fitur-fitur unggulan dari Desain 1 dan 2 diadopsi ke dalam Desain 3:

```
+---------------------------------------------------------------------------------------------------+
|                                  DESAIN 3 YANG DISEMPURNAKAN                                      |
+---------------------------------------------------------------------------------------------------+
|  [DARI DESAIN 3]  -> Tab Ruangan/Cabang yang tegas + Layout Kartu Zona Visual                     |
|  [DARI DESAIN 1]  -> Pill Quick-Filter (Menipis, SN, Curah) + Detail Interaktif Custody Teknisi   |
|  [DARI DESAIN 2]  -> Status Kesiapan SN (Ready vs SPK) + Tracking Drum Kabel + Icon Spesifikasi   |
|  [DIELIMINASI]    -> Persentase kapasitas fiktif (83%), slot Tray/Bin mikro yang tidak ada        |
+---------------------------------------------------------------------------------------------------+
```

### Rincian Peningkatan Spesifik:
1. **Dari Desain 1:**
   - **Pills Quick-Filter di Atas Tabel:** Tombol instan `[Semua Stok (22)]`, `[• Stok Menipis (1)]`, `[# Serial Number (14)]`, dan `[Curah & Roll (8)]`.
   - **Transparansi Custody:** Kolom "Di Teknisi" berupa badge yang dapat diklik untuk membuka modal berisi daftar teknisi pemegang barang dan nomor SPK terkait.
   - **Tombol Scan Barcode:** Tombol aksi `[Scan Barcode / SN]` di kanan atas untuk mempermudah operasional input/output fisik.
2. **Dari Desain 2:**
   - **Status Kesiapan SN (Ready vs Assigned):** Memisahkan stok modem ONT yang murni bebas (*Ready to Issue*) dengan yang sudah dipesan untuk jadwal pasang baru (*Assigned SPK*).
   - **Tracking Kabel Dropcore Nyata:** Menampilkan rincian jumlah drum utuh/segel (1.000m) dan drum terbuka/sisa tarikan IKR.
   - **Sub-Spesifikasi Barang:** Menampilkan parameter kunci di bawah nama barang (misal: *XPON Dualband 2.4/5GHz*, *Redaman ≤ 10.5dB*, *Steel Tube*).
3. **Penyederhanaan Konsep "Rak":**
   - Menghapus label slot fiktif seperti `RAK-A01-01` atau `Kapasitas 78%`.
   - Mengubah kartu atas menjadi **Kartu Kategori & Zona Barang** yang berfungsi ganda sebagai indikator ringkas sekaligus filter interaktif (klik kartu untuk memfilter tabel di bawahnya).

---

## 4. Spesifikasi Information Architecture & Tata Letak

### A. Navigasi & Segmented Cabang (Top Bar)
- **Segmented Control:** `[ Ruang Logistik Pusat WHUSNET ]` | `[ Ruang Distribusi Cabang Jetis ]` | `[ Semua Ruangan ]`.
- **Aksi Cepat Header:**
  - `[+ Barang Masuk]` (Penerimaan dari distributor/transfer)
  - `[Scan Barcode / SN]` (Modal scanner cepat)
  - `[+ Aksi Inventori v]` (Transfer Cabang, Stock Opname, Penyesuaian Saldo)

### B. KPI Global Summary Cards
Terdapat 5 metrik utama:
1. **Total Material Terdaftar:** Jumlah SKU aktif dan jenis barang.
2. **Kesiapan Serial Number:** `X SN Ready` • `Y SN Assigned SPK`.
3. **Kabel Roll Terdata:** Total meter aktif (rincian drum segel vs drum terbuka).
4. **Alokasi Teknisi (Custody):** Total unit yang sedang dibawa teknisi lapangan.
5. **Kesehatan Stok & Opname:** Persentase sinkronisasi opname dan peringatan stok menipis.

### C. Kartu Zona & Kategori Barang (Visual Overview Cards)
Empat kartu status kategori utama berfungsi sebagai **panel monitoring ringkas (visual overview)** yang mencerminkan penataan fisik di gudang (bukan tab navigasi halaman):
1. **Zona Modem & CPE (Aktif):**
   - Menampilkan: Total Unit Fisik di Gudang (59), Total Unit di Custody Teknisi (7), Kesiapan SN (52 Ready • 7 SPK).
2. **Zona Kabel Dropcore:**
   - Menampilkan: Total Panjang (119.000 Meter), Rincian 118 Drum Segel + 1 Drum Aktif Lapangan.
3. **Zona ODP & Splitter (Pasif):**
   - Menampilkan: 5 SKU • 248 Unit (Gudang: 241 Unit, Custody: 7 Unit).
4. **Zona Aksesoris & Fastener (Curah):**
   - Menampilkan: 4 SKU • 599 Pcs (dengan early warning 1 SKU Menipis).

*Catatan Navigasi:* Seluruh baris data barang ditampilkan lengkap secara terorganisir per grup di tabel bawah. Filtering dilakukan secara alami melalui **Toolbar Search, Dropdown Kategori, dan Quick Filter Pills**, bukan dengan menyembunyikan grup lewat kartu.

### D. Toolbar & Quick Filters
- **Input Pencarian Global:** Mencari berdasarkan Nama Barang, Kode SKU, Serial Number, atau MAC Address.
- **Pill Badges:**
  - `Semua Stok (22)`
  - `Stok Menipis (1)` (Badge merah jika di bawah ambang batas minimum)
  - `Serial Number (14)`
  - `Curah & Roll (8)`
- **Toggle View:** Mode Tabel Rinci (*Detail List*) vs Ringkasan per Kategori.

### E. Pembatas Antar Cabang & Header Rak (Accordion Collapse/Expanded)
Struktur pengelompokan tabel menerapkan 2 level visual hierarchy:
1. **Level 1: Pembatas Antar Cabang (Branch Divider)**
   - Format Header: `RUANGAN WHUSNET PUSAT LOGISTIK [GDG-PST-01] 11 SKU • 4 Rak Fisik • 100% Online`
   - Metadata PIC & Lingkungan: `Penanggung Jawab: Bambang S. (Logistik) Suhu Ruang: 22.4°C`
   - Desain: Dark bar (`bg-slate-900`) berkesan kokoh, menegaskan batas fisik antar gudang cabang (Pusat vs Jetis).
2. **Level 2: Header Rak (Interactive Accordion Header)**
   - Format Label: `RAK A-01 [ZONE MODEM & ACTIVE CPE] • Kompartemen Tingkat 1-4 Kapasitas: 50/60 pcs (83%) • 1 SKU • 5 SN Siap Diterbitkan`
   - Interaktivitas:
     - Klik header rak untuk **Collapse** (ciutkan baris) dan **Expand** (bentangkan baris).
     - Indikator visual dinamis: Chevron berputar (`expand_more` vs `chevron_right`) dan status badge (`Expanded` vs `Collapsed`).
     - Tombol Global di Toolbar: `[+] Bentang Semua Rak` dan `[-] Ciutkan Rak` untuk kemudahan navigasi cepat.
     - Auto-Expand saat Pencarian: Mengetik di search bar otomatis membentangkan seluruh rak agar data hasil pencarian langsung terlihat.

### F. Struktur Kolom Tabel Operasional
| No | Kolom | Elemen Tampilan | Keterangan UX |
| :--- | :--- | :--- | :--- |
| 1 | **Area Rak / Kompartemen** | Kode Tray/Pallet/Laci (misal: `TRAY-A1-01`, `DRUM-PLT-01`) | Menunjukkan titik peletakan fisik barang |
| 2 | **Kode Item** | Kode SKU Master Item (misal: `TP-LINK-2AE`, `DC-1C`) | Font mono tebal dengan warna primer |
| 3 | **Nama Barang & Spesifikasi** | Icon + Nama Barang + Parameter Teknis Lengkap | Informasi spesifikasi perangkat tanpa buka detail |
| 4 | **Model Pelacakan** | Chip: `Serialized (SN)`, `Linear Meter (Roll)`, atau `Quantity` | Menentukan jenis mutasi barang |
| 5 | **Status Opname** | Chip `Valid`, `Pending`, atau `Selisih` | Kepatuhan audit fisik opname |
| 6 | **Stok Fisik Gudang** | Angka besar + satuan (`pcs`, `meter`) + breakdown Ready/SPK | Jumlah riil yang ada di lemari/gudang |
| 7 | **Alokasi Custody (Teknisi)** | Badge interaktif: `X Unit (Nama Teknisi)` | Klik untuk membuka modal detail teknisi pemegang |
| 8 | **Kepemilikan** | `Company Asset`, `Customer Owned`, `Consignment` | Klasifikasi finansial kepemilikan aset |
| 9 | **Aksi Cepat** | Tombol: `Daftar SN`, `Transfer Barang`, `Audit/Opname` | Akses 1-klik untuk alur rutin |

---

## 5. Rekomendasi Implementasi Teknis (Frontend)

1. **Komponen Blade & Alpine.js:**
   - Gunakan `x-data="{ activeCategory: 'all', activeFilter: 'all', search: '' }"` untuk filter instan di sisi klien tanpa perlu me-reload halaman jika data di bawah 100 SKU.
2. **Komponen Modal & Drawer Interaktif:**
   - **Slide-Over Drawer Kompartemen Tray (`openDrawerTray`):** Muncul saat badge kode tray (seperti `TRAY-A1-01` atau `TRAY-A1-02`) ditekan. Menampilkan panel slide-over dari sisi kanan dengan rincian:
     1. **Serial Number:** Lengkap dengan MAC Address dan tombol 1-klik salin clipboard (`TP21AE016`, dll).
     2. **Kondisi:** Status fisik unit (`Baru (New Segel)` vs `Bekas Baik (QC Passed)`).
     3. **Harga Beli:** Nilai perolehan/HPP pengadaan aset (e.g. `Rp 215.000` baru, `Rp 185.000` second retur).
     4. **Diterima:** Waktu penerimaan dan nomor PO/Surat Jalan (`22 Okt 2026 • PO-WHU-2026-044`).
     5. Dilengkapi filter pencarian realtime di dalam drawer dan ringkasan total nilai aset tray.
   - **Modal Quick Custody:** Muncul saat badge alokasi teknisi diklik; menampilkan daftar teknisi pemegang dan SPK terkait.
   - **Modal Serial Number Explorer:** Muncul saat tombol "Daftar SN" diklik.
3. **Responsivitas Layar:**
   - Pada layar laptop/desktop: Tampilan penuh dengan kartu kategori sejajar 4 kolom dan Drawer slide-over yang lapang.
   - Pada tablet (iPad/Android untuk staf di lapangan): Kartu kategori membungkus (*wrap*) menjadi 2x2 grid, dan tabel mempertahankan scroll horizontal halus untuk menjaga keterbacaan data.

---
*Dokumen ini menjadi acuan resmi perancangan dan implementasi UI/UX modul Gudang (`warehouse.stock.index`) pada Whusnet Operasional.*
