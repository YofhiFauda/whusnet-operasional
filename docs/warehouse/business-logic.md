# Business Logic — Modul Gudang

## 1. Tiga Axis Klasifikasi Item (Independen)

Setiap `Item` diklasifikasi lewat 3 axis yang **tidak boleh digabung** — tiap axis menjawab pertanyaan berbeda:

| Axis | Enum | Nilai | Menjawab |
|---|---|---|---|
| Cara hitung stok | `TrackingType` | `serialized`, `quantity`, `batch` | Dilacak per-unit (SN) atau per-jumlah? |
| Tujuan akhir | `OwnershipMode` | `installable`, `company_asset` | Boleh transisi ke `SerialStatus::INSTALLED`? |
| Klasifikasi bisnis | `EquipmentClass` | `aktif`, `pasif` | Perangkat aktif atau material pasif/support (buat grouping form laporan)? |

Korelasinya tinggi (Aktif≈SERIALIZED, Pasif≈QUANTITY/BATCH) tapi sengaja dipisah — bisa saja tidak sinkron. Contoh yang butuh 3 axis independen: OTDR/laptop kerja adalah SERIALIZED + AKTIF (sama seperti ONT/router) tapi **tidak pernah** boleh terpasang ke pelanggan → `OwnershipMode::COMPANY_ASSET`.

### Resolusi EquipmentClass (2 level)

Default disetel di `ItemCategory.equipment_class`; bisa di-override per baris lewat `Item.equipment_class_override` (nullable). `Item::getEffectiveEquipmentClassAttribute()` menentukan nilai final. Kategori `lainnya` (catch-all) default `pasif`; item spesifik yang ternyata aktif pakai override, bukan bikin kategori baru.

### TrackingType — detail per nilai

- **SERIALIZED** — setiap unit punya SN unik (modem, ONT, router, OLT module). Dilacak di `inventory_serials`, satu baris per unit fisik.
- **QUANTITY** — dikelola berdasar jumlah polos (RJ45, cable tie, baut). Tidak ada identitas per-unit.
- **BATCH** — QUANTITY + tag `lot_no` opsional (drum/roll kabel fiber). Bukan genealogy batch penuh (tanpa split/merge/expiry) — cukup menjawab "drum LOT-2026-001 sisa berapa meter".

## 2. Tipe Transaksi Ledger (`InventoryTransactionType`)

8 nilai, masing-masing menulis kombinasi kolom `from_*`/`to_*` yang berbeda di `inventory_transactions` (validasi kombinasi ini tugas Service, bukan DB constraint):

| Type | Kombinasi kolom | Makna |
|---|---|---|
| `receive` | `to_pop_id` | Barang masuk dari distributor ke Pusat (distributor entitas eksternal, tidak dicatat) |
| `transfer` (dispatch) | `from_pop_id`, `inventory_transfer_id` | Baris pertama transfer — Pusat berkurang |
| `transfer` (confirm) | `to_pop_id`, `inventory_transfer_id` | Baris kedua, independen — Cabang bertambah |
| `issue` | `from_pop_id`, `to_technician_id` | Gudang Cabang → custody teknisi |
| `return` | `from_technician_id`, `to_pop_id` | Teknisi → gudang, fisik balik, dikonfirmasi admin |
| `adjustment` | `to_pop_id` **atau** `to_technician_id`, `reason` wajib | Koreksi non-alur-normal: opname biasa, lost/damaged/scrapped, shrinkage |
| `transfer_custody` | `from_technician_id`, `to_technician_id` | Teknisi → teknisi lain, langsung, tidak sentuh `inventory_balances` gudang |
| `stock_opname` | `to_pop_id`, `reason='stock_opname_diff'` | Hasil hitung fisik gudang, **termasuk selisih nol** |
| `install` | `from_technician_id`, `fop_task_id` | SN terpasang ke pelanggan — titik terminal traceability |

Sengaja **tidak ada** tipe `SHRINKAGE` terpisah — selisih return dicatat sebagai `ADJUSTMENT` dengan `reason='shrinkage_on_return'`, supaya vocabulary tipe ledger tidak menumpuk tiap ada skenario baru.

