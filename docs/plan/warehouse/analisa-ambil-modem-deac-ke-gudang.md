# Analisa & Rancangan: Ambil Modem (DEAC) → Gudang, termasuk SN Legacy

**Status:** SUDAH DIKERJAKAN 2026-09-19 (ADHOC-86, di luar Sprint 8.10, permintaan eksplisit user). Dokumen modul: `docs/warehouse/business-logic.md` §12a, `docs/warehouse/flowchart.md`, `docs/task-teknisi/business-logic.md`. Test: `DeviceRetrievalDeacToWarehouseTest` (17 kasus).

**Lanjutan (ADHOC-88, 2026-09-21):** riwayat/log teknisi per SN, kartu di Detail Pelanggan, "Terima modem dari pelanggan" (tanpa task), nilai taksiran opsional, petunjuk merek data lama, dan reset `device_retrieved_at` saat Langganan Lagi sudah dikerjakan — lihat `analisa-riwayat-dan-terima-modem-dari-pelanggan.md`. Butir "Belum dikerjakan" di ADHOC-86 yang terkait itu sudah tertutup di sana; BAP tetap tidak dipakai (keputusan user), backfill massal tetap tidak dilakukan.

**Penyimpangan dari rancangan awal (dicatat supaya tidak dianggap lupa):**
1. **Reject FOP TIDAK membalik SN `RETURNED`** (rancangan awal §4.5: dibalik ke `INSTALLED`, baris adopsi dihapus). Diputuskan saat implementasi: modem itu memang ada di tangan teknisi, ledger append-only tidak boleh dihapus/dibalik diam-diam, dan membalik baris hasil adopsi butuh ledger koreksi. Sebagai gantinya reject hanya mengosongkan `device_retrieved_at`, dan `pickupSerialFromCustomer()` idempoten untuk SN yang sudah `RETURNED` dari pelanggan yang sama, jadi kirim ulang laporan aman.
2. **Penanda asal DEAC di ledger = `fop_task_id` terisi** pada baris `RETURN` — tanpa kolom/tipe baru (retur custody biasa tidak pernah mengisi `fop_task_id`).
3. **`returnInstalledSerialFromCustomer()` dipertahankan** (tidak dihapus): 11 test ADHOC-80 memakainya sebagai fixture "bekas belum dicek", dan data DEAC lama masih berada di state itu. `TaskService` tidak lagi memanggilnya.
4. `customer_id`/`fop_task_id` SN **dipertahankan selama transit** (rancangan awal: dikosongkan), baru dikosongkan saat gudang menerima — jejak "modem ini dari pelanggan siapa" tidak putus.

**Terkait:**
- `docs/plan/warehouse/analisa-gap-kondisi-barang.md` (ADHOC-80) — sumber gate "Sudah Dicek"; gap SN legacy sudah diakui di §3 tapi tanpa rancangan solusi. Dokumen ini menutupnya.
- `docs/plan/billing/analisa-rancangan-putus-langganan.md` (ADHOC-69) — billing saat putus. Tidak bentrok, lihat §6.
- `docs/plan/billing/rancangan-terminate-reactivate-state-machine.md` (ADHOC-85) — terminate & "Langganan Lagi".

---

## 1. Ringkasan Putusan

Alur "Ambil Alat" sekarang mengembalikan SN yang tercatat `INSTALLED` langsung ke `AVAILABLE` di cabang, tanpa konfirmasi gudang, lewat form laporan yang salah sasaran. SN modem legacy tidak ikut sama sekali karena tidak pernah ada di `inventory_serials`. `device_retrieved_at` tetap terisi walau tidak ada SN yang masuk gudang.

Rancangan: form DEAC khusus (teknisi input SN fisik), SN legacy di-adopsi otomatis, dan modem masuk status transit sampai gudang cabang mengonfirmasi penerimaan.

---

## 2. Kondisi Sekarang (hasil baca kode, 2026-09-19)

### 2.1 Alur

