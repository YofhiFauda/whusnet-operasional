# Analisa: Nilai Rugi Barang RUSAK/HILANG di Laporan Gudang

**Status:** **Diimplementasikan sebagian** — 2026-09-17. Lihat §"Yang sudah dikerjakan" di bawah buat cakupan pasti & keterbatasan yang SENGAJA belum digarap (STOK AWAL/AKHIR historis, HUTANG).

## Latar belakang

Saat adjustment status barang ke `damaged` (RUSAK) — lewat `InventoryAdjustmentService::adjustSerialStatus()` (serial), `adjustRollStatus()` (roll kabel), atau `adjustCustody()`/`adjustPopBalance()` (non-serial) — sistem sekarang cuma mengurangi stok. **Tidak ada pencatatan nilai kerugian finansial** dari barang yang rusak.

## Keputusan lingkup (dari user, 2026-09-17)

- Nilai rugi dihitung dari **harga beli / HPP item** yang tercatat di tabel `items` (bukan harga jual/pendapatan pelanggan).
- Ini **murni laporan internal Gudang** — bukan pengurang pendapatan Billing/laporan keuangan pelanggan. Tidak ada perubahan ke `Invoice`/`Payment`/modul Billing.
- Ditempatkan di **Laporan Bulanan** Gudang — modul/halaman ini **belum ada rancangannya**, akan dibahas terpisah.

## Referensi format (user, 2026-09-17)

Bentuk akhir Laporan Bulanan: **filter Periode → download Excel**, isinya **banyak rincian per-POP** — bukan sama persis, tapi serupa bentuk dengan template manual yang sudah dipakai selama ini: `docs/plan/warehouse/laporan/LAPORAN ADMIN GUDANG PER AGST 26.xlsx`.

**Struktur template referensi (dibaca 2026-09-17, `openpyxl`):**
- 1 sheet, banyak **blok per-POP** disusun 2-kolom (2 blok bersebelahan per baris, blok berikutnya di bawahnya) — 11 blok di contoh Agustus 2026: OLT Whusnet, Slahung, Sumberejo, Pacitan, Jetis, Kasihan, Siman, Caruban, Temon, Pacitan Kota, Sambit.
- Tiap blok header: judul laporan + nama POP (`OLT {nama}`) + `Bulan / Periode` (mis. "Agustus 2026"), lalu tabel item.
- Kolom tabel per blok (2 baris header, group + sub-kolom):
  - `No`, `Nama Barang`, `Satuan`
  - `STOK BELI` → Qty, Harga Awal Satuan, Qty, Harga Baru Satuan, Nilai (Rp)
  - `HUTANG` → Qty
  - `STOK AWAL` → Qty, Harga Awal Satuan, Qty, Harga Baru Satuan, Nilai (Rp)
  - `STOK TERPAKAI` → Qty
  - `STOK AKHIR` → Qty, Harga Awal Satuan, Qty, Harga Baru Satuan, Nilai (Rp)
- Baris `TOTAL` per blok (jumlah kolom Nilai).
- **Tidak ada kolom RUSAK/HILANG/nilai rugi sama sekali di template ini** — ini gap murni baru, bukan mengisi kolom yang sudah ada.

Konsekuensi untuk rancangan: Laporan Bulanan sistem nanti minimal harus punya breakdown per-POP serupa (grup kolom + baris TOTAL), dengan **grup kolom baru** untuk RUSAK (qty × harga beli/HPP = nilai rugi) — posisi & apakah HILANG ikut digabung dalam grup yang sama atau terpisah, belum diputuskan (lihat poin 4 di bawah).

### Rumus asli per baris item (dibaca 2026-09-17, mode formula bukan cached value)

Per baris (`{r}` = nomor baris item), kolom Excel merujuk ke A-U (blok POP pertama; blok kedua W-AQ pola identik, offset +22 kolom):

