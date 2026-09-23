# Business Logic — Modul Gudang

## 1. Tiga Axis Klasifikasi Item (Independen)

Setiap `Item` diklasifikasi lewat 3 axis yang **tidak boleh digabung** — tiap axis menjawab pertanyaan berbeda:

| Axis | Enum | Nilai | Menjawab |
|---|---|---|---|
| Cara hitung stok | `TrackingType` | `serialized`, `quantity`, `roll` | Dilacak per-unit (SN) atau per-jumlah? |
| Tujuan akhir | `OwnershipMode` | `installable`, `company_asset` | Boleh transisi ke `SerialStatus::INSTALLED`? |
| Klasifikasi bisnis | `EquipmentClass` | `aktif`, `pasif` | Perangkat aktif atau material pasif/support (buat grouping form laporan)? |

Korelasinya tinggi (Aktif≈SERIALIZED, Pasif≈QUANTITY/ROLL) tapi sengaja dipisah — bisa saja tidak sinkron. Contoh yang butuh 3 axis independen: OTDR/laptop kerja adalah SERIALIZED + AKTIF (sama seperti ONT/router) tapi **tidak pernah** boleh terpasang ke pelanggan → `OwnershipMode::COMPANY_ASSET`.

### Resolusi EquipmentClass (2 level)

Default disetel di `ItemCategory.equipment_class`; bisa di-override per baris lewat `Item.equipment_class_override` (nullable). `Item::getEffectiveEquipmentClassAttribute()` menentukan nilai final. Kategori `lainnya` (catch-all) default `pasif`; item spesifik yang ternyata aktif pakai override, bukan bikin kategori baru.

### TrackingType — detail per nilai

- **SERIALIZED** — setiap unit punya SN unik. Dilacak di `inventory_serials`, satu baris per unit fisik. Dua sub-mode lewat `Item.auto_generate_serial`:
  - `false` (default) — barang punya SN vendor asli (modem, ONT, router, OLT module), staf ketik manual saat Receive.
  - `true` — barang **tidak** punya SN vendor (ODP, Splitter, dll). SN digenerate sistem (`InventoryReceiveService::generateSerialCode()`, pola `{item.code}-{YYYYMMDD}-{6digit}`, sama seperti `generateRollCode()`) lewat `receiveSerializedAuto()` — staf cukup isi jumlah unit. Barcode 1D-nya bisa dicetak (`warehouse.serials.print`/`warehouse.receive.serials.print`, reuse `RollLabelBarcodeRenderer`) buat ditempel ke unit fisik. Sub-mode ini **dikunci bareng** `tracking_type` — begitu barang punya pergerakan ledger, gak bisa diganti diam-diam. Lihat `docs/plan/warehouse/analisa-generate-id-barang-non-serial.md`.
- **QUANTITY** — dikelola berdasar jumlah polos (RJ45, cable tie, baut). Tidak ada identitas per-unit. Kalau harga beli berubah, otomatis pecah maksimal 2 lot (`lot_no` digenerate sistem, staf gak pernah isi manual) — lihat `docs/plan/warehouse/analisa-2-slot-harga-quantity.md`.
- **ROLL** — SERIALIZED + qty per-unit, khusus kabel (drum/roll fiber atau UTP). Identitas unik PER-ROLL digenerate sistem (`generateRollCode()`, bukan SN vendor) + `length_remaining` yang berkurang sebagian-sebagian (bukan atomik seperti SERIALIZED). Tabel `inventory_rolls`, status `RollStatus`. `batch` (lot bebas ala genealogy) **sudah dihapus** (ADHOC-75, 2026-09-16) — 0 item pernah pakai, kebutuhan realnya cuma 2-slot-harga QUANTITY (di atas) dan per-roll kabel (ROLL) ini.

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
- `lot_no` untuk `QUANTITY` ditentukan otomatis (`resolveQuantityLot()`, maksimal 2 lot aktif per harga) — staf gak pernah isi manual (ADHOC-75).
- Satu event bisa berisi banyak item sekaligus (`InventoryReceiveService::receiveBatch()`), dibungkus satu `reference_number` (`RCV-YYYYMMDD-NNNNNN`, reset counter per bulan).
- SN dobel (dalam satu submit atau sudah pernah terdaftar) ditolak dengan pesan spesifik per kasus — bukan error 500 mentah dari unique constraint DB.
- Barang SERIALIZED manual (`auto_generate_serial=false`): satu baris `inventory_serials` + satu baris ledger **per SN** (bukan digabung agregat), supaya Traceability bisa menunjukkan RECEIVE sebagai titik pertama riwayat SN.
- Barang SERIALIZED auto (`auto_generate_serial=true`, mis. ODP/Splitter): staf isi jumlah unit lewat `receiveSerializedAuto()`, bukan daftar SN — sistem generate SN + baris ledger per unit sendiri.
- Barang ROLL: staf isi jumlah roll (+ vendor), ID roll digenerate sistem (`receiveRoll()`/`generateRollCode()`) — bukan SN vendor.

