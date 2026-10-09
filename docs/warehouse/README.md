# Modul Gudang / Warehouse

Modul manajemen inventory: stok gudang (Pusat + Cabang), custody teknisi, dan traceability unit bernomor seri (SN) — dari barang masuk dari distributor sampai terpasang di pelanggan.

## Konsep Inti

**Ledger append-only sebagai satu-satunya sumber kebenaran.** Setiap pergerakan barang ditulis sebagai satu baris `inventory_transactions` yang **tidak pernah** diubah/dihapus (ditegakkan `InventoryTransactionObserver`, bukan sekadar konvensi). Tabel-tabel lain — `inventory_balances` (stok per gudang), `inventory_serials.status` (status SN saat ini), `technician_custody` (custody teknisi) — adalah **proyeksi yang diturunkan** dari ledger ini, bukan sumber kebenaran independen. Kalau ada keraguan, hitung ulang dari `SUM(inventory_transactions)`, jangan percaya angka proyeksi begitu saja.

Salah catat dilawan dengan baris koreksi baru (`ADJUSTMENT`/`STOCK_OPNAME`), bukan edit baris lama.

## Entitas Utama

| Entitas | Tabel | Peran |
|---|---|---|
| `Item` / `ItemCategory` | `items`, `item_categories` | Master barang, 3 axis klasifikasi independen (lihat [business-logic.md](business-logic.md)) |
| `InventoryBalance` | `inventory_balances` | Stok saat ini per `(gudang, item, lot)` — proyeksi |
| `InventorySerial` | `inventory_serials` | Satu baris per unit fisik SN, status kanonik (`SerialStatus`) + kondisi fisik (`ItemCondition`, axis independen — lihat [business-logic.md §12](business-logic.md#12-kondisi-fisik-barang-serialized-adhoc-80)) |
| `InventoryTransaction` | `inventory_transactions` | Ledger append-only — sumber kebenaran |
| `InventoryTransfer` | `inventory_transfers` | Header mutable Transfer Pusat→Cabang (2 fase) |
| `TechnicianCustody` | `technician_custody` | Custody barang QUANTITY yang dipegang teknisi |
| `StockRequest` / `StockRequestItem` | `stock_requests`, `stock_request_items` | Tiket permintaan stok Cabang→Pusat (bukan ledger) |
| `DeviceRetrievalLog` | `device_retrieval_logs` | Jejak pengambilan modem dari pelanggan, satu baris per SN (teknisi pengambil, penerima gudang, transit/diterima) — ADHOC-86/88, lihat [business-logic.md §12a](business-logic.md#12a-ambil-modem-deac--terima-retur-adhoc-86) |

Gudang **direpresentasikan lewat `pops`** (`type` = `pusat`/`cabang`) — sengaja tidak ada tabel `warehouses` terpisah.

## Aktor

- **Admin Gudang Pusat** — Receive (barang masuk dari distributor), dispatch Transfer ke Cabang, kelola Stock Request masuk.
- **Admin Gudang Cabang** (`pop_admin`, scoped ke POP-nya) — confirm Transfer, Issue ke teknisi, adjustment/opname, reassign custody, ajukan Stock Request ke Pusat.
- **Teknisi** — pemegang custody (tidak punya akses UI Gudang); custody-nya dikonsumsi otomatis lewat integrasi ke Task Teknisi (`InventoryService::consumeFromCustody()` / `installSerial()`) saat submit laporan pemasangan/maintenance. Pada task **Ambil Modem (DEAC)** teknisi menginput SN modem yang dicabut lewat form laporan khusus; modem itu jadi `RETURNED` (transit) sampai gudang cabang menerimanya (§12a).
- **Owner/atasan** — dashboard KPI, laporan, traceability lintas cabang (kalau scope-nya `all_pop`).

## Entry Point per Peran

- Dashboard: `warehouse.index` (`/warehouse`)
- Barang Masuk: `warehouse.receive.create`
- Transfer: `warehouse.transfers.create` (dispatch) → `warehouse.transfers.show` (confirm oleh Cabang)
- Keluar ke Teknisi: `warehouse.issues.create`
- Kelola Stok (saldo + serial + threshold): `warehouse.stock.index`
- Adjustment: `warehouse.adjustments.{balance,opname,custody,serial}.create`
- Reassign custody: `warehouse.reassign.{custody,serial}.create`
- Permintaan Stok Cabang→Pusat: `warehouse.stock-requests.*`
- Custody aktif (read-only): `warehouse.custody.index` — termasuk tab **Return dari Pelanggan** (modem transit di tangan teknisi)
- Terima Retur (konfirmasi modem hasil task Ambil Modem): `warehouse.returns.index` → `warehouse.returns.receive.create`
- Terima modem dari pelanggan (diantar sendiri, tanpa task): `warehouse.returns.from-customer.create`
- Riwayat Pengambilan Alat (log teknisi per SN): `warehouse.retrievals.index`
- Traceability per SN: `warehouse.traceability.index`
- Riwayat ledger: `warehouse.history.index`
- Laporan bulanan: `warehouse.reports.index`
- Scan-first: `warehouse.scan.index`

## Dokumen Terkait

- [business-logic.md](business-logic.md) — aturan bisnis lengkap per alur
- [database-schema.md](database-schema.md) — struktur tabel & relasi
- [user-flow.md](user-flow.md) — langkah UI per peran/halaman
- [flowchart.md](flowchart.md) — diagram alur & state machine

Riwayat rancangan (historis, bukan dokumentasi final) ada di `docs/plan/warehouse/` — **kode aktual adalah sumber kebenaran** kalau berbeda dari rancangan.

## Stack Relevan

- Enum: `App\Enums\{InventoryTransactionType,SerialStatus,ItemCondition,CustodyStatus,TransferStatus,StockRequestStatus,TrackingType,RollStatus,OwnershipMode,EquipmentClass}`
- Service: `app/Services/Inventory{Receive,Transfer,Issue,Adjustment,Reassign}Service.php`, `StockRequestService.php`, `InventoryService.php` (jembatan ke Task Teknisi)
- Controller: `app/Http/Controllers/Warehouse/*` (13 file) + trait `Concerns\AuthorizesWarehousePop`
- Observer: `app/Observers/InventoryTransactionObserver.php` (block update/delete)
- View: `resources/views/warehouse/*`