## 3. Alur Receive (Barang Masuk)

- **Hanya di Gudang Pusat** (`Pop.type=pusat`) — Cabang tidak pernah RECEIVE langsung dari distributor, cuma lewat Transfer.
- `unit_price` **wajib** diisi dan > 0 — ini titik "last-cost" yang dibaca ulang saat ISSUE untuk menyalin harga ke custody teknisi.
- `lot_no` **wajib** untuk `tracking_type=BATCH`, **harus kosong** untuk `QUANTITY` biasa.
- Satu event bisa berisi banyak item sekaligus (`InventoryReceiveService::receiveBatch()`), dibungkus satu `reference_number` (`RCV-YYYYMMDD-NNNNNN`, reset counter per bulan).
- SN dobel (dalam satu submit atau sudah pernah terdaftar) ditolak dengan pesan spesifik per kasus — bukan error 500 mentah dari unique constraint DB.
- Barang SERIALIZED: satu baris `inventory_serials` + satu baris ledger **per SN** (bukan digabung agregat), supaya Traceability bisa menunjukkan RECEIVE sebagai titik pertama riwayat SN.

## 4. Alur Transfer Pusat→Cabang (2 Fase)

Dua fase independen, `InventoryTransfer` (header mutable, `TransferStatus`: `in_transit`→`received`/`received_partial`) menaungi dua ledger yang berbeda:

1. **Dispatch** (`createTransfer()`, di Pusat) — stok Pusat berkurang **seketika** (barang sudah fisik keluar gudang). SN → `TRANSFERRED`, `current_pop_id` di-null-kan.
2. **Confirm** (`receiveTransfer()`, di Cabang) — stok Cabang bertambah **baru saat dikonfirmasi**, bukan saat dispatch. Alasan: mencegah stok Cabang naik palsu kalau ada barang hilang/mismatch di jalan.

**Partial receive diperbolehkan** — `$confirmedSerialNumbers` (subset dari yang dikirim) dan `$confirmedQuantities` (qty aktual per item/lot) dikirim terpisah dari yang didispatch. SN yang tidak dikonfirmasi tetap berstatus `TRANSFERRED` (limbo, butuh investigasi manual) — tidak otomatis dianggap hilang. Status header jadi `RECEIVED_PARTIAL` kalau ada baris yang tidak 100% cocok, `RECEIVED` kalau semua cocok.

Guard konkurensi: `receiveTransfer()` re-fetch + `lockForUpdate()` transfer **di dalam** transaction (bukan pakai parameter yang bisa stale) — mencegah double-klik/2 request bersamaan dua-duanya lolos cek `isInTransit()` lalu dua-duanya mengkredit stok Cabang.

## 5. Alur Issue (Cabang → Teknisi)

Satu aksi langsung tuntas (beda dari Transfer yang 2 fase): stok Cabang berkurang **dan** custody teknisi terbentuk sekaligus.

- QUANTITY/BATCH: **setiap** issue membuat baris `technician_custody` **baru** — tidak digabung ke baris existing teknisi yang sama untuk item yang sama, supaya FIFO consumption bisa jalan per-lot per-waktu-ambil.
- SERIALIZED: SN langsung `status=ISSUED`, `current_technician_id` + `issued_from_pop_id` diisi.
- Harga custody (`unit_price_snapshot`) diambil dari `resolveLastCost()` — baca ulang RECEIVE terakhir untuk item/lot itu (filter `type=RECEIVE` secara eksplisit, supaya tidak salah baca dari baris RETURN/TRANSFER_CUSTODY yang bisa membawa harga lama).

## 6. Integrasi ke Task Teknisi

Saat teknisi submit laporan (Pemasangan/Maintenance/INFR/O-REQ), `InventoryService` menjembatani custody teknisi ke pemakaian aktual:

- **`consumeFromCustody()`** — khusus QUANTITY/BATCH. FIFO diurutkan `issued_at` ASC (bukan `lot_no`) **lintas semua anggota tim**, bukan per-orang — barang bisa diambil siapa saja di tim. Kalau custody tidak cukup, melempar `InsufficientCustodyException` (structural constraint — teknisi cuma bisa klaim sejumlah yang benar-benar ada di custody-nya). Potongan bisa jatuh di beberapa baris custody sekaligus → `task_materials` jadi beberapa baris, masing-masing membawa `unit_price_snapshot` sendiri.
- **`installSerial()`** — transisi SN ke `INSTALLED`. Guard `OwnershipMode::INSTALLABLE` ditegakkan **di sini** (satu-satunya pintu transisi), bukan hanya predikat yang bisa diabaikan pemanggilnya. Re-fetch + `lockForUpdate()` mencegah race dua submit bersamaan untuk SN yang sama.
- **`reconcileMaterialsAgainstCustody()`** — rekonsiliasi baris `task_materials` generik (dari form, tanpa lot/harga) ke custody sungguhan; dipanggil sekali di titik penyelesaian laporan (bukan tiap resubmit draft).

## 7. Adjustment & Stock Opname

4 varian, semua lewat `InventoryAdjustmentService`, **tanpa gerbang approval berjenjang** (keputusan produk sadar — threshold nominal belum bisa ditentukan tanpa data operasional riil; monitoring saat ini berbasis status barang yang tercatat di ledger, visible di dashboard):

| Method | Target | Delta | Catatan |
|---|---|---|---|
| `adjustPopBalance()` | `InventoryBalance` | Signed, **menolak delta=0** | Koreksi manual — tolak juga kalau bikin stok negatif |
| `recordStockOpname()` | `InventoryBalance` | Qty absolut hasil hitung fisik (delta 0 **diperbolehkan**) | Satu-satunya jalur yang boleh menulis ledger qty=0 — supaya "belum pernah opname" vs "opname hasilnya pas" tetap beda status di ledger |
| `adjustCustody()` | `TechnicianCustody` | Signed | Kategori reason terarah (dropdown, bukan enum baru) |
| `adjustSerialStatus()` | `InventorySerial` | — | Hanya ke `LOST`/`DAMAGED`/`SCRAPPED`/`QUARANTINE` |

**Reason categories** (`InventoryAdjustmentService::REASON_CATEGORIES`): `lost`, `damaged`, `quarantine`, `shrinkage_on_return`, `stock_opname_diff`. Evidence foto **wajib** hanya untuk `lost`/`damaged` (dan status target `LOST`/`DAMAGED`/`SCRAPPED` di `adjustSerialStatus()`) — `quarantine` cukup tanpa bukti (status tahan sementara, bukan klaim rugi). Guard evidence ditegakkan di Service, bukan cuma validasi UI.

`SCRAPPED` bersifat **final** — tidak ada transisi keluar dari status itu; SN yang sudah SCRAPPED tidak bisa diadjust lagi.

Saat SN sedang `INSTALLED` diadjust ke LOST/DAMAGED/SCRAPPED/QUARANTINE, `customer_id`/`fop_task_id`/`installed_at` dikosongkan (karena sudah tidak beneran terpasang) — tapi `current_pop_id`/`current_technician_id` **sengaja dibiarkan** sebagai jejak forensik "terakhir ada di mana".

## 8. Reassign Custody

Untuk skenario teknisi resign/cuti/rotasi — `InventoryReassignService`, tanpa approval gate, tapi tercatat penuh di ledger:

- **Return** — seluruh sisa custody kembali ke gudang cabang (`RETURN`, custody → `RETURNED`).
- **Transfer langsung** — custody pindah ke teknisi lain (`TRANSFER_CUSTODY`), tidak menyentuh `inventory_balances` gudang sama sekali.
- Sengaja hanya bisa reassign **seluruh** sisa custody, bukan qty parsial pilihan.
- `created_by`/`from_*` di ledger selalu `$actor` (admin eksekutor), **bukan** teknisi lama — teknisi lama mungkin sudah resign dan tidak bisa dimintai konfirmasi.
- `returnInstalledSerialFromCustomer()` — jalur retrieve SN dari status `INSTALLED` (mis. tombol "Ambil Alat" saat deaktivasi) balik ke `issued_from_pop_id` SN itu sendiri (gudang cabang asal ISSUE terakhir), bukan dari POP task retrieval yang bisa jadi mini_pop.

## 9. Stock Request (Cabang → Pusat)

