# Fase 2 Gudang — Adaptasi 7 Komponen WMS Klasik ke Konteks ISP

**Status: rancangan selesai & final, implementasi belum dimulai.** Lanjutan ADHOC-54 (Fase 1 selesai 2026-09-02). Dikerjakan atas pertanyaan eksplisit user: apakah modul Gudang/Inventory & Asset Traceability sudah menyediakan 7 komponen WMS klasik (Inbound, Inventory Control, Outbound, Cross-docking, Master Data, Storage, Laporan & Dashboard), dan kalau belum, apakah perlu diadaptasi ke konteks ISP — bukan ditolak mentah, bukan diadopsi mentah.

## Kenapa Fase 1 Bukan WMS Generik (ringkasan)

Modul kita = **asset traceability + custody ledger** untuk ISP lokal, bukan WMS gudang besar retail/3PL:
- Gudang = `Pop` (`type` in `[pusat, cabang]`, reuse `pops.id`), **bukan** tabel `warehouses`/bin/rak terpisah — keputusan sadar §29.9 dokumen analisa pertama: "jangan bikin master lokasi terpisah kalau POP sudah cukup".
- Sumber kebenaran = `inventory_transactions` (ledger append-only), `inventory_balances` derivative.
- Supplier **tidak dicatat sebagai entity** — migration RECEIVE eksplisit: "dari supplier, entitas eksternal, gak dicatat".
- Valuasi harga = last-cost (§29.8), bukan FIFO/average costing formal — tapi FIFO fisik **sudah ada** untuk BATCH lewat `lot_no` + `issued_at` di `InventoryService::consumeFromCustody()`.

Prinsip yang dipegang di Fase 2 ini sama seperti Fase 1: **reuse struktur existing, jangan bikin sumber kebenaran kedua, controller tipis → Service → Observer, tabel baru cuma kalau kolom existing gak cukup.**

## Metodologi Tinjauan

Tiap poin diberi salah satu keputusan:
- **ADOPT** — bangun versi ringan yang cocok skala ISP kita.
- **ADAPT** — fondasinya sudah ada, perlu diperjelas/diperkuat.
- **SKIP** — dengan alasan bisnis eksplisit (skala gudang, volume barang, gak ada armada eksternal, dst — bukan "gak relevan" generik), plus opsi kalau skala naik nanti.

---

## Ringkasan & Urutan Prioritas

| # | Poin WMS | Keputusan | Prioritas |
|---|---|---|---|
| 2 | Inventory Control (bukti fisik + kategori kerugian custody) | ADAPT | **1 — TINGGI** |
| 7 | Laporan agregat (bukan realtime) | ADAPT | **2 — SEDANG** |
| 1 | Inbound (reference_no dokumen) | ADOPT ringan | 3 — RENDAH |
| 3 | Outbound (checklist print) | ADOPT ringan | 4 — RENDAH |
| 6 | Storage/FIFO (badge umur lot) | ADAPT (polish) | 5 — RENDAH |
| 5 | Master data (barang sudah cukup; supplier/lokasi) | SKIP | tidak dibangun |
| 4 | Cross-docking | SKIP | tidak dibangun |

Disetujui user (2026-09-02) via klarifikasi eksplisit: prioritas 1 dikonfirmasi tinggi, supplier & lokasi/bin skip total, cross-docking skip total. Sesi awal cuma rancangan — implementasi task terpisah.

---

## Prioritas 1 (TINGGI) — Inventory Control: bukti fisik wajib untuk klaim kerugian

**Poin asal:** #2 Inventory Control — stock opname, damaged/quarantine, adjustment+approval.

**Yang sudah ADA (Fase 1):** `InventoryAdjustmentService` (3 method: `adjustPopBalance` termasuk opname, `adjustCustody`, `adjustSerialStatus` untuk LOST/DAMAGED/SCRAPPED/QUARANTINE unit serial), `reason`+`notes` wajib (`assertReason()`), event-driven bukan calendar-driven, approval berjenjang dengan threshold nominal **sengaja SKIP** (keputusan eksplisit user, `kontrol-anti-manipulasi.md` §1: belum ada data operasional buat tentukan threshold masuk akal — tetap ditunda, bukan gap baru).

**Gap:**
1. `adjustSerialStatus()`/`adjustCustody()` cuma terima `reason` string bebas, gak ada guard bukti fisik — padahal `kontrol-anti-manipulasi.md` §2 eksplisit minta ini wajib di level **Service** ("tanpa foto, klaim gak bisa disetujui — guard di Service, bukan validasi UI doang").
2. Custody QUANTITY/BATCH (kabel/RJ45 rusak di tangan teknisi) gak punya kategori kerugian terpisah dari konsumsi normal — `CustodyStatus` sengaja gak punya nilai DAMAGED/QUARANTINE (docblock: "vocabulary beda dari `SerialStatus` karena qty custody bisa habis SEBAGIAN"), jadi satu-satunya jalur adalah `adjustCustody()` qty negatif + `reason` teks bebas tanpa struktur.
3. "Opname terakhir per item per gudang" belum ditampilkan di dashboard — `kontrol-anti-manipulasi.md` §5 minta ini, tapi `WarehouseController::index()` cuma tampilkan ledger 25 baris terakhir campur semua tipe.

