# SUDAH DI KERJAKAN — 2026-09-17 (ADHOC-80)
# Analisa Gap — Kondisi Barang (Baru / Terpakai / Bekas-Copotan / Rusak)

Dicatat 2026-09-16. Pemicu: pertanyaan user soal "Riwayat Pemakaian" (Barang Baru / Terpakai / Bekas-Copotan / Rusak), lalu ditajamkan ke kasus konkret: **status barang yang diambil balik dari pelanggan putus langganan (DEAC/Ambil Alat)**.

## 1. Yang sudah ada — status LIFECYCLE, bukan kondisi fisik

`SerialStatus` (12 case) & `RollStatus` (11 case) cuma jawab **posisi barang di alur gudang→lapangan** (`received/available/issued/in_use/installed/transferred/returned/damaged/lost/scrapped/quarantine`), bukan **kondisi fisiknya**. `MaterialKind` (`estimasi`/`terpakai`) juga bukan kondisi — itu fase pencatatan realisasi vs estimasi per task.

Dipetakan ke 4 label yang ditanya user:

| Label user | Ketutup sekarang? | Lewat apa |
|---|---|---|
| **Barang Baru** | Implisit doang | `RECEIVED`/`AVAILABLE` pasca `receiveSerialized()` — gak ada label eksplisit "Baru" |
| **Barang Terpakai** | Ya | `IN_USE`/`INSTALLED` + `TaskMaterial` kind `terpakai` |
| **Barang Rusak** | **Ya, solid** | `InventoryAdjustmentService::adjustSerialStatus()`/`adjustRollStatus()` (`app/Services/InventoryAdjustmentService.php:186-245`, `:255-295`) — transisi ke `DAMAGED`/`LOST`/`SCRAPPED`/`QUARANTINE` WAJIB `evidence_file_path`+`reason`, ditegakkan di Service (bukan cuma UI), `SCRAPPED` terminal. **Bukan gap — jangan dikerjain ulang.** |
| **Barang Bekas/Copotan** | **TIDAK ADA** | — inilah gap utamanya |

## 2. Gap utama — retrieval DEAC nyamain "bekas" dengan "baru"

Alur konkret yang ditanya user, `TaskService::completeTask()` (`app/Services/TaskService.php:250-278`):

```
Task AMBIL_MODEM selesai
  → customer_devices.device_retrieved_at di-set (legacy, lihat poin 4)
  → loop InventorySerial WHERE customer_id=X AND status=INSTALLED
      → InventoryReassignService::returnInstalledSerialFromCustomer()
```

`returnInstalledSerialFromCustomer()` (`app/Services/InventoryReassignService.php:142-183`):

```php
$serial->update([
    'status' => SerialStatus::AVAILABLE,   // <- SAMA PERSIS status SN baru
    'current_pop_id' => $cabang->id,
    'current_technician_id' => null,
    'customer_id' => null,
    'fop_task_id' => null,
    'installed_at' => null,
]);
```

Bandingkan `receiveSerialized()` (Barang Masuk baru dari vendor) — hasil akhirnya juga `status=AVAILABLE`. **Begitu SN sudah-dipakai-bertahun-tahun balik dari pelanggan, dia gak bisa dibedakan dari SN baru yang belum pernah dipasang** — keduanya kepool rata di query `WHERE status='available'` yang dipakai `InventoryIssueService::issueSerialized()`. Konsekuensi: modem bekas copotan (potensi sudah soak/lemah, walau fisiknya belum "rusak" — makanya gak lolos ke jalur `adjustSerialStatus()`) bisa langsung ke-Issue lagi ke teknisi lain tanpa jejak "ini bekas" dan tanpa gate inspeksi.

`InventoryTransaction` yang ditulis di baris 172-183 (`type=RETURN`) cuma punya kolom `reason` teks bebas — gak terstruktur, gak bisa dibikin laporan "berapa unit balik dari DEAC bulan ini vs balik dari batal-pasang" tanpa parsing teks.

