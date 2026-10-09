# Analisa Trace Modem Legacy (Belum Pernah Tercatat) & Jejak Pindah Pelanggan

Tanggal: 2026-10-06
Status: **DIKERJAKAN** (ADHOC-108, 2026-10-08) — C1 fix + alur retur 3 tahap, lihat `docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md` & `docs/warehouse/business-logic.md` §12b. C2–C4 masih berlaku (lihat §5).
Terkait: `docs/warehouse/business-logic.md` §12a/§12b, `docs/plan/warehouse/analisa-riwayat-dan-terima-modem-dari-pelanggan.md`, `docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md`

---

## 1. Pertanyaan

1. Modem lama hasil import data legacy yang belum pernah tercatat di sistem warehouse: bagaimana runtutannya, dan siapa yang memeriksa kondisinya?
2. Kalau modem itu kembali ke gudang, diterima, lalu dipakai lagi: apakah log/trace mampu mencatat dari awal sampai akhir, walaupun modem pernah dicabut dan dipindah ke pelanggan lain?

---

## 2. Jawaban Singkat

1. **Tidak ada riwayat "awal" di sistem.** Titik pertama tercatat adalah saat teknisi melapor Ambil Alat (DEAC), atau saat pelanggan mengantar sendiri ke gudang. Tidak ada baris `RECEIVE` (modem legacy tidak pernah lewat Barang Masuk), dan tidak ada harga beli. Kondisi fisik diperiksa **staf gudang saat Terima Retur**.
2. **Sebagian.** Rantai pergerakan per SN tetap utuh (ledger append-only). Pelanggan ke pelanggan terlacak lewat `fop_task_id`. Tetapi ada celah pada jalur "modem diganti saat pasang SN baru" (lihat §4).

---

## 3. Runtutan Modem Legacy (Kode Saat Ini)

| Langkah | Kode | Yang terjadi |
|---|---|---|
| a. Teknisi lapor SN (DEAC, "Diambil") | `InventoryReassignService::pickupSerialFromCustomer()` | SN belum ada di `inventory_serials` → dibuat otomatis. Model dari pilihan teknisi, atau placeholder `MODEM-PELANGGAN-LAMA`. Status `RETURNED` (transit), `condition=used_good`, `condition_checked_at=null`. |
| b. Ledger pertama | sama | Baris `RETURN` pelanggan → teknisi (`to_technician_id`, tanpa `to_pop_id`). Catatan `LEGACY_SERIAL_NOTE`. Belum dihitung stok gudang. |
| c. Jejak per SN | sama | `DeviceRetrievalLog` ditulis (sumber `DEAC`, `customer_id`, `task_id`, `retrieved_by`, `warehouse_pop_id`). |
| d. Staf gudang terima | `InventoryReassignService::confirmReturnedSerial()` | Staf memeriksa fisik, memilih kondisi (`used_good`/`used_damaged`), boleh koreksi model. Status → `AVAILABLE`, `condition_checked_at/by` terisi, ledger `RETURN` kedua dengan `to_pop_id` (stok gudang bertambah). |
| e. Alternatif: pelanggan antar sendiri | `receiveSerialFromCustomerAtWarehouse()` | Langsung `AVAILABLE`, tanpa transit. Kondisi dinilai saat itu. |

**Siapa memeriksa:**
- Teknisi: mengisi form laporan (foto kondisi, kelengkapan). Bukan penilaian resmi kondisi.
- Staf gudang: pemeriksa resmi saat terima. Gate `isClearedForIssue()` lepas di sini.

**Batas yang diakui:**
- Model legacy bisa salah tebak sampai staf gudang mengoreksi.
- Harga Rp 0 kecuali diisi taksiran (`estimated_value`). Laporan nilai untuk SN ini tidak akurat.

---

## 4. Jejak Saat Modem Pindah ke Pelanggan Lain

### 4a. Yang sudah tercatat (utuh)

