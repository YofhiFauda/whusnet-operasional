# Analisa UI/UX Modul Warehouse

Tanggal: 2026-10-07
Status: Analisa (belum ada perubahan kode)
Sumber: pembacaan kode view & controller, belum diuji langsung di browser.

## Latar Belakang

Modul Warehouse sudah lengkap secara fitur, tetapi:

- Penyajian data dinilai kurang rapi dan kurang profesional.
- Mencari data (terutama SN, roll, lot) masih sulit.

Dokumen ini mencatat masalah yang ditemukan beserta usulan solusi, urut prioritas.

## Ruang Lingkup

Halaman yang dianalisa:

| Halaman | View | Controller |
|---|---|---|
| Dasbor Gudang | `warehouse/index.blade.php` | `WarehouseController` |
| Kelola Stok | `warehouse/stock/index.blade.php` | `WarehouseStockController` |
| Riwayat / Ledger | `warehouse/history/index.blade.php` | `WarehouseHistoryController` |
| Custody Teknisi | `warehouse/custody/index.blade.php` | `WarehouseCustodyController` |
| Traceability | `warehouse/traceability/index.blade.php` | `WarehouseTraceabilityController` |
| Header bersama | `components/warehouse/header.blade.php` | — |

## Masalah

### A. Pencarian & Temu Data (prioritas tertinggi)

**A1. Paginasi (status 2026-10-07).**
- Kelola Stok: paginasi 25/halaman (fetch-all lalu potong di PHP karena gabungan 3 tabel).
- Ledger: paginasi 30/halaman (fetch-all lalu grouping+paginate di PHP).
- Custody: **SELESAI** — 4 daftar dipaginasi 25/halaman, KPI dari agregat (lihat "Sisa Fase 1").

Catatan: ketiganya fetch-all lalu paginate di PHP (pola yang diterima untuk skala SKU gudang ISP lokal). Optimisasi fetch-level DB bisa menyusul kalau data membengkak.

**A2. Pencarian Stok tidak mencakup SN/roll/lot.**
Kolom "Cari Cepat" di Kelola Stok hanya mencari `item.name` dan `item.code`. Padahal SN dan nomor roll adalah kunci yang paling sering dicari teknisi dan admin. Pencarian SN baru tersedia di halaman Scan/Traceability.

**A3. Perilaku pencarian tiap halaman berbeda.**
- History: referensi, barang, SN.
- Custody: lot, SN, roll, nama barang, nama pelanggan.
- Stok: nama & kode barang saja.

User harus mengingat aturan per halaman.

**A4. Filter tidak terlihat sebagai status.**
Kelola Stok punya 4 dropdown, 2 pill (Semua/Menipis), search, dan tombol Reset yang muncul-hilang. Tidak ada chip filter aktif, sehingga user bisa lupa data sedang terfilter.

**A5. Tidak ada sort kolom.**
Tabel stok dan ledger tidak bisa diurutkan (mis. by qty, by tanggal opname, by POP).

**A6. Dropdown Nama Barang tanpa pencarian.**
Semua item dimuat ke satu `<select>` tanpa search-in-select. Dengan katalog besar sulit dipakai.

### B. Penyajian Data

**B1. Tabel stok terlalu padat per baris.**
Kolom "Jenis & Lot" menumpuk tombol, harga, lot, label "Harga Baru/Lama", min stok, dan info opname. Satu baris bisa terdiri 4–5 baris visual.

**B2. Teks terlalu kecil.**
Banyak `text-[10px]` dan `text-[11px]`/`text-[11.5px]` untuk informasi penting. Di bawah minimum 12px untuk body text.

**B3. Format angka & satuan tidak konsisten.**
- Stok: `rtrim(rtrim(number_format(..., 2)))` di setiap kolom.
- KPI dasbor: "+12 Transaksi", "−3 Transaksi", "3 Kejadian", "5 Tiket", "3 Unit SN". Satuan berbeda untuk konsep sejenis.

**B4. Dasbor tidak berhierarki.**
Lima kartu KPI berukuran sama dengan warna rose/amber/emerald/sky. Tidak jelas mana yang butuh tindakan sekarang. Tautan "Lihat →"/"Proses →"/"Lacak →"/"Detail →" berbeda-beda dan kecil.

**B5. Peringatan opname berlebihan.**
Setiap baris stok yang belum pernah diopname diberi teks kuning. Jika banyak baris, halaman terlihat darurat.

**B6. Dua tampilan mobile & desktop berbeda konten.** — **SEBAGIAN TUNTAS (ADHOC-149)**
Tabel `hidden lg:block` dan card `md:hidden` ditulis dua kali. Maintenance ganda dan risiko tidak sinkron.
Markup tetap 2 blok (CSS-responsive, pola Tailwind umum — gabung total jadi 1 markup butuh verifikasi browser yang tidak tersedia sesi ini). Yang dibenahi: **logic/komputasi dideduplikasi** jadi satu sumber hitung per baris sebelum 2 blok render, bukan dihitung ulang identik di masing-masing — menghapus risiko drift nyata (bukan cuma kosmetik). Ditemukan & dibenahi: `stock/index.blade.php` (status Aman/Kritis, opname, harga lot, pemegang/penginput — sebelumnya dihitung 2x persis sama, kalau rumus diubah di satu blok & lupa blok lain, desktop & mobile bisa tampilkan status beda untuk data sama), `history/index.blade.php` (`$detailItemData` — rute, resolusi `$detailRoute` via `route()`, label — blok ~45 baris logic bisnis disalin persis 2x). Disurvei & TIDAK perlu diubah: `custody/index.blade.php` (lookup map sederhana, bukan formula bercabang), `usage/index.blade.php`, `reports/index.blade.php` (keduanya sudah baca field siap-pakai dari controller, bukan komputasi di view). Belum disurvei: `returns/*`, `retrievals/index`, `transfers/create`, `receive/create`, `issues/create`.

### C. Kesan Profesional & Konsistensi

**C1. Header terlalu berat.**
`components/warehouse/header.blade.php` (613 baris) berisi: judul besar, subtitle panjang, tombol quick menu, nav horizontal ±10 item, info-tip, dan menu mobile terpisah. Nav bisa tidak muat di layar menengah (`min-w-max`).

**C2. Bahasa campur.**
"Dasbor", "Ledger", "Custody", "Opname", "Receive", "Issue", "Traceability", "Reassign", "Adjustment". Untuk teknisi lapangan terasa asing. Label uppercase ("QUANTITY", "SERIAL NUMBER") tampil seperti badge, bukan label data.

**C3. File view terlalu besar.**
- `custody/index.blade.php`: 1400 baris
- `stock/index.blade.php`: 972 baris
- `history/index.blade.php`: 931 baris

Banyak blok inline dan modal dalam satu file.

**C4. Gaya visual tidak seragam antar halaman.** — **SEBAGIAN TUNTAS (ADHOC-148)**
Contoh: `rounded-lg` vs `rounded-2xl`, `shadow-2xs` vs `shadow-xs`, padding kartu berbeda.
Dua tier dibakukan: **Tier A** (kontainer level halaman — filter toolbar, list/table utama) = `rounded-2xl` + `shadow-xs`; **Tier B** (kartu KPI/stat kecil, ikut pola Dashboard) = `rounded-lg` + `shadow-2xs`. Disamakan di: Traceability, Transfer Pending, Transfer Show, Transfer Index, History (termasuk 5 stat tile-nya diturunkan ke Tier B). Custody sudah konsisten dari awal, tidak disentuh. **Belum disentuh**: `receive/create`, `issues/create`, `returns/*`, `usage/index`, `reports/index`, `stock-requests/*`, `pic-gudang/index`, `scan/index`, `retrievals/index` — nyusul kalau diminta lanjut.

**C5. Subtitle halaman panjang.**
Contoh: "Pusat kendali stok fisik, arus barang harian, custody teknisi, dan buku besar logistik ISP." Tidak menambah informasi.

**C6. Empty state generik.**
Pesan kosong tidak menunjukkan langkah berikutnya.

## Usulan Solusi

Urut prioritas. Poin 1–2 menyelesaikan keluhan "data sulit dicari"; poin 3–7 menyelesaikan kesan kurang profesional.

1. **Pagination + pencarian server-side** di Kelola Stok, Ledger, dan Custody.
   - Pindah filter ke query builder dengan `paginate()`.
   - Pertahankan query string filter saat pindah halaman.
   - Perlu test feature untuk filter, pagination, dan scope POP.

2. **Satu kotak pencarian universal** di Kelola Stok.
   - Cari nama, kode, SN, nomor roll, dan lot sekaligus.
   - Hasil langsung menunjukkan barang dan gudang.
   - Konsisten dengan pola search di halaman lain.

3. **Chip filter aktif + sort header kolom.**
   - Setiap filter aktif tampil sebagai chip yang bisa dihapus.
   - Header kolom bisa diurutkan (`aria-sort`).

4. **Pecah kolom "Jenis & Lot"** menjadi kolom terpisah atau drawer detail. Naikkan minimum teks informasi ke 12px.

5. **Satu sistem visual untuk modul gudang.**
   - Satu komponen tabel, satu komponen KPI, satu komponen empty state.
   - Satu aturan radius, shadow, dan padding.
   - Format angka dan satuan terpusat (helper).

6. **Sederhanakan header/nav** menjadi 4–5 item utama. Sisanya masuk menu "Lainnya".

7. **Dasbor berhierarki.**
   - Blok "Perlu tindakan" (stok kritis, permintaan pending, karantina) di atas.
   - Ringkasan arus barang di bawah.
   - Satu tautan aksi per kartu dengan label seragam.

Catatan tambahan (prioritas lebih rendah):
- Tampilan mobile: pakai satu sumber render (card atau tabel responsif), bukan dua markup.
- Subtitle halaman dipersingkat atau dihapus.
- Opname: tampilkan teks kuning hanya jika melewati ambang waktu tertentu, bukan untuk semua "Belum pernah".

## Pertanyaan Terbuka

- Apakah SN/roll boleh dicari langsung dari Kelola Stok, atau tetap di Traceability dengan tautan dari Stok?
- Apakah ada batas ukuran halaman yang diinginkan user (mis. 25/50/100)?
- Apakah istilah teknis (Ledger, Custody, Opname) dipertahankan atau diganti istilah lapangan?

## Langkah Berikut

Saran urutan kerja:
1. Konfirmasi pertanyaan terbuka di atas.
2. Kerjakan poin 1–2 (pagination & pencarian) dengan test feature.
3. Kerjakan poin 3–5 (chip, sort, sistem visual).
4. Poin 6–7 (header & dasbor) setelah pola komponen stabil.

Sesuai aturan repo, perubahan modul ini perlu dicek dulu terhadap sprint aktif di `docs/TASKS.md` sebelum mulai coding.

---

## Desain Ulang Navigasi & Trace Multi-Cabang

Tanggal analisa lanjutan: 2026-10-07
Fokus: fungsi sudah benar; masalahnya di desain. Pengelolaan aset banyak cabang sulit, trace barang sulit, dan dokumen surat jalan sulit dilihat.

### Akar Masalah

**M1. Konteks cabang tidak konsisten.**
- Dasbor, Kelola Stok, Riwayat, dan Traceability masing-masing punya filter POP sendiri dengan default berbeda.
- Traceability tidak punya filter POP sama sekali.
- Scope akses sudah benar di controller (`EffectiveAccessService`), tapi tidak terlihat di UI. User tidak bisa membedakan "barang tidak ada" dengan "barang ada di cabang yang tidak terlihat olehnya".

