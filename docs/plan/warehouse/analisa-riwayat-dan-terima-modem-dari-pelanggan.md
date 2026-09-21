# Riwayat Pengambilan Alat, Terima Modem dari Pelanggan, dan Petunjuk SN Legacy (ADHOC-88)

**Status:** SUDAH DIKERJAKAN 2026-09-21 (di luar Sprint 8.10, permintaan eksplisit user). Lanjutan ADHOC-86 (`analisa-ambil-modem-deac-ke-gudang.md`).

Test: `DeviceRetrievalHistoryAndWalkInTest` (29 kasus). Dokumen modul: `docs/warehouse/business-logic.md` §12a.

## 1. Latar

Setelah ADHOC-86 muncul pertanyaan user (2026-09-21):

1. Log siapa teknisi yang menarik modem harus **terpisah dan bisa dilihat**, bukan hanya tersimpan.
2. "Langganan Lagi" akan mereset `device_retrieved_at`, tetapi **riwayat pengambilan tidak boleh hilang**.
3. Data legacy hanya punya SN (tanpa nama barang, kualitas tidak bersih) — bagaimana modem itu bisa kembali ke gudang tanpa gudang input manual satu per satu?
4. Modem legacy tidak punya harga — bagaimana nilainya?
5. Modem yang diantar pelanggan sendiri (tanpa task DEAC) — fitur khusus atau lewat gudang?

## 2. Data lama (database lokal, 2026-09-21)

| Hal | Jumlah |
|---|---|
| Record `customer_technical_details` | 1.957 |
| SN terisi / kosong | 1.684 / 273 |
| Nilai SN yang muncul di >1 pelanggan | 93 (mis. `ZTEGC88D7690` ×4) |
| SN < 6 karakter | 6 |
| Catatan memuat merek (`Perangkat: …`) | 426 |
| Pelanggan `terminated` / punya SN | 231 / 226 |

Kesimpulan yang memengaruhi rancangan:
- **Backfill massal tetap tidak dilakukan.** 273 SN kosong dan 93 SN dobel. SN dobel kemungkinan besar satu modem fisik yang berpindah antar pelanggan (data lama tidak menghapus riwayatnya), bukan salah ketik — jadi mana yang benar hanya bisa dipastikan di fisik saat modem ditarik.
- **Merek di catatan berantakan** (39 label unik: `ZTE F609` 65, `router GPON` 59, `ZTE` 45, `ZTE f660` 31, `1` 105). Bisa jadi petunjuk, tidak bisa jadi sumber kebenaran.

## 3. Keputusan (user, 2026-09-21)

1. Nilai modem: **opsional di Terima Retur, kosong = Rp 0.**
2. Modem diantar pelanggan: **fitur khusus "Terima modem dari pelanggan"**, bukan Barang Masuk.
3. Cakupan: halaman Riwayat + log teknisi, kartu riwayat di Detail Pelanggan, petunjuk merek data lama + pilih model otomatis, reset `device_retrieved_at` saat Langganan Lagi.
4. BAP tidak dipakai — foto kondisi dan SN input teknisi sudah cukup.

## 4. Rancangan

### 4.1 Tabel `device_retrieval_logs` (satu baris per SN)

Kenapa tabel sendiri: `inventory_serials.customer_id` dikosongkan begitu gudang menerima modem (SN itu nanti di-Issue ke pelanggan lain), dan `customer_devices.device_retrieved_at` direset saat Langganan Lagi. Tanpa tabel ini, "modem ini pernah diambil dari pelanggan siapa, oleh teknisi siapa, diterima siapa" hilang.

Kolom utama: `customer_id`, `serial_id`, `serial_number` (snapshot), `item_id`, `source` (`deac`/`walk_in`), `task_id`, `retrieved_by` (teknisi atau petugas gudang), `received_by`, `warehouse_pop_id`, `condition`, `estimated_value`, `condition_photo`, `accessories`, `notes`, `retrieved_at`, `received_at`.

Penulis:
- `pickupSerialFromCustomer()` — jalur DEAC, `received_at` kosong (transit).
- `confirmReturnedSerial()` — melengkapi `received_by/at`, kondisi, nilai, model final.
- `receiveSerialFromCustomerAtWarehouse()` — jalur diantar pelanggan, langsung diterima.

