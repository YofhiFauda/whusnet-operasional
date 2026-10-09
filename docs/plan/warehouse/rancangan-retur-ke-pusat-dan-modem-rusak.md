# Rancangan: Retur Modem Wajib Pusat + Label Asal + Halaman Modem Rusak

Tanggal: 2026-10-08
Status: **rancangan — belum ada perubahan kode.**
Keputusan user (2 ronde AskUserQuestion, 2026-10-08):

1. Fix C1 (modem lama hasil Ganti Modem langsung keitung stok tanpa Terima Retur) — **kerjakan sekarang**.
2. **SEMUA retur modem (DEAC, Ganti Modem, Migrasi, walk-in) wajib verifikasi Pusat** — barang jadi stock Pusat, bukan lagi stock Cabang asal.
3. Mekanisme fisik: **2 langkah konfirmasi** — Cabang terima dari teknisi, lalu Pusat konfirmasi terima kiriman dari Cabang. Kondisi FINAL (baik/rusak) dinilai di Pusat, bukan di Cabang.
4. Label asal (Migrasi/Ganti Modem/Deact/Walk-in) tampil **di mana saja** staf bisa lihat SN itu — Lacak Barang, Kelola Stok, Custody, Terima Retur, Modem Rusak.
5. Data retur LAMA (sudah `AVAILABLE` di Cabang sebelum perubahan ini) **dibiarkan** — tidak ada migrasi data massal, aturan baru cuma untuk retur baru.
6. Halaman **Modem Rusak** (listing SN `DAMAGED`/`QUARANTINE`/`SCRAPPED`/`used_damaged`) — dibuat.

**Ini mengubah fundamental ADHOC-86** (`docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md`, final 2026-09-19): dulu "Terima Retur di Cabang = selesai, `AVAILABLE` di Cabang". Sekarang itu cuma TAHAP 1 dari 2. Semua 17 test `DeviceRetrievalDeacToWarehouseTest` yang assert `AVAILABLE` di Cabang langsung **akan gagal** dan butuh ditulis ulang untuk assert transit→Pusat.

---

## 1. Fix C1 — Ganti Modem lewat transit juga

`InventoryService::installSerial()` (app/Services/InventoryService.php:181-209) sekarang menulis SN lama `RETURNED` dengan `to_pop_id` TERISI (keitung stok seketika, padahal fisik masih di tangan teknisi).

**Fix:** samakan dengan pola DEAC —
- SN lama → `RETURNED`, `current_technician_id` = teknisi yang nyabut, **TANPA** `to_pop_id` di baris ledger `RETURN` ini (transit, belum keitung stok manapun).
- Tambah baris jejak setara `DeviceRetrievalLog` (sumber baru, mis. `ORIGIN_CREQ_SWAP`, atau reuse model yang sama dengan kolom `source` dibedakan) — supaya "modem ini dari pelanggan siapa, kapan dicabut, siapa yang pegang" tetap terlacak sampai diterima gudang, sama seperti jalur DEAC.
- Modem ini lanjut ke alur TAHAP 1 & 2 yang sama di bawah (Cabang terima dari teknisi → kirim ke Pusat → Pusat konfirmasi).

## 2. Alur retur baru (3 tahap, semua origin — DEAC/Ganti Modem/Migrasi/walk-in)