**Perubahan Teknis Minimum:**
- Migration: kolom `evidence_file_path` (nullable string) di `inventory_transactions`.
- `InventoryAdjustmentService::adjustSerialStatus()` — guard baru: transisi ke `LOST`/`DAMAGED`/`SCRAPPED` tanpa `evidence_file_path` terisi → `InvalidArgumentException`. `QUARANTINE` tetap boleh tanpa foto (bukan klaim kerugian, cuma status tahan sementara buat dicek).
- `InventoryAdjustmentService::adjustCustody()` — parameter opsional `?string $evidenceFilePath`, guard sama kalau `reason` masuk kategori kerugian (`lost`/`damaged`). Dropdown `reason` terarah di UI (bukan textarea bebas): `damaged`, `quarantine`, `shrinkage_on_return`, `lost`, `stock_opname_diff` — supaya bisa direkap terpisah, tanpa nambah enum baru.
- Upload file reuse `FileUploadService` (preseden existing dipakai evidence tiket/laporan) — bukan storage baru.
- Query kecil (bukan tabel baru) di `WarehouseController::index()` atau service ringan: `InventoryTransaction::where('type', STOCK_OPNAME)->groupBy('to_pop_id', 'item_id')->max('created_at')`, ditampilkan sebagai info per baris "Opname terakhir: N hari lalu" di dashboard.

**Prioritas:** TINGGI — satu-satunya gap dengan risiko fraud/kerugian finansial riil (klaim kerugian tanpa bukti fisik terverifikasi struktural).

**Test:**
- `InventoryAdjustmentEvidenceRequiredTest` — `adjustSerialStatus()` ke LOST/DAMAGED/SCRAPPED tanpa `evidence_file_path` dilempar exception; QUARANTINE tanpa foto tetap boleh; `adjustCustody()` dengan `reason` kategori kerugian tanpa bukti ditolak sama.
- `WarehouseDashboardLastOpnameTest` — dashboard tampilkan tanggal opname terakhir per item per gudang; item belum pernah diopname tampil beda (bukan "0 hari lalu").

---

## Prioritas 2 (SEDANG) — Laporan & Dashboard: agregat periodik

**Poin asal:** #7 Laporan & Dashboard real-time.

**Rasional realtime SKIP:** keputusan sadar `rancangan-ui.md` §2.1 — "beda dari Setoran Kas yang butuh live-update duit fisik; stok gudang gak sekritis itu, hindari nambah broadcast channel yang belum perlu". Volume transaksi gudang ISP lokal (puluhan/hari) gak sebanding kompleksitas broadcast channel Reverb/Echo tambahan.

**Gap nyata:** dashboard sekarang (`WarehouseController::index()`) cuma snapshot titik-waktu (stat card + tabel stok + 25 baris ledger terakhir) — gak ada agregat lintas periode. Ini beda dari "Inventory Control Tower enterprise" (`rancangan-ui.md` §4, tetap SKIP) — laporan periodik sederhana beda skala dari dashboard command-center realtime lintas-cabang.

**Perubahan Teknis Minimum:**
- Controller baru tipis `WarehouseReportController` (pola sama controller laporan existing):
  - `movementSummary()` — agregat qty RECEIVE/TRANSFER/ISSUE per periode per gudang (`groupBy` bulan + pop + type).
  - `adjustmentSummary()` — rekap LOST/DAMAGED/SCRAPPED per cabang per periode. Jawab kebutuhan `kontrol-anti-manipulasi.md` §1: "dashboard HQ pantau SEMUA transisi kerugian lintas cabang" — sekarang cuma daftar 25 baris terakhir campur semua tipe, belum ada agregat per periode.
- Permission baru `warehouse_report.view` di `config/rbac.php` (pola sama entri existing). Scope via `EffectiveAccessService` (pop_admin cuma lihat cabangnya).
- Reuse komponen stat card/tabel existing (pola ADHOC-14) — bukan komponen visualisasi baru.

**Prioritas:** SEDANG — nilai bisnis nyata (memantau tren kerugian & pergerakan barang lintas waktu), tapi data mentahnya sudah tercatat di ledger, cuma belum diagregasi — risiko implementasi rendah, read-only.

**Test:**
- `WarehouseReportMovementSummaryTest` — agregat benar per gudang per periode, discope POP.
- `WarehouseReportAdjustmentSummaryTest` — rekap kerugian per cabang per periode benar, item tanpa transaksi di periode tidak muncul (bukan baris nol).