1. Pelanggan `terminated` → admin/NOC/FOP/pop_admin tekan **Ambil Alat** (`CustomerController::retrieveDevice()`, `CustomerController.php:159`). Teknisi sengaja tidak punya izin ini.
2. `TicketService::createDeviceRetrievalTask()` (`:632`) membuat FopTask `DEAC` berstatus `DRAFT`, tanpa Ticket dan tanpa teknisi.
3. FOP assign teknisi di `/fop-tasks` → Task eksekusi kebentuk.
4. Teknisi Start lalu isi laporan lewat **form Maintenance generik** (`TaskMaintenanceController::store()`).
5. `TaskService::complete()` (`:250-278`), khusus `AMBIL_MODEM`:
   - `customer_devices.device_retrieved_at = completed_at`
   - `InventoryReassignService::returnInstalledSerialFromCustomer()` (`:143-195`): semua SN `INSTALLED` milik pelanggan → `AVAILABLE`, kondisi `USED_GOOD` belum dicek, `current_pop_id = issued_from_pop_id`, ledger `RETURN`.
6. FOP review (approve/reject/pending) lewat `TaskController::review()`.

### 2.2 Gap

| # | Gap | Bukti |
|---|---|---|
| G1 | Form laporan salah sasaran: wajib `opm_photo` + `speedtest_photo`; tidak ada input SN, foto kondisi, kelengkapan, atau opsi "tidak ditemukan/ditolak". Dropdown "Modem Aktif" di form itu memanggil `installSerial()` ke pelanggan yang sudah putus. | `TaskMaintenanceController.php:192-193`, `:331-334` |
| G2 | Tidak ada verifikasi SN: semua SN `INSTALLED` milik pelanggan dikembalikan tanpa teknisi mengonfirmasi mana yang fisiknya dibawa. | `TaskService.php:266-277` |
| G3 | **SN legacy diabaikan diam-diam.** Data legacy hanya di `customer_technical_details.router_or_ont_serial` / `customers.ont_sn`, tidak pernah ke `inventory_serials`. Query `INSTALLED` kosong, tapi `device_retrieved_at` tetap terisi → badge "Sudah Diambil" padahal tidak ada SN masuk gudang. | `TaskService.php:263-266`, `CustomerController.php:2821-2899`, `MigrateLegacyDataCommand.php:957-973` |
| G4 | Receive biasa bukan jalur legacy: kondisi `NEW`, harga wajib ≥ 1, hanya Gudang Pusat, ledger pengadaan, pelanggan tidak tertaut. Scan Barang malah menyarankan "Catat sebagai Barang Masuk" untuk SN tak dikenal. | `InventoryReceiveService.php:92-156`, `:421-442`; `WarehouseScanController.php:142-149` |
| G5 | Tanpa serah terima: SN tidak lewat custody teknisi dan tidak ada konfirmasi fisik gudang. Satu-satunya kontrol = gate "Sudah Dicek" sebelum Issue. | `InventoryReassignService.php:143-195` |
| G6 | `returnInstalledSerialFromCustomer()` melempar `InvalidArgumentException` (mis. `issued_from_pop_id` kosong) dan `TaskService::complete()` tidak menangkapnya / tidak punya transaksi sendiri → task bisa setengah jadi lewat `TaskStatusController::complete`, atau gagal selesai + foto yatim lewat `TaskMaintenanceController`. | `InventoryReassignService.php:157-159`, `TaskService.php:271-277` |
| G7 | Reject FOP tidak membalik efek: `TaskController::review()` (`:613-635`) hanya mengembalikan `Task.status` ke `in_progress`; `device_retrieved_at` dan SN yang sudah `AVAILABLE` tetap. | `TaskController.php:613-635` |
| G8 | Tidak ada laporan per teknisi/per task DEAC; ledger `RETURN` DEAC tidak bisa dibedakan dari `RETURN` custody biasa (hanya teks `reason`). | `WarehouseReportController.php`, `InventoryReassignService.php:182-193` |
| G9 | Traceability tanpa fallback ke SN di data pelanggan → SN legacy tidak bisa dilacak dari pelanggan ke gudang. | `WarehouseTraceabilityController.php:33-94` |
| G10 | Endpoint `POST /tasks/{task}/complete` bisa menyelesaikan DEAC tanpa laporan (tidak ada tombol UI, tapi route ada). | `routes/web.php:937` |
| G11 | Non-serialized (roll, material) tidak punya jalur balik. Gap lama, sengaja ditunda. | `analisa-gap-kondisi-barang.md` §4 |

Temuan sampingan (tidak dikerjakan di sini): empat `SerialStatus` tidak pernah ditulis (`RECEIVED`, `RESERVED`, `IN_USE`, `RETURNED`); flowchart menggambar `QUARANTINE → AVAILABLE` yang tidak ada di kode; `description` task DEAC generik tanpa alasan putus; `createDeviceRetrievalTask()` tidak memanggil notifikasi ke FOP (belum diverifikasi apakah masih berlaku).

---

## 3. Keputusan (user, 2026-09-19)

1. **SN legacy: teknisi input SN fisik, sistem auto-adopsi.** Data legacy hanya punya SN, **tanpa nama barang** → lihat §4.1 soal item.
2. **Serah terima: status transit + konfirmasi gudang.** Modem tidak langsung `AVAILABLE`.
3. **Laporan: form DEAC khusus.** Laporan per teknisi per periode dan BAP **tidak dipilih** → di luar cakupan.

4. **DEAC tanpa SN** (tidak ditemukan / pelanggan menolak): task boleh selesai, alasan wajib, `device_retrieved_at` tetap **kosong** → badge "Belum Diambil" dan tombol Ambil Alat muncul lagi.
5. **Permission "Terima Retur"**: reuse `warehouse_reassign.create`.
6. **Mulai implementasi sekarang** sebagai ADHOC-86, di luar Sprint 8.10.

---

## 4. Rancangan

### 4.1 Alur target

```
Teknisi (form DEAC khusus)
  input SN fisik + pilih item + foto kondisi + kelengkapan   (atau "tidak ditemukan/ditolak" + alasan)
    │  TaskService::complete()
    ▼
SN → RETURNED (transit), current_technician_id = teknisi, customer_id dikosongkan
     ledger RETURN pelanggan → teknisi
    │
    ▼  Gudang cabang: halaman "Terima Retur" (halaman create, bukan modal)
SN → AVAILABLE, current_pop_id = cabang, kondisi dinilai staf gudang (used_good / used_damaged)
     ledger RETURN teknisi → gudang; gate isClearedForIssue terpenuhi sekaligus
```

`SerialStatus::RETURNED` sudah ada di enum tapi tidak pernah ditulis. Dipakai ulang sebagai "transit" → **tanpa case enum baru**. Pola sama dengan `TRANSFERRED → AVAILABLE` di `InventoryTransferService`.

**Item untuk SN legacy.** Data legacy tidak punya nama barang, tapi `inventory_serials.item_id` wajib.
- Form DEAC menyediakan dropdown item (SERIALIZED + INSTALLABLE); teknisi memilih model yang ia lihat di fisik.
- Item placeholder **"Modem Legacy (Belum Teridentifikasi)"** ditambah lewat `ItemSeeder` sebagai pilihan cadangan.
- Staf gudang boleh **mengoreksi item** saat Terima Retur.

### 4.2 Form DEAC khusus

- Route + view baru, di-`match` di `tasks/show.blade.php:1186` dan `tasks/partials/own-card.blade.php:235` (pola SURVEY/PSB yang sama).
- Field: SN (scan/ketik, boleh lebih dari satu), item, foto kondisi, kelengkapan (adaptor, patchcord, dll.), opsi "tidak ditemukan/ditolak" + alasan wajib.
- Foto OPM/speedtest dan dropdown "Modem Aktif" tidak dipakai untuk DEAC. `TaskMaintenanceController` tidak lagi menangani DEAC.

### 4.3 Service (`InventoryReassignService`)

- `returnInstalledSerialFromCustomer()` diganti `pickupSerialFromCustomer()`, menerima SN input teknisi:
  - SN `INSTALLED` milik pelanggan ini → `RETURNED`.
  - SN belum ada di sistem → buat baris baru (`RETURNED`, kondisi `USED_GOOD` belum dicek, `issued_from_pop_id` = POP task).
  - SN ada tapi di status/pelanggan lain → **tolak** dengan pesan jelas; jangan timpa (konflik data, admin yang menyelesaikan).
- `confirmReturnedSerial()` baru: `RETURNED → AVAILABLE`, set kondisi, koreksi item, tulis ledger.
- SN `INSTALLED` milik pelanggan yang tidak dilaporkan: tetap `INSTALLED`; teknisi wajib memberi alasan.

### 4.4 Halaman Gudang "Terima Retur"

