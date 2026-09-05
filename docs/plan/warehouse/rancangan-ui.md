# Rancangan UI Modul Gudang/Inventory — Fase 1

Lanjutan dari [`warehouse_inventory_asset_traceability_analysis.md`](warehouse_inventory_asset_traceability_analysis.md) (§29) dan [`warehouse_inventory_asset_traceability_analysis_advanced`](warehouse_inventory_asset_traceability_analysis_advanced) (§16). Dokumen ini fokus ke **layar** dan **siapa boleh apa** — belum ERD/migration.

`image.png`/`image-2.png` di folder ini itu flowchart proses (struktur gudang & lifecycle SN), bukan wireframe layar — jangan dianggap rancangan UI.

---

## 1. RBAC & Permission

Ikut pola `ItemFeatureSeeder`/`WorkToolFeatureSeeder` yang sudah ada: satu `Feature` root per sub-modul, permission `{feature_code}.{action_code}` digenerate `PermissionGeneratorService` dari `config/rbac.php`, **bukan hardcode**. **Tidak ada role baru per cabang** — otorisasi per-cabang seluruhnya lewat POP scope (`EffectiveAccessService`), bukan role.

### 1.1 Role yang terlibat (tidak ada role baru)

| Role | Peran di modul gudang |
|---|---|
| `owner`/`atasan` | Wildcard `*` — akses penuh semua gudang, semua aksi (existing). |
| `admin` | Akses penuh operasional gudang, biasanya scope `all_pop`. |
| **`pop_admin`** | Akses gudang **cabangnya sendiri** (scope `selected_pop`/`pop_tree`) — role ini SUDAH ADA, dipakai apa adanya, tinggal digrant permission gudang. Ini yang jadi "admin gudang cabang" — bukan role baru. |
| **`fop`** | Approve/monitor lintas cabang dalam wilayahnya (sama pola FOP Task) — opsional lihat 1.3. |
| **`teknisi`** | Lihat custody sendiri, dipakai saat validasi SN di Laporan Pemasangan. |

**Tidak perlu role `gudang` baru** — `pop_admin` sudah representasi "penanggung jawab operasional di satu POP", termasuk cabang. Bikin role terpisah cuma duplikasi tanggung jawab yang sudah ada.

### 1.2 Feature & Permission baru

`config/rbac.php` → `allowed_actions`, ditambah (pola sama entri existing):

```php
'warehouse' => [
    ActionCode::VIEW->value, // dashboard stok + ledger
],

'warehouse_transfer' => [
    ActionCode::VIEW->value,
    ActionCode::CREATE->value,   // Pusat kirim
    ActionCode::RECEIVE->value,  // Cabang konfirmasi terima — action code BARU
],

'warehouse_issue' => [
    ActionCode::VIEW->value,
    ActionCode::CREATE->value,   // Cabang → Teknisi
],

'warehouse_custody' => [
    ActionCode::VIEW->value, // admin/gudang: lihat custody SEMUA teknisi
],

'warehouse_traceability' => [
    ActionCode::VIEW->value, // cari SN → riwayat lengkap
],
```

Satu `ActionCode` baru perlu ditambah ke `app/Enums/ActionCode.php`:
- `RECEIVE = 'receive'` — dokumentasikan alasan terpisah dari `APPROVE` (pola sama `PAY`/`DEPOSIT`/`VISIT` yang sudah dijelaskan alasannya di enum): konfirmasi fisik "barang sudah nyampe", bukan keputusan approve/reject.

**`VIEW_OWN` DIBATALKAN (keputusan user, dicek ulang ke kode).** `task.view.own` yang jadi acuan awal ternyata BUKAN pola reusable — itu terdaftar sebagai *exception* eksplisit di `config/rbac.php` (`view_permission_overrides`, baris ~468-471), didokumentasikan sendiri di situ sebagai "gak ikut konvensi [feature].[action] biasa". Nambah `ActionCode::VIEW_OWN` buat modul gudang berarti niru exception, bukan niru pola umum — salah acuan. **Custody "punya sendiri" (§2.5) gak butuh permission terpisah sama sekali** — itu widget yang nempel di halaman Task teknisi yang udah bisa diakses teknisi, query-nya otomatis discope `custodian = auth()->id()`. `warehouse_custody.view` (tanpa akhiran apa pun) tetap cuma buat layar admin "lihat custody SEMUA teknisi" (§2.6).

