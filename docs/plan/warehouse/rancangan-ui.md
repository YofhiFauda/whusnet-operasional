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
    ActionCode::VIEW->value,        // admin/gudang: lihat custody SEMUA teknisi
    ActionCode::VIEW_OWN->value,    // teknisi: lihat custody DIRI SENDIRI — action code BARU, pola sama task.view.own
],

'warehouse_traceability' => [
    ActionCode::VIEW->value, // cari SN → riwayat lengkap
],
```

Dua `ActionCode` baru perlu ditambah ke `app/Enums/ActionCode.php`:
- `RECEIVE = 'receive'` — dokumentasikan alasan terpisah dari `APPROVE` (pola sama `PAY`/`DEPOSIT`/`VISIT` yang sudah dijelaskan alasannya di enum): konfirmasi fisik "barang sudah nyampe", bukan keputusan approve/reject.
- `VIEW_OWN = 'view_own'` — cek dulu apakah `task.view.own` sebenarnya sudah lahir dari kombinasi feature/action lain (grep sebelum nambah, jangan sampai action code dobel makna).

### 1.3 Matrix hak akses per layar

| Layar | `owner`/`atasan` | `admin` | `pop_admin` | `fop` | `teknisi` |
|---|---|---|---|---|---|
| Dashboard stok (semua gudang) | ✅ | ✅ | ✅ *(scope cabangnya saja)* | — | — |
| Transfer: buat (Pusat→Cabang) | ✅ | ✅ | — | — | — |
| Transfer: terima (di Cabang) | ✅ | ✅ | ✅ *(cabangnya saja)* | — | — |
| Issue ke Teknisi | ✅ | ✅ | ✅ *(cabangnya saja)* | — | — |
| Custody — lihat semua | ✅ | ✅ | ✅ *(teknisi cabangnya saja)* | — | — |
| Custody — lihat punya sendiri | ✅ | ✅ | ✅ | ✅ | ✅ |
| Validasi SN di Laporan Pemasangan | *(otomatis, bagian form existing)* | | | | ✅ *(pemilik custody)* |
| Ledger/riwayat transaksi | ✅ | ✅ | ✅ *(cabangnya saja)* | — | — |
| Asset Traceability (cari SN) | ✅ | ✅ | ✅ *(cabangnya saja)* | opsional `fop` kalau perlu troubleshoot lapangan | — |

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

## 3. Yang SENGAJA belum dibuat di Fase 1

Sesuai pentahapan §29.9/§16.8 dokumen sebelumnya — jangan dibangun sekarang:

- Approval berlapis Stock Request (DRAFT→SUBMITTED→REVIEWED→...).
- Halaman Quarantine/Repair terstruktur (cukup catatan bebas di record ADJUSTMENT untuk sekarang).
- Reorder alert otomatis / notifikasi low-stock push.
- Dashboard "Inventory Control Tower" lintas-cabang bergaya enterprise (§27 doc pertama) — dashboard 2.1 cukup untuk Fase 1.

## 4. Yang perlu diverifikasi sebelum coding

1. Cek existing `ActionCode`/permission apakah `view_own`-style sudah ada mekanismenya di tempat lain (`task.view.own`) sebelum nambah `VIEW_OWN` baru — jangan sampai dobel makna.
2. Putuskan siapa yang berhak assign/reassign custody kalau teknisi resign/cuti sebelum barangnya balik — belum ada di rancangan mana pun, perlu keputusan user.