Mutasi data → **halaman create tersendiri**, bukan modal (aturan CLAUDE.md pola 2: `back()->withErrors()` dari modal menutup modal tanpa pesan). Daftar SN `RETURNED` per cabang, scope POP lewat `EffectiveAccessService`. Reuse pola `WarehouseReassignController` dan `WarehouseTraceabilityController::checkCondition()`.

### 4.5 Perbaikan guard

- Bungkus retur di `TaskService::complete()` dengan `DB::transaction()` dan tangkap exception (G6).
- ~~Reject FOP membalikkan efek inventori~~ → **diubah saat implementasi**: reject hanya mengosongkan `device_retrieved_at`; SN `RETURNED` tidak dibalik (lihat "Penyimpangan" di atas) (G7).
- `device_retrieved_at` hanya terisi kalau minimal satu SN dilaporkan (G3).

### 4.6 Penanda asal DEAC di ledger

Supaya laporan berikutnya bisa memisahkan RETURN-DEAC dari RETURN custody (G8). Bentuk (kolom vs tipe) diputuskan saat implementasi; bukan sekadar teks `reason`.

### 4.7 Yang tidak berubah

Gate `isClearedForIssue()` dan aksi "Sudah Dicek" (ADHOC-80) tetap. SN `RETURNED` tidak muncul di dropdown Issue.

---

## 5. Pertanyaan Terbuka

1. **DEAC selesai tanpa SN ("tidak ditemukan/ditolak").** Usulan: boleh selesai, alasan wajib, `device_retrieved_at` tetap **kosong** → badge "Belum Diambil" dan tombol Ambil Alat muncul lagi untuk dicoba ulang (guard hanya melarang FopTask DEAC yang masih terbuka). Alternatif: tetap terisi seperti sekarang, tapi badge menyesatkan.
2. **Permission "Terima Retur".** Usulan: reuse `warehouse_reassign.create` (konsisten dengan "Sudah Dicek"). Alternatif: feature baru. Cek `docs/rbac/` saat implementasi.
3. **Backfill SN legacy massal** (dari `customer_technical_details`) tidak dipilih user; bisa jadi task lanjutan.
4. **Non-SN** (roll/material): tetap tanpa jalur balik.

---

## 6. Dampak ke Modul Lain

- **ADHOC-69 (putus langganan):** tidak ada tabrakan file (69: `CustomerTerminationController`, `terminated.blade.php`, Master Alasan; dokumen ini: `TaskService::complete`, `InventoryReassignService`, view task, Gudang). Titik singgung: §3.2 ADHOC-69 mewajibkan badge "Sudah Diambil" dan tombol Ambil Alat tetap berfungsi → perubahan semantik `device_retrieved_at` (§4.5) **wajib dijaga test regresi**. Setelah ADHOC-69 jadi, `description` task DEAC bisa membawa `termination_reason`; jangan buat kolom alasan sendiri.
- **ADHOC-85 ("Langganan Lagi"):** belum dicek apakah reaktivasi mereset `device_retrieved_at`. Kalau tidak, pelanggan yang kembali tetap berbadge "Sudah Diambil". Cek saat implementasi.
- **ADHOC-80:** SN hasil adopsi memakai kondisi/gate yang sama.

---

## 7. Test yang Wajib Ada (nama sesuai gejala)

- DEAC dengan SN terdaftar `INSTALLED` → `RETURNED`; Terima Retur → `AVAILABLE` + kondisi + ledger.
- DEAC dengan SN legacy tak dikenal → baris baru dibuat (tidak diabaikan), item sesuai pilihan teknisi.
- SN konflik (status/pelanggan lain) → ditolak, task tidak setengah jadi.
- DEAC tanpa SN + alasan → selesai, `device_retrieved_at` kosong, tombol Ambil Alat muncul lagi.
- Reject FOP mengosongkan `device_retrieved_at`, SN tetap `RETURNED`, kirim ulang laporan idempoten (tanpa ledger dobel).
- SN `RETURNED` tidak muncul di dropdown Issue sebelum diterima gudang.
- Terima Retur tunduk POP scope dan permission.
- Regresi: `CustomerRetrieveDeviceCreatesFopTaskTest`, `InventorySerialConditionTrackingTest`, badge/tombol Ambil Alat (ADHOC-69 §3.2).

## 8. Docs yang Diupdate Saat Implementasi

`docs/warehouse/business-logic.md`, `docs/warehouse/flowchart.md`, `docs/task-teknisi/`, `docs/TASKS.md`.