### 1.3 Matrix hak akses per layar

| Layar | `owner`/`atasan` | `admin` | `pop_admin` | `fop` | `teknisi` |
|---|---|---|---|---|---|
| Dashboard stok (semua gudang) | ✅ | ✅ | ✅ *(scope cabangnya saja)* | — | — |
| Transfer: buat (Pusat→Cabang) | ✅ | ✅ | — | — | — |
| Transfer: terima (di Cabang) | ✅ | ✅ | ✅ *(cabangnya saja)* | — | — |
| Issue ke Teknisi | ✅ | ✅ | ✅ *(cabangnya saja)* | — | — |
| Custody — lihat semua | ✅ | ✅ | ✅ *(teknisi cabangnya saja)* | — | — |
| Custody — lihat punya sendiri | *(selalu bisa — embedded di halaman sendiri, tanpa permission terpisah)* | | | | |
| Validasi SN di Laporan Pemasangan | *(otomatis, bagian form existing)* | | | | ✅ *(pemilik custody)* |
| Ledger/riwayat transaksi | ✅ | ✅ | ✅ *(cabangnya saja)* | — | — |
| Asset Traceability (cari SN) | ✅ | ✅ | ✅ *(cabangnya saja)* | ✅ *(troubleshoot lapangan)* | — |
| Analisa material per `fop_task_id` (Aktif+Pasif gabung) | ✅ | ✅ | ✅ *(cabangnya saja)* | ✅ *(wajib — bukan permission baru, extend halaman Verifikasi Admin ADHOC-28 yang udah ada)* | — |

**Keputusan FOP (dikonfirmasi user):** akses read-only terbatas, BUKAN akses data gudang mentah. `fop` **TIDAK** dapat: Dashboard stok, Transfer, Issue, Ledger/riwayat transaksi (terlalu granular, bukan kebutuhannya). `fop` **DAPAT**: Custody — lihat semua (dibatasi teknisi dalam wilayahnya, sama scope FOP Task existing), Custody — punya sendiri (kalau FOP juga pegang barang), Asset Traceability, dan **wajib** Analisa material per `fop_task_id` (ini yang jadi alasan utama FOP butuh modul ini — §3.4).

Semua baris "cabangnya saja" ditegakkan lewat `EffectiveAccessService::getAllowedPopIds()` di query, **bukan** permission terpisah per cabang — sama persis pola Worksheet NOC/Helpdesk existing.

---

## 2. Layar & Komponen UI

Setiap layar reuse pola/komponen yang sudah ada di repo — tidak bikin pola baru.

### 2.1 Dashboard Gudang (`warehouse.view`)

- Stat cards di atas (pola `payments/index.blade.php`, ADHOC-14): Total item, Serialized, Available, Custody Teknisi, Low Stock count.
- Tabel "Stok Rendah" per gudang — muncul kalau `qty < minimum_stock` (`inventory_balances`).
- `pop_admin` login → otomatis cuma lihat baris cabangnya (query discope, bukan filter dropdown yang bisa diakalin).
- Tanpa realtime — cukup reload manual (beda dari Setoran Kas yang butuh live-update duit fisik; stok gudang gak sekritis itu, hindari nambah broadcast channel yang belum perlu).

### 2.2 Transfer — Buat (`warehouse_transfer.create`)

- Form: pilih gudang tujuan (Cabang aktif — dropdown dari `pops.type=cabang`), pilih item + qty, kalau `tracking_type=SERIALIZED` field tambahan input/scan SN per unit (textarea multi-baris atau repeatable input — pola sama input SN cetak QR ADHOC-46, bukan bikin komponen scan baru).
- Submit → **PRG**: redirect ke halaman Detail Transfer (`warehouse.transfers.show`), bukan render balik form (`docs/PRG_REDIRECT_CONVENTION.md`).
- Nomor `TRF-{tahun}-{4 digit}` — generate pola sama `TicketService::generateFopTaskNumber()`, daftarkan ke `docs/ID_NUMBERING_RULES.md`.