**M2. Trace barang hanya lewat SN/roll yang diketik.**
- Traceability hanya punya input `sn` dan `roll`.
- Tidak ada pencarian by pelanggan, nomor transfer, atau teknisi.
- Tidak ada daftar barang per status (in transit, di teknisi, per cabang).
- Riwayat berupa ledger mentah tanpa pengelompokan per transfer.

**M3. Surat jalan tidak punya tempat tinggal.**
- Surat jalan hanya bisa dibuka dari detail transfer, dan terbuka di tab baru (`target="_blank"`) tanpa konteks.
- Tidak ada daftar dokumen surat jalan.
- `transfers/pending` hanya menampilkan transfer in-transit. Setelah diterima, transfer hilang dari daftar operasional.
- Nomor surat jalan (`suratJalanNumber()`) tidak dipakai sebagai kunci pencarian di UI.

**M4. Satu objek tersebar di banyak tab.**
- Header punya ±10 tab. Transfer muncul di "Konfirmasi Barang Transfer", "Buat Transfer", dan Riwayat.
- Tidak ada satu halaman Transfer dengan daftar lengkap, status, dan filter.

### Solusi

**S1. Konteks cabang global, terlihat, dan persisten.**
- Chip pilihan cabang di header: "Cabang: Semua / Pusat / <Cabang>".
- Disimpan di query string atau session; dibaca semua halaman gudang.
- Badge "Menampilkan: Cabang X" di setiap halaman.
- Opsi "Semua cabang saya" untuk user multi-cabang, dengan sumber data `EffectiveAccessService::getAllowedPopIds()` (bukan `$user->pops()`).
- Filter cabang di server tetap lewat POP scope.

**S2. Pencarian universal sebagai pintu utama trace.**
- Satu kotak di header: SN, roll, nomor transfer, nomor surat jalan, nama pelanggan, kode barang.
- Hasil dikelompokkan per tipe (Barang, Transfer, Surat Jalan, Pelanggan) dengan badge cabang dan status.
- Klik hasil membuka halaman detail objek.
- Traceability menjadi halaman detail per SN/roll, bukan form input.

**S3. Halaman Transfer tunggal sebagai daftar utama.**
- Gabungkan pending + riwayat transfer: nomor, rute (asal → tujuan), tanggal kirim, tanggal terima, status (Dalam perjalanan / Diterima / Selisih), jumlah item.
- Filter: status, cabang asal/tujuan, rentang tanggal.
- Setiap baris punya aksi langsung Surat Jalan dan Invoice.

**S4. Surat jalan sebagai dokumen first-class.**
- Tab "Dokumen" di detail transfer dengan preview inline + tombol Unduh & Cetak. Tidak lagi membuka tab baru tanpa konteks.
- Daftar dokumen per cabang (keluar & masuk) dengan pencarian nomor.
- Pola view-only: preview di modal/panel, sesuai aturan pola aksi di `CLAUDE.md`.

**S5. Timeline per objek, bukan ledger mentah.**
- Detail SN/roll menampilkan timeline: diterima Pusat → transfer ke Cabang X (TRF-…, SJ-…) → diterima Cabang X → issue ke teknisi Y → terpasang di pelanggan Z.
- Setiap langkah link ke dokumen terkait.
- Ledger tetap tersedia untuk audit.

**S6. Struktur navigasi & sistem visual.**
- Header dipangkas menjadi 4 grup: Stok (Kelola Stok, Lacak), Transfer (Daftar, Buat), Custody (Teknisi), Laporan (Riwayat, Opname, Pemakaian). Sisanya di menu "Lainnya".
- Satu komponen tabel, satu komponen badge status (warna + ikon + teks), satu komponen preview dokumen.

### Urutan Kerja

1. S1 + S2 (konteks cabang & pencarian universal).
2. S3 + S4 (daftar transfer & dokumen surat jalan).
3. S5 (timeline per objek).
4. S6 (navigasi & sistem visual) setelah pola komponen stabil.

Setiap langkah butuh test feature untuk filter cabang/scope dan akses dokumen (`warehouse_transfer.view`, `warehouse_transfer_invoice.view`). Sebelum coding, cek sprint aktif di `docs/TASKS.md`.

---

## Visibilitas Stok: Jumlah, Pemegang, Penginput, Kondisi

Tanggal: 2026-10-07
Keluhan: sulit tahu cepat berapa jumlah, siapa yang pegang, siapa yang input, dan kondisinya. Desain sekarang "mutar-mutar" antar halaman.

### Data yang sebenarnya sudah ada

| Pertanyaan | Sumber data | Terlihat di UI sekarang? |
|---|---|---|
| Berapa jumlahnya | `inventory_balances` (per POP+item+lot), `inventory_serials` (per unit) | Ya, tapi terpecah per POP dan per lot |
| Ada di gudang mana | `inventory_serials.current_pop_id`, `inventory_balances.pop_id` | Ya, sebagai kolom |
| Dipegang siapa | `inventory_serials.current_technician_id`, `technician_custody.technician_id` | Hanya di halaman Custody, tidak di daftar stok |
| Sejak kapan dipegang | `technician_custody.issued_at` (`ageLabel()`) | Hanya di Custody |
| Kondisi | `inventory_serials.condition`, `condition_checked_at`, `condition_checked_by` | Hanya per SN; qty/lot tidak punya kondisi |
| Siapa yang input | `inventory_transactions.created_by` (ledger) | Hanya di Riwayat, berupa baris mentah |
| Transaksi terakhir | `inventory_transactions` (type, reference_number, created_at) | Tersebar di Riwayat |

