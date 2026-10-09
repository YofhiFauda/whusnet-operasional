# SUDAH DI KERJAKAN

# Analisa: Generate ID Otomatis + Barcode 1D buat Barang Non-Serial (ODP, Splitter, dll)

Status: **Rancangan, Selesai.** Ditulis 2026-09-17, dari diskusi soal barang yang gak punya SN bawaan dari pabrik (ODP, Splitter) tapi tetap butuh ditrace per-unit kayak kabel per-roll.

## Masalah

Barang macam ODP dan Splitter gak punya serial number bawaan. Sekarang dicatat pakai `TrackingType::QUANTITY` — stok polos, gak ada identitas per-unit. Konsekuensi: kalau 1 unit dari batch 10 dipasang ke pelanggan A lalu hilang/rusak, sistem cuma tau "stok berkurang 1", gak bisa nunjuk unit spesifik mana. Gak ada history per-barang, gak bisa dicetak barcode buat tempel fisik.

Barang lain (kabel) sudah punya solusi ini lewat `TrackingType::ROLL` — ID digenerate sistem (bukan dari vendor), dicetak barcode 1D Code128, ditempel ke roll fisik, bisa discan.

Pertanyaan: apa perlu ubah kode tiap kali ada barang baru sejenis (non-SN, butuh trace per-unit) masuk ke sistem, atau bisa dikelompokkan biar otomatis?

## Temuan Kode Existing

- `app/Enums/TrackingType.php:20–56` — 3 nilai: `SERIALIZED` (SN asli per unit, tabel `inventory_serials`), `QUANTITY` (qty polos, auto-split 2 lot harga), `ROLL` (SN digenerate sistem per roll kabel + meter tersisa, tabel `inventory_rolls`).
- `app/Services/InventoryReceiveService.php:294–306` (`generateRollCode()`) — pola generate ID: `{item.code}-{YYYYMMDD}-{6digit}`, counter per bulan per prefix, MAX+1 tanpa lock (risiko diterima, unique constraint jadi jaring pengaman terakhir).
- `app/Services/Warehouse/RollLabelBarcodeRenderer.php` — render Code128 SVG generik, terima string apa aja, gak spesifik ke roll walau namanya begitu. Dipakai bareng `resources/views/warehouse/rolls/print.blade.php` dan `WarehouseRollController::print()/printBatch()` (routes `warehouse.rolls.print`, `warehouse.receive.rolls.print`).
- `app/Services/InventoryReceiveService.php:86–145` (`receiveSerialized()`) — jalur SERIALIZED SEKARANG selalu terima `array $serialNumbers` dari staf (manual ketik/textarea), gak ada opsi auto-generate. Sudah py guard SN dobel (`assertSerialNumbersUsable()`).
- `app/Models/InventorySerial.php` — satu baris per unit fisik, `status` (`SerialStatus`), `current_pop_id`/`current_technician_id`/`customer_id`, terhubung `FopTask` buat instalasi. Infrastruktur trace per-unit SUDAH lengkap, cuma sumber SN-nya yang kaku (harus manual).
- `app/Models/Item.php` — `tracking_type` + `ownership_mode` + `equipment_class_override` tiga axis independen (ADHOC-54). Tempat natural buat nambah flag baru per-item.

## Kesimpulan

Gak perlu `TrackingType` baru. `SERIALIZED` sudah py semua infrastruktur yang dibutuhin (trace per-unit, custody, install, ledger). Gap satu-satunya: sumber serial number-nya wajib manual. Solusinya nambah mode "SN digenerate sistem" di jalur SERIALIZED yang sudah ada, reuse pola `generateRollCode()` + `RollLabelBarcodeRenderer` yang sudah terbukti jalan di kabel.

Hasil: sekali infrastruktur ini dibangun, barang baru sejenis (non-SN, butuh trace per-unit) tinggal ditandai pas didaftarkan di Master Barang — **gak perlu sentuh kode lagi** tiap ada barang baru.

## Rancangan

### 1. Schema
Migration tambah kolom `items.auto_generate_serial` (boolean, default `false`, taruh setelah `tracking_type`).
- `true` → SN digenerate sistem pas Receive (ODP, Splitter, dan sejenisnya).
- `false` → tetap manual ketik (modem/ONT/router yang py SN vendor asli — behavior sekarang gak berubah).

### 2. Service (`InventoryReceiveService`)
Tambah `generateSerialCode(Item $item): string` — pola identik `generateRollCode()` (baris 294–306): `{item.code}-{YYYYMMDD}-{6digit}`, counter per bulan per prefix.

`receiveSerialized()` cabang jadi dua jalur:
- `auto_generate_serial=false` → tetap terima `array $serialNumbers` manual, gak berubah.
- `auto_generate_serial=true` → terima `int $count`, loop `generateSerialCode($item)` sejumlah itu, masuk jalur create yang sama (`InventorySerial::create()` + 1 baris ledger per SN).

Guard: kalau mode item dan parameter yang dikirim gak cocok (manual dikirim ke item auto, atau sebaliknya) → tolak dengan pesan jelas, jangan campur dua mode dalam satu call.

### 3. Barcode & Cetak
`RollLabelBarcodeRenderer` sudah generik, gak perlu diubah — tinggal dipakai ulang.

Tambahan baru:
- `WarehouseSerialController::print($serial)` / `printBatch()` — pola sama `WarehouseRollController`.
- View `resources/views/warehouse/serials/print.blade.php` — copy layout `rolls/print.blade.php`, ganti isi ke `serial_number` + nama barang.
- Route `warehouse.serials.print`.

### 4. Form Receive
Kalau item yang dipilih py `auto_generate_serial=true`, textarea SN disembunyikan, diganti input angka "jumlah unit" (Alpine toggle dari data item yang sudah di-load, gak butuh request baru). Kalau `false`, textarea tetap tampil seperti sekarang.

### 5. Master Data (Item)
Form create/edit Item: checkbox "SN digenerate sistem (barang gak punya nomor seri dari pabrik)" — muncul cuma kalau `tracking_type = SERIALIZED`. Ditandai sekali pas daftar barang, otomatis berlaku tiap Receive berikutnya.

### 6. Migrasi barang existing — DIPUTUSKAN: Cutover
User (2026-09-16): masih tahap pengembangan, data existing boleh hilang. Gak perlu backfill.
Stok lama di `inventory_balances` (QUANTITY) dibiarkan apa adanya sampai habis — TIDAK dimigrasikan ke `inventory_serials`. Cuma barang yang masuk lewat Receive baru (setelah Item ditandai `SERIALIZED` + `auto_generate_serial=true`) yang kena identitas per-unit. Gak perlu command Artisan backfill.

### 7. Test
- `InventoryReceiveServiceTest`: receive SERIALIZED auto-generate → jumlah SN sesuai `$count`, format sesuai pola, gak bentrok unique constraint.
- Guard mismatch mode (manual vs auto) → ditolak dengan pesan jelas.
- `WarehouseSerialController` print: permission + POP scope (pola sama test print roll kalau ada).

### Alur akhir
```
Item baru (ODP) didaftarkan → tracking_type=SERIALIZED, auto_generate_serial=true
        ↓
Receive → staf isi jumlah (bukan SN) → sistem generate N SN + N InventorySerial + N baris ledger
        ↓
Cetak barcode per SN (reuse RollLabelBarcodeRenderer) → tempel ke unit fisik
        ↓
Scan pas Issue/Transfer/Install → trace sama persis kayak modem/kabel
```

## Belum Diputuskan
- Belum ada estimasi sprint/prioritas — task ini belum masuk `docs/TASKS.md`.