### 2.3 Transfer — Terima (`warehouse_transfer.receive`)

- Halaman Detail Transfer (yang sama dari 2.2) menampilkan expected items/SN + tombol "Terima" — cuma nongol buat user yang scope-nya cocok dgn gudang tujuan.
- Klik Terima → cek per-SN: cocok → `RECEIVED`, gak cocok → badge merah "SN Mismatch", tetap bisa diproses partial (barang lain masuk, yang mismatch di-log terpisah, TIDAK block seluruh transfer — pola sama toleransi partial-match modul lain, mis. pencocokan kwitansi ADHOC-24).
- Submit → PRG redirect balik ke halaman Detail Transfer yang sama (sekarang status `RECEIVED`/`RECEIVED_PARTIAL`).

### 2.4 Issue ke Teknisi (`warehouse_issue.create`)

- Dibuka dari Gudang Cabang (`pop_admin`/`admin`) — pilih teknisi (dropdown user berrole teknisi/fop DI CABANG yang sama, discope), pilih item+qty/SN dari stok cabang yang tersedia.
- Submit → decrement `inventory_balances` cabang, tambah custody teknisi, catat `inventory_transactions` (ISSUE). Redirect ke halaman Detail Issue (`ISS-{tahun}-{4 digit}`) — bisa dicetak jadi bon (pola cetak sama `ReceiptPresenter`/kwitansi, bukan komponen print baru).

### 2.5 Custody — "Stok Saya" (`warehouse_custody.view_own`)

- **Bukan halaman terpisah** — tempel sebagai tab/card baru di halaman Task teknisi existing (`tasks/own.blade.php`, sama tempat "Riwayat Task Saya" ADHOC-15 nangkring), biar teknisi gak perlu buka menu baru buat cek barang yang dia bawa.
- Isi: daftar item + qty/SN yang sedang custody, badge status (`ISSUED`/`RESERVED`).

### 2.6 Custody — Lihat Semua (`warehouse_custody.view`)

- Tabel padat filter per-teknisi/per-cabang, pola sama tabel Worksheet (ADHOC-08/09) — bukan kartu bertumpuk yang berat discroll.

### 2.7 Validasi SN di Laporan Pemasangan

- **Bukan layar baru** — extend form existing `installations.report` (`storePemasangan()`). Field SN modem yang sudah ada (`router_or_ont_serial`) sekarang dropdown/autocomplete dibatasi ke SN yang berstatus `ISSUED` & custody = teknisi yang login; submit di luar itu ditolak validasi server-side.
- Setelah submit sukses → `inventory_serials` status jadi `INSTALLED` + `customer_id` diisi (nunjuk `customer_technical_details`, bukan nyalin field device — lihat §29.3 doc analisa pertama).

### 2.8 Asset Traceability (`warehouse_traceability.view`)

- Search box: masukkan SN → halaman detail timeline (RECEIVE→TRANSFER→ISSUE→INSTALL, dst), pola sama halaman riwayat token QR (`CustomerQrController::show()`).

### 2.9 Ledger/Riwayat Transaksi (`warehouse.view`, bagian dari dashboard)

- Tabel filter tanggal + tipe transaksi + gudang, pola sama `TaskAuditTimeline`/riwayat ticket — bukan komponen tabel baru.

---

## 3. Klasifikasi Aktif/Pasif & Split Form Laporan

### 3.1 `equipment_class` — kolom baru, ditaruh di `item_categories`

Klasifikasi bisnis "Perangkat Aktif" (Router, Switch, OLT, ONU/ONT, Access Point, SFP Transceiver — wajib SN, sering ada MAC Address) vs "Perangkat Pasif & Material Support" (RJ45, patch cord, kabel rol, cable tie, conduit — SKU/lot/qty) itu **axis beda** dari `tracking_type` (§16.1 doc advanced) — walau korelasinya tinggi (Aktif≈SERIALIZED, Pasif≈QUANTITY/BATCH), keduanya jangan digabung jadi satu kolom.