Ringkasnya: datanya lengkap, tapi tidak ada satu tempat yang menjawab keempat pertanyaan sekaligus.

### Masalah

**V1. Tidak ada ringkasan per item lintas cabang.**
Satu item bisa tersebar di banyak baris (per POP × per lot). Tidak ada baris "Modem X: total 120 = 40 di Pusat + 30 di Ponorogo + 25 di teknisi + 5 karantina". User harus menjumlahkan sendiri.

**V2. Lot memecah satu item menjadi banyak baris.**
Kelola Stok menampilkan satu baris per lot. Dengan beberapa lot dan harga berbeda, satu item bisa 4–6 baris. Ini sumber utama "mutar-mutar".

**V3. Pemegang barang tidak terlihat di daftar.**
Qty yang ada di teknisi tidak muncul di kolom stok. Angka "tersedia" bisa terbaca seolah semua barang ada di gudang, padahal sebagian sudah di lapangan.

**V4. Penginput tidak pernah tampil di tampilan operasional.**
`created_by` hanya ada di ledger. Tidak ada kolom "Diinput oleh" atau "Terakhir diubah oleh" di daftar stok, custody, atau detail SN.

**V5. Kondisi hanya per unit, dan tidak konsisten.**
SN punya kondisi (baru / bekas baik / bekas rusak / karantina). Barang quantity dan roll tidak punya kondisi sama sekali. Filter kondisi hanya ada di Riwayat.

**V6. Istilah status tidak satu pola.**
"Tersedia", "AVAILABLE", "Stok Aman", "Kritis / Menipis", "Custody", "Karantina" dipakai di tempat berbeda dengan bentuk berbeda.

### Usulan Desain

**U1. Satu tampilan "Posisi Stok" per item (ringkasan utama).**
Satu baris per item, kolom tetap:

| Item | Total | Tersedia (per cabang) | Dipegang teknisi | In transit | Karantina/Rusak | Kondisi | Terakhir diubah |
|---|---|---|---|---|---|---|---|

- Angka total besar, angka rincian kecil di bawahnya.
- Klik baris → panel samping (drawer), bukan pindah halaman.
- Lot tidak lagi jadi baris utama; lot tampil sebagai rincian di panel.

**U2. Drill-down tiga tingkat di panel yang sama.**
Item → Cabang (jumlah) → Lot / SN. Setiap tingkat menampilkan jumlah, pemegang, dan kondisi. Tidak perlu pindah ke Custody atau Traceability untuk hal dasar.

**U3. Kolom "Dipegang" dan "Diinput oleh" di setiap daftar.**
- Stok: "Dipegang: 3 teknisi (Andi 10, Budi 5, Citra 2)" atau "Di gudang".
- Custody: "Sejak 2 hari · diinput oleh X · ref ISS-…".
- Detail SN: timeline dengan nama penginput tiap langkah (sudah diusulkan sebagai S5 di analisa sebelumnya).
- Nama penginput diambil dari `inventory_transactions.created_by` lewat join, bukan dari kolom baru di tabel stok.

**U4. Satu komponen badge kondisi dan status untuk semua halaman.**
- Kondisi: Baru, Bekas Baik, Bekas Rusak, Karantina, Belum Dicek.
- Status: Di Gudang, Dipegang Teknisi, In Transit, Terpasang, Scrap.
- Setiap badge punya warna + ikon + teks (tidak hanya warna).
- Istilah dibakukan: satu nama per konsep di seluruh modul.

**U5. Kondisi juga untuk quantity dan roll, atau jelaskan kenapa tidak.**
Kalau kondisi per lot tidak dicatat, tampilkan label "Tidak dilacak per unit" supaya user tidak menyangka barang itu sudah dicek. Keputusan ini perlu konfirmasi bisnis.

**U6. Tampilan "Per Teknisi" sebagai saldo lapangan.**
Satu baris per teknisi: jumlah per item, lama dipegang (`ageLabel()`), dan link ke daftar SN-nya. Ini menjawab "siapa pegang apa" tanpa harus cari dari Custody per item.

**U7. KPI dasbor yang menjawab pertanyaan langsung.**
Tiga angka utama: total unit per status, total unit per cabang, total unit di teknisi (dengan jumlah teknisi). Setiap angka membuka daftar yang sudah difilter, bukan halaman umum.

### Urutan Kerja

1. U1 + U2: ringkasan per item dan drill-down. Ini menghilangkan keluhan "mutar-mutar".
2. U3: kolom pemegang dan penginput di setiap daftar.
3. U4: satu komponen badge dan istilah baku.
4. U6: tampilan saldo per teknisi.
5. U5: keputusan bisnis soal kondisi quantity/roll (perlu konfirmasi dulu).
6. U7: rapikan dasbor setelah data ringkasan tersedia.

Catatan implementasi: U1 butuh query agregat per item (group by item, pop, status) dengan POP scope lewat `EffectiveAccessService`. Hindari N+1 dengan eager load dan batasi jumlah baris per halaman (pagination dari analisa sebelumnya).

---

## Status Implementasi

Catatan terbaru: 2026-10-07. Keputusan default yang diambil tanpa menunggu jawaban pertanyaan terbuka: SN/roll dicari langsung dari Kelola Stok (opsi pertama); U5 (kondisi quantity/roll) ditunda sampai ada keputusan bisnis.