## 4. Alur Transfer Pusat→Cabang (2 Fase)

Dua fase independen, `InventoryTransfer` (header mutable, `TransferStatus`: `in_transit`→`received`/`received_partial`) menaungi dua ledger yang berbeda:

1. **Dispatch** (`createTransfer()`, di Pusat) — stok Pusat berkurang **seketika** (barang sudah fisik keluar gudang). SN → `TRANSFERRED`, `current_pop_id` di-null-kan.
2. **Confirm** (`receiveTransfer()`, di Cabang) — stok Cabang bertambah **baru saat dikonfirmasi**, bukan saat dispatch. Alasan: mencegah stok Cabang naik palsu kalau ada barang hilang/mismatch di jalan.

**Partial receive diperbolehkan** — `$confirmedSerialNumbers` (subset dari yang dikirim) dan `$confirmedQuantities` (qty aktual per item/lot) dikirim terpisah dari yang didispatch. SN yang tidak dikonfirmasi tetap berstatus `TRANSFERRED` (limbo, butuh investigasi manual) — tidak otomatis dianggap hilang. Status header jadi `RECEIVED_PARTIAL` kalau ada baris yang tidak 100% cocok, `RECEIVED` kalau semua cocok.

Guard konkurensi: `receiveTransfer()` re-fetch + `lockForUpdate()` transfer **di dalam** transaction (bukan pakai parameter yang bisa stale) — mencegah double-klik/2 request bersamaan dua-duanya lolos cek `isInTransit()` lalu dua-duanya mengkredit stok Cabang.

## 5. Alur Issue (Cabang → Teknisi)

Satu aksi langsung tuntas (beda dari Transfer yang 2 fase): stok Cabang berkurang **dan** custody teknisi terbentuk sekaligus.

- QUANTITY: **setiap** issue membuat baris `technician_custody` **baru** — tidak digabung ke baris existing teknisi yang sama untuk item yang sama, supaya FIFO consumption bisa jalan per-lot per-waktu-ambil.
- SERIALIZED: SN langsung `status=ISSUED`, `current_technician_id` + `issued_from_pop_id` diisi.
- Harga custody (`unit_price_snapshot`) diambil dari `resolveLastCost()` — baca ulang RECEIVE terakhir untuk item/lot itu (filter `type=RECEIVE` secara eksplisit, supaya tidak salah baca dari baris RETURN/TRANSFER_CUSTODY yang bisa membawa harga lama).

## 6. Integrasi ke Task Teknisi

Saat teknisi submit laporan (Pemasangan/Maintenance/INFR/O-REQ), `InventoryService` menjembatani custody teknisi ke pemakaian aktual:

- **`consumeFromCustody()`** — khusus QUANTITY. FIFO diurutkan `issued_at` ASC (bukan `lot_no`) **lintas semua anggota tim**, bukan per-orang — barang bisa diambil siapa saja di tim. Kalau custody tidak cukup, melempar `InsufficientCustodyException` (structural constraint — teknisi cuma bisa klaim sejumlah yang benar-benar ada di custody-nya). Potongan bisa jatuh di beberapa baris custody sekaligus → `task_materials` jadi beberapa baris, masing-masing membawa `unit_price_snapshot` sendiri.
- **`installSerial()`** — transisi SN ke `INSTALLED`. Guard `OwnershipMode::INSTALLABLE` ditegakkan **di sini** (satu-satunya pintu transisi), bukan hanya predikat yang bisa diabaikan pemanggilnya. Re-fetch + `lockForUpdate()` mencegah race dua submit bersamaan untuk SN yang sama.
- **`reconcileMaterialsAgainstCustody()`** — rekonsiliasi baris `task_materials` generik (dari form, tanpa lot/harga) ke custody sungguhan; dipanggil sekali di titik penyelesaian laporan (bukan tiap resubmit draft).

## 7. Adjustment & Stock Opname

4 varian, semua lewat `InventoryAdjustmentService`, **tanpa gerbang approval berjenjang** (keputusan produk sadar — threshold nominal belum bisa ditentukan tanpa data operasional riil; monitoring saat ini berbasis status barang yang tercatat di ledger, visible di dashboard):

| Method | Target | Delta | Catatan |
|---|---|---|---|
| `adjustPopBalance()` | `InventoryBalance` | Signed, **menolak delta=0** | Koreksi manual — tolak juga kalau bikin stok negatif |
| `recordStockOpname()` | `InventoryBalance` | Qty absolut hasil hitung fisik (delta 0 **diperbolehkan**) | Satu-satunya jalur yang boleh menulis ledger qty=0 — supaya "belum pernah opname" vs "opname hasilnya pas" tetap beda status di ledger |
| `adjustCustody()` | `TechnicianCustody` | Signed | Kategori reason terarah (dropdown, bukan enum baru) |
| `adjustSerialStatus()` | `InventorySerial` | — | Hanya ke `LOST`/`DAMAGED`/`SCRAPPED`/`QUARANTINE`, snapshot ke `resulting_status` (ADHOC-80) |
| `adjustRollStatus()` | `InventoryRoll` | — | Sama 4 tujuan, snapshot `resulting_status` juga |

**Reason categories** (`InventoryAdjustmentService::REASON_CATEGORIES`): `lost`, `damaged`, `quarantine`, `shrinkage_on_return`, `stock_opname_diff`. Evidence foto **wajib** hanya untuk `lost`/`damaged` (dan status target `LOST`/`DAMAGED`/`SCRAPPED` di `adjustSerialStatus()`) — `quarantine` cukup tanpa bukti (status tahan sementara, bukan klaim rugi). Guard evidence ditegakkan di Service, bukan cuma validasi UI.

`SCRAPPED` bersifat **final** — tidak ada transisi keluar dari status itu; SN yang sudah SCRAPPED tidak bisa diadjust lagi.

Saat SN sedang `INSTALLED` diadjust ke LOST/DAMAGED/SCRAPPED/QUARANTINE, `customer_id`/`fop_task_id`/`installed_at` dikosongkan (karena sudah tidak beneran terpasang) — tapi `current_pop_id`/`current_technician_id` **sengaja dibiarkan** sebagai jejak forensik "terakhir ada di mana".

## 8. Reassign Custody

Untuk skenario teknisi resign/cuti/rotasi — `InventoryReassignService`, tanpa approval gate, tapi tercatat penuh di ledger:

- **Return** — seluruh sisa custody kembali ke gudang cabang (`RETURN`, custody → `RETURNED`).
- **Transfer langsung** — custody pindah ke teknisi lain (`TRANSFER_CUSTODY`), tidak menyentuh `inventory_balances` gudang sama sekali.
- Sengaja hanya bisa reassign **seluruh** sisa custody, bukan qty parsial pilihan.
- `created_by`/`from_*` di ledger selalu `$actor` (admin eksekutor), **bukan** teknisi lama — teknisi lama mungkin sudah resign dan tidak bisa dimintai konfirmasi.
- **ADHOC-86:** task DEAC tidak lagi memakai method di bawah — sekarang lewat transit `RETURNED` + konfirmasi gudang, lihat §12a. Deskripsi berikut menggambarkan method-nya sendiri:
- `returnInstalledSerialFromCustomer()` — jalur retrieve SN dari status `INSTALLED` (mis. tombol "Ambil Alat" saat deaktivasi) balik ke `issued_from_pop_id` SN itu sendiri (gudang cabang asal ISSUE terakhir), bukan dari POP task retrieval yang bisa jadi mini_pop. Sekalian set `condition=used_good` + `condition_checked_at=null` (belum dicek fisik) — lihat §12.

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