**Keputusan (direvisi — dua level, bukan satu):** default `equipment_class` (enum `EquipmentClass`: `AKTIF`/`PASIF` — **cast Enum**, bukan raw string, sesuai aturan keras CLAUDE.md "jangan bikin string baru") ditaruh di **`item_categories`**. Tapi kategori `lainnya` itu catch-all — kalau di-default `pasif` lalu ada satu item di dalamnya yang ternyata perangkat aktif (modul jaringan tak terkategorikan), petugas gudang gak punya cara override tanpa pecah kategori baru. Solusinya **dua level**:

```php
// items
'equipment_class_override' => nullable, cast EquipmentClass::class

// accessor
public function getEffectiveEquipmentClassAttribute(): EquipmentClass
{
    return $this->equipment_class_override
        ?? $this->category->equipment_class
        ?? EquipmentClass::PASIF; // fallback absolut
}
```

Kategori normal gak perlu isi override sama sekali (ikut default kategorinya). Cuma item pengecualian di `lainnya` (atau kategori mixed apa pun ke depannya) yang diisi override — bukan bikin kategori baru tiap ada 1 barang nyimpang. Pola ini konsisten sama resolusi permission yang udah ada di `EffectiveAccessService` (exact match → wildcard → prefix fallback) — layered default+override bukan pola baru buat repo ini.

Kategori yang sudah ada tinggal dipetakan (default level kategori):

| `item_categories.code` (existing) | `equipment_class` |
|---|---|
| `media_converter` | aktif |
| `antena_radio` | aktif |
| `splitter_odp` | pasif |
| `kabel_dropcore` | pasif |
| `patch_cord` | pasif |
| `aksesoris_pasang` | pasif |
| `lainnya` | `pasif` (default kategori — item spesifik di dalamnya bisa `equipment_class_override` kalau ternyata aktif) |

Satu tempat diisi (pas kelola kategori), semua item di bawahnya otomatis ikut — gak perlu isi ulang tiap item, gak bisa saling gak sinkron.

`mac_address` (nullable) ditambah di `inventory_serials` — nempel di baris SN yang sama, opsional (gak semua Aktif equipment expose MAC dari kemasan, mis. sebagian SFP Transceiver).

Tiga axis independen yang kepake bareng (jangan dipaksa jadi satu kolom):

| Axis | Nilai | Fungsi |
|---|---|---|
| `tracking_type` (`items`) | SERIALIZED/QUANTITY/BATCH | cara hitung stok |
| `equipment_class` (`item_categories`) | aktif/pasif | grouping tampilan laporan |
| `ownership_mode` (`items`) | installable/company_asset | boleh/gak boleh transisi ke `INSTALLED` |

### 3.2 Split form: satu komponen existing dipertahankan, satu komponen baru

Komponen `<x-material-rows>` (`resources/views/components/material-rows.blade.php`) **sudah ada** dan dipakai DUA fase — Laporan Survey (`kind=estimasi`) dan Laporan Pemasangan (`kind=terpakai`) — baris qty-based, tanpa SN. Rencana split:

- **Form Perangkat Pasif** — TETAP `<x-material-rows>` apa adanya, tinggal filter `categoryOptions` ke `equipment_class=pasif` saja (satu baris filter di query pemanggil, komponennya sendiri tidak berubah).
- **Form Perangkat Aktif** — komponen BARU (`<x-serial-rows>` atau sejenis), beda total dari material-rows: bukan input qty/text bebas, tapi **dropdown SN yang dibawa teknisi**. Dropdown-nya **satu daftar gabungan**, isinya SEMUA SN yang lagi custody teknisi login — lintas item/kategori aktif (modem, router, OLT module, SFP, dst campur jadi satu list, gak dipisah per jenis barang), teknisi tinggal cari & pilih yang mana yang dia pakai di pemasangan ini. Sumbernya `inventory_serials` where `custodian=teknisi login` & `status=ISSUED`, item-nya kategori `equipment_class=aktif` (sama sumber data dgn §2.7 Validasi SN).

