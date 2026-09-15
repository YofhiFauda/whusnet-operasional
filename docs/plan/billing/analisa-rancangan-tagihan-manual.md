# Analisa & Rancangan: Tagihan Manual (Detail Pelanggan + Halaman Tagihan)

**Status:** Terbuka — analisa selesai 2026-09-15, implementasi belum mulai. Di luar sprint aktif, dicatat sebagai ADHOC-70 di `docs/TASKS.md`.

**Sumber ide awal:** permintaan user (chat 2026-09-15) — dua pintu masuk Tagihan Manual: modal di Detail Pelanggan, dan Halaman Tagihan (dengan search by CID/Nama).

**Konteks penting:** ada rancangan lama, `docs/plan/analisa-tagihan-kategori-pendapatan.md` (ADHOC-60, 2026-09-09), yang merancang **persis** konsep master kategori pendapatan buat kebutuhan ini. Implementasinya **dieksekusi cuma separuh jalan lalu sebagian dihapus user** (kualitasnya jelek) — sisanya sekarang jadi infrastruktur dorman: ada di DB & kode, tapi tidak dipakai user manapun. Dokumen ini **bukan pengganti** ADHOC-60, tapi lanjutannya + revisi di titik yang perlu direvisi (§3.2).

**Terkait:** `docs/plan/upgrade-downgrade/analisa-upgrade-downgrade-paket.md`, `docs/plan/billing/analisa-rancangan-putus-langganan.md` — tiga rancangan billing ini disatukan user sebelum eksekusi, urutan pengerjaan ditentukan belakangan.

---

## 1. Ringkasan Putusan

Permintaan user match dengan apa yang **sudah dirancang** (kategori Perbaikan/Instalasi/Lainnya + rincian bebas + nominal manual), tapi:
- Implementasinya **kepotong** — cuma layer data (migrasi, model, service) yang selamat, UI-nya tidak pernah selesai / sudah dihapus.
- **Modal ditolak** untuk kedua pintu masuk (Detail Pelanggan sudah ada modal lama yang perlu diganti; Halaman Tagihan direncanakan modal juga di request awal) — dikoreksi jadi **halaman create tersendiri**, konsisten dengan keputusan lama ADHOC-60 §7 dan aturan CLAUDE.md 2026-09-07 (mutasi data ≠ modal, alasan: `back()->withErrors()` balik ke *referer*, modal-di-atas-List bikin gagal-validasi kehilangan pesan error).
- Satu revisi terhadap keputusan lama: kategori pendapatan **tidak lagi kaku terkunci di 4** — bisa nambah kategori besar baru (lihat §3.2).

---

## 2. Gap Terhadap Kode Nyata

### 2.1 Infrastruktur ADHOC-60 — sebagian ada, sebagian tidak

**Masih ada (dipertahankan, tinggal dipakai):**
- `revenue_categories` / `revenue_subcategories` (tabel + model `RevenueCategory`/`RevenueSubcategory`).
- `invoice_items` (tabel + model `InvoiceItem`), relasi `Invoice::items()`.
- `App\Services\InvoiceItemBuilder`, `App\Services\ManualInvoiceService`, `App\Services\InvoiceNumberGenerator`.
- `InvoiceType::INSIDENTAL` (`app/Enums/InvoiceType.php`).
- Satu pemakai nyata: `CustomerAcquisitionController::updateInstallationFee()` — memanggil `ManualInvoiceService::create()` untuk menerbitkan invoice biaya instalasi (kategori `jasa_instalasi`/`biaya_aktivasi`).

**Tidak ada / sudah dihapus (perlu dibangun):**
- Halaman Master Kategori Pendapatan (`Master\RevenueCategoryController`, view, route, menu sidebar) — dicek, nihil di `app/Http/Controllers/Master/`, `routes/web.php`, `resources/views/`.
- Permission `revenue_categories.*` — nihil di `config/rbac.php`.
- Feature seeder-nya — nihil.
- Halaman `/invoices/create` — nihil, `InvoiceController` tidak punya method `create()`/`store()`, route `web.php` cuma punya index/lunas/belum-lunas/show untuk `/invoices`.
- `RevenueBreakdownService` (breakdown kategori di laporan) — nihil.
- `BackfillInvoiceItemsCommand` — nihil.

### 2.2 Modal lama di Detail Pelanggan masih pakai kolom flat, bukan kategori

`resources/views/customers/show.blade.php:1249` (`#manual-invoice-modal`) submit ke `CustomerController::storeManualInvoice()` (`app/Http/Controllers/CustomerController.php:3719`) — validasi & simpan masih pakai kolom lama `invoices.prorate_amount`/`extra_cable_fee`/`extra_installation_fee`/`extra_pole_fee`, **tidak** menyentuh `revenue_categories`/`invoice_items` sama sekali. Ini yang akan diganti.