| Fase | Isi | Status |
|---|---|---|
| 1 | Cari Cepat (§A2) + paginasi Ledger/Custody (§A1) + samakan search (§A3) | Selesai (2026-10-07). Cari Kelola Stok by SN/roll/lot; Ledger & Kelola Stok sudah paginated; Custody dipaginasi (KPI dari agregat, tab dari `?tab=`). Test: `WarehouseStockSearchTest` (4), `WarehouseStockPageTest` (14), `WarehouseCustodyPaginationTest` (4). Sisa: pencarian universal header = S2 (terpisah). |
| 2 | **TUNTAS.** Chip filter aktif + sort kolom (§A4, §A5, §A6) | Selesai (2026-10-08). Sort Kelola Stok: POP, Barang, Jumlah, Jenis, Kesehatan; sort Ledger: Waktu/Tipe; sort Custody per-tab (teknisi/item/qty/remaining/pelanggan, via `orderByRaw`); chip per filter; combobox "Nama Barang" (A6). Test: `WarehouseStockSortAndChipTest`, `WarehouseHistorySortTest`, `WarehouseCustodySortTest`. |
| 3 | **TUNTAS.** Ringkasan "Posisi Stok" per item + drill-down (U1, U2, V1, V2) | Selesai (2026-10-08). Toggle `view=ringkasan`. Service: total, per gudang, dipegang teknisi, in transit, kondisi (SN saja), karantina/rusak; scope POP. Sort kolom. Drill-down: lot via drawer `<x-ui.drawer>`, SN/Roll via chip → reuse modal yang sama dengan mode per-lot. Test: `WarehouseStockPositionTest` (20). Verifikasi browser belum dilakukan. |
| 4 | Kolom Dipegang & Diinput oleh di daftar (U3, V3, V4) | **Selesai penuh (2026-10-07).** Ringkasan: "Dipegang Teknisi" + "Terakhir Diubah". Custody (Serial/Material/Roll, desktop+mobile): "Diinput oleh & sejak". Mode per-lot Kelola Stok: kolom "Pemegang & Penginput". Timeline SN: "Operator" per langkah (S5). Test: `WarehouseStockPositionTest`, `WarehouseCustodyPaginationTest`, `WarehouseStockPageTest`. |
| 5 | **TUNTAS.** Satu komponen badge kondisi/status & istilah baku (U4, V6, C2) | Selesai (2026-10-07). Komponen badge + design token (server & Alpine) + ringkasan karantina + istilah badge tracking-type ("SN"/"REGULER", selaras "Roll Kabel"). Nav/header sudah 100% Indonesia sejak awal — diaudit, tidak perlu diubah. Test: `WarehouseBadgeTest`, `WarehouseScanLookupTest`, `WarehouseBadgeTerminologyTest`, dll. |
| 6 | Saldo per teknisi (U6) | Selesai (2026-10-07). Toggle "Per Barang \| Saldo per Teknisi" di halaman Custody (`view=teknisi`). Satu baris per teknisi: barang + jumlah, SN count, lama dipegang, retur menunggu, link ke daftar per barang. Dibangun dari koleksi yang sudah di-scope (tanpa query baru). Test: `WarehouseCustodyAndTraceabilityTest` (+3). |
| 7 | **TUNTAS.** Konteks cabang global + Traceability lintas cabang (S1, M1) | Selesai (2026-10-08). Traceability: filter POP + pencarian daftar `q` (§M2), scope SQL (bukan filter-PHP lagi). Switcher cabang global di header (`WarehouseSwitchPopController`, session `warehouse.pop_id`), dibaca 5 halaman listing lewat `ResolvesSelectedPop` trait. Test: `WarehouseTraceabilitySearchTest` (7), `WarehouseSwitchPopTest` (10). |
| 8 | Halaman Transfer tunggal + dokumen surat jalan (S3, S4, M3) | Sebagian (2026-10-07). Halaman `warehouse.transfers.index`: semua transfer (bukan cuma in-transit), filter status/cabang/tanggal, pagination, scope POP, aksi langsung Surat Jalan/Invoice/Detail per baris. Surat jalan kini punya tempat tinggal (§M3). Test: `WarehouseTransferListTest` (5). **Belum:** preview dokumen inline (§S4) — surat jalan & invoice masih buka tab PDF; tab "Dokumen" di detail belum. |
| 9 | Header ringkas & dasbor berhierarki (C1, B4, S6, U7) | Sebagian (2026-10-07). **Dasbor (B4/U7):** blok "Perlu Tindakan" (Zona 0) di atas — stok kritis, permintaan pending, transfer menunggu, karantina — tautan langsung, state "Semua aman" bila nol. **Header:** link "Daftar Transfer" ditambah (desktop+mobile) agar Fase 8 terjangkau dari nav. Test: `WarehouseDashboardPageTest` (+3). **Belum (C1/S6):** trim nav ke overflow "Lainnya" — ditunda; nav sudah berkelompok (Ringkasan/Stok/Lapangan), redesign penuh berisiko tanpa test & tanpa verifikasi browser. |

Fase 3 adalah yang paling berdampak untuk keluhan "mutar-mutar", tapi butuh query agregat baru. Dikerjakan setelah Fase 2 agar komponen tabel sudah seragam.

## Daftar Belum Diimplementasi

Dicatat 2026-10-07, setelah Fase 1–4. Semua item di bawah belum dikerjakan. Verifikasi browser (layout, mobile, dark mode) untuk seluruh perubahan di atas juga belum dilakukan.