```
TAHAP 0 — Teknisi lapor (SUDAH ADA, tidak berubah)
  DEAC: form DEAC khusus → pickupSerialFromCustomer()
  Ganti Modem/Migrasi: installSerial() (DIPERBAIKI §1)
  → SN: RETURNED, current_technician_id = teknisi, TANPA to_pop_id

TAHAP 1 — Cabang terima dari teknisi (GANTI MAKNA confirmReturnedSerial())
  Staf gudang Cabang konfirmasi custody fisik PINDAH dari teknisi ke Cabang.
  BUKAN LAGI penilaian kondisi resmi, BUKAN LAGI AVAILABLE.
  → SN: tetap RETURNED (transit), current_technician_id = null,
        current_pop_id = Cabang (penanda "ada di Cabang, nunggu dikirim"),
        condition = diisi APA ADANYA kalau staf mau catat observasi awal
        (opsional, bukan gate), condition_checked_at TETAP NULL
        (gate isClearedForIssue() belum lepas — SN ini memang belum
        boleh di-issue, masih nunggu Pusat).
  Ledger: baris RETURN kedua, to_pop_id = Cabang TAPI type baru atau
  flag "belum final" — ATAU, lebih sederhana, baris RETURN ini
  SENGAJA tanpa to_pop_id juga (gak keitung stok Cabang sama sekali),
  current_pop_id dipakai cuma buat UI "ada di Cabang mana" tanpa
  mengklaim sebagai stok Cabang. → detail teknis diputuskan saat
  implementasi, lihat §5.

TAHAP 2 — Cabang kirim ke Pusat (BARU, service baru)
  Staf gudang Cabang pilih SN-SN yang mau dikirim (dari daftar "Retur
  Menunggu Dikirim ke Pusat"), bikin pengiriman.
  → SN: TRANSFERRED, current_pop_id = null (sama kayak transfer biasa).
  Ledger: baris TRANSFER, from_pop_id = Cabang, inventory_transfer_id
  terisi (header InventoryTransfer baru, arah Cabang→Pusat — field
  header-nya generik, cuma SERVICE yang menegakkan arah; butuh method
  baru, BUKAN pakai createTransfer() yang mengunci arah Pusat→Cabang).

TAHAP 3 — Pusat konfirmasi terima (BARU, reuse pola receiveTransfer()
  tapi untuk arah sebaliknya)
  Staf Pusat buka daftar "Retur Masuk dari Cabang", per SN nilai
  kondisi FINAL (used_good/used_damaged), boleh koreksi model (sama
  seperti confirmReturnedSerial() sekarang).
  → SN: AVAILABLE, current_pop_id = Pusat, condition + condition_checked_at/by
        TERISI DI SINI (bukan di Tahap 1). Kalau kondisi rusak → lanjut
        §4 (gak otomatis AVAILABLE, masuk alur adjustment DAMAGED/QUARANTINE
        — detail keputusan ini perlu dikonfirmasi lagi saat implementasi:
        apakah rusak tetap "AVAILABLE di Pusat tapi condition=used_damaged
        dan gate isClearedForIssue() nahan dia", atau staf Pusat langsung
        pilih status DAMAGED/QUARANTINE lewat form yang sama).
  Ledger: baris RETURN ketiga (atau TRANSFER confirm, konsisten pola
  receiveTransfer()), to_pop_id = Pusat — INI baris yang bikin stok
  Pusat bertambah.
```

**Kenapa 3 tahap, bukan 2:** permintaan user "2 langkah konfirmasi fisik" (Cabang terima dari teknisi, Pusat terima dari Cabang) + TAHAP 2 (kirim) sebagai penghubung administratif antara dua konfirmasi itu — sama pola dengan Transfer Pusat→Cabang yang sudah ada (dispatch lalu receive), cuma dibalik arahnya dan ditambah satu langkah di depan (terima dari teknisi) karena originnya dari lapangan, bukan dari rak Pusat.

## 3. Label asal — derivasi, bukan kolom baru

Data penentu asal SUDAH ADA di ledger:

| Asal | Cara deteksi |
|---|---|
| DEAC (Ambil Alat) | `inventory_transactions.fop_task_id` → `task.task_type = AMBIL_MODEM` |
| Ganti Modem | `fop_task_id` → `task.task_type = CREQ` & `creqDetail.category = tambah_modem` |
| Migrasi | `fop_task_id` → `task.task_type = CREQ` & `creqDetail.category = migrasi` |
| Walk-in (antar sendiri) | `fop_task_id` NULL, baris pertama tipe `RETURN` dari `receiveSerialFromCustomerAtWarehouse()` |