### 2.3 Halaman Tagihan belum punya fitur "Buat Tagihan" apapun

`InvoiceController::index()` (`/invoices`) cuma listing + search (`app/Http/Controllers/InvoiceController.php:19-51`, sudah bisa cari by nama/CID/nomor invoice — pola pencariannya bisa **direuse** buat search pelanggan di form create). Tidak ada tombol/aksi bikin tagihan baru dari halaman ini sama sekali sekarang.

### 2.4 "Metode Pembayaran" — enum sudah pas, tinggal disambung ke alur invoice+payment sekaligus

`App\Enums\PaymentMethod` sudah punya `CASH`/`TRANSFER`/`QRIS`/`KOLEKTOR`/`LAINNYA` — persis "Cash atau TF" yang diminta, **tidak perlu enum baru**. Yang belum ada: jalur yang menerbitkan invoice **dan** payment dalam satu submit form (sekarang dua aksi terpisah — invoice dulu via `ManualInvoiceService`, payment nyusul lewat `/invoices/{id}/payments` via `PaymentController::store()`/`PaymentService`).

---

## 3. Keputusan (2026-09-15)

### 3.1 Modal → Halaman create tersendiri (kedua pintu masuk)

- **Detail Pelanggan:** tombol "Buat Tagihan Manual" jadi link ke `/invoices/create?customer_id=...` (customer sudah ter-prefill & terkunci — pola sama dengan rencana ADHOC-60 lama), modal `#manual-invoice-modal` dihapus.
- **Halaman Tagihan:** tombol "Buat Tagihan" link ke `/invoices/create` polos, dengan search pelanggan (CID/Nama) di dalam form itu sendiri — pola pencarian reuse dari `InvoiceController::index()` (`orWhereHas('customer', ...)` by `full_name`/`cid`/`customer_code`) atau endpoint typeahead yang sudah ada polanya (`customers.search-referral`, `app/Http/Controllers/CustomerController.php`) — pilih salah satu pola existing, jangan bikin mekanisme pencarian ketiga.

Satu halaman `/invoices/create` melayani dua pintu masuk (beda cuma ada/tidaknya `?customer_id=` prefill) — sama seperti rancangan ADHOC-60 lama.

### 3.2 Kategori pendapatan: dibuka, tidak lagi terkunci di 4 (revisi ADHOC-60)

Rancangan lama mengunci kategori level atas di 4 baris tetap, admin cuma boleh nambah/ubah **sub**kategori. Keputusan sekarang: **"Pindah Lokasi" naik jadi kategori besar sendiri**, sejajar Perbaikan/Instalasi/Lainnya — bukan lagi subkategori di bawah "Jasa Perbaikan".

Konsekuensi ke desain lama:
- **Dua kode yang tetap terkunci** (tidak bisa diubah/dihapus, perilaku spesial di kode): `jasa_layanan_internet` (nominal dari `customer_services`, penentu guard satu-langganan-per-periode) dan `lainnya` (baris nama ketik-bebas, tanpa subkategori — lihat §3.3). Ini yang beneran "dirujuk kode" (`RevenueCategory::CODE_JASA_LAYANAN_INTERNET`, `CODE_LAINNYA`).
- **Kategori lain (`jasa_instalasi`, `jasa_perbaikan`, dan kategori baru seperti `pindah_lokasi`) berperilaku sama** — baris biasa, nominal manual/`default_amount`, tidak ada logic bercabang berdasarkan `code`-nya. Kategori jenis ini **boleh ditambah admin** lewat halaman Master (bukan lagi hardcode migrasi doang).
- Aturan hapus kategori/subkategori: **sama seperti master alasan putus-langganan** (`docs/plan/billing/analisa-rancangan-putus-langganan.md` §3.4, konsisten satu pola across master data di sistem ini) — **tidak bisa dihapus kalau masih dipakai** minimal satu `invoice_items`, **bisa dihapus permanen kalau tidak dipakai siapa pun**. FK `invoice_items.revenue_category_id`/`revenue_subcategory_id` pakai `restrictOnDelete()` (bukan `nullOnDelete()`), dicek dulu di controller sebelum DELETE biar errornya jelas bukan raw SQL violation.