**Kolom kondisi TIDAK ADA di skema manapun** — dicek langsung: `inventory_serials`, `inventory_rolls`, `inventory_transactions`, `customer_devices` (migration masing-masing: `2026_09_02_100004`, `2026_09_15_140002`, `2026_09_02_100006`+`2026_09_03_100001`+`2026_09_15_140003`, `2026_06_13_120000`+`2026_07_18_163955`). Gak ada satu pun kolom `condition`/`kondisi` di mana pun.

## 3. Dua sumber kebenaran paralel buat "sudah diambil" — celah lama, masih hidup

`customer_devices.device_retrieved_at` (legacy) vs `InventorySerial.status` (modul Inventory) — **dua jalur independen** buat pertanyaan yang sama "alatnya udah diambil belum":

- `CustomerController::retrieveDevice()` (`:159-189`) baca/tulis `device_retrieved_at` manual — gate tombol "Ambil Alat" di UI lama.
- `resources/views/customers/terminated.blade.php` (`:50`, `:122`) baca `device_retrieved_at` buat badge di List Putus Langganan.
- `returnInstalledSerialFromCustomer()` cuma jalan kalau SN itu match `customer_id` + `status=INSTALLED` di `inventory_serials`.

Kalau pelanggan itu datanya legacy (SN gak pernah ke-link ke `InventorySerial`), `device_retrieved_at` ke-set tapi `InventorySerial` gak ikut balik — SN itu bisa permanen macet di `INSTALLED` walau fisiknya sudah di gudang. Ada `BackfillDeviceRetrievedStatusCommand` yang pernah dibikin buat nutup gap historis ini — artinya masalahnya **sudah pernah kejadian**, bukan hipotetis. Ini bukan gap kondisi-barang secara langsung, tapi berdampingan dan harus disebut karena solusi kondisi-barang bakal nempel di titik yang sama (`returnInstalledSerialFromCustomer()`).

## 4. Gap turunan — QUANTITY/BATCH gak punya jalur retrieval DEAC sama sekali

`TaskService::completeTask()` loop-nya sempit: cuma `InventorySerial::where('customer_id', ...)->where('status', 'installed')`. Kalau ada barang `QUANTITY`/`BATCH` yang seharusnya ikut balik pas Ambil Alat (skenario kurang umum utk modem, tapi relevan kalau nanti ada material consumable yang dicopot ulang), **tidak ada kode yang menuliskannya ke mana pun** — bukan salah kondisi, tapi hilang total dari ledger. Dicatat sebagai gap terpisah, prioritas lebih rendah (SERIALIZED/modem adalah kasus dominan DEAC).

## 5. Kenapa `ownership_mode` BUKAN jawabannya

`Item::ownership_mode` (`OwnershipMode` enum, 2 case: `INSTALLABLE`/`COMPANY_ASSET`) itu axis independen — cuma gate "boleh dipasang ke pelanggan atau enggak" (level barang/Item, bukan per-unit). Gak ada hubungan ke kondisi fisik satuan SN/Roll. Jangan dipakai/ditumpangi buat nutup gap ini — bakal nyampur dua konsep beda.

## 6. Titik nempel UI — belum ada satu pun badge kondisi

Grep `"Kondisi"` di `resources/views/warehouse/` cuma nongol 3 tempat: `adjustments/roll.blade.php`, `adjustments/serial.blade.php`, `adjustments/custody.blade.php` — semua itu LABEL FILE INPUT ("foto kondisi fisik barang" buat evidence Lapor Rusak), bukan badge status yang ditampilkan di list. Kelola Stok, Scan Barang, Traceability, Custody — **tidak ada satu pun** yang nampilin indikator kondisi barang sekarang. Kalau field kondisi ditambah, semua 4 tempat ini jadi titik nempel badge baru (pola sama kayak badge ROLL KABEL yang baru ditambah ADHOC-76/77/78).

## 7. Dokumen existing — belum pernah dibahas