## 12. Kondisi Fisik Barang SERIALIZED (ADHOC-80)

Axis **terpisah** dari `SerialStatus` (lifecycle/posisi) — `SerialStatus` menjawab "SN ini lagi di mana/tahap apa" (di gudang, custody, terpasang, dst), `ItemCondition` menjawab "SN ini fisiknya baru atau bekas pakai". Enum `App\Enums\ItemCondition`: `new` / `used_good` / `used_damaged`.

**Kenapa dibutuhkan** — `returnInstalledSerialFromCustomer()` (retrieval DEAC/Ambil Alat) sebelumnya nyetel SN balik ke `status=AVAILABLE`, SAMA PERSIS status SN baru dari `receiveSerialized()`. Modem bekas-copotan (mungkin sudah soak, belum tentu "rusak" secara formal) jadi tidak bisa dibedakan dari stok baru begitu masuk pool `available` — bisa langsung di-Issue lagi tanpa jejak/gate inspeksi.

Alur:

1. `receiveSerialized()`/`receiveSerializedAuto()` set `condition=new` — barang baru dari distributor, tidak butuh cek ulang.
2. `returnInstalledSerialFromCustomer()` set `condition=used_good` + `condition_checked_at=null` — **asumsi awal**, belum dicek fisik.
3. **Gate Issue** — `InventorySerial::isClearedForIssue()`: SN `new` selalu lolos; SN bekas cuma lolos kalau `condition_checked_at` sudah terisi. Ditegakkan di `InventoryIssueService::issueSerialized()` (Service, bukan cuma UI) — SN yang belum lolos juga di-exclude dari dropdown "Stok Tersedia" (`WarehouseIssueController::availableStock()`).
4. **Aksi "Sudah Dicek"** (`InventoryReassignService::markSerialConditionChecked()`) — satu-satunya jalur yang boleh mengisi `condition_checked_at`/`condition_checked_by`, jadi satu-satunya jalur yang melepas gate di atas. Tidak menulis ledger (murni metadata inspeksi). Menolak kalau hasil akhirnya `new` (tidak masuk akal) atau SN-nya masih `condition=new` (belum pernah dipakai, tidak ada yang perlu dicek). Endpoint: `POST warehouse.traceability.serial.condition-check`, permission reuse `warehouse_reassign.create`, inline toggle di halaman Lacak Barang (bukan halaman/modal baru — sudah di halaman Detail SN spesifik, pola-3 CLAUDE.md).

**Badge kondisi** muncul di 4 halaman: Lacak Barang (detail penuh + tombol "Sudah Dicek"), Kelola Stok (modal quick-look SN), Custody (list SN dipegang teknisi), Scan Barang (hasil lookup). 4 state visual: 🟢 Baru, 🟡 Bekas — Belum Dicek, 🔵 Bekas — Sudah Dicek, 🔴 Bekas — Rusak.

**Riwayat Mutasi** dapat 2 filter baru (`WarehouseHistoryController`): **Kondisi** (baca `serial.condition`/`condition_checked_at` langsung, 4 opsi niru badge di atas) dan **Alasan** (baca `resulting_status`, aktif cuma pas filter Tipe=`adjustment` dipilih — nilai `lost`/`damaged`/`scrapped`/`quarantine` dari `adjustSerialStatus()`/`adjustRollStatus()`, plus `shrinkage_on_return` dari `reason` `adjustCustody()`). "Opname" sengaja tidak masuk filter Alasan — itu type ledger terpisah (`STOCK_OPNAME`) yang sudah punya filter Tipe sendiri.