- `inventory_transactions` append-only (`InventoryTransactionObserver` menolak `updating`/`deleting`). Semua baris per `serial_id` tetap ada.
- Pemasangan: baris `INSTALL` dengan `fop_task_id`. Pelanggan ditelusuri lewat `fopTask.customer`. Sudah ditampilkan di Lacak Barang.
- `device_retrieval_logs` menyimpan `customer_id` per kejadian. Log tetap ada walau `customer_id` di `inventory_serials` dikosongkan saat diterima.
- Riwayat Pengambilan Alat (`/warehouse/retrievals`) membaca log ini.

### 4b. Celah

**C1 — Modem yang digantikan saat pasang SN baru** (`InventoryService::installSerial()`, sekitar baris 179–209):
- SN lama otomatis jadi `RETURNED` dengan `to_pop_id` = gudang asal.
- Fisik modem masih di tangan teknisi (`current_technician_id` = teknisi pemasang).
- Ledger `RETURN` ini ikut dihitung stok gudang (`WarehouseStockAsOfService`, karena `to_pop_id` terisi) padahal barang belum diterima gudang.
- Tidak ada `DeviceRetrievalLog`, tidak ada `condition_checked`, tidak ada langkah "Terima Retur".
- Nama pelanggan hanya di kolom `notes` (teks), bukan relasi.

**C2 — Riwayat sebelum ledger dimulai**
- Data sebelum pengambilan pertama hanya ada di dump legacy (`BackfillDeviceRetrievedStatusCommand`, `BackfillLegacyDeviceAndPaymentDataCommand`). Tidak ada rantai dari pembelian sampai pelanggan pertama.
- Backfill massal SN legacy sengaja tidak dilakukan (273 SN kosong, 93 SN dobel).

**C3 — Model placeholder tidak dikoreksi**
- Kalau teknisi memilih `MODEM-PELANGGAN-LAMA` dan staf tidak koreksi saat terima, riwayat model salah dan tidak terdeteksi.

**C4 — Nilai aset**
- Legacy tanpa harga beli. Nilai hanya dari taksiran saat terima.

---

## 5. Rekomendasi (belum dikerjakan, perlu konfirmasi)

1. **C1: DIKERJAKAN (ADHOC-108, 2026-10-08).** `installSerial()` sekarang menulis `RETURN` ke `to_technician_id` (bukan `to_pop_id`) + `DeviceRetrievalLog` (`source=CREQ_SWAP`). Modem lama lanjut ke alur retur 3-tahap yang sama dengan DEAC (`docs/warehouse/business-logic.md` §12b) — stok baru bertambah di Pusat setelah Tahap 3, bukan di Cabang saat Terima Retur (yang juga bukan lagi titik final).
2. **C3:** tampilkan peringatan di Terima Retur bila model masih placeholder, dan wajibkan koreksi model sebelum `AVAILABLE`.
3. **C2 & C4:** biarkan. Dokumentasikan di laporan bahwa riwayat sebelum tanggal pengambilan pertama berasal dari data legacy.

**Catatan repo:** perubahan §5.1 menyentuh alur Ticketing/ledger yang berpotensi mengubah riwayat. Perlu persetujuan user sebelum coding. Test wajib dibuat bila dikerjakan.

---

## 6. Referensi Kode

- `app/Services/InventoryReassignService.php` — `pickupSerialFromCustomer()`, `receiveSerialFromCustomerAtWarehouse()`, `claimSerialFromCustomer()`, `confirmReturnedSerial()`
- `app/Services/InventoryService.php` — `installSerial()` (blok pelanggan lama → `RETURNED`, sekitar baris 179–209)
- `app/Http/Controllers/Warehouse/WarehouseTraceabilityController.php` — ledger per SN, eager load `fopTask.customer`
- `app/Models/DeviceRetrievalLog.php`, `app/Models/InventoryTransaction.php`