`docs/plan/warehouse/kontrol-anti-manipulasi.md` §1-2 udah bahas evidence wajib utk LOST/DAMAGED (dasar poin 2 di atas soal "Rusak sudah solid"), tapi TIDAK menyinggung "kondisi baru vs bekas". `fase-2-adaptasi-wms.md` nyebut "kondisi" cuma dalam konteks sama (evidence foto). **Dokumen ini yang pertama** bahas retention kondisi barang bekas-copotan secara eksplisit.

## 8. RBAC — kemungkinan besar reuse, bukan permission baru

Route existing relevan (`routes/web.php:635-701`): `permission:warehouse_adjustment.create` (Lapor Rusak/Hilang/Scrap, Threshold, Stock Opname), `permission:warehouse_reassign.create` (alih custody/return ke gudang — TERMASUK titik `returnInstalledSerialFromCustomer()` kalau nanti field kondisi nempel di situ). Konsisten pola project ini (ADHOC-74/77/78 semua reuse permission lama, gak pernah nambah permission baru buat variasi tracking/kondisi) — kemungkinan besar gak perlu permission baru buat set kondisi juga.

---

## Keputusan (dikonfirmasi user 2026-09-16)

1. **Enum kondisi** — `new` / `used_good` / `used_damaged`. Skema flat 3-case, **bukan** duplikat `SerialStatus::DAMAGED` — `used_damaged` di sini nempel di SN yang balik dari pelanggan tapi BELUM (atau gak perlu) masuk alur formal Lapor Rusak; kalau nanti dieskalasi lewat `adjustSerialStatus()`, `SerialStatus` tetap yang jadi status utama/otoritatif (kondisi cuma metadata tambahan, gak menggantikan status lifecycle).
2. **Gate wajib "sudah dicek fisik"** sebelum boleh di-Issue ulang — analog guard evidence `adjustSerialStatus()`. Konsekuensi desain: SN kondisi `used_good`/`used_damaged` yang **belum diperiksa** TIDAK BOLEH lolos `InventoryIssueService::issueSerialized()` sampai ada aksi eksplisit "Sudah Dicek" (siapa yang cek + kapan, dicatat). SN `new` gak kena gate ini (gak pernah dipakai, gak butuh pemeriksaan ulang).
3. **Roll kabel — SKIP, out of scope.** Kerjaan ini murni utk perangkat aktif (SERIALIZED/modem-ONT-router). `RollStatus`/`InventoryRoll` tidak disentuh sama sekali.
4. **`customer_devices.device_retrieved_at` — SKIP, tidak disentuh.** Tetap dua-sumber-kebenaran paralel apa adanya (poin 3 di atas), bukan scope kerjaan ini.
5. **Riwayat Mutasi Gudang — TIDAK CUKUP, dikonfirmasi lewat audit ulang (lihat detail di bawah).** Kondisi wajib jadi kolom sendiri yang bisa difilter di Riwayat Mutasi, bukan cuma badge di halaman lain.

### Audit poin 5 — kenapa Riwayat Mutasi existing gak bisa bedain 4 kategori

`WarehouseHistoryController::index()` (`app/Http/Controllers/Warehouse/WarehouseHistoryController.php:28-76`) filter satu-satunya adalah kolom `type` (`InventoryTransactionType`, 8 case). Dites langsung ke 4 label user:

| Label user | Type ledger yang relevan | Bisa dibedain via filter `type` sekarang? |
|---|---|---|
| Barang Baru | `RECEIVE` | Ya — 1:1, `RECEIVE` cuma berarti barang baru dari distributor |
| Barang Terpakai | `ISSUE`/`INSTALL` | Ya — cukup jelas |
| Barang Rusak | `ADJUSTMENT` | **TIDAK** — `ADJUSTMENT` adalah SATU bucket gabungan buat Stock Opname, LOST, DAMAGED, SCRAPPED, QUARANTINE, DAN shrinkage-on-return sekaligus (lihat docblock enum baris 30-34, "SENGAJA gak ada tipe `SHRINKAGE` terpisah"). Beda alasan cuma kebedain lewat kolom `reason` teks bebas, yang di `warehouse/history/index.blade.php` cuma DITAMPILIN kalau baris itu punya `evidence_file_path` (baris 218-223) — kalau gak ada evidence, `reason` gak kelihatan di list sama sekali, apalagi difilter |
| Barang Bekas/Copotan | `RETURN` | **TIDAK, paling parah** — `RETURN` = "Pengembalian ke Gudang" generik, dipakai buat SEMUA skenario balik (batal pasang barang yang masih baru, DEAC/Ambil Alat barang bekas, dll). Filter `type=return` bakal nyampur barang yang balik dalam kondisi baru dengan yang bekas-dipakai-bertahun-tahun — **exactly kasus yang ditanyain user di awal** |