### Sisa Fase 1 — Pencarian
- [x] Paginasi Ledger — **sudah paginasi tampilan** (LengthAwarePaginator 30/halaman, `WarehouseHistoryController:164`). Sama pola Kelola Stok: fetch-all lalu grouping+paginate di PHP (grouping per dokumen beda bentuk per tipe, GROUP BY SQL portable sqlite/mysql tidak sepadan). Catatan sebelumnya ("`->get()` tanpa batas") kurang tepat — output sudah paginated; optimisasi fetch-level masih bisa nanti kalau ledger membengkak.
- [x] Paginasi Custody (`WarehouseCustodyController`) — **Selesai (2026-10-07).** 4 daftar (serial/material/roll/retur) dipaginasi 25/halaman dengan pageName sendiri; KPI & dropdown teknisi dari query agregat (tetap total penuh); mode "teknisi" tetap muat penuh untuk agregasi; tab aktif dipulihkan dari `?tab=`. Test: `WarehouseCustodyPaginationTest` (4).
- [x] Pencarian Traceability dan Custody disamakan dengan pola Kelola Stok — analisa A3. **Efektif selesai:** tiap halaman kini mencari identitas kuncinya (Stok: nama/kode/SN/roll/lot; Custody: lot/SN/roll/item/pelanggan; Traceability: `q` SN/roll/pelanggan/teknisi/nomor transfer/item).
- [x] Hasil pencarian satu tampilan terpadu (universal) — **S2, selesai (2026-10-08).** Lihat bagian S2 di bawah.

### Sisa Fase 2 — Chip & Sort
- [x] Sort kolom Ledger — **Selesai (2026-10-07).** Bar urut Waktu (default desc) / Tipe Dokumen di Riwayat; whitelist; ikut terbawa saat filter diganti. Test: `WarehouseHistorySortTest` (4).
- [x] Sort kolom Custody — **Selesai (2026-10-08).** Param TERPISAH per tab (`serial_sort`/`material_sort`/`roll_sort`/`return_sort` + `_dir`), klik urut 1 tab gak ganggu tab lain. Kolom relasi (teknisi/item/pelanggan) di-sort lewat `orderByRaw` subquery korelasi, BUKAN `leftJoin` — join bikin kolom `status` ambigu (users juga punya kolom `status`) begitu `InventorySerial::status()` scope ikut kepake. Kolom native (qty_remaining, length_remaining) tetap `orderBy` biasa. `reorder()` dipanggil dulu kalau tab itu punya default order (biar custom sort jadi primary, bukan tiebreaker kedua). Test: `WarehouseCustodySortTest` (4).
- [x] Dropdown "Nama Barang" dengan pencarian (search-in-select) — analisa A6. **Selesai (2026-10-07).** `<select>` diganti `<x-combobox>` (ketik untuk cari; opsi di-filter server per kategori; pilih = submit form). Test: `WarehouseStockSortAndChipTest::dropdown_barang_pakai_combobox`.
- [x] Sort kolom "Jenis & Lot" dan "Kesehatan Stok" di Kelola Stok — analisa A5. **Selesai (2026-10-07).** Whitelist sort diperluas (`jenis`, `kesehatan`); header kolom jadi sortable. Test: `WarehouseStockSortAndChipTest` (+3).

### Sisa Fase 3 — Ringkasan Posisi per Item
- [x] Drill-down sampai level SN (Item → Cabang → Lot/SN) — analisa U2. **Selesai (2026-10-08).** Chip "Per Gudang" di ringkasan jadi clickable untuk SN & Roll — reuse modal "Daftar Serial Number"/"Daftar Roll Kabel" yang SAMA dengan mode per-lot (`$dispatch('open-serial-modal'/'open-roll-modal')`, endpoint `warehouse.stock.serials`/`.rolls`), nol kode backend baru. Quantity tetap `<span>` biasa (gak punya identitas per-unit, drill-down berhenti di lot via `<details>` yang sudah ada). Test: `WarehouseStockPositionTest` (+3).
- [x] Kolom "In transit" di ringkasan — analisa U1. **Selesai (2026-10-07).** `WarehouseStockPositionService::inTransitByItem()` (transfer IN_TRANSIT, leg dispatch, scope POP) + kolom di ringkasan. Test: `WarehouseStockPositionTest`.
- [x] Kolom "Kondisi" di ringkasan — analisa U1, U5. **Selesai (2026-10-07).** Keputusan user: HANYA SN punya kondisi asli (breakdown Baru/Bekas Baik/Bekas Rusak per jumlah). Quantity & roll: "Tidak dilacak per unit" — roll tidak punya alur retur/pengecekan yang bisa mengubah kondisinya (beda dari SN pas Terima Retur), jadi disamakan Quantity daripada melacak angka yang statis selamanya. `WarehouseStockPositionService::conditionByItem()`. Test: `WarehouseStockPositionTest` (+2).
- [x] Sort di mode ringkasan (item/total/held/in_transit/problem) — **Selesai (2026-10-07).** Whitelist di controller + header sortable. Test: `WarehouseStockPositionTest`. (Filter stok menipis di ringkasan tetap dimatikan — ambang di level lot.)
- [x] Panel samping (drawer) untuk rincian, sesuai rancangan U1. **Selesai (2026-10-08).** `<details>` native diganti drawer slide-over pakai komponen `<x-ui.drawer>` yang sudah ada di repo (reusable, bukan komponen baru). Chip "Rincian lot per gudang" dispatch `lot-drawer-data` (isi) + `open-drawer` (tampil). Test: `WarehouseStockPositionTest` (+1 → 20). **Catatan:** verifikasi visual di browser belum dilakukan — kode & test memastikan markup benar, bukan tampilan final.
- [ ] Mode "Per Lot" dipertahankan sebagai default; belum ada perubahan untuk mengurangi baris lot (V2).

### Sisa Fase 4 — Pemegang & Penginput
- [x] Mode "Per Lot" menampilkan pemegang dan penginput — analisa U3. **Selesai (2026-10-07).** Kolom "Pemegang & Penginput" di tabel per-lot: qty di teknisi + jumlah teknisi per (pop,item), plus penginput transaksi terakhir. Test: `WarehouseStockPageTest::mode_per_lot_menampilkan_pemegang_dan_penginput`. Catatan: "held" per item+pop (bukan per lot), jadi sama di tiap baris lot item itu — murni informasi.
- [x] Halaman Custody menampilkan "diinput oleh" dan "sejak" — analisa U3. **Selesai (2026-10-07).** Tab Serial (desktop+mobile), Material (desktop+mobile, "sejak" dari `ageLabel`), Roll (desktop+mobile). Sumber: transaksi ISSUE — SN via serial_id, roll via roll_id, quantity via kunci teknisi+item+lot+waktu. Test: `WarehouseCustodyPaginationTest` (SN, roll, quantity).
- [x] Timeline per SN dengan nama penginput tiap langkah (S5). **Sudah ada** — traceability detail menampilkan "Operator: <nama>" per langkah ledger (SN & roll); label diperjelas dari "Diverifikasi oleh".