Kedua form dipasang bersisian di panel yang sama (§2.7, extend `installations.report`/`maintenance-report`/laporan INFR/laporan O-REQ — 4 jenis laporan yang disebut user), bukan pindah ke halaman terpisah.

### 3.3 Pengecualian: fase Survey (estimasi) TIDAK ikut split ini

Komentar di `material-rows.blade.php` bilang komponen ini dipakai juga di Laporan Survey (`kind=estimasi`) — **saat itu teknisi belum ambil barang dari gudang, custody-nya masih kosong.** Dropdown SN-dari-custody di §3.2 gak bisa dipaksakan ke fase ini karena secara logis belum ada SN yang bisa dipilih.

**Keputusan (dikonfirmasi user):** split Aktif/Pasif berbasis custody SN cuma berlaku di 4 laporan **realisasi** (Pemasangan, Maintenance, INFR, O-REQ — `kind=terpakai`). Fase Survey (`kind=estimasi`) tetap pakai `<x-material-rows>` + `items` master **yang sama** (nama barang tetap seragam sejak Survey — itu justru alasan `items` dibikin di ADHOC-11), tapi **lepas dari seluruh syarat Inventory**: gak ada validasi SN, gak ada cek custody, gak ada cek stok Gudang. "Universal" di sini artinya "bebas aturan Inventory", **bukan** katalog barang baru yang terpisah dari `items` — kalau dipisah total, balik lagi ke masalah lama ADHOC-11 (nama beda-beda antara estimasi & realisasi, verifikasi admin harus bandingin manual).

### 3.4 Ke mana data Aktif tersimpan — bukan `task_materials`, dan FOP wajib baca dari Inventory juga

Data Pasif tetap masuk `task_materials` seperti sekarang (qty-based, `kind=terpakai`). Data Aktif **tidak** ikut disalin ke `task_materials` sebagai baris qty=1 — itu duplikasi, karena unit-nya sudah dilacak penuh di `inventory_serials` (status berubah ke `INSTALLED`, `fop_task_id` & `customer_id` terisi — lihat §2.7). Bagian "Perangkat Aktif" di laporan cukup **query gabungan** dari `inventory_serials` yang terikat ke `fop_task_id` laporan itu — bukan tabel/kolom baru, dan bukan disalin ke `task_materials`.

**Konsekuensi penting (dikonfirmasi user):** alur lengkapnya Gudang Pusat → Anak Gudang → Teknisi → Laporan → **FOP menganalisa barang apa & berapa jumlah yang dipakai teknisi**. Titik terakhir ini artinya layar analisa/verifikasi FOP (`verifications/admin.blade.php` dan/atau FOP Dashboard existing) **wajib ditarik dari DUA sumber sekaligus**, bukan cuma `task_materials` kayak sekarang:

```
Analisa FOP per fop_task_id
   ├── Perangkat Aktif  ← query inventory_serials (fop_task_id, status=INSTALLED)
   └── Material Pasif   ← query task_materials (fop_task_id, kind=terpakai)
```

Halaman verifikasi/analisa FOP yang sekarang cuma baca `task_materials` (§ADHOC-28 — "halaman Verifikasi Admin menampilkan data yang benar-benar diinput") harus di-extend join ke `inventory_serials` juga, supaya FOP bisa liat gambaran utuh — bukan cuma separuh (material habis pakai) sementara perangkat aktif yang sebenarnya paling mahal malah gak kelihatan di layar itu. Ini PR/tugas terpisah nanti pas coding, dicatat di sini biar gak kelewat pas eksekusi.

### 3.5 Kapan `unit_price_snapshot` diisi — saat ISSUE, bukan saat submit laporan

**Keputusan user:** harga di-snapshot saat `ISSUE` (gudang cabang → custody teknisi), **bukan** saat submit laporan (harga master mungkin udah berubah di antara ISSUE dan laporan disubmit — bisa berhari-hari) dan **bukan** saat form laporan dibuka (bisa berubah lagi di tengah pengisian kalau admin ganti harga master pas teknisi masih isi form). Ini penerapan konkret dari syarat Batch C (§29.4 poin 3 doc pertama) yang sebelumnya cuma bilang "wajib diisi" tanpa nentuin titik waktunya.