- `H{r}` (Nilai STOK BELI) = `=IFERROR((D{r}*E{r})+(F{r}*G{r}),"")` — (qty lama × harga lama) + (qty baru × harga baru).
- `N{r}` (Nilai STOK AWAL) = `=SUM((J{r}*K{r})+(L{r}*M{r}))` — pola sama, tanpa `IFERROR`.
- `P{r}` (Qty STOK AKHIR) = `=SUM(D{r}+F{r}+I{r}+J{r}+L{r}-O{r})` — total qty masuk (STOK BELI qty lama+baru, HUTANG, STOK AWAL qty lama+baru) **dikurangi** STOK TERPAKAI. **Belum ada pengurang untuk RUSAK/HILANG di formula ini** — konfirmasi gap: kalau nanti ditambah kolom RUSAK, `P{r}` harus ikut dikurangi qty rusak, kalau tidak stok akhir laporan akan lebih besar dari stok akhir sistem (yang sudah dipotong oleh `InventoryAdjustmentService`).
- `T{r}` (Nilai STOK AKHIR) = `=SUM((P{r}*Q{r})+(R{r}*S{r}))` — qty akhir × harga lama + qty-baru-akhir(`R`) × harga baru(`S`). Catatan: `R{r}` di seluruh baris contoh berisi **string literal `"0"`**, bukan formula/qty asli — kemungkinan kolom ini gak pernah dipakai isi beneran di praktik lapangan (selalu nol).
- Baris `TOTAL`: `=SUM(H8:H27)`, `=SUM(N8:N33)`, `=SUM(T8:T33)` — **rentang SUM tidak konsisten** (`H` cuma sampai baris 27, `N`/`T` sampai 33, padahal blok punya item sampai baris 32). Kemungkinan bug manual di template (lupa extend range saat nambah baris item baru). **Jangan direplikasi** kalau bikin generator baru — pakai range dinamis sesuai jumlah baris item sungguhan.
- Beberapa sel `P`/`H` di beberapa baris (mis. `P10`, `P22`, `H28`-`H32` pada contoh Agustus) berisi **angka hasil ketik manual**, bukan formula — override manual staf gudang (data override rumus, konsisten dengan sifat template yang "diisi manual tiap bulan"). Bukan pola sistematis yang perlu ditiru.

**Kesimpulan soal "replikasi rumus":** logika hitung (Nilai = Qty × Harga, Stok Akhir = Masuk − Terpakai) bisa & sebaiknya direplikasi persis supaya angka laporan sistem konsisten dengan kebiasaan staf gudang selama ini. Tapi 2 hal template lama **tidak boleh ditiru** karena itu bug/kelalaian manual, bukan desain: (1) rentang `SUM` TOTAL yang gak konsisten, (2) formula `P` (stok akhir) yang belum memperhitungkan RUSAK/HILANG — ini justru gap yang mau ditutup lewat task ini.

## Yang perlu dirancang nanti

1. Kolom harga beli/HPP di `items` — pastikan field-nya sudah ada & konsisten dipakai (cek `Item` model, migration terkait `docker-compose`/`ItemSeeder`).
2. Titik hitung nilai rugi: dihitung on-the-fly saat generate laporan (qty dari `InventoryTransaction` type `ADJUSTMENT` reason `damaged` × harga beli item saat itu), atau disnapshot ke kolom baru saat adjustment terjadi (lebih akurat kalau harga beli berubah-ubah, tapi nambah kolom).
3. Struktur & lokasi halaman **Laporan Bulanan** itu sendiri — belum ada rancangan sama sekali sebagai fitur sistem (menu, filter Periode, generate/download Excel per-POP mengikuti bentuk template referensi di atas — bukan sama persis, unsur mana yang dipertahankan/diubah masih terbuka).
4. Apakah `lost` (Hilang) ikut dihitung sebagai nilai rugi juga, atau cuma `damaged` sesuai literal permintaan awal — perlu dikonfirmasi ulang saat rancangan laporan dibahas.
5. Library export Excel per-POP dengan struktur blok/group-header seperti ini — cek apakah `spatie/simple-excel` (dipakai import/export pelanggan) cukup, atau perlu library lain yang lebih mendukung layout non-tabular (group header, multi-blok per sheet, style bawaan seperti template).

## Referensi kode terkait