### Sisa Fase 5 — Badge seragam
- [x] Badge Alpine (serial modal Kelola Stok, halaman Scan) — **Selesai (2026-10-07).** Diubah pakai design token `.badge-*` (`:class` memetakan varian success/info/warning/error) + label baku, selaras `<x-warehouse.condition-badge>`. Test: `WarehouseStockSortAndChipTest`, `WarehouseScanLookupTest`.
- [x] Kolom "Karantina / Rusak" di ringkasan — **Selesai (2026-10-07).** Pakai `<x-ui.badge variant="error">`. Test: `WarehouseStockPositionTest`.
- [x] Istilah nav/menu dibakukan (Ledger, Custody, Opname, Receive, Issue, dst) — analisa C2. **Selesai (2026-10-07), keputusan user: istilah Indonesia lapangan.** Audit nav/header: label sudah 100% Indonesia ("Riwayat Mutasi", "Barang di Tangan Teknisi", "Kelola Stok", "Terima Barang Masuk", "Serah ke Teknisi", dst) — "Ledger/Custody/Receive/Issue" di analisa cuma nama variabel PHP (`$canReceive`) & komentar kode, tidak pernah tampil ke user. Leakage Inggris nyata yang ditemukan & dibenahi: badge tipe tracking "SERIAL NUMBER"/"QUANTITY" (all-caps) di Kelola Stok dan 3 form (Receive/Transfer/Issue) dibakukan jadi "SN"/"REGULER", selaras badge "Roll Kabel" yang sudah ada. Test: `WarehouseBadgeTerminologyTest` (4). Dibiarkan: "Dashboard"/"Scan" (istilah umum terserap), komentar HTML lama di `stock/index.blade.php` (tidak terlihat user, `WarehouseStockRollTest` kebetulan bergantung padanya).

### Sisa Fase 6 — Saldo per teknisi
- Lengkap. Catatan (bukan sisa): "lama dipegang" hanya akurat dari baris custody (punya `issued_at`); teknisi yang hanya pegang SN/roll tampil "—".

### Sisa Fase 7 — Konteks cabang + Traceability
- [ ] Switcher cabang global persisten (S1): chip cabang di header + badge "Menampilkan: Cabang X" tiap halaman — **ditunda, keputusan user 2026-10-07**.
- [x] Pencarian `q` Traceability memfilter scope di PHP setelah ambil maks 100 kandidat/jenis — **Selesai (2026-10-08).** Scope POP sekarang jadi constraint SQL (`ChecksAssetScope::scopeSerialQuery()`/`scopeRollQuery()`, dipakai bareng Traceability & S2) — `limit(50/20)` ambil baris yang SUDAH valid scope-nya, bukan `limit(100)` mentah lalu difilter lalu dipotong. Bukan cuma soal performa: sebelumnya ada kebocoran-negatif nyata — kalau >100 baris di luar scope kebetulan tersortir lebih dulu, SN yang valid di scope bisa gak pernah ketemu (gak pernah ke-fetch). Test: `WarehouseTraceabilitySearchTest` (+1, reproduksi 101 baris di luar scope + 1 target, mengunci fix).

### Sisa Fase 8 — Daftar transfer + dokumen
- [ ] Preview dokumen inline / tab "Dokumen" di detail transfer (§S4). Surat jalan & invoice masih buka tab PDF, bukan preview di panel.

### Sisa Fase 9 — Header + dasbor
- [ ] Trim nav penuh ke menu overflow "Lainnya" (C1/S6) — **ditunda**; nav sudah berkelompok (Ringkasan/Stok/Lapangan), redesign penuh komponen 613-baris berisiko tanpa test & verifikasi browser. Hanya link "Daftar Transfer" yang ditambah di Fase 9.

### Verifikasi
- [ ] Belum ada satu pun perubahan Fase 1–9 yang dicek di browser (layout, mobile, dark mode). Semua lewat test feature + HTML.