**Detail implementasi (biar gak ambigu pas coding):** harga di-capture di baris `inventory_transactions` (ISSUE) pas itu terjadi (last-cost, sesuai §16.4/§29.8). Begitu teknisi submit Laporan Pemasangan/Maintenance/INFR/O-REQ dan `task_materials` (kind=terpakai) dibuat, `unit_price_snapshot`-nya **DISALIN** dari baris ISSUE terkait — **bukan** query ulang ke harga master `Item` saat itu. Query ulang ke master sama aja balik ke "harga saat submit" yang justru mau dihindari.

### 3.6 Reassign Custody — teknisi resign/cuti sebelum barang balik

**Keputusan user:**

```
Admin/pop_admin buka halaman Custody teknisi X
       ↓
Tombol "Reassign" per baris SN/item
       ↓
Pilih tujuan:
  - Kembalikan ke Gudang Cabang (RETURN)
  - Pindah ke Teknisi Lain (TRANSFER_CUSTODY)
       ↓
Catat di inventory_transactions:
  - type: RETURN atau TRANSFER_CUSTODY
  - notes: wajib diisi (alasan: resign/cuti/rotasi)
  - created_by: admin yang eksekusi (bukan teknisi lama)
       ↓
inventory_serials.custodian diupdate
```

Langsung eksekusi (gak ada approval gate) tapi tercatat penuh di ledger — konsisten sama keputusan Fase 1 lain (jangan bikin approval berlapis sebelum kebutuhan riil kelihatan).

**Tiga catatan koreksi/penajaman:**

1. **`TRANSFER_CUSTODY` adalah type ledger BARU, beda dari `TRANSFER` dan `ISSUE`** — perlu ditegaskan eksplisit: `TRANSFER_CUSTODY` **tidak menyentuh `inventory_balances` gudang sama sekali** (stok cabang gak berubah, barang gak pernah "mampir" balik ke gudang), cuma pointer `custodian` di `inventory_serials`/custody balance yang pindah langsung teknisi-ke-teknisi. Beda dari `TRANSFER` (gudang↔gudang, ubah `inventory_balances` dua gudang) dan `ISSUE` (gudang→teknisi, ubah `inventory_balances` gudang DAN custody). Tiga hal ini gampang ketuker kalau gak ditulis eksplisit siapa yang nyentuh apa.
2. **Sisi teknisi baru tetap wajib ack sendiri, bukan cuma notifikasi.** Ini beda dari alasan `created_by=admin` di sisi teknisi lama (dia mungkin udah resign, gak bisa/gak perlu diminta konfirmasi apa pun). Tapi kalau tujuannya `TRANSFER_CUSTODY` (bukan `RETURN` ke gudang), **teknisi baru** yang nerima harus tetap klik "Saya terima" sendiri (pola sama §4 `kontrol-anti-manipulasi.md` — acknowledgment digital dari penerima) — notifikasi doang belum nutup celah "saya gak pernah pegang barang itu" dari sisi penerima. Status custody idealnya `PENDING_ACK` dulu sampai teknisi baru konfirmasi, baru resmi jadi custody-nya.
3. **"Integrasi ke HR/payroll" DIKOREKSI — di luar scope, sistemnya gak ada.** Dicek ke kode: `UserStatus` cuma py `ACTIVE`/`INACTIVE` (`app/Enums/UserStatus.php`), gak ada modul HR/payroll apa pun di repo ini buat diintegrasikan. Ganti jadi guard ringan yang reuse struktur yang sudah ada: pas admin coba ubah `User::status` jadi `INACTIVE` sementara user itu masih py baris custody aktif (`inventory_serials`/custody balance belum nol), sistem kasih peringatan/block dulu ("User ini masih pegang N barang — reassign dulu sebelum dinonaktifkan") — bukan bikin integrasi ke sistem eksternal yang gak exist.

### 3.7 Dua neraca beda — stok gudang vs custody teknisi

