# Rancangan: Master Rekening Bank, Nama Pengirim & Penyesuaian Status Tagihan (UI)

Status: **Selesai — diimplementasi 2026-09-23.** Dicatat sebagai **ADHOC-95** di `docs/TASKS.md`, di luar sprint aktif. Lihat [Catatan Implementasi](#catatan-implementasi-2026-09-23) di akhir dokumen untuk koreksi logic terhadap rancangan.
Tanggal: 2026-09-22

Tiga perubahan dari permintaan user (satu batch, modul Tagihan/Pembayaran):
1. [Master Rekening Bank → dropdown Transfer](#1-master-rekening-bank--dropdown-transfer-di-payment) *(sudah didesain sebelumnya, dokumen asal)*
2. [Kolom baru "Nama Pengirim" di Pembayaran](#2-kolom-baru-nama-pengirim-di-pembayaran)
3. [Sembunyikan badge status "Belum Dibayar" di list Tagihan (UI only)](#3-sembunyikan-badge-status-belum-dibayar-di-list-tagihan-ui-only)

## 1. Master Rekening Bank → Dropdown Transfer di Payment

### Latar Belakang

Sekarang field `bank_name` dan `account_number` pada form pembayaran metode
Transfer (`quick-payment-modal.blade.php`, dipicu dari `invoices.show`) adalah
input teks bebas — admin/kolektor ketik manual nama bank & nomor rekening
setiap kali mencatat pembayaran TF. Tidak ada satu sumber kebenaran untuk
daftar rekening perusahaan; rawan salah ketik, rawan rekening tak resmi
dipakai.

Tujuan: rekening (nomor + nama pemilik) diatur di Master. Saat mencatat
pembayaran TF, admin/kolektor tinggal **pilih dari dropdown**, bukan ketik
manual.

### Keputusan Desain (sudah dikonfirmasi user)

1. **Scope rekening: GLOBAL**, bukan per-POP/cabang. Satu daftar rekening
   company-wide, semua POP pakai daftar yang sama. Model `BankAccount` **tidak**
   pakai `HasPopScope`.
2. **Payment simpan FK + snapshot**, bukan FK saja. `payments.bank_account_id`
   (FK, nullable) ditambah, TAPI kolom `payments.bank_name` /
   `payments.account_number` yang sudah ada **tetap dipertahankan** — diisi
   otomatis dari master saat submit. Ini snapshot historis: kalau suatu saat
   rekening di master diedit namanya atau dinonaktifkan/dihapus, riwayat
   payment lama tidak berubah/rusak.

### Komponen yang Dibuat/Diubah

#### 1. Master baru: `bank_accounts`

Tabel baru, kolom:
- `id`
- `bank_name` — mis. BCA, BRI, Mandiri
- `account_number`
- `account_holder_name` — nama pemilik rekening (bisa beda dari nama usaha)
- `label` (nullable) — nama tampilan singkat opsional di dropdown, mis. "BCA Utama"
- `is_active` (boolean, default true) — soft-disable. **Tidak boleh hard delete**
  kalau sudah pernah dipakai payment (riwayat butuh FK-nya tetap valid) —
  gunakan pola `toggleStatus()` seperti `InternetPackageController`.
- timestamps

Model: `App\Models\BankAccount`. Tanpa trait `HasPopScope` (global, sesuai
keputusan #1).

#### 2. CRUD Master — ikuti pola `app/Http/Controllers/Master/*`

`App\Http\Controllers\Master\BankAccountController`, method:
`index()`, `create()`, `store()`, `edit()`, `update()`, `toggleStatus()` —
pola identik `InternetPackageController` (lihat referensi kode itu untuk
konvensi validasi/redirect/pesan).

Views: `resources/views/master/rekening/{index,create,edit}.blade.php`.

**Pola UI aksi** (aturan CLAUDE.md, 3 pola): input rekening = mutasi data
sederhana tapi tetap mutasi (nulis master baru + validasi server) →
**halaman create tersendiri**, bukan modal — supaya
`back()->withErrors()->withInput()` tidak menutup modal & menampilkan list
kosong tanpa pesan error kalau validasi gagal.

Route baru di grup master (`routes/web.php`, static-first): mis.
`master.rekening.index|create|store|edit|update|toggle-status`.

Permission baru lewat matrix (bukan hardcode) — feature code usulan:
`master_rekening` dengan action `view`/`manage` (ikut pola
`PermissionGeneratorService`, ditambahkan ke seeder feature yang relevan,
di-assign ke role yang berhak, minimal `owner`/`admin`).

#### 3. Migration: kolom baru di `payments`

- `bank_account_id` — nullable, FK ke `bank_accounts.id`, `nullOnDelete()`
  (kalau rekening dihapus — seharusnya jarang karena ada soft-disable — payment
  lama tidak ikut hilang/rusak, cuma link-nya jadi null, snapshot teks tetap ada).
- `bank_name`, `account_number` — **tetap ada, tidak dihapus**. Sekarang
  diisi otomatis dari `BankAccount` terpilih (bukan diketik manual).

`Payment::$fillable` tambah `bank_account_id`. `auditPayload()` di model
`Payment` ikut menambahkan field ini supaya audit log tetap lengkap.

Tambah relasi `Payment::bankAccount(): BelongsTo`.

#### 4. Enum `PaymentMethod`

Tidak berubah struktur. `requiresBankDetails()` tetap `TRANSFER` saja — cuma
sumber datanya yang berubah: dulu user ketik teks, sekarang **wajib pilih**
`bank_account_id` dari master.

#### 5. Service: `PaymentService::record()`

Tambah validasi/logic:
- `bank_account_id` wajib (`required_if:payment_method,transfer`,
  `exists:bank_accounts,id`) dan **wajib `is_active`** — rekening yang sudah
  dinonaktifkan tidak boleh dipakai transaksi baru (validasi ini idealnya di
  Service, bukan cuma `exists` rule, supaya pesan errornya jelas: "rekening ini
  sudah tidak aktif").
- Ambil row `BankAccount`, isi `bank_name` & `account_number` payment dari situ
  (snapshot), simpan juga `bank_account_id`-nya.

**Catatan scope**: dari investigasi kode saat ini, jalur pembayaran **kolektor**
(`CollectorPaymentService`, bulk pay di `collector-worksheet.show`,
`collector-pay-script.blade.php`) **tidak** punya opsi metode Transfer sama
sekali (kolektor cuma `cash`/`kolektor`). Rancangan ini **hanya menyentuh
jalur admin** (`PaymentController` + `quick-payment-modal.blade.php`). Kalau
nanti kolektor perlu opsi TF juga, itu task terpisah — jangan dicampur di sini
tanpa konfirmasi user.

#### 6. Controller: `PaymentController::store()`

Ganti aturan validasi:
```
'bank_name' => 'required_if:payment_method,transfer|nullable|string|max:100',
'account_number' => 'required_if:payment_method,transfer|nullable|string|max:50',
```
menjadi:
```
'bank_account_id' => 'required_if:payment_method,transfer|nullable|exists:bank_accounts,id',
```
Field `bank_name`/`account_number` tidak lagi diambil dari input request —
diisi Service dari data master.

Method yang menyiapkan data untuk `invoices.show`/modal (tempat
`quick-payment-modal` dirender) perlu ikut mengoper daftar
`BankAccount::where('is_active', true)->get()` ke view.

#### 7. View: `quick-payment-modal.blade.php`

Ganti 2 input teks (`#qp-bank-name`, `#qp-account-number`) →
1 `<select id="qp-bank-account" name="bank_account_id">`, opsi dari
`$bankAccounts` (rekening aktif saja).

Format tampilan tiap opsi, mis.:
`"{$bank_name} — {$account_number} (a.n. {$account_holder_name})"`.

JS yang perlu disesuaikan (semua di file yang sama):
- `qpToggleMethodFields()` — toggle show/hide field select, bukan 2 input.
- Validasi submit (baris ~660) — cek `bank_account_id` terisi, bukan cek 2
  teks kosong.
- Payload append (baris ~686-689) — kirim `bank_account_id`, bukan
  `bank_name`+`account_number`.
- Reset form (baris ~442-443) — reset value select ke default/kosong.

#### 8. Data lama — tidak ada migrasi mundur

Payment lama tetap punya `bank_name`/`account_number` teks bebas apa adanya,
`bank_account_id` null. Tidak perlu backfill/mapping ke master baru — riwayat
valid sebagaimana tercatat. Halaman `payments/show`, kwitansi, laporan, dll
tidak berubah cara baca kolom `bank_name`/`account_number` (masih field yang
sama, cuma sumber pengisiannya beda untuk data baru).

#### 9. Test yang perlu ditambah/diubah

- Feature test CRUD master rekening: create/update/toggle status + permission
  gate (role tanpa permission ditolak).
- Update test payment TF existing (`PaymentControllerTest` atau sejenis) —
  ganti input `bank_name`/`account_number` jadi `bank_account_id`, assert
  snapshot `bank_name`/`account_number` di row `payments` kebentuk otomatis
  sesuai data master yang dipilih.
- Test: submit payment TF dengan `bank_account_id` yang `is_active=false` →
  ditolak dengan pesan jelas.
- Test: rekening dinonaktifkan/dihapus setelah dipakai — payment lama yang
  sudah tercatat tidak berubah (snapshot tetap utuh, tidak ikut null/berubah).

### Dampak File (perkiraan scope)

**Baru:**
- Migration `create_bank_accounts_table` + `add_bank_account_id_to_payments_table`
- `app/Models/BankAccount.php` + factory
- `app/Http/Controllers/Master/BankAccountController.php`
- `resources/views/master/rekening/{index,create,edit}.blade.php`
- Route group master + permission seeder entry (feature `master_rekening`)
- Test CRUD master rekening

**Diubah:**
- `app/Models/Payment.php` (fillable, casts kalau perlu, relasi `bankAccount()`,
  `auditPayload()`)
- `app/Services/PaymentService.php` (validasi + snapshot dari master)
- `app/Http/Controllers/PaymentController.php` (validasi `store()`, data view)
- `resources/views/payments/partials/quick-payment-modal.blade.php` (select +
  JS)
- Test payment TF existing yang menabrak field lama

**Tidak disentuh (di luar scope kecuali user minta lain):**
- Jalur pembayaran kolektor (`CollectorPaymentService`,
  `collector-worksheet.show`, `collector-pay-script.blade.php`) — saat ini
  tidak punya opsi Transfer.
- Struktur besar enum `PaymentMethod`.

### Keputusan (dikunci user, 2026-09-22)

1. **Nama route/permission: `master.rekening`** — dipakai persis seperti usulan (konsisten dengan `master.paket`).
2. **Role via permission matrix, bukan hardcode.** Tidak dikunci ke role tertentu (owner/admin) di dokumen ini — cukup dibuat feature `master_rekening` lewat `PermissionGeneratorService` seperti biasa, assignment role final diatur di Role Matrix (`RolePermissionSeeder`/UI Role Matrix) saat implementasi, bisa diubah kapan saja tanpa ubah kode. Minimal `owner` dapat akses (via `*`), sisanya menyusul sesuai kebutuhan operasional nyata.

## 2. Kolom Baru "Nama Pengirim" di Pembayaran

### Latar Belakang

Permintaan user: tambahan kolom **Nama Pengirim** — nama yang dipakai
pelanggan saat mengirim uang (mis. transfer atas nama anak/pasangan pelanggan,
atau nama yang beda dari `account_holder_name` di master rekening tujuan/atau
dari `Customer::full_name`). Ini identitas pengirim FAKTUAL di transaksi
tersebut, bukan identitas rekening. Tanpa kolom ini, admin harus menebak dari
mutasi bank manual mana pembayaran ini dan pelanggan mana pemiliknya kalau
nama pengirim tidak sama dengan nama pelanggan.

### Keputusan Desain (dikonfirmasi user)

1. **Field baru, bukan reuse `note`.** `note` sudah dipakai bebas untuk
   alokasi/catatan lain (lihat `quick-payment-modal.blade.php` — format
   `"[Alokasi Pembayaran] - [Catatan]"`). Mencampur nama pengirim ke situ
   berarti field itu punya dua arti tak terstruktur — sulit dicari/dilaporkan.
2. **Opsional (`nullable`), tampil KHUSUS metode Transfer & Kolektor.**
   Dipasangkan ke `PaymentMethod::requiresBankDetails()`/`requiresCollector()`
   — dua metode yang uangnya lewat pihak ketiga (rekening bank/kolektor
   lapangan) sehingga nama pengirim FAKTUAL bisa beda dari nama pelanggan.
   Cash langsung di kantor dan QRIS **tidak** menampilkan field ini.
3. **Tidak tampil di kwitansi/struk cetak** (thermal, A4, kartu kolektor) —
   cukup tersimpan dan terlihat di halaman detail internal
   (`payments/show`), bukan dokumen yang diserahkan ke pelanggan.
4. **Bukan bagian snapshot rekening (`BankAccount`)** — ini atribut per
   transaksi (`payments`), bukan atribut master. Tidak berkaitan dengan
   rancangan #1 di atas selain sama-sama field baru di form pembayaran.

### Komponen yang Dibuat/Diubah

#### 1. Migration: kolom baru di `payments`

- `sender_name` — `string`, nullable, `max:150` (sejajar `bank_name`/lebar
  nama orang Indonesia yang umum di form pelanggan).

`Payment::$fillable` tambah `sender_name`. `auditPayload()` ikut
menambahkannya.

#### 2. Enum `PaymentMethod`

Tambah method baru `requiresSenderName(): bool` — `true` untuk `TRANSFER` dan
`KOLEKTOR`, `false` untuk `CASH`/`QRIS`/`LAINNYA`. Dipakai backend
(`PaymentController::store()`, kalau perlu wajib-required nanti) maupun
sebagai satu sumber kebenaran untuk toggle tampil field di kedua view (item
#4, #5).

#### 3. Controller: `PaymentController::store()`

Tambah rule validasi:
```
'sender_name' => 'nullable|string|max:150',
```
Tetap `nullable` (bukan `required_if`) — user tidak minta wajib, cuma field
tambahan. Diteruskan apa adanya ke `PaymentService::record()` → disimpan
langsung ke kolom `payments.sender_name`, tidak ada transformasi/derivasi.

#### 4. View: `payments/create.blade.php` (form penuh)

Tambah input teks "Nama Pengirim", tampil hanya saat `payment_method` yang
dipilih di dropdown = `transfer` atau `kolektor` (toggle JS, pola sama
dengan blok transfer di `quick-payment-modal.blade.php`). Ditempatkan
setelah **Metode Bayar**, sebelum **Nominal Diterima**. Label:
`Nama Pengirim (opsional)`, placeholder: `"Nama sesuai yang tercantum di
bukti transfer, jika berbeda dari nama pelanggan"`.

**Catatan**: halaman ini SAAT INI tidak punya field bank rekening apa pun
untuk metode Transfer meski `required_if:payment_method,transfer` sudah
berlaku di controller — gap ini sudah punya rancangan sendiri dari sebelum
sesi ini (bukan temuan baru task ini) dan ditutup lewat rancangan #1
(dropdown `bank_account_id`) yang menambahkan field rekening yang hilang ke
halaman ini juga. Field "Nama Pengirim" baru ditambahkan berdampingan
dengan itu, bukan pengganti perbaikan gap tersebut.

#### 5. View: `payments/partials/quick-payment-modal.blade.php`

Tambah `<input type="text" id="qp-sender-name" name="sender_name">`,
ditempatkan di dalam blok yang sudah kondisional per metode — gabung ke
`#qp-transfer-fields` (tampil saat Transfer) dan `#qp-collector-fields`
(tampil saat Kolektor), atau satu blok baru yang di-toggle bareng keduanya
di `qpToggleMethodFields()`. Payload JS (baris `payload.append(...)`) kirim
`sender_name` hanya saat `method === 'transfer' || method === 'kolektor'`,
sejajar pola pengiriman `bank_name`/`collected_by` yang sudah kondisional
per metode. Reset field ini disatukan ke blok reset form (`qp-bank-name.value
= ''`, dst).

#### 6. Tampilan hasil: `payments/show.blade.php`

`sender_name` ditampilkan di detail pembayaran (`payments/show`) saja, di
dekat field `bank_name`/kolektor — kalau kosong, baris ini disembunyikan
(`@if`). **Tidak** ditambahkan ke `ReceiptPresenter`/view kwitansi cetak
(thermal/A4/kartu kolektor) sesuai keputusan #3 di atas.

### Dampak File (perkiraan scope)

**Baru:**
- Migration `add_sender_name_to_payments_table`

**Diubah:**
- `app/Models/Payment.php` (fillable, `auditPayload()`)
- `app/Enums/PaymentMethod.php` (`requiresSenderName()`)
- `app/Http/Controllers/PaymentController.php` (rule validasi)
- `resources/views/payments/create.blade.php` (input baru + toggle JS
  per metode)
- `resources/views/payments/partials/quick-payment-modal.blade.php` (input +
  JS payload/reset, kondisional Transfer/Kolektor)
- `resources/views/payments/show.blade.php` (tampilkan kalau terisi)
- Test payment create (assert kolom tersimpan untuk Transfer/Kolektor,
  tidak muncul/tidak wajib untuk Cash/QRIS)

**Tidak disentuh:** `app/Services/Receipts/ReceiptPresenter.php` dan semua
view kwitansi/struk — sesuai keputusan #3, field ini tidak tampil di
dokumen cetak.

## 3. Sembunyikan Badge Status "Belum Dibayar" di List Tagihan (UI Only)

### Latar Belakang

Permintaan user: pada halaman **Daftar Tagihan** (`invoices/index.blade.php`),
badge status di kolom STATUS untuk baris berstatus `belum_dibayar` **tidak
tampil teks apa pun** — dikosongkan. Ini murni kosmetik: enum
`InvoiceStatus::BELUM_DIBAYAR`, logic status, filter dropdown, dan semua
perhitungan/laporan **tidak berubah sama sekali**. Hanya representasi visual
badge di baris tabel list ini yang disembunyikan untuk status tersebut.

### Konfirmasi Scope: Halaman "Tagihan Belum Lunas", Bukan Daftar Tagihan Umum

Route ini sudah ada dan pakai blade yang SAMA dengan Daftar Tagihan biasa —
`InvoiceController::belumLunas()` cuma memaksa `status_group=belum_lunas` lalu
delegasi ke `index()` (`app/Http/Controllers/InvoiceController.php:120-125`),
render tetap lewat `resources/views/invoices/index.blade.php`. Query di
`index()` (baris 73-74) mengambil dua status sekaligus untuk grup ini:
`belum_dibayar` DAN `sebagian`.

Maksud user: badge status disembunyikan **khusus saat halaman ini diakses
lewat rute/tab "Tagihan Belum Lunas"** (`$statusGroup === 'belum_lunas'`) —
bukan di Daftar Tagihan umum tanpa filter, dan bukan berarti mengubah label
enum. Karena kedua halaman berbagi satu file Blade, pembeda satu-satunya yang
tersedia di view adalah variabel `$statusGroup` yang sudah dioper `index()`.

### Keputusan Desain

1. **Tidak menyentuh enum.** `InvoiceStatus::BELUM_DIBAYAR->label()` dan
   `SEBAGIAN->label()` tetap seperti semula — dipakai di tempat lain (filter
   dropdown, laporan, notifikasi, halaman lain yang tak diminta berubah:
   `invoices/show.blade.php`, `customers/show.blade.php`,
   `reports/invoices/index.blade.php`, `verifications/admin.blade.php`,
   `customer-acquisitions/index.blade.php`). Mengubah label di enum akan
   bocor ke semua pemakai lain tanpa diminta.
2. **Badge disembunyikan TOTAL untuk SEMUA baris di halaman ini** (baik
   `belum_dibayar` maupun `sebagian`, dikonfirmasi user 2026-09-22) — bukan
   cuma satu status, karena satu-satunya pembeda yang diminta adalah
   "halaman Tagihan Belum Lunas", dan semua baris di halaman itu memang
   unpaid/parsial. **"Disembunyikan total"** = elemen `<span>` badge tidak
   dirender sama sekali untuk halaman ini (bukan pil kosong tanpa teks) —
   kolom STATUS di header tabel tetap ada (biar layout tak berubah), sel
   isinya kosong tanpa elemen apa pun.
3. **Daftar Tagihan umum (`status_group` kosong) TIDAK berubah** — badge
   tampil normal seperti sekarang, termasuk saat difilter manual via dropdown
   Status ke "Belum Dibayar"/"Sebagian" (itu beda dari tab "Tagihan Belum
   Lunas" — filter dropdown pakai param `status`, bukan `status_group`).
4. **Realtime patch (`INVOICE_STATUS_BADGE_CLASSES`, baris ~308-330) ikut
   disesuaikan** — kalau invoice di halaman ini berubah status lewat event
   broadcast/`payment-recorded`, JS yang mem-patch badge juga harus tahu
   halaman sedang dalam mode `belum_lunas` (mis. cek satu flag JS global
   `window.__invoiceStatusGroup` yang di-`@json`-render dari `$statusGroup`)
   dan tetap mengosongkan teksnya — bukan cuma render awal Blade saja yang
   berubah, kalau tidak badge kosong di initial load tapi "Belum
   Dibayar"/"Sebagian" muncul lagi begitu ada update realtime.

### Implementasi

```blade
{{-- invoices/index.blade.php, baris badge status --}}
@unless ($statusGroup === 'belum_lunas')
    <span ... id="invoice-status-badge-{{ $invoice->id }}" ...>
        {{ $invoice->invoice_status->label() }}
    </span>
@endunless
```

Elemen `<span>` **tidak dirender sama sekali** untuk halaman `belum_lunas` (§Keputusan Desain poin 2) — bukan dirender dengan teks kosong. Plus flag JS dan penyesuaian fungsi patch badge realtime (baris ~308-330): kalau `window.__invoiceStatusGroup === 'belum_lunas'`, fungsi patch **tidak menyuntikkan ulang** elemen badge saat event realtime masuk (skip total), supaya konsisten sama render awal yang juga tidak punya elemennya.

### Dampak File (perkiraan scope)

**Diubah:**
- `resources/views/invoices/index.blade.php` (badge Blade kondisional
  `$statusGroup` + JS realtime patch)
- Test yang menegaskan tampilan halaman Tagihan Belum Lunas (kalau ada test
  yang assert teks "Belum Dibayar"/"Sebagian" muncul di halaman
  `invoices.belum-lunas` — perlu disesuaikan; test untuk Daftar Tagihan
  umum/filter manual TIDAK berubah karena badge tetap tampil di sana).

### Keputusan (dikunci user, 2026-09-22)

1. Badge dikosongkan untuk **kedua** status (`belum_dibayar` DAN `sebagian`)
   di halaman Tagihan Belum Lunas — bukan cuma `belum_dibayar` saja.
2. Elemen `<span>` **disembunyikan total** (tidak dirender sama sekali),
   bukan pil kosong tanpa teks.

**Tidak ada lagi pertanyaan terbuka di bagian ini.**

## Catatan Implementasi (2026-09-23)

Dikerjakan sesuai rancangan, dengan koreksi berikut (temuan saat baca kode — rancangan asli akan meninggalkan celah):

1. **Jalur form bayar ada TIGA, bukan satu.** Rancangan cuma menyebut `quick-payment-modal.blade.php`. Ternyata input teks `bank_name`/`account_number` juga ada di **Modal Hub List Pelanggan** (`customers/partials/_quick_hub_modal.blade.php` + `_list_scripts.blade.php`) dan **halaman Catat Pembayaran** (`payments/create.blade.php`). Ketiganya POST ke endpoint yang sama (`invoices.payments.store`) — kalau cuma satu yang diganti, dua lainnya langsung gagal validasi `bank_account_id`. Ketiganya diganti dropdown.
2. **Daftar rekening dikirim lewat payload JSON, bukan `$bankAccounts` ke view.** Modal Bayar Cepat di-include di beberapa halaman (`invoices/index`, `customers/show`) dan sudah mengisi daftar kolektor dari fetch `invoices.show` (`available_collectors`). Rekening ikut pola yang sama: `available_bank_accounts` di JSON `InvoiceController::show()` dan `CustomerController` (payment-info Modal Hub). Satu sumber: `BankAccount::activeOptions()` (+ `displayName()` untuk teks opsi). Cuma `payments/create` (halaman Blade biasa) yang menerima `$bankAccounts` dari controller.
3. **Permission: `master_rekening.view|create|update`, tanpa `delete` & tanpa `manage`.** `manage` tidak ada di `ActionCode`; format permission `{feature}.{action}` membuat "`master.rekening`" mustahil jadi kode permission — `master.rekening.*` dipakai sebagai **nama route**, feature code-nya `master_rekening` (konsisten `master_distribusi`, `master_status_pelanggan`). Toggle aktif/nonaktif = `.update` (pola Master Alat Kerja). Feature ditanam `BankAccountFeatureSeeder` (dipanggil `DatabaseSeeder`); owner dapat via `*`, role lain via Role Matrix.
4. **`bank_name`/`account_number` dari request SENGAJA diabaikan** oleh `PaymentService` — snapshot selalu dari master. Tanpa ini klien bisa kirim `bank_account_id` valid + nama bank palsu dan snapshot jadi tak cocok dengan FK-nya.
5. **Guard duplikat & format di master**: unique `(bank_name, account_number)`; nomor rekening dinormalkan (spasi/strip/titik dibuang) dan wajib angka — `"123 456"` dan `"123456"` tak bisa jadi dua rekening.
6. **Nama Pengirim di-trim & di-null-kan untuk metode selain Transfer/Kolektor di Service** (bukan cuma disembunyikan di UI) — Modal Hub mengirim `FormData(form)` apa adanya, termasuk field tersembunyi.
7. **Badge Tagihan Belum Lunas**: flag JS `HIDE_INVOICE_STATUS_BADGE` (di-render dari `$statusGroup`), bukan `window.__invoiceStatusGroup` — cukup konstanta lokal script halaman itu.
8. **Irisan ADHOC-70 (Tagihan Manual)**: `InvoiceController::store()` ikut memanggil `PaymentService::record()` dengan metode Transfer — disesuaikan ke `bank_account_id` + `sender_name` oleh sesi ADHOC-70 (dikoordinasikan 2026-09-23).

**Di luar scope, dicatat:** Setoran Kas (`cash_deposits.bank_name`) masih teks bebas — domain lain (uang admin → bank), bukan pembayaran pelanggan. Kalau mau ikut pakai master rekening, task terpisah.

**Test:** `MasterRekeningBankTest`, `PaymentMethodTransferBankFieldsTest` (ditulis ulang: tolak tanpa rekening, snapshot + audit, tolak rekening nonaktif, snapshot tak berubah setelah edit/nonaktif, nama pengirim Transfer/Kolektor/Cash, payload JSON cuma rekening aktif), `InvoiceBelumLunasHidesStatusBadgeTest`; `PaymentInputTest` & `PaymentCollectedByNotCopiedFromCustomerTest` disesuaikan ke `bank_account_id`.