- `app/Services/InventoryAdjustmentService.php` — semua jalur adjustment RUSAK/HILANG.
- `app/Models/InventoryTransaction.php` — ledger sumber data (`type=ADJUSTMENT`, `reason=damaged`).
- `docs/plan/warehouse/laporan/` — referensi laporan lama (Excel), belum ada dokumen rancangan digital.

## Keputusan final & yang sudah dikerjakan (2026-09-17)

Setelah 3 ronde klarifikasi sama user, keputusan final:

1. **HILANG ikut dihitung**, bukan cuma RUSAK — dua kolom terpisah biar tetap bisa direkap sendiri-sendiri.
2. **HUTANG di-skip total** (bukan placeholder kosong) — gak ada konsep utang-ke-distributor di sistem sama sekali, task terpisah kalau dibutuhkan beneran.
3. **Harga = last-cost dari ledger** (`unit_price_snapshot` RECEIVE terakhir per item, lintas lot) — BUKAN kolom master `items.harga_beli` (kolom itu emang gak ada, `Item.php` masih "harga menyusul bareng modul Inventory"). User klarifikasi: "harga" yang dia maksud adalah input harga saat RECEIVE barang, bukan field statis di master — cocok sama pola `resolveLastCost()` yang sudah dipakai di seluruh sistem.
4. **Bukan bikin fitur/halaman baru** — `WarehouseReportController`/`warehouse.reports.index` (feature `warehouse_report`, permission `warehouse_report.view`) TERNYATA sudah ada sejak Fase 2 P2, isinya persis "Laporan Gudang agregat periodik per POP" yang dimaksud. Diperluas, bukan dibikin ulang.

### Ditemukan & diperbaiki sekalian (kesalahan logika existing, bukan scope baru)

`buildAdjustmentSummary()` sebelumnya grouping kategori kerugian pakai kolom `reason` MENTAH-MENTAH. Itu cuma kolom terkontrol (nilai `lost`/`damaged`/dst) di jalur `adjustCustody()`. Di jalur `adjustSerialStatus()`/`adjustRollStatus()` (SERIALIZED/ROLL), `reason` itu CATATAN BEBAS staf (mis. "jatuh kena air pas hujan"), klasifikasi asli ada di kolom `resulting_status` (ADHOC-80). Akibatnya: adjustment RUSAK/HILANG pada barang serialized/roll SEBELUMNYA nyasar ke grup sendiri-sendiri per teks bebas, gak pernah kehitung sebagai kerugian agregat. **Fix:** grouping sekarang pakai `resulting_status ?? reason` (utamakan `resulting_status` kalau keisi). Test regresi: `WarehouseReportLossValueTest::serial_damaged_dengan_reason_teks_bebas_tetap_masuk_kategori_rusak_dan_kehitung_nilai_ruginya`.

### Yang sudah dikerjakan

- `WarehouseReportController::buildAdjustmentSummary()` — tambah `unit_cost`/`loss_value` per baris (cuma kategori `lost`/`damaged`), plus fix klasifikasi di atas.
- KPI baru "Nilai Rugi (Rusak+Hilang)" di halaman + tabel Kerugian dapat kolom Nilai Rugi.
- `WarehouseReportController::export()` + route `warehouse.reports.export` (permission sama, `warehouse_report.view`) — download Excel 2 sheet (Pergerakan Barang, Kerugian) lewat `spatie/simple-excel`, filter Periode+POP sama persis halaman.
- Test baru: `tests/Feature/WarehouseReportLossValueTest.php` (6 kasus — fix klasifikasi, nilai rugi custody, quarantine gak dihitung, KPI total, export sukses+permission ditolak).

### Revisi struktur export (2026-09-17, ronde ke-3 — "masih kurang, laporan di-plot per POP")