### Di luar Fase 1–4, belum dikerjakan
- [x] Keputusan bisnis U5 — **Selesai (2026-10-07).** Quantity & roll: "Tidak dilacak per unit". Hanya SN punya kondisi asli. Diterapkan di kolom Kondisi ringkasan (lihat Fase 3).
- [x] Badge kondisi dan status yang seragam, dengan istilah baku — analisa U4, V6. **Selesai Fase 5 (2026-10-07).** Komponen `<x-warehouse.condition-badge>` & `<x-warehouse.status-badge>`; warna dari `->badgeVariant()` di `ItemCondition`/`SerialStatus`/`RollStatus` ke design token `.badge-*`. Migrasi: traceability, custody (2), retrievals (2). Test: `WarehouseBadgeTest` (16). **Sisa:** badge yang dirender Alpine (serial modal di stock, scan) belum ikut; kolom "Karantina/Rusak" di ringkasan masih hardcode.
- [x] Saldo per teknisi (satu baris per teknisi, dengan lama dipegang) — analisa U6. **Selesai Fase 6 (2026-10-07).** Toggle di Custody, `WarehouseCustodyController::buildPerTechnician()`, partial `custody/per-teknisi.blade.php`.
- [x] Dasbor berhierarki: blok "Perlu tindakan" di atas, KPI yang menjawab langsung — analisa B4, U7. **Selesai Fase 9 (2026-10-07).** Zona 0 "Perlu Tindakan" di `warehouse/index.blade.php` (stok kritis, permintaan pending, transfer menunggu, karantina + state "Semua aman"). Test: `WarehouseDashboardPageTest` (+3).
- [ ] Header dipangkas menjadi 4–5 item utama (saat ini sekitar 10 tab) — analisa C1, S6. **Sebagian:** link "Daftar Transfer" ditambah ke nav (Fase 9). **Ditunda:** trim overflow "Lainnya" — nav sudah berkelompok; redesign penuh komponen 613-baris berisiko tanpa test & verifikasi browser.
- [x] Konteks cabang global: chip cabang di header, badge "Menampilkan: Cabang X" di tiap halaman — analisa S1, M1. **Selesai (2026-10-07).** Switcher di header (`warehouse.switch-pop`, session `warehouse.pop_id`) berlaku di Dashboard, Kelola Stok, Riwayat, Custody, Traceability. Select yang menunjuk cabang aktif ITU SENDIRI jadi badge "Menampilkan". `pop_id` eksplisit di query selalu menang (tidak menulis session) — semua test lama tidak terpengaruh. Tersembunyi kalau cuma 1 cabang. Test: `WarehouseSwitchPopTest` (10). **Sengaja tidak diterapkan:** Daftar Transfer (S3) — punya dua sisi (asal/tujuan), tidak map 1:1 ke satu `pop_id`.
- [x] Traceability lintas cabang: pencarian by pelanggan, nomor transfer, atau teknisi; filter POP — analisa M1/M2. **Selesai Fase 7 (2026-10-07).** `WarehouseTraceabilityController::searchCandidates()` + form `q`/filter POP di view. Test: `WarehouseTraceabilitySearchTest` (6).
- [x] Halaman Transfer tunggal: daftar, status, dan filter dalam satu tempat — analisa S3, M4. **Selesai Fase 8 (2026-10-07).** `WarehouseTransferController::index()` + `transfers/index.blade.php`, route `warehouse.transfers.index`. Test: `WarehouseTransferListTest` (5).
- [ ] Dokumen surat jalan sebagai tab "Dokumen" dengan preview inline, bukan tab baru — analisa S4, M3. **Sebagian:** daftar transfer sudah jadi "tempat tinggal" surat jalan (aksi langsung per baris); preview inline/iframe belum, masih buka tab PDF.
- [ ] Istilah dibakukan (Ledger, Custody, Opname, Receive, Issue, dan seterusnya) — analisa C2.
- [ ] Pemecahan file view besar (custody 1400 baris, stock, history) menjadi komponen — analisa C3.
- [x] Seragamkan gaya visual (radius, shadow, padding) — Traceability/Transfer(index,show,pending)/History (ADHOC-148). Sisa: receive/issues create, returns, usage, reports, stock-requests, pic-gudang, scan, retrievals — analisa C4, S6.
- [x] Dedup logic komputasi mobile/desktop (stock, history) — markup tetap 2 blok, bukti nyata 1x hitung (ADHOC-149). Sisa: survei returns/retrievals/transfers-create/receive-create/issues-create; gabung markup total (CSS-only) butuh browser — analisa B6.
- [ ] Teks di bawah 12px dinaikkan — analisa B2.
- [ ] Format angka dan satuan terpusat dalam satu helper — analisa B3.
- [ ] Opname: teks kuning hanya jika melewati ambang waktu — analisa B5.
- [ ] Subtitle halaman dipersingkat, empty state menunjukkan langkah berikutnya — analisa C5, C6.

### S2 — Pencarian universal (2026-10-08)
- [x] **Selesai.** Satu kotak di header (desktop+mobile), reuse permission `warehouse.view`. Cari SN, roll, nomor referensi transfer, dan nomor surat jalan (format `SJ/WHUS/{tahun}/{bulan}/{urut}`, dibalik dari rumus `WarehouseTransferController::suratJalanNumber()`). Hasil dikelompokkan per tipe dengan badge warna (SN=info, Roll=warning, Transfer=success), klik → halaman detail (Traceability/Transfer show). "Pelanggan" bukan tipe hasil terpisah — tercakup lewat SN yang match nama pelanggan, sama pola Traceability.
- Scope SN/roll: logic `isSerialInScope()`/`isRollInScope()` diekstrak dari `WarehouseTraceabilityController` ke trait `ChecksAssetScope` (dipakai bareng, SATU aturan, bukan disalin). Scope Transfer: sisi manapun (asal/tujuan) dalam allowed POP.
- `WarehouseSearchController`, route `GET /warehouse/search`, view `warehouse/search/index.blade.php`.
- Test: `WarehouseUniversalSearchTest` (9) — cari by SN/teknisi/roll/referensi transfer/surat jalan, isolasi scope pop_admin, kotak tampil di header, gerbang permission (role `sales` tanpa `warehouse.view` → 403).
- **Tidak diimplementasikan:** halaman detail "Pelanggan" tersendiri (di luar scope — modul Customer beda permission); indexing full-text (masih `LIKE`, cukup untuk skala SKU gudang lokal).

### Pertanyaan terbuka yang belum dijawab
- SN/roll dicari langsung dari Kelola Stok atau tetap di Traceability (default saat ini: di Kelola Stok).
- Ukuran halaman yang diinginkan (25/50/100). Saat ini 25.
- Istilah teknis dipertahankan atau diganti istilah lapangan.

### Catatan implementasi
- Setiap item di atas perlu test feature sebelum dinyatakan selesai, sesuai aturan repo.
- Sebelum mulai, cek sprint aktif di `docs/TASKS.md`. Modul gudang berjalan sebagai ADHOC (ADHOC-124 sampai ADHOC-127).