**Scope sengaja cuma SERIALIZED** — Roll kabel (`InventoryRoll`/`RollStatus`) dan `customer_devices.device_retrieved_at` (jalur legacy paralel) eksplisit di-skip, keputusan user 2026-09-16. Rancangan lengkap: `docs/plan/warehouse/analisa-gap-kondisi-barang.md`.

## 12a. Ambil Modem (DEAC) → Terima Retur (ADHOC-86)

Menggantikan perilaku lama "task DEAC selesai → semua SN `INSTALLED` milik pelanggan otomatis `AVAILABLE` di cabang". Rancangan lengkap + daftar gap: `docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md`.

Alur:

1. **Form laporan khusus** (`TaskDeviceRetrievalController`, `/tasks/{task}/device-retrieval-report`) — form Maintenance generik tidak lagi dipakai DEAC (URL lamanya dialihkan). Teknisi memilih hasil: **diambil** / **tidak ditemukan** / **pelanggan menolak**.
   - *Diambil*: SN fisik (boleh lebih dari satu) + foto kondisi + kelengkapan. Pilihan model barang hanya dipakai untuk SN yang belum pernah tercatat.
   - *Tidak diambil*: alasan wajib. Task tetap selesai, **`device_retrieved_at` tetap kosong** → badge "Belum Diambil" dan tombol "Ambil Alat" muncul lagi di List Putus Langganan untuk dicoba ulang.
2. **`InventoryReassignService::pickupSerialFromCustomer()`** — per SN yang dilaporkan:
   - `INSTALLED` milik pelanggan ini → `RETURNED`.
   - Belum ada di `inventory_serials` (**modem legacy** — data lama hanya punya SN, tanpa nama barang) → didaftarkan otomatis dengan model pilihan teknisi (atau item placeholder `MODEM-PELANGGAN-LAMA`), kondisi `used_good` belum dicek.
   - Ada tapi bukan milik pelanggan ini / bukan `INSTALLED` → **ditolak**, tidak ditimpa (konflik data, admin yang menyelesaikan). Satu SN gagal membatalkan seluruh laporan (satu transaksi).
   - Idempoten untuk SN yang sudah `RETURNED` dari pelanggan yang sama (kirim ulang laporan setelah FOP me-reject).
3. **Status `RETURNED` = transit**: `current_technician_id` = teknisi, belum `current_pop_id`, `customer_id`/`fop_task_id` sengaja dipertahankan sampai diterima. Ledger `RETURN` pertama (pelanggan → teknisi) **tanpa `to_pop_id`**, jadi belum dihitung stok oleh `WarehouseStockAsOfService`. SN `RETURNED` tidak bisa di-Issue (status ≠ `AVAILABLE`).
4. **Terima Retur** (`WarehouseReturnReceiveController`, `/warehouse/returns`) — halaman create tersendiri, permission **reuse `warehouse_reassign.create`**, scope POP lewat `issued_from_pop_id`. Staf memeriksa fisik, memilih kondisi (`used_good`/`used_damaged`, bukan `new`), boleh mengoreksi model barang. `confirmReturnedSerial()`: `RETURNED → AVAILABLE` di gudang cabang + ledger `RETURN` kedua (teknisi → gudang, `to_pop_id`). Sekaligus mengisi `condition_checked_*`, jadi **tidak perlu "Sudah Dicek" terpisah** untuk SN jalur ini.
5. **Gudang tujuan** = `issued_from_pop_id` SN (gudang asal ISSUE terakhir); untuk SN legacy ditelusuri naik dari POP task lewat `parent_id` sampai ketemu `pusat`/`cabang`. Kalau tidak ketemu, laporan ditolak dengan pesan jelas — tidak ada modem tanpa tujuan.

Guard di `TaskService::complete()`: task DEAC **tidak bisa selesai tanpa laporan** (menutup celah `POST /tasks/{task}/complete`). Reject FOP pada DEAC mengosongkan `device_retrieved_at`, tapi **tidak membalik SN yang sudah `RETURNED`** (modem memang ada di tangan teknisi; ledger append-only) — kirim ulang laporan aman karena idempoten.