Menegaskan ulang §29.4 poin 2 (doc pertama, dikonfirmasi user tanpa perubahan): submit Laporan Pemasangan/Maintenance/INFR/O-REQ **TIDAK** mengurangi stok gudang lagi (udah kepotong sekali pas `ISSUE`) — tapi ini **bukan berarti gak ada pengurangan apa pun**. Yang berkurang adalah **custody teknisi**: qty yang dia pegang turun sesuai konsumsi aktual yang dilaporkan (mis. ambil 50 RJ45 dari cabang, dipakai 20 di satu pemasangan → custody-nya sisa 30 buat pekerjaan berikutnya, stok CABANG tetap -50 sejak ISSUE, gak dipotong lagi -20).

```
❌ SALAH:  ISSUE (stok gudang -1) → Submit Laporan (stok gudang -1 lagi) = stok -2
✅ BENAR:  ISSUE (stok gudang -1, custody teknisi +1)
              → Submit Laporan → update status SN (Aktif) / task_materials kind=terpakai (Pasif)
                                → custody teknisi berkurang sesuai qty terpakai
                                → stok GUDANG tidak disentuh lagi
```

### 3.8 Custody Barang QUANTITY/BATCH — tabel baru `TechnicianCustody`

**Gap yang ketauan pas nurunin alur `lot_no` (keputusan user):** tabel minimum Fase 1 sebelumnya (`warehouses`, `inventory_balances`, `inventory_transactions`, `inventory_serials`) cuma nyiapin custody buat barang **SERIALIZED** (`inventory_serials`, per-unit). Custody buat barang **QUANTITY/BATCH** (kabel per meter, RJ45 per pcs) butuh tabel sendiri karena sifatnya beda — bisa dipakai SEBAGIAN (parsial), gak atomic kayak unit serial.

**Tabel ke-5:** `technician_custody` — `technician_id`, `item_id`, `lot_no` (nullable, cuma kepake buat item BATCH), `qty_remaining`, `status` (`ISSUED`/`PARTIALLY_USED`/`RETURNED`/`CONSUMED` — **vocabulary status TERPISAH** dari status kanonik §15 doc advanced, karena qty custody bisa parsial sementara unit serial gak bisa; lihat `kontrol-anti-manipulasi.md` §7).

**Alur multi-lot saat ISSUE (kabel dari 2 drum berbeda):**

```
ISS-2026-0042
  ├── item: Kabel Dropcore G657A2, lot_no: LOT-2026-001 (drum 1), qty: 80m
  └── item: Kabel Dropcore G657A2, lot_no: LOT-2026-002 (drum 2), qty: 50m
```

`inventory_balances` jadi keyed per `(warehouse_id, item_id, lot_no)` — bukan cuma per item — buat item BATCH. **Admin gudang yang pilih drum mana + berapa meter dari tiap drum** saat form Issue, BUKAN teknisi — admin gudang yang tau fisik drum mana yang udah kebuka.

**Konsumsi saat submit laporan — FIFO otomatis, teknisi cuma input angka:**

Teknisi cuma isi "Jumlah dipakai: 30m" — gak milih lot manual. Backend (`InventoryService::consumeFromCustody()`) motong dari lot dgn `lot_no` paling kecil dulu (FIFO — drum lama abis duluan, alesan fisik: drum yang udah kebuka emang harus dihabisin duluan). Kalau motong 2 lot sekaligus → `task_materials` jadi 2 baris (satu per lot), masing-masing bawa `unit_price_snapshot`-nya SENDIRI (lihat di bawah) + `lot_no`.

**Harga per-lot, bukan per-item flat.** Karena tiap drum bisa beda harga beli, `unit_price_snapshot` yang disalin ke `task_materials` (§3.5) itu **harga lot spesifik yang dipotong** (dicatat sekali pas lot itu di-RECEIVE dari supplier) — bukan "harga terakhir item secara umum". Ini penyempurnaan alami dari prinsip last-cost (§16.4/§29.8): buat item BATCH, last-cost jadi otomatis granular per-lot; buat item QUANTITY biasa tanpa lot (RJ45, cable tie), tetep last-cost flat per-item kayak semula — gak semua QUANTITY item butuh lot.