Rencana: satu method statis (mis. `InventorySerial::originLabel()` atau helper service kecil) yang menelusuri baris `RETURN` PERTAMA milik SN itu dan menerjemahkan jadi label + warna badge — dipakai komponen Blade `<x-warehouse.origin-badge>` (pola sama `<x-warehouse.condition-badge>` yang sudah ada), ditaruh di: Lacak Barang, Kelola Stok (modal quick-look), Custody (tab Return dari Pelanggan), halaman Terima dari Teknisi (Cabang, Tahap 1), halaman Retur Masuk dari Cabang (Pusat, Tahap 3), Modem Rusak (§4), Scan Barang.

## 4. Halaman Modem Rusak (baru)

Listing SN `status IN (DAMAGED, QUARANTINE, SCRAPPED)` ATAU `condition = used_damaged`, mencakup barang RETUR rusak maupun barang BARU yang rusak (hasil Lapor Rusak dari custody/opname biasa — jalur ini sudah ada, cuma belum punya listing).

- Filter: POP (Cabang/Pusat), status, kategori barang, rentang tanggal.
- Kolom: SN, nama barang, label asal (§3, kalau ada), status, kondisi, lokasi/pemegang sekarang, tanggal kejadian terakhir, link ke Lacak Barang (detail penuh).
- Permission baru atau reuse `warehouse_reassign.create`/`warehouse.adjustments.*` — diputuskan saat implementasi (kemungkinan reuse `warehouse.view` + scope POP biasa, read-only).

## 5. Yang masih perlu diputuskan saat implementasi (bukan ambigu secara bisnis, tapi detail teknis)

1. Baris ledger Tahap 1 (Cabang terima dari teknisi) — apa PERSIS isi `to_pop_id`-nya supaya tidak ikut terhitung `WarehouseStockAsOfService` sebagai stok Cabang, tapi tetap bisa di-query "ada di Cabang mana sekarang". Opsi: tanpa `to_pop_id` + andalkan `current_pop_id` SN untuk lokasi tampilan (bukan ledger).
2. SN kondisi rusak di Tahap 3 — tetap `AVAILABLE` (tertahan gate kondisi) atau langsung `DAMAGED`/`QUARANTINE`.
3. Siapa yang boleh eksekusi Tahap 2 (kirim ke Pusat) dan Tahap 3 (konfirmasi Pusat) — permission spesifik baru atau reuse yang ada.
4. Nama-nama halaman/route baru (Tahap 1 rename dari "Terima Retur", Tahap 2 "Kirim ke Pusat", Tahap 3 "Retur Masuk dari Cabang" di sisi Pusat).

## 6. Dampak ke dokumen & test existing (perlu ditulis ulang, bukan ditambah)

- `docs/warehouse/business-logic.md` §12a — restrukturisasi total (sekarang 2 fase, jadi 3 tahap + beda kepemilikan stok).
- `docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md` — tambah catatan "digantikan rancangan ini" di bagian atas, JANGAN dihapus (riwayat keputusan).
- `tests/Feature/DeviceRetrievalDeacToWarehouseTest.php` (17 kasus) — assert `AVAILABLE` di Cabang jadi salah; ditulis ulang assert transit→kirim→Pusat.
- Test C-REQ Tambah Modem (`TaskCreqBillingReportTest`, `TaskMaintenanceModemInstallTest`) kalau ada assert stok langsung bertambah — perlu cek ulang.
- `docs/plan/warehouse/analisa-trace-modem-legacy.md` §5 — rekomendasi C1 di situ jadi bagian dari rancangan ini, tandai "dikerjakan di sini".

---

Minta konfirmasi sebelum eksekusi: rancangan di atas OK dijalankan apa adanya (termasuk 4 keputusan teknis §5 dengan opsi "Recommended" implisit di tiap baris), atau ada yang mau dikoreksi dulu?