Penanda asal DEAC di ledger: baris `RETURN` dengan `fop_task_id` terisi.

`returnInstalledSerialFromCustomer()` tetap ada (INSTALLED → AVAILABLE langsung) tetapi tidak lagi dipanggil `TaskService`.

**Lanjutan ADHOC-88 (2026-09-21):**
- **Jejak per SN** di tabel `device_retrieval_logs` (siapa teknisi/petugas pengambil, dari pelanggan mana, diterima siapa dan kapan, kondisi, nilai, foto). Dibaca oleh halaman **Riwayat Pengambilan Alat** (`/warehouse/retrievals`, `warehouse.view`, scope POP gudang tujuan) dan kartu di tab Perangkat Detail Pelanggan. Log **tidak ikut hilang** walau `customer_id` SN dikosongkan saat diterima atau `device_retrieved_at` direset.
- **Terima modem dari pelanggan** (`/warehouse/returns/from-customer`, `warehouse_reassign.create`): pelanggan putus mengantar modem sendiri tanpa task DEAC. Satu langkah tanpa transit → `AVAILABLE` langsung, kondisi dinilai saat itu. Bukan lewat Barang Masuk (pengadaan: kondisi dipaksa baru, harga wajib, hanya Pusat, tidak tertaut pelanggan). Ditolak kalau ada task Ambil Alat berjalan.
- **Nilai taksiran opsional** (Terima Retur & walk-in): disimpan di `unit_price_snapshot` baris ledger penerimaan; kosong = Rp 0 di Laporan Bulanan.
- **Petunjuk merek data lama** (`LegacyDeviceHintService`): hanya tebakan awal, dipetakan ke master barang kalau label ≥5 karakter dan tepat satu barang cocok.
- **Langganan Lagi** mengosongkan `device_retrieved_at`; riwayat tetap utuh di log.
- **Tab "Return dari Pelanggan"** di Barang di Tangan Teknisi (`/warehouse/custody`): modem `RETURNED` (transit) yang masih dipegang teknisi, per teknisi, dengan tombol Terima → Terima Retur. Hilang dari tab begitu diterima gudang. Berlawanan arah dengan tab Perangkat Serial Number (`ISSUED`, barang yang dibawa ke lapangan).
- **Riwayat Task FOP** (`/fop-tasks/history/{id}`) untuk kategori Ambil Modem menampilkan laporan yang sama, termasuk foto kondisi alat yang terlampir (sebelumnya jatuh ke "tidak punya laporan lapangan terstruktur").
- **Detail Laporan task DEAC** di halaman Task memakai format laporan pengambilan alat (hasil, modem per SN dengan status transit/diterima, kelengkapan, foto kondisi, catatan/alasan), bukan format Maintenance.

Rancangan & data lama: `docs/plan/warehouse/analisa-riwayat-dan-terima-modem-dari-pelanggan.md`.

**Belum dikerjakan:** BAP, jalur balik barang non-SN (roll/material), backfill massal SN legacy (sengaja tidak — 273 SN kosong, 93 SN dobel), export Excel riwayat, laporan agregat per teknisi.

## 13. Kontrol Anti-Manipulasi

- **Ledger append-only** — `InventoryTransactionObserver` melempar `LogicException` di `updating()`/`deleting()`, tanpa kecuali (termasuk owner). Batasan yang diakui: tidak menangkap bulk query builder update/raw SQL.
- **`unit_price_snapshot`** disalin di titik transaksi terjadi, tidak diquery ulang — histori harga tidak berubah kalau harga master berubah belakangan.
- **Evidence foto wajib** untuk klaim kerugian (lost/damaged/scrapped), ditegakkan di Service.
- **Row locking (`lockForUpdate()`)** di semua titik rawan race: dispatch/receive transfer, issue serial, receive transfer, reassign serial — plus urutan lock item_id yang konsisten (`usort` sebelum lock) untuk mencegah deadlock antar transaction yang menyebut item sama dengan urutan input berbeda.
- **Tanpa approval gate berjenjang** — trade-off eksplisit, lihat §7.
