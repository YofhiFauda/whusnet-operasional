# Rancangan: Visibilitas Modem Terjual di Antrean BD + Rekonsiliasi "SN Terjual Belum Ditagih"

**Status:** ✅ DIIMPLEMENTASI 2026-11-01 (ADHOC-250). Dibuat 2026-10-31 atas permintaan user, hasil penelusuran alur "Pelanggan Beli dari Gudang". K1–K3 (§9) diputuskan sepihak saat implementasi — lihat §11.

## 11. Keputusan Implementasi (2026-11-01)

- **K1:** permission `modem_sale_reconciliation.view` / `.export`, feature `modem_sale_reconciliation` (root sendiri, bukan numpang `reports.*`). Diberikan ke owner (lewat `*`), `atasan`, `admin`.
- **K2:** menu masuk ke grup sidebar "Laporan" yang sudah ada (submenu, bersama Laporan Bulanan Admin/Laporan Bayar Kolektor).
- **K3:** tidak ada notifikasi otomatis untuk baris Anomali — laporan dibuka manual, sesuai default di §9.
- `invoice_items.inventory_serial_sale_id` ditambahkan via migration `2026_11_01_090000_...` dan **sudah diterapkan ke database aplikasi** (bukan cuma kode lokal).
- `ModemSaleReconciliationService::resolvePendingStatus()` membedakan jalur lewat `FopTask->task->task_type`: `CREQ`/`MAINTENANCE` → baca `verification_status` C-REQ/MTN; selain itu (jalur Pemasangan) → baca status pelanggan (`verification_admin`/`waiting_business_development_verification`).
- Fallback data lama (§5.4) HANYA dipakai kalau pelanggan itu punya tepat satu `inventory_serial_sales` — tidak dipakai kalau pelanggan pernah beli modem dua kali, supaya tidak menebak salah sale.

**Alur kerja:** analisa → dokumentasi (ini) → implementasi **hanya setelah diinstruksikan user**.

**Terkait:**
- [`../../billing-pembayaran/README.md`](../../billing-pembayaran/README.md)
- [`../../task-teknisi/business-logic.md`](../../task-teknisi/business-logic.md)
- Kode: `InventoryService::installSerial()` / `recordSerialSale()`, `InitialInvoiceService`, `CustomerVerificationController::finalVerify()`, `BusinessDevelopmentVerificationController`, `TaskCreqBillingController::approve()`

---

## 1. Latar Belakang

Pelanggan "Beli dari Gudang" (modem milik pelanggan) menulis `inventory_serial_sales` begitu laporan teknisi selesai. Baris tagihan "Jual Modem" (subkategori `jual_modem`) baru terbit belakangan:

| Jalur | Kapan SN keluar dari stok | Kapan tagihan modem terbit |
|---|---|---|
| Pemasangan, non-Bisnis | Submit speedtest | `finalVerify()` CS, seketika |
| Pemasangan, **Bisnis** | Submit speedtest | **Setelah BD `verify()`** — snapshot disimpan di `customers.pending_initial_invoice` |
| C-REQ / MTN | Submit laporan | Setelah CS `approve()` (`TaskBillingItem` menunjuk sale) |

Selama jeda itu SN sudah `ownership = CUSTOMER` dan keluar dari stok gudang, tetapi belum ada invoice. Untuk jalur Bisnis jeda ini ditentukan oleh antrean BD.

Yang **sudah ada dan tidak diubah**: kolom "Menunggu Sejak" di `/business-development-verifications` (`index.blade.php`). Rancangan ini **bukan** soal umur antrean.

## 2. Tujuan

1. **Poin 1 — Visibilitas:** BD melihat bahwa antrean pelanggan membawa tagihan modem yang belum terbit.
2. **Poin 2 — Rekonsiliasi:** ada satu laporan yang menunjukkan setiap SN terjual yang belum ditagih, dengan alasan, sehingga kebocoran pendapatan modem terdeteksi.

Bonus di luar dua poin (disepakati user): baris invoice "Jual Modem" memuat keterangan modem (lihat §3).