---

## Prioritas 3 (RENDAH) — Inbound: referensi dokumen fisik

**Poin asal:** #1 Inbound — PO matching + putaway suggestion.

**Keputusan:** SKIP (PO matching formal, putaway suggestion) + ADOPT ringan (reference_no).

**Rasional SKIP:** PO matching mengasumsikan modul Purchase Order/procurement formal — gak ada dan sengaja gak dibangun (supplier gak dicatat sebagai entity). ISP lokal skala kecil-menengah beli dari 1-3 distributor langganan lewat invoice manual, bukan sistem procurement multi-vendor dengan approval berlapis. Putaway suggestion (algoritma penempatan rak optimal) gak relevan karena gak ada bin/rak (§29.9) — gudang pusat ISP lokal biasanya satu ruangan/rak sederhana dikelola manual 1-2 admin yang hafal isi gudang.

**Perubahan Teknis Minimum:**
- Kolom `reference_no` (nullable string) di `inventory_transactions` — nomor invoice/DO/nota fisik distributor, diisi manual saat RECEIVE. **Bukan** FK ke tabel PO, cuma catatan referensi dokumen fisik — pola sama `repair_reference` (§29.7: cukup field, bukan modul vendor/RMA penuh).
- Tidak ada service/controller baru — tambah 1 field opsional ke form RECEIVE existing (`InventoryReceiveService::receiveQuantity()`/`receiveSerialized()` terima `?string $referenceNo`).

**Opsi kalau skala naik:** kalau nanti ISP ekspansi jadi multi-distributor dengan approval pembelian formal, baru masuk akal modul PO minimal (`purchase_orders`/`purchase_order_lines`, status DRAFT→SENT→PARTIALLY_RECEIVED→RECEIVED) — bukan sekarang.

**Test:** `InventoryReceiveReferenceNoTest` — `reference_no` tersimpan di baris RECEIVE (quantity & serialized), boleh kosong, gak divalidasi format.

---

## Prioritas 4 (RENDAH) — Outbound: checklist print serah-terima

**Poin asal:** #3 Outbound — picking list rute optimal + shipping manifest.

**Keputusan:** SKIP (rute optimal, shipping manifest kurir eksternal) + ADOPT ringan (checklist print).

**Rasional SKIP:** picking list rute optimal & shipping manifest adalah konsep 3PL/e-commerce — gudang besar dengan banyak SKU tersebar di banyak rak, pengiriman ke banyak alamat pelanggan lewat kurir eksternal. Outbound gudang kita cuma 2 bentuk: TRANSFER (pusat→cabang, internal) dan ISSUE (cabang→teknisi, diambil langsung). Gak ada "gudang kirim ke pelanggan lewat kurir" — device sampai ke pelanggan lewat teknisi yang memasang langsung. Rute optimal antar-rak gak relevan (gak ada bin/rak, sama alasan poin 1).

**Perubahan Teknis Minimum:**
- Tidak ada tabel/service baru — view print (`transfers/print.blade.php`, `issues/print.blade.php`) reuse pola cetak kwitansi existing, tampilkan daftar item+qty/SN per baris transaksi untuk dicetak/dicocokkan fisik saat pengambilan.
- Action `print()` tipis di `WarehouseTransferController`/`WarehouseIssueController` existing (sudah punya `show()`).

**Prioritas:** RENDAH — nice-to-have, bukan gap fungsional (acknowledgment digital sudah menjawab kebutuhan inti verifikasi serah-terima).

**Test:** `WarehouseTransferPrintTest`/`WarehouseIssuePrintTest` — halaman print merender semua baris item, akses digate permission `warehouse_transfer.view`/`warehouse_issue.view` di scope POP yang sama (bukan permission baru).

---

## Prioritas 5 (RENDAH) — Storage: badge umur lot

**Poin asal:** #6 Storage — FIFO/slotting.

**Keputusan:** FIFO fisik **sudah ADA dan benar** (ADAPT-polish) + SKIP slotting rak.

**Rasional:** `InventoryService::consumeFromCustody()` sudah urut `issued_at` ASC (sengaja bukan `lot_no` ASC — lebih mencerminkan urutan pengambilan riil daripada label drum). Valuasi last-cost bukan FIFO costing (§29.8) itu pemisahan yang benar: FIFO di sini untuk **pergerakan fisik barang**, bukan metode akuntansi biaya. Slotting (algoritma penempatan rak optimal) gak relevan — gak ada rak (sama alasan poin 1 & 5).

FIFO saat ini cuma diterapkan di titik konsumsi custody teknisi. Pemilihan drum mana yang keluar duluan saat ISSUE dari gudang **sengaja manual** (admin gudang pilih, `rancangan-ui.md` §3.8) — bukan gap, keputusan sadar (admin lebih tahu kondisi fisik drum daripada sistem menebak dari `received_at`).