`retrieved_by` = teknisi **pengirim laporan**, bukan seluruh anggota tim task.

### 4.2 Halaman & fitur

| Fitur | Route | Permission |
|---|---|---|
| Riwayat Pengambilan Alat (filter teknisi/status/sumber/periode/cari, scope POP gudang tujuan) | `GET /warehouse/retrievals` | `warehouse.view` |
| Terima modem dari pelanggan (cari → pilih → form, satu langkah tanpa transit) | `GET/POST /warehouse/returns/from-customer` | `warehouse_reassign.create` |
| Nilai taksiran opsional | field di Terima Retur & Terima modem dari pelanggan | (sama) |
| Kartu "Riwayat Pengambilan Alat" | tab Perangkat, Detail Pelanggan | `customers.detail.devices.view` |

Modem diantar pelanggan sengaja **bukan** lewat Barang Masuk (`InventoryReceiveService`): itu jalur pengadaan — kondisi dipaksa `new`, harga wajib > 0, hanya Pusat, tidak tertaut ke pelanggan.

Guard walk-in: hanya pelanggan `terminated` dalam scope POP; ditolak kalau masih ada task Ambil Alat berjalan (modem bisa tercatat dua kali); banyak SN dalam satu form atomik; foto yatim dihapus kalau gagal.

### 4.3 Nilai (Rp)

SN adopsi tidak punya harga beli, dan Laporan Bulanan menghitung `qty × harga` dengan harga kosong = 0 (`WarehouseStockAsOfService`). Nilai taksiran disimpan di `unit_price_snapshot` baris ledger penerimaan (bukan menimpa apa pun). Kosong/0 → `null`. Nominal dinormalkan di server lewat `RupiahInput` ("150.000" → 150000).

Risiko yang diakui: nilai manual bisa dimanipulasi; terlihat di ledger append-only dan log riwayat.

### 4.4 Petunjuk merek data lama (`LegacyDeviceHintService`)

Membaca SN dari `customer_technical_details.router_or_ont_serial` dan merek dari catatan `Perangkat: {label} (dari data aset migrasi)`. Label dipetakan ke master barang **hanya kalau** ≥ 5 karakter (setelah dinormalisasi) dan cocok tepat **satu** barang ber-SN installable (nama atau kode). `ZTE F609`/`ZTE f660` terpetakan; `ZTE`, `1`, `router GPON`, dan `ZTE F6` (ambigu) tidak. Hanya **tebakan awal** di form DEAC/walk-in dan petunjuk di Terima Retur — teknisi/gudang tetap memutuskan; SN di stiker fisik yang berlaku.

### 4.5 Langganan Lagi

`CustomerController::reactivate()` (area ADHOC-85) mengosongkan `device_retrieved_at` di dalam transaksi yang sama. Riwayat tidak terpengaruh karena tersimpan di `device_retrieval_logs`. Perubahan hanya satu blok di dalam `DB::transaction` yang sudah ada.

## 5. Koreksi ADHOC-86 yang ditemukan di sini

Tiga view ADHOC-86 memakai `$customer->name`, padahal kolomnya `full_name` — nama pelanggan tampil kosong di Terima Retur, halaman receive, dan header form DEAC. Test lama tidak menangkapnya (hanya memeriksa SN). Sudah diperbaiki dan dijaga test `nama_pelanggan_tampil_di_halaman_terima_retur`.

## 6. Belum dikerjakan

- Backfill massal SN legacy (sengaja tidak — lihat §2).
- Export Excel Riwayat Pengambilan Alat.
- Laporan per teknisi berbentuk agregat (jumlah per teknisi per periode) — riwayat per SN sudah ada dan bisa difilter per teknisi.
- Nilai modem legacy di Laporan Bulanan bergantung pada nilai taksiran yang diisi staf; belum ada peringatan "belum dinilai".
- Notifikasi ke FOP/gudang saat modem transit terlalu lama.

## 7. Deploy

`php artisan migrate` (tabel `device_retrieval_logs`), `php artisan db:seed --class=ItemSeeder` (item `MODEM-LEGACY` dari ADHOC-86), `npm run build`.