## 3. Prasyarat — Keterangan Modem di Baris Invoice

Saat ini `InitialInvoiceService::lineSpecs()` mengirim `description => null` untuk `jual_modem`, jadi invoice hanya menampilkan nama kategori.

| Hal | Rancangan |
|---|---|
| Isi `description` | `"Pembelian modem {brand model} · SN {serial_number}"` |
| Sumber data | `inventory_serial_sales` + `inventory_serials` + `items` |
| Jalur Pemasangan | Keterangan dibawa lewat snapshot billing (`modem_sale_description` di array `$billing`), ikut tersimpan di `pending_initial_invoice` untuk jalur BD |
| Jalur C-REQ/MTN | Nama default baris di form `items[]` diisi dengan teks yang sama; CS tetap bisa mengedit |

Pembawa lewat snapshot dipilih agar jalur BD tidak perlu query ulang SN saat `verify()`, dan tetap konsisten dengan angka yang sudah dikonfirmasi CS.

## 4. Poin 1 — Kartu Modem di Halaman BD

**Lokasi:** `/business-development-verifications` (index) dan halaman `show` pelanggan.

**Isi:** untuk pelanggan dengan `modem_sale_amount` > 0 di `pending_initial_invoice['billing']`:

> Modem terjual: {brand model} · SN {serial} · Rp{harga} — akan masuk Invoice Awal bersama Biaya Instalasi.

| Aspek | Keputusan |
|---|---|
| Sumber | Nominal dari `pending_initial_invoice['billing']['modem_sale_amount']`; SN dari `inventory_serial_sales` (lewat `fop_task_id` instalasi pelanggan) |
| Tanpa modem | Kartu tidak tampil (pelanggan sewa / beli sendiri) |
| Ubah alur/data | Tidak. Read-only, tanpa migrasi |
| Izin lihat harga | Ikut izin halaman BD yang sudah ada; tidak menambah permission |
| POP scope | Sudah ditegakkan `BusinessDevelopmentVerificationQueue`; tidak dilonggarkan |

## 5. Poin 2 — Laporan "SN Terjual Belum Ditagih"

### 5.1 Masalah penghubung

| Jalur | Penghubung sale ↔ tagihan |
|---|---|
| C-REQ / MTN | **Ada** — `task_billing_items.inventory_serial_sale_id` |
| Pemasangan | **Tidak ada** — baris `jual_modem` di `invoice_items` tidak menunjuk sale mana pun |

Mencocokkan lewat pelanggan + subkategori `jual_modem` meleset kalau satu pelanggan membeli modem dua kali.

### 5.2 Perubahan skema

Satu migrasi: `invoice_items.inventory_serial_sale_id` — nullable, FK ke `inventory_serial_sales`, **`restrictOnDelete`** (selaras `task_billing_items`; sale yang sudah ditagih tidak boleh terhapus).

- Diisi saat `InitialInvoiceService::issue()` untuk baris `jual_modem`.
- Data lama **tidak** di-backfill. Laporan memakai pencocokan cadangan untuk data lama (§5.4) dan menandainya.
- Perlu dicek saat implementasi: `InvoiceItemBuilder::rebuildFor()` belum dibaca; kemungkinan harus menerima kolom tambahan per baris.

### 5.3 Status baris laporan

| Kondisi | Status | Tindakan |
|---|---|---|
| Sale pelanggan jalur Pemasangan, pelanggan `waiting_business_development_verification`, `pending_initial_invoice` terisi | **Menunggu BD** | Wajar |
| Sale jalur C-REQ/MTN, `verification_status = PENDING` | **Menunggu approve CS** | Wajar |
| Sale jalur Pemasangan, pelanggan `verification_admin` | **Menunggu verifikasi CS** | Wajar |
| Tidak ada invoice item penaut, tidak ada pending, tidak termasuk tiga baris di atas | **Anomali** | Perlu ditindaklanjuti |
| Ada invoice item penaut (kolom baru) atau `task_billing_items` | — | Tidak tampil |