**Perubahan Teknis Minimum:**
- Tidak ada tabel/kolom baru — `inventory_balances` sudah punya `lot_no`.
- Badge umur lot (hari sejak RECEIVE pertama lot itu) di tabel "Stok per gudang+lot" dashboard existing — pola sama badge durasi custody (`kontrol-anti-manipulasi.md` §3, informasional bukan alert otomatis). Query ringan: `InventoryTransaction::where('type', RECEIVE)->where('lot_no', ...)->min('created_at')` per baris balance BATCH.

**Prioritas:** RENDAH — fondasi FIFO sudah solid, ini cuma polish informasional.

**Test:** `WarehouseDashboardLotAgeBadgeTest` — badge tampil untuk item BATCH dengan `lot_no` terisi, gak tampil untuk item QUANTITY biasa.

---

## SKIP Total (gak dibangun, dikonfirmasi user)

### Cross-docking (poin #4)
Volume inbound ISP lokal rendah & periodik (restock mingguan/bulanan, bukan aliran harian bervolume tinggi). Gak ada armada distribusi ke banyak tujuan simultan. Stok buffer di pusat justru dibutuhkan untuk menghadapi lonjakan pemasangan tak terduga — pola inbound-langsung-outbound kontraproduktif. RECEIVE lalu TRANSFER manual berurutan sudah cukup kalau memang mau langsung diteruskan.

**Opsi kalau skala naik:** relevan hanya kalau ISP berkembang jadi belasan+ cabang dengan pola distribusi terjadwal reguler bervolume tinggi (mis. rollout massal perangkat baru serentak).

### Supplier master (bagian poin #5)
Sudah diputuskan sadar (migration RECEIVE: "entitas eksternal, gak dicatat"). ISP lokal kecil-menengah beli dari 1-3 distributor tetap yang sudah dikenal personal oleh staf gudang — mencatat supplier formal (kontak, term pembayaran, riwayat harga per-vendor) bermanfaat kalau ada *banyak* vendor yang perlu dibandingkan/dievaluasi, yang gak terjadi di skala ini. `reference_no` (Prioritas 3) sudah cukup menjawab "tahu dari dokumen mana" tanpa master vendor penuh.

**Opsi kalau skala naik:** kalau distributor bertambah >5-10 dan perlu dibandingkan (harga, lead time, kualitas), baru masuk akal tabel `suppliers` ringan (nama, kontak) jadi FK opsional di `reference_no`/RECEIVE.

### Lokasi/bin/rak (bagian poin #5)
Sudah diputuskan sadar §29.9. Gudang cabang ISP lokal biasanya satu ruangan kecil dikelola 1 admin yang hafal fisik letak barang — granularitas rak/bin/slot itu kebutuhan gudang besar dengan puluhan-ratusan SKU tersebar luas dan banyak petugas berbeda.

**Opsi kalau skala naik:** kalau satu gudang cabang tumbuh jadi gudang besar multi-rak dengan staf >1 orang per shift yang butuh dituntun lokasi fisik, baru masuk akal `warehouse_locations` sebagai kolom tambahan opsional di `inventory_balances`/`inventory_serials` — bukan hierarki lokasi terpisah.

### Master data barang (bagian poin #5) — TIDAK ADA GAP
`Item`/`ItemCategory` sudah cukup untuk skala ISP (`code, name, item_category_id, unit, is_active, tracking_type, ownership_mode, equipment_class_override`), konsisten prinsip "tabel baru kalau kolom cukup". Tidak perlu ADOPT tambahan.

---

## Tetap Ditunda Tanpa Perubahan (dari Fase 1, bukan gap baru)

Dikonfirmasi ulang, bukan hasil tinjauan 7 poin di atas — sudah diputuskan sadar sejak Fase 1 (`rancangan-ui.md` §4, `docs/TASKS.md` ADHOC-54):
- Approval berjenjang Stock Request + threshold nominal.
- Reorder alert otomatis (push notification — beda dari tampilan low-stock pasif di dashboard yang sudah ada).
- Inventory Control Tower dashboard enterprise realtime lintas-cabang.

---

## Urutan Implementasi (kalau/ketika dieksekusi)

1. Prioritas 1 (bukti fisik) — migration `evidence_file_path` → guard Service → dropdown reason UI → test → dashboard opname terakhir.
2. Prioritas 2 (laporan agregat) — `WarehouseReportController` → permission `warehouse_report.view` → view → test.
3. Prioritas 3, 4, 5 — bisa paralel, masing-masing independen, risiko rendah.

Tiap fase implementasi wajib entry baru di `docs/TASKS.md` dengan status & tanggal, ikut pola ADHOC-54.