**Ceiling enforcement** — `qty` yang diklaim di laporan gak boleh lebih dari total `qty_remaining` custody teknisi buat item itu. Lebih dari itu → `InsufficientCustodyException`, submit ditolak. Ini juga jadi fondasi kontrol §7 `kontrol-anti-manipulasi.md`.

**Edge case yang diakui, bukan diselesaikan di Fase 1:** kalau teknisi udah fisik ngabisin drum lama tapi sistem masih nyatet ada sisa (karena laporan sebelumnya belum disubmit) — itu diselesaikan lewat stock opname custody, bukan fitur Fase 1, dicatat sebagai backlog.

---

## 4. Yang SENGAJA belum dibuat di Fase 1

Sesuai pentahapan §29.9/§16.8 dokumen sebelumnya — jangan dibangun sekarang:

- Approval berlapis Stock Request (DRAFT→SUBMITTED→REVIEWED→...).
- Halaman Quarantine/Repair terstruktur (cukup catatan bebas di record ADJUSTMENT untuk sekarang).
- Reorder alert otomatis / notifikasi low-stock push.
- Dashboard "Inventory Control Tower" lintas-cabang bergaya enterprise (§27 doc pertama) — dashboard 2.1 cukup untuk Fase 1.

## 5. Status keputusan (update 2026-09-02)

**Sudah diputuskan** (lihat §3.5–3.7, §1.2 revisi):
- ~~`view_own`/`VIEW_OWN`~~ — dibatalkan, gak dibutuhkan (§1.2).
- ~~Reassign custody teknisi resign/cuti~~ — flow ditetapkan (§3.6).
- ~~Default `equipment_class` kategori `lainnya`~~ — `pasif`, dengan `equipment_class_override` per-item (§3.1).
- ~~Titik waktu `unit_price_snapshot`~~ — saat `ISSUE`, disalin (bukan diquery ulang) ke `task_materials` saat submit (§3.5).

**Sudah diputuskan (update 2026-09-02, ronde ke-2):**
- ~~Penomoran `TRF-`/`REQ-`/`ISS-`~~ — **global**, format `{PREFIX}-{YYYY}-{0001}`, pola sama `TKT-`/`TFOP-`/`TASK-` (MAX+1, lock kalau backend DB-nya dukung row-lock beneran — lihat catatan di bawah). **Koreksi lokasi dokumentasi:** BUKAN didaftarkan ke `docs/ID_NUMBERING_RULES.md` (dokumen itu 100% khusus penomoran customer per-POP via `PopSequence`, format & mekanismenya beda total) — format global kayak gini tempatnya ikut pola `TKT-`/`TFOP-`/`TASK-` di **CLAUDE.md § Penomoran & ID**, didaftarkan pas implementasi beneran nanti (bukan sekarang, dokumen itu di luar scope perubahan sesi ini).
- ~~Akses `fop`~~ — final, read-only terbatas (§1.3 revisi): Custody (scope wilayah) + Traceability + wajib Analisa material per `fop_task_id`. TIDAK dapat Dashboard/Transfer/Issue/Ledger.
- ~~Alur `lot_no`~~ — dijabarkan penuh §3.8 (multi-lot saat Issue, FIFO otomatis saat konsumsi, harga per-lot, tabel baru `technician_custody`).
- ~~Verifikasi material non-serial~~ — dijabarkan penuh `kontrol-anti-manipulasi.md` §7 (structural constraint + return reconciliation, bukan anomaly detection).

**Catatan teknis nempel di keputusan penomoran:** DB default repo ini SQLite (`.env.example`) — `lockForUpdate()` di situ gak ngasih row-level lock kayak MySQL/Postgres (grammar SQLite Laravel gak nerbitin `FOR UPDATE`), yang bikin generate nomor tetep aman itu SQLite ngunci seluruh file DB per write-transaction. Tetep aman di skala sekarang, tapi kalau nanti pindah backend DB, perlu row-lock beneran biar gak asumsi salah.

**Masih terbuka:** tidak ada lagi — 4 poin sebelumnya semua clear. Sisa kerjaan: eksekusi ke ERD/migration beneran (di luar sprint aktif, perlu konfirmasi lompat sprint dulu).