Tiket komunikasi murni — **bukan** ledger, **tidak pernah** menyentuh `InventoryBalance`/`inventory_transactions` sendiri. Pergerakan fisik sungguhan tetap wajib lewat Transfer terpisah.

Lifecycle (`StockRequestStatus`): `pending` → `partial` (via `recordDelivery()`, satu-satunya jalur transisi ini) → `fulfilled`/`rejected`/`cancelled`.

- `isOpen()` = `pending` atau `partial` (masih bisa diproses lanjut).
- `canRejectOrCancel()` = **hanya** `pending` murni — begitu masuk `partial` (barang mulai bergerak), reject/cancel sudah tidak masuk akal lagi.
- `fulfill()` — langsung tutup `FULFILLED` apa pun sisanya (dipakai kalau admin anggap cukup meski belum 100%).
- `recordDelivery()` — append `qty_fulfilled` per baris (clamp ke sisa yang diminta), status otomatis `PARTIAL`/`FULFILLED` tergantung kelengkapan semua baris.
- `cancel()` dicek kepemilikan eksplisit di Controller (bukan cuma permission) — hanya pengaju sendiri yang boleh membatalkan.

## 10. Traceability

Setiap SN dilacak lewat **seluruh** baris `inventory_transactions` miliknya (RECEIVE→TRANSFER→ISSUE→INSTALL→...), terurut kronologis — konsekuensi langsung dari prinsip ledger append-only sebagai satu-satunya sumber kebenaran. SN dianggap "tidak ditemukan" (bukan 403) kalau di luar jangkauan POP aktor — supaya keberadaan SN di cabang lain tidak bocor lewat pesan error.

## 11. RBAC & POP Scope

Permission (format `{feature}.{action}`, di-generate dari `features`×`actions`, bukan hardcode):

`warehouse.view`, `warehouse_transfer.{create,view,receive}`, `warehouse_issue.{create,view}`, `warehouse_custody.view`, `warehouse_traceability.view`, `warehouse_adjustment.create`, `warehouse_reassign.create`, `warehouse_report.view`, `warehouse_stock_request.{view,create,approve,reject,cancel}`.

Catatan penting: **RECEIVE reuse `warehouse_transfer.create`** (tidak ada permission baru sendiri) — barang masuk dianggap bagian dari alur "memasukkan stok" yang sama kelasnya dengan dispatch transfer. Ambang stok rendah (threshold) reuse `warehouse_adjustment.create`.

### POP Scope

Trait `Concerns\AuthorizesWarehousePop` (`assertPopInScope()`/`assertPopIdInScope()`) adalah **satu-satunya** titik penegakan scope untuk controller detail/mutasi (Transfer, Issue, Adjustment, Reassign, Stock Request, threshold). Controller list murni (Dashboard, Custody, Traceability, History) discope langsung via `EffectiveAccessService` tanpa trait ini.

Ditemukan lewat audit 2026-09-02: dropdown POP di halaman create cuma penyaring **tampilan** — `store()`/route-model-binding tidak otomatis menolak POP di luar scope kalau id dikirim langsung. Karena itu semua controller detail/mutasi wajib panggil trait ini secara eksplisit, bukan cuma andalkan filter dropdown.

## 12. Kontrol Anti-Manipulasi

- **Ledger append-only** — `InventoryTransactionObserver` melempar `LogicException` di `updating()`/`deleting()`, tanpa kecuali (termasuk owner). Batasan yang diakui: tidak menangkap bulk query builder update/raw SQL.
- **`unit_price_snapshot`** disalin di titik transaksi terjadi, tidak diquery ulang — histori harga tidak berubah kalau harga master berubah belakangan.
- **Evidence foto wajib** untuk klaim kerugian (lost/damaged/scrapped), ditegakkan di Service.
- **Row locking (`lockForUpdate()`)** di semua titik rawan race: dispatch/receive transfer, issue serial, receive transfer, reassign serial — plus urutan lock item_id yang konsisten (`usort` sebelum lock) untuk mencegah deadlock antar transaction yang menyebut item sama dengan urutan input berbeda.
- **Tanpa approval gate berjenjang** — trade-off eksplisit, lihat §7.