Kesimpulan: `type` ledger terlalu kasar buat 2 dari 4 kategori. Solusi minimal **bukan** nambah `InventoryTransactionType` case baru (enum ini sengaja dijaga gak numpuk per skenario, lihat docblock baris 12-14) — solusinya nambah kolom `condition` di `inventory_serials` (poin 1), lalu Riwayat Mutasi JOIN `serial.condition` ke ledger row-nya dan jadiin filter kedua (`type` + `condition`), independen dari `reason` teks bebas yang emang gak didesain buat difilter.

---

## Rancangan implementasi (siap dieksekusi — semua blocker sudah dijawab)

**Scope: SERIALIZED (`inventory_serials`) doang.** Roll & `customer_devices.device_retrieved_at` eksplisit skip (poin 3-4).

1. **Migrasi** — `inventory_serials`: kolom `condition` (string, default `new`), `condition_checked_at` (timestamp nullable), `condition_checked_by` (FK `users` nullable). Enum `ItemCondition` baru (`new`/`used_good`/`used_damaged`).
2. **`receiveSerialized()`** — set `condition = new`, `condition_checked_at/_by` null (barang baru dari vendor, gak butuh cek).
3. **`returnInstalledSerialFromCustomer()`** — auto-set `condition = used_good` (default asumsi awal, staf koreksi ke `used_damaged` pas cek fisik kalau perlu) + `condition_checked_at/_by = null` (BELUM dicek — ini yang men-trigger gate poin 4).
4. **Gate Issue** — `InventoryIssueService::issueSerialized()`: query `WHERE status=available` ditambah kondisi — kalau `condition != new` DAN `condition_checked_at IS NULL`, SN itu di-exclude dari pool available-buat-Issue (gak keluar di dropdown/available-stock, dan submit langsung ditolak server-side kalau maksa lewat SN manual/scan — analog guard evidence LOST/DAMAGED, ditegakkan di Service bukan cuma UI).
5. **Aksi baru "Sudah Dicek"** — halaman/endpoint kecil (pola inline-toggle, karena ini aksi lanjutan di halaman Detail SN yang udah spesifik 1 record — CLAUDE.md §3 pola-3), isi `condition` final (`used_good`/`used_damaged`) + `condition_checked_at=now()` + `condition_checked_by=actor`. Permission reuse `warehouse_reassign.create` atau `warehouse_adjustment.create` (belum final — cek mana yang lebih pas pas mulai koding, tapi TIDAK bikin permission baru, poin 8 riset).
6. **Riwayat Mutasi** — **update 2026-09-16, user udah rombak halaman ini jadi grouped-per-dokumen** (`WarehouseHistoryController::groupByDocument()` — RECEIVE/ISSUE/TRANSFER digabung 1 kartu per `reference_number`, RETURN/ADJUSTMENT/STOCK_OPNAME/TRANSFER_CUSTODY/INSTALL tetap 1 baris = 1 kartu karena gak punya halaman detail). Rancangan condition TETAP KOMPATIBEL, gak perlu rearsitektur:
   - Filter `condition` (dropdown baru, sejajar filter `type`) ditempel di query `$ledger` SEBELUM `->get()`/grouping (index.php baris ~47-69, sebelum `groupByDocument($ledger)` dipanggil) — filtering jalan di level baris mentah, grouping tinggal jalan di atas hasil yang udah difilter, gak ada perubahan ke `groupByDocument()` itu sendiri.
   - **Justru RETURN & ADJUSTMENT — dua tipe yang paling butuh badge kondisi (poin audit di atas) — SENGAJA gak digabung**, jadi badge kondisi nempel gampang di baris per-SN yang sudah ada (sama pola badge SN existing, `resources/views/warehouse/history/index.blade.php` baris ~188-198), gak perlu tangani kasus "1 kartu isi campuran kondisi beda-beda".
   - RECEIVE selalu `condition=new` seragam per dokumen (gak perlu badge, trivial). TRANSFER gak berubah kondisi cuma pindah lokasi (gak perlu badge di kartu grouped-nya). ISSUE grouped BISA berisi campuran SN `new` + `used_good` dalam satu dokumen — kalau mau ditampilkan, tambah breakdown ringkas di kartu (mis. "8 unit — 6 baru, 2 bekas") sebagai enhancement opsional, BUKAN blocker buat versi awal.
7. **Filter "Alasan" buat baris `ADJUSTMENT`** — permintaan susulan user (2026-09-16): "apakah bisa lihat riwayat barang rusak dll". Ditemukan gap TERPISAH dari kondisi-barang pas dicek `adjustSerialStatus()`/`adjustRollStatus()` (`app/Services/InventoryAdjustmentService.php:213-244`, `:255-295`): `InventoryTransaction` yang ditulis SAMA SEKALI GAK NYIMPEN `$newStatus` (LOST/DAMAGED/SCRAPPED/QUARANTINE) sebagai kolom sendiri — cuma `reason` (teks bebas, diketik staf) + `notes`. Ngambil "status hasil adjustment itu transaksi apa" cuma bisa nebak dari `serial->status` SAAT INI, yang **gak akurat secara historis** (kalau SN itu di-adjust lagi belakangan — mis. dari QUARANTINE balik ke AVAILABLE — baris ledger lama ikut "berubah makna" walau isinya gak pernah diubah, append-only cuma jamin baris gak dihapus, bukan jamin baris itu masih mencerminkan status yang benar).
   - **Fix**: tambah kolom `resulting_status` (string nullable) di `inventory_transactions`, diisi eksplisit di `adjustSerialStatus()`/`adjustRollStatus()` saat create (snapshot `$newStatus->value`, bukan baca ulang dari model). Kolom ini general buat SEMUA row (bukan cuma SERIALIZED) — beda dari `condition` yang sengaja SERIALIZED-only (poin 3 keputusan), karena "alasan kenapa di-adjust" relevan buat Roll juga, gak ada alasan buat skip Roll di sini.
   - Riwayat Mutasi: filter kedua "Alasan" (dropdown: Semua / Rusak / Hilang / Scrap / Karantina / Opname / Selisih Return), aktif cuma pas `type=adjustment` dipilih (disabled/disembunyikan buat tipe lain — gak relevan). Baca `resulting_status` langsung, bukan parsing `reason` teks.
   - Data lama (row `ADJUSTMENT` yang sudah ada sebelum kolom ini) otomatis `resulting_status=NULL` — tampil sebagai "Lainnya/Tidak Diketahui" di filter, TIDAK di-backfill paksa (nebak dari `reason` teks bebas berisiko salah, lebih aman dibiarkan eksplisit kosong daripada nebak keliru).
8. **Badge UI** — Kelola Stok, Scan Barang, Traceability, Custody (poin 6 riset) — badge kondisi (hijau `Baru`, kuning `Bekas — Belum Dicek`, biru `Bekas — Sudah Dicek`, merah `Bekas — Rusak`).
9. **Test baru** — kondisi ter-set benar di Receive vs Return, gate Issue nolak SN belum dicek, aksi "Sudah Dicek" mengubah kondisi+jejak actor, filter Riwayat Mutasi (`condition` + `resulting_status`) jalan, `resulting_status` ke-snapshot benar tiap transisi adjustment (serial & roll), regresi filter `Warehouse|Serial|Issue|Reassign|Adjustment`.