Baris "wajar" tetap ditampilkan agar jeda kelihatan; hanya **Anomali** yang disorot dan dihitung di ringkasan.

### 5.4 Pencocokan data lama

Untuk sale tanpa penaut yang pelanggannya sudah `active`: cocokkan ke Invoice Awal pelanggan yang punya baris `jual_modem`. Cocok → dianggap ditagih, ditandai "cocok via pelanggan (data lama)". Tidak cocok → Anomali.

### 5.5 Akses & scope

| Hal | Keputusan |
|---|---|
| Permission | Fitur baru di matrix RBAC, misal `reports.modem_sale_reconciliation.view` — di-generate dari `features` × `actions`, bukan hardcode. **Perlu keputusan user (K1)** |
| POP scope | Wajib lewat `EffectiveAccessService`; query tanpa scope dilarang |
| Role awal | owner, atasan, admin; role lain via Role Matrix |
| Kolom nominal | Mengikuti izin lihat harga yang sudah ada |

### 5.6 Lokasi UI

Halaman laporan tersendiri (pola 2: bukan modal), dengan filter Cabang, status, rentang tanggal sale. Ekspor CSV mengikuti pola laporan lain. **Perlu keputusan user (K2):** menu di grup mana (Gudang atau Laporan Keuangan).

## 6. Urutan Implementasi

1. Keterangan modem di baris invoice (§3) + test.
2. Kartu modem di halaman BD (§4) + test.
3. Migrasi penghubung + isi saat `issue()`, lalu laporan (§5) + test.

Tiap langkah punya test sendiri, dijalankan per file (bukan full suite).

## 7. Skenario Test

| # | Skenario |
|---|---|
| 1 | Invoice Awal non-Bisnis memuat deskripsi modem (merek/model/SN) |
| 2 | Jalur BD: deskripsi dari snapshot tetap muncul di invoice hasil `verify()` |
| 3 | Pelanggan tanpa modem jual: tidak ada baris dan tidak ada kartu |
| 4 | Kartu BD tampil nominal + SN; tidak tampil untuk pelanggan di luar POP scope |
| 5 | `issue()` mengisi `invoice_items.inventory_serial_sale_id` |
| 6 | Laporan: pelanggan menunggu BD → status "Menunggu BD", bukan Anomali |
| 7 | Laporan: sale tanpa penaut, tanpa pending → Anomali |
| 8 | Laporan: sale sudah punya penaut → tidak tampil |
| 9 | Pelanggan beli modem dua kali: tiap sale dicocokkan terpisah |
| 10 | Data lama: dicocokkan via pelanggan dan diberi tanda |
| 11 | POP scope & permission (user tanpa izin ditolak; kolom harga tersembunyi tanpa izin) |
| 12 | Ekspor CSV memuat kolom sesuai izin |

## 8. Risiko

| Risiko | Mitigasi |
|---|---|
| `restrictOnDelete` pada FK baru menahan hapus sale | Disengaja; sesuai aturan invoice/payment tidak dihapus sembarangan |
| Backfill data lama tidak pasti | Tidak di-backfill; pencocokan cadangan ditandai |
| Snapshot `pending_initial_invoice` bertambah kunci | Pembaca lama mengabaikan kunci tidak dikenal; cek `withInstallationFee()` tidak menimpanya |
| Laporan memicu kecemasan palsu dari status "wajar" | Status wajar dibedakan jelas dari Anomali; hanya Anomali dihitung di ringkasan |

## 9. Keputusan yang Masih Terbuka

- **K1:** nama dan role awal permission laporan.
- **K2:** letak menu laporan.
- **K3:** perlu atau tidak notifikasi saat muncul baris Anomali (default: tidak, laporan dibuka manual).

## 10. Di Luar Lingkup

- Mengubah alur BD, menerbitkan invoice modem terpisah (ditolak: kembali ke dua invoice per pelanggan, lihat catatan 2026-09-16 di `BusinessDevelopmentVerificationController`).
- Notifikasi pengingat terjadwal untuk antrean BD.
- Penandaan umur antrean — sudah ada ("Menunggu Sejak").