**Migrasi data:** kategori `pindah_lokasi` yang sekarang ada sebagai **sub**kategori di bawah `jasa_perbaikan` (id lihat `revenue_subcategories`) dipromosikan jadi kategori baru level atas. Karena fiturnya belum pernah benar-benar dipakai user (satu-satunya pemakai `ManualInvoiceService` sekarang cuma `jasa_instalasi`/`biaya_aktivasi` untuk installation fee), migrasi ini kemungkinan besar **tidak perlu mikirin data invoice existing** yang mereferensikannya — tapi **wajib dicek dulu** saat implementasi (`SELECT COUNT(*) FROM invoice_items WHERE revenue_subcategory_id = <id pindah_lokasi>`) sebelum menghapus/mengubah baris subkategori lama, ikuti aturan §3.2 di atas (restrict kalau ternyata terpakai).

### 3.3 Kategori "Lainnya": tetap ketik bebas, contoh yang disebutkan bukan sub baku

"Pendapatan over kabel" / "Pendapatan A" / "Pendapatan B" yang disebut user adalah **contoh nama yang diketik bebas** saat membuat tagihan di kategori Lainnya — bukan permintaan bikin daftar subkategori tetap. Desain lama tetap berlaku: kategori `lainnya` **tanpa** subkategori master, form-nya jadi input teks bebas untuk nama baris. Tidak ada perubahan skema di titik ini.

### 3.4 Invoice + Payment sekaligus dalam satu submit

Form Tagihan Manual submit **satu kali** menghasilkan **dua record**: `Invoice` (via `ManualInvoiceService::create()`, tipe otomatis — `insidental` untuk tagihan non-langganan, sesuai §"Revisi 2026-09-09" di ADHOC-60) **dan** `Payment` (via jalur yang sama dipakai `PaymentController::store()`/`PaymentService`, bukan ditulis ulang) — dalam **satu `DB::transaction()`**.

Field form tambahan yang perlu ada:
- **Metode Pembayaran** — dropdown `PaymentMethod::CASH`/`TRANSFER` (dua opsi sesuai permintaan; `QRIS`/`KOLEKTOR`/`LAINNYA` di enum tidak usah ditampilkan di form ini kecuali dibutuhkan nanti — enum-nya tidak perlu diubah, cukup dibatasi pilihan di level form/validasi).
- **Nominal Dibayar** — default = total invoice (asumsi lunas penuh saat itu juga, sesuai skenario "cash/TF di tempat"), tapi **tetap field terpisah yang bisa diedit** (bukan otomatis dikunci sama total) supaya kasus bayar sebagian tetap bisa dicatat lewat form yang sama — reuse logic pemisahan bayar/lebih-bayar yang sudah ada di `PaymentService`, jangan bikin cabang baru.
- Field pendukung metode transfer (`bank_name`, `account_number`) — ikut aturan existing `PaymentMethod::requiresBankDetails()`.

**Kalau payment gagal disimpan** (misal validasi nominal), invoice yang baru dibuat **ikut di-rollback** (satu transaksi) — bukan invoice nyangkut ke-generate tapi paymentnya gagal diam-diam.

---

## 4. Rancangan Implementasi

### 4.1 Skema DB — perubahan dari ADHOC-60

- Tambah kategori baru `pindah_lokasi` di `revenue_categories` (bukan lagi baris di `revenue_subcategories`).
- `revenue_categories.is_system` dipertahankan, tapi maknanya diperjelas: cuma `jasa_layanan_internet` & `lainnya` yang `is_system = true` (dirujuk kode). `jasa_instalasi`, `jasa_perbaikan`, `pindah_lokasi`, dan kategori baru admin ke depan = `is_system = false`.
- FK `invoice_items.revenue_category_id` & `revenue_subcategory_id`: pastikan `restrictOnDelete()` (§3.2) — cek migrasi existing, kemungkinan sudah begini, tinggal diverifikasi masih konsisten setelah kategori dibuka buat ditambah admin.
- Tidak ada tabel baru di luar yang sudah dirancang ADHOC-60 (`revenue_categories`, `revenue_subcategories`, `invoice_items`) — cukup direvisi isi datanya + dibuka aksi tambahnya.

### 4.2 Halaman & Controller