Excel awal (1 sheet gabungan semua POP jadi baris) dianggap kurang — laporan manual staf **satu blok/tabel per POP**, bukan flat. Diperbaiki: `WarehouseReportController::export()` generate **satu SHEET per POP** (nama sheet = nama POP, dipotong 31 karakter kalau kepanjangan — limit Excel), baris TOTAL di bawah tiap sheet. Baris kerugian custody teknisi (gak py `pop_id`, barang lagi di tangan teknisi) dikumpulkan ke SATU sheet terpisah "Custody Teknisi (Tanpa POP)" di akhir — nebak gudang asalnya lebih bahaya daripada jujur pisah (prinsip yang sama dipakai `buildAdjustmentSummary()`). Test: `WarehouseReportLossValueTest::export_excel_diplot_per_pop_satu_sheet_per_gudang_plus_sheet_custody_terpisah`.

### Stok Awal & Stok Akhir historis (2026-09-17, ronde ke-4 — "ini LAPORAN AKHIR BULAN, wajib cover semua barang")

Ronde sebelumnya Stok Awal/Stok Akhir SENGAJA di-skip (keterbatasan yang didokumentasikan). User menegaskan ini laporan CLOSING bulanan resmi, bukan laporan parsial — harus cover SEMUA tracking type. Diimplementasikan lewat service baru `App\Services\WarehouseStockAsOfService` (bukan nempel di controller — busines logic, `docs/warehouse/business-logic.md` sudah nyebut Service = satu-satunya tempat logic gudang).

**Insight kunci yang buka jalan** (bukan state machine baru, cuma manfaatin konvensi kolom yang SUDAH konsisten dipakai semua Service Gudang sejak awal): transaksi TERAKHIR sebuah unit/lot SEBELUM tanggal X menentukan semuanya.
- **QUANTITY** — replay sum(`to_pop_id`)−sum(`from_pop_id`) per `lot_no`, filter `created_at < tanggal`. "Lama"/"Baru" ditentukan dari lot mana yang PERTAMA kali nyampe di ledger POP itu (arrival id terkecil) — niru 2-slot manual yang sudah ada (`resolveQuantityLot()`), bukan mekanisme baru.
- **SERIALIZED/ROLL** — buat tiap unit (serial_id/roll_id), ambil baris ledger TERAKHIR sebelum tanggal X (1 query gabungan `MAX(id)` per unit, bukan N+1, portable sqlite+mysql). Kalau baris itu `to_pop_id` = POP yang dicari → unit itu "stok" di situ. RECEIVE/TRANSFER-confirm/RETURN/REASSIGN semua nulis `to_pop_id` pas barang BENERAN nyampe gudang; ISSUE/TRANSFER-dispatch/ADJUSTMENT/INSTALL/TRANSFER_CUSTODY TIDAK PERNAH nulis `to_pop_id` — jadi otomatis gak kehitung stok TANPA percabangan per `type` sama sekali. Satu harga (gak ada slot Lama/Baru — sesuai `analisa-2-slot-harga-quantity.md` §4: mekanisme itu emang cuma buat QUANTITY).

**Item idle ikut ditampilkan** — barang yang gak py pergerakan/kerugian BULAN INI tapi tetap py saldo (nongkrong di rak) tetap wajib keliatan Stok Awal/Akhirnya (dicari dari SELURUH riwayat ledger POP sampai akhir periode, bukan cuma bulan berjalan).

**Export sekarang 21 kolom per sheet**: No, Nama Barang, Satuan, Stok Beli, Transfer Masuk, Transfer Keluar, Stok Terpakai, Qty+Nilai Rugi Rusak, Qty+Nilai Rugi Hilang, **Stok Awal (Qty Lama/Harga Lama/Qty Baru/Harga Baru/Nilai)**, **Stok Akhir (5 kolom sama)**.

