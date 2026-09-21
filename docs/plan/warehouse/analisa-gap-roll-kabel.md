# SUDAH DI KERJAKAN

# Analisa Gap — Fitur Kabel Per-Roll (`TrackingType::ROLL`)

Dicatat 2026-09-15. Fitur ROLL baru dibuat (uncommitted saat analisa ini ditulis — cek `git status` sebelum lanjut, kalau udah dicommit sebagian info "belum ada" di bawah bisa basi). Konteks pemicu: pertanyaan user — "2 roll kabel 1000m, masing sisa 30m, otomatis gak kepake, gimana sistem nanganin?"

## 1. Fungsi LOT (`BATCH` + `lot_no`) vs ROLL — beda konsep

`BATCH` (`TrackingType::BATCH`) = QUANTITY + tag `lot_no` opsional. Cuma jawab "lot LOT-2026-001 sisa berapa" — agregat angka, TIDAK ada identitas per-unit yang bisa dicetak/discan. 1 baris `inventory_balances` per `(pop_id, item_id, lot_no)`.

`ROLL` beda kelas: identitas per-unit fisik (`roll_code` unik digenerate sistem, bisa discan) + `length_remaining` yang berkurang sebagian-sebagian per pemakaian (dipotong ke banyak pelanggan/task beda dari 1 roll yang sama). 1 baris `inventory_rolls` per roll fisik.

## 2. Kenapa `ROLL` dibikin field baru, bukan varian `BATCH`

BATCH gak bisa jawab "roll MANA yang dipake ke pelanggan MANA, sisa berapa" — cuma tau lot-nya doang, gak granular ke fisik individual. ROLL butuh krn tracking per-pelanggan (traceability) + label QR fisik yang bisa ditempel di drum kabel bener-bener.

## 3. Status implementasi ROLL saat ditemukan (audit `git status`)

**Sudah ada (RECEIVE only):**
- Enum `TrackingType::ROLL`, `RollStatus` (11 status, termux `IN_USE`/`DEPLETED` beda dari `SerialStatus`).
- Model `InventoryRoll` (`length_total`/`length_remaining`, status lifecycle sendiri).
- Migration: `inventory_rolls`, `items.meter_per_roll`, `inventory_transactions.roll_id`, `customer_installations.selected_inventory_roll_id`+`roll_meters_used`.
- `InventoryReceiveService::receiveRoll()` — generate N roll baru di Pusat, snapshot `meter_per_roll` → `length_total`.
- Cetak label QR roll (`WarehouseRollController`, `RollLabelQrRenderer`) — permission reuse `warehouse_transfer.view`.
- Diverifikasi konsisten: relasi model (`Item::inventoryRolls()`, `InventoryTransaction::roll()`) kepasang bener, validasi form Receive bener, `WarehouseReceiveTest` diupdate.

**Belum ada sama sekali** — lihat poin 4-7.

## 4. Gap Transfer (Pusat → Cabang)

`InventoryTransferService` gak py cabang ROLL. Perlu `dispatchRoll()`/`receiveRollTransfer()`, pola sama `dispatchSerialized()`/`receiveSerialized()` (pindah `current_pop_id`, `AVAILABLE`↔`TRANSFERRED`, `lockForUpdate()`).

## 5. Gap Issue (Cabang → custody Teknisi) — BUG LATEN

`InventoryIssueService::issue()` (line 57-59) cuma cabang `SERIALIZED` vs else→`issueQuantity()`. Item ROLL SEKARANG bakal nyasar ke `issueQuantity()` yang nyari `InventoryBalance` — salah jalur total, bakal throw ngaco atau salah tulis data kalau dicoba.

Perlu `issueRoll()` baru. **Keputusan masih terbuka (belum dijawab user):** teknisi pilih roll spesifik manual (kayak pilih SN), atau sistem auto-pilih roll `length_remaining` PALING KECIL dulu (biar sisa dikit kepake duluan, hindarin numpuk banyak roll sisa dikit — relevan langsung ke pertanyaan awal user).

## 6. Gap Consume (roll dipakai ke pelanggan)

Docblock migration nyebut `InventoryService::consumeFromRoll()` tapi method itu **belum ada**. Perlu dibikin pola sama `installSerial()` (re-fetch+lock, kurangi `length_remaining`, kalau nyampe 0 → `DEPLETED`), dipanggil dari `CustomerInstallationController::storeSpeedtest()` pas `selected_inventory_roll_id` keisi (sejalan pola `selected_inventory_serial_id` yang udah ada) — plus kemungkinan jalur `TaskMaintenanceController` (kabel juga kepake di MTN, bukan cuma PSB).

## 7. Gap Return / Reassign (balikin sisa roll)

`InventoryReassignService` belum py jalur ROLL. Dua skenario: **Return** ke Gudang Cabang (`status→AVAILABLE`, `current_pop_id` keisi lagi, `current_technician_id=null`), atau **Reassign** custody ke teknisi lain langsung tanpa muter ke gudang. Pola nyontek existing buat SN/BATCH.

## 8. Threshold `minimum_length` — jawaban langsung buat kasus "2 roll sisa 30m"

Kolom baru **`items.minimum_length`** (BUKAN di `inventory_rolls` — ambang itu properti jenis kabel, bukan per-roll individual). Diisi di Master Barang bareng `meter_per_roll`. Roll dg `length_remaining < item.minimum_length` di-flag "Sisa Kecil":
- Badge di halaman list roll/custody.
- Section baru di Dashboard (sejalan low-stock existing), agregat total meter "nganggur".
- **Tetap tercatat sebagai stok, gak di-write-off otomatis** — sistem cuma kasih tau, keputusan lanjut (splice gabung, scrap, biarin) tetap manusia. Konsisten prinsip repo: gak ada auto-write-off tanpa approval (`docs/warehouse/business-logic.md:81`).
- Ini beda axis dari `minimum_stock`/`maximum_stock` di `inventory_balances` (itu ambang TOTAL AGREGAT per pop+item+lot, bukan per-unit fisik) — jangan disamain/digabung kolomnya.

## 9. Urutan pengerjaan — dependency chain WAJIB, bukan pilihan prioritas

Transfer → Issue → Consume → Return/Reassign. Issue butuh roll udah di Cabang (hasil Transfer). Consume butuh roll udah di custody teknisi (hasil Issue). Return/Reassign butuh roll yang lagi di custody. Gak bisa diloncat/dikerjain acak. Threshold (poin 8) independen dari rantai ini — bisa duluan/paralel karena cuma nempel di Item + query `length_remaining`, gak nunggu Transfer/Issue/Consume selesai.

---

## Belum diputuskan (blocker sebelum mulai koding)

- **Poin 5**: Issue roll — manual pilih vs auto-pilih sisa terkecil dulu?
- Entri ADHOC baru di `docs/TASKS.md` — belum ditulis, nunggu keputusan di atas kelar biar scope-nya final sekali tulis (bukan diedit bolak-balik).

## Test yang wajib ditambah (belum ada satu pun)

`WarehouseRollTransferTest`, `WarehouseRollIssueTest`, `InventoryConsumeFromRollTest`, `WarehouseRollReassignTest`, `WarehouseRollThresholdTest`.