1. **`Master\RevenueCategoryController`** (baru, sesuai rencana ADHOC-60 yang belum jadi) — CRUD kategori (sekarang termasuk **tambah** kategori baru, bukan cuma sub) + sub, toggle aktif, guard hapus (§3.2). Permission `revenue_categories.view|create|update|delete` (delete ditambah dari rencana lama, karena sekarang hapus permanen dimungkinkan kalau tidak dipakai).
2. **`InvoiceController::create()`/`store()`** (baru) — halaman `/invoices/create`:
   - Terima `?customer_id=` opsional (prefill dari Detail Pelanggan) atau search CID/Nama (dari Halaman Tagihan, §3.1).
   - Baris rincian (kategori→sub cascading, tombol Tambah Cepat) — reuse pola `<x-invoice-line-rows>` dari rancangan ADHOC-60 kalau filenya masih ada, atau dibuat ulang kalau sudah ikut terhapus.
   - Field Metode Pembayaran + Nominal Dibayar (§3.4).
   - `store()`: `DB::transaction()` → `ManualInvoiceService::create()` → langsung susul `PaymentService`-nya untuk payment → redirect `invoices.show` (PRG, sesuai konvensi).
3. **Modal lama di `customers/show.blade.php`** dihapus, tombol "Buat Tagihan Manual" diganti link ke `/invoices/create?customer_id=...`. Route `customers.invoices.manual` + `CustomerController::storeManualInvoice()` **dihapus** (bukan dibiarkan nyangkut sebagai kode mati).
4. **Halaman Tagihan (`/invoices` index)** — tambah tombol "Buat Tagihan" yang link ke `/invoices/create`.

### 4.3 Test yang wajib ada

- `/invoices/create` dari Detail Pelanggan (customer terkunci dari `?customer_id=`) — invoice + payment lunas terbit sekaligus, kategori & nominal sesuai input.
- `/invoices/create` dari Halaman Tagihan — search customer by CID & by Nama, submit menghasilkan invoice untuk customer yang benar (bukan tertukar hasil search lain).
- Kategori `lainnya` — wajib nama ketik bebas, tidak menampilkan dropdown subkategori.
- Kategori `jasa_layanan_internet` — nominal tetap dari server (`customer_services.monthly_price`), bukan dari input klien (regresi aturan lama).
- Payment gagal (nominal invalid) → invoice yang baru dibuat ikut rollback, tidak nyangkut sebagai invoice `belum_dibayar` yatim.
- Nominal dibayar < total invoice → invoice `sebagian`, bukan otomatis dipaksa lunas.
- Nominal dibayar > total invoice → kelebihan masuk `overpay_amount`/saldo pelanggan (reuse `PaymentService`, regresi bukan fitur baru).
- Master Kategori Pendapatan: tambah kategori baru (mis. `pindah_lokasi`) → langsung muncul di dropdown form create.
- Master Kategori Pendapatan: hapus kategori/sub yang masih dipakai ≥1 invoice → ditolak, pesan jelas.
- Master Kategori Pendapatan: hapus kategori/sub yang tidak dipakai → berhasil permanen.
- Modal lama & route `customers.invoices.manual` sudah tidak ada (regresi — pastikan tidak ada view/test lain yang masih mereferensikannya).
- POP scope: form create & search customer tunduk `applyUserScope()` (tidak bisa bikin tagihan untuk pelanggan di luar POP yang diizinkan).

### 4.4 Yang belum diputuskan / perlu dicek saat implementasi

1. Apakah komponen Blade dari rencana ADHOC-60 (`<x-invoice-line-rows>`) masih ada file-nya (sebagian "dihapus karena jelek") atau perlu ditulis ulang dari nol — cek dulu sebelum mulai coding, jangan asumsi.
2. Pola search customer di form create — pakai gaya `InvoiceController::index()` (`whereHas` langsung di server, submit form biasa) atau bikin endpoint typeahead AJAX terpisah (pola `customers.search-referral`)? Pilih yang paling konsisten dengan UX form lain di app ini.
3. Kalau `QRIS`/`KOLEKTOR`/`LAINNYA` ternyata dibutuhkan juga di form ini nanti (bukan cuma Cash/TF) — tidak perlu perubahan enum, cukup buka pilihan di form.

---

## 5. Dampak ke Modul Lain

- `InvoiceObserver::creating()` — pastikan invoice `insidental` dari jalur baru ini tunduk guard dedup yang sama (customer+type+billing_period+total_amount dalam 5 menit) tanpa false-positive untuk kasus wajar (dua tagihan insidental beda kejadian, nominal sama, di hari yang sama — mungkin perlu dicek datanya).
- Laporan (`InvoiceReportController`, `PaymentReportController`) — breakdown kategori pendapatan (`RevenueBreakdownService` dari rancangan ADHOC-60) belum ada; kalau mau laporan per-kategori jalan, itu pekerjaan tambahan di luar scope dokumen ini (dicatat sebagai follow-up, bukan bagian wajib rancangan Tagihan Manual).
- `docs/billing-pembayaran/` — begitu diimplementasi, update README/business-logic sesuai `docs/DEFINITION_OF_DONE.md`.