**Caveat yang TETAP berlaku** (bukan diselesaikan, cuma gak lagi jadi alasan skip total):
- **HUTANG** — masih belum ada, sistem gak punya konsep utang-ke-distributor sama sekali (beda dari Stok Awal/Akhir yang bisa diturunkan dari ledger existing, Hutang butuh field/tabel baru).
- **Adjustment QUANTITY tanpa `lot_no` eksplisit** (`adjustPopBalance()` dipanggil tanpa parameter `lot_no` — form `storeBalance()` SEBENARNYA nyediakan field ini, tapi kalau staf/test manggil Service langsung tanpa isi) nulis ke lot `''` yang TERPISAH dari lot 2-slot beneran (`LOT-xxx`) — bisa bikin balance "hilang" dari 2 slot Lama/Baru yang ditampilkan (nyangkut di bucket ke-3 yang gak pernah ditampilkan, cuma qty 2 lot pertama by-arrival-order yang ditampilkan). Ini PERILAKU LAMA (bukan baru dari task ini), relevan cuma kalau staf salah pilih lot pas adjustment manual.

Test: `WarehouseReportLossValueTest::stok_awal_dan_stok_akhir_quantity_direplay_dari_ledger_2_slot_lama_baru`, `::stok_awal_dan_stok_akhir_serialized_ngikutin_lokasi_unit_di_ledger` — 9 test total di file ini, semua hijau. Full suite Warehouse (135 test) disapu ulang, hijau.

### Layout kolom final — persis `test1.html` (2026-09-18, ronde ke-6)

User kasih HTML mockup header 2-baris (warna per grup) buat dituruti persis. Kolom export direstrukturisasi total dari revisi sebelumnya:

- **"Barang Masuk"** gantiin "Stok Beli" + "Transfer Masuk"/"Transfer Keluar" (3 kolom lama) jadi SATU grup 5-kolom 2-slot Lama/Baru (Qty/Harga Awal/Qty/Harga Baru/Nilai) — gabung RECEIVE (Pusat) + TRANSFER-confirm (Cabang) jadi satu angka, niru laporan manual lama yang tetap nyatet "Stok Beli" di sheet Cabang walau Cabang gak pernah RECEIVE langsung dari distributor. `Transfer Masuk`/`Transfer Keluar` TETAP ada di tab Pergerakan Barang ON-SCREEN (`warehouse.reports.index`), cuma gak diplot lagi ke Excel.
- Urutan grup: Identitas → **Barang Masuk** → **Stok Awal** → **Stok Terpakai** → **Stok Akhir** → **Barang Rusak** → **Hilang** (23 kolom total, indeks 0-22).
- Warna: biru `#2B70B3` (identitas), hijau `#4F7928` (Barang Masuk), emas `#B8860B` (Stok Awal), merah `#B30000` (Stok Terpakai), ungu `#7A00CC` (Stok Akhir), merah-sedang `#D9534F` (Barang Rusak — beda dari revisi sebelum ini yang pakai merah tua), abu gelap `#333333` (Hilang — beda dari oranye di revisi sebelum ini).
- Subtitle 2-baris di header grup Stok Awal/Stok Terpakai/Stok Akhir (niru `<br>` di HTML — Excel gak kenal HTML, diganti `"\n"` dalam sel + `setShouldWrapText()`).

**Bug ketemu & ditutup pas nulis `receivedInPeriod()` buat "Barang Masuk"**: query awal cuma filter `to_pop_id=POP` tanpa filter `type`, jadi ADJUSTMENT (`adjustPopBalance()`, yang JUGA nulis `to_pop_id` walau qty-nya delta bertanda, lihat §"kesalahan logika" `buildAdjustmentSummary()` sebelumnya) ikut numpang kehitung sebagai "barang masuk" — RUSAK -8 bikin Barang Masuk yang seharusnya 100 kebaca 92. Ketauan dari test (`export_excel_diplot_per_pop_...` gagal "92 != 100"), BUKAN dari review manual. Fix: `WarehouseStockAsOfService::ARRIVAL_TYPES` — cuma `RECEIVE`/`TRANSFER`/`RETURN` yang dihitung "barang masuk", `ADJUSTMENT`/`STOCK_OPNAME`/`ISSUE`/`INSTALL`/`TRANSFER_CUSTODY` dikecualikan eksplisit. Diverifikasi ulang ke DB dev sungguhan (`WarehouseReportDemoSeeder` data) lewat tinker — cocok.

Test: 9 kasus di `WarehouseReportLossValueTest`, semua hijau. Full suite Warehouse 140 test disapu ulang, hijau.
