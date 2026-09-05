
Urutan kerja (dependency-based, tiap tahap bisa di-test sendiri sebelum lanjut ke berikutnya):

0. Gerbang dulu (bukan teknis)
Ini di luar Sprint 8.10 aktif (S8.10-T003 masih jalan). Sebelum baris kode pertama, perlu diputusin: modul gudang ini masuk sprint baru/ad-hoc terpisah, dan docs/TASKS.md diupdate nyatet itu. Saya gak mulai coding tanpa ini eksplisit. DI LUAR DARI SPRINT S8.10-T003. Kerjakan Fase ini 

1. Enum dulu — fondasi semua yang lain nunjuk ke sini
    1. EquipmentClass (aktif/pasif)
    2. TrackingType (SERIALIZED/QUANTITY/BATCH)
    3. OwnershipMode (installable/company_asset)
    4. InventoryTransactionType (RECEIVE/TRANSFER/ISSUE/RETURN/ADJUSTMENT/TRANSFER_CUSTODY/STOCK_OPNAME)
    5. Status kanonik serial (§15 doc advanced) + status custody QUANTITY/BATCH (ISSUED/PARTIALLY_USED/RETURNED/CONSUMED) — dua enum terpisah
    6. ActionCode::RECEIVE nambah ke enum existing

2. Migration — urut karena ada FK
    1. Alter items: tracking_type, ownership_mode, equipment_class_override
    2. Alter item_categories: equipment_class
    3. inventory_balances (pakai pop_id langsung sebagai referensi gudang — gak perlu tabel warehouses terpisah, cukup pops.type IN (pusat,cabang), sesuai keputusan "reuse pops.id" §29.9. Ini simplifikasi, satu migration lebih dikit dari draf awal)
    4. inventory_transactions (ledger)
    5. inventory_serials
    6. technician_custody
    7. Alter task_materials: tambah lot_no nullable (unit_price_snapshot udah ada, tinggal mulai diisi)

3. RBAC
WarehouseFeatureSeeder pola sama ItemFeatureSeeder + entri config/rbac.php (warehouse, warehouse_transfer, warehouse_issue, warehouse_custody, warehouse_traceability) + grant ke role lewat UI Role Management (bukan hardcode).

4. Model + relasi
InventoryBalance, InventoryTransaction, InventorySerial, TechnicianCustody + update Item/ItemCategory (accessor getEffectiveEquipmentClassAttribute()).

5. Observer — invariant ledger
InventoryTransactionObserver: larang update()/delete() (append-only, §6 kontrol-anti-manipulasi) — dipasang SEBELUM Service ditulis, biar dari baris pertama gak mungkin ada yang nyoba edit histori.

6. Service — business logic (di sinilah alur utama hidup)
    Urut sesuai alur barang:
    1. InventoryReceiveService (RECEIVE di Pusat)
    2. InventoryTransferService (create + terima, multi-lot)
    3. InventoryIssueService (issue ke teknisi, isi TechnicianCustody/inventory_serials)
    4. InventoryService::consumeFromCustody() (dipanggil pas submit laporan — FIFO, ceiling enforcement, InsufficientCustodyException)
    5. InventoryReassignService (RETURN/TRANSFER_CUSTODY)
    6. InventoryAdjustmentService (LOST/DAMAGED/SCRAPPED/shrinkage via ADJUSTMENT)

Tiap service ada test-nya sendiri sebelum lanjut ke berikutnya — jangan nulis 6 service dulu baru test belakangan.

7. Integrasi ke modul existing (paling rawan regresi — hati-hati)
- Extend installations.report/maintenance-report/laporan INFR/O-REQ: split form Aktif (dropdown SN custody) / Pasif (<x-material-rows> existing, difilter)
- Extend halaman Verifikasi Admin FOP (ADHOC-28): join inventory_serials + task_materials

8. Controller + route + view
Urutan layar ikut §2 rancangan-ui.md: Dashboard → Transfer (buat→terima) → Issue → Custody (admin lihat semua, teknisi lihat sendiri) → Reassign → Traceability.

9. Test regresi penuh + docs/TASKS.md update
php artisan test full suite (bukan cuma modul baru) — sesuai kebiasaan repo ini nyapu regresi RBAC/Policy tiap fitur baru (liat ADHOC-43/45/46 sebagai preseden).