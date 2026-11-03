# Rancangan — UI Reconcile Paket & Harga dari Excel

> Status: **BELUM DIKERJAKAN** — baru tahap desain disetujui user, implementasi belum dimulai.
> Tanggal: 2026-11-03. Sumber: diskusi brainstorming user + analisa kode `app:customer-package-reconcile-excel`.

---

## 1. Latar Belakang

`app:customer-package-reconcile-excel` (`app/Console/Commands/Operational/CustomerPackageReconcileFromExcelCommand.php`)
cuma bisa dijalankan lewat terminal. Makin banyak cabang yang migrasi, makin
sering command ini dipakai berulang dengan opsi panjang (`--pop-name`,
`--config=path/ke/json`) — menyusahkan kalau harus bolak-balik ke terminal,
apalagi kalau yang menjalankan nanti bukan developer tapi staf operasional.

Ide user: command yang sifatnya **Operational** (dipakai berulang, bukan
sekali pakai) dipindah jadi halaman web, dicontoh dari pola Import Pelanggan
by Excel yang sudah ada (`CustomerController::importForm/validateImport/
confirmImport`, `routes/web.php:302-308`).

v1 scope: **cuma `app:customer-package-reconcile-excel`**. Pola generik buat
command Operational lain (ADHOC lanjutan) ditunda — belum dibahas.

---

## 2. Keputusan (sudah disetujui user)

| # | Keputusan | Sumber |
|---|---|---|
| K1 | Akses: **Owner & Admin** untuk sekarang, lewat permission matrix RBAC (feature baru `customers.package_reconcile`, action `view`/`apply`) — **bukan** hardcode role. Owner otomatis full access (`*`); Admin wajib di-grant eksplisit di Role Matrix. | user, 2026-11-03 |
| K2 | Scope POP: **all_pop** untuk sekarang. Dropdown Cabang tetap lewat `EffectiveAccessService` (tidak di-bypass hardcode) — otomatis jadi semua POP karena Owner/Admin defaultnya `all_pop`; kalau nanti role lain dikasih akses, otomatis terbatas sendiri tanpa ubah kode. | user, 2026-11-03 |
| K3 | Flow dry-run → apply: **Opsi B** — satu halaman, AJAX. Upload Excel → tombol "Pratinjau" (fetch, render tabel di halaman yang sama, tanpa reload) → tombol "Terapkan" muncul setelah preview sukses, masih di halaman yang sama. **Bukan** dua halaman/dua step. | user, 2026-11-03 |
| K4 | **Tidak ada audit log tambahan.** Fitur ini 100% internal (Owner/Admin saja) — gak perlu jejak audit terpisah seperti fitur customer-facing. Command CLI yang mendasarinya juga sudah pakai `updateQuietly()` (observer disengaja dimatikan), jadi perilaku ini **dipertahankan**, bukan ditambal. | user, 2026-11-03 |
| K5 | 4 config command (`name_aliases`, `skip_names`, `package_code_typo_fixes`, `manual_overrides`) **jadi form di UI**, bukan upload/paste JSON — tiap blok dapet deskripsi yang jelasin kegunaannya buat user yang gak familiar sama command-nya. | user, 2026-11-03 |

---

## 3. Kenapa Bukan Modal, Kenapa Bukan Dua Halaman

Sesuai konvensi aksi baru di CLAUDE.md ("3 pola, bukan asal pilih"): ini
**mutasi data** (nulis `customer_services`/`customers`/invoice) + validasi
server + input majemuk (file Excel + 4 blok config) → **halaman create
tersendiri**, bukan modal. Alasannya teknis: `back()->withErrors()` balik ke
*referer* — kalau formnya modal di atas List, gagal validasi nutup modal dan
nampilin List kosong tanpa pesan error yang nyantol.

"Satu halaman AJAX" (K3) tetap memenuhi "halaman create tersendiri" — bedanya
cuma preview & apply tidak bikin full page reload, pakai `fetch()` kayak
preview Ganti Paket yang sudah ada di Detail Pelanggan. Hasil akhir (apply)
tetap ditampilkan sebagai tabel di halaman yang sama (bukan redirect ke
halaman lain) — sesuai pengecualian PRG untuk "aksi list-oriented" (impor
massal), bukan "create/update satu record".

---

## 4. Referensi Pola Yang Dicontoh

Customer Import Excel (`routes/web.php:302-308`, `CustomerController.php`):

```
GET  /customers/import              -> importForm()        (halaman form)
POST /customers/import/validate     -> validateImport()     (baca file, return JSON parsed rows + validasi, STATELESS — gak nyimpen file sementara di server)
POST /customers/import/confirm      -> confirmImport()      (terima balik parsed rows dari browser, tulis beneran)
GET  /customers/import/history(/{batch}) -> laporan batch import
```

Poin penting yang dipertahankan: **stateless antara preview dan apply** —
server tidak menyimpan file upload sementara. `validate` membaca file,
memprosesnya, dan mengembalikan hasil olahan (bukan cuma pesan error) ke
browser; `confirm` menerima balik data itu dan menulis. Reconcile-excel
mengikuti pola yang sama, hanya tanpa halaman history/batch (K4 — gak perlu
jejak audit terpisah).

---

## 5. Desain

### 5.1 Service baru — satu sumber kebenaran buat CLI & UI

Logic yang sekarang nempel di `CustomerPackageReconcileFromExcelCommand::handle()`
(matching nama/alias/skip, diskriminator paket harga+speed, `applyCorrection()`,
koreksi invoice BULANAN stale) diekstrak ke:

```
app/Services/CustomerPackageReconcileService.php
    public function preview(string $excelPath, Pop $pop, array $config): array
    public function apply(string $excelPath, Pop $pop, array $config): array
```

`$config` shape (sama dengan JSON `--config` sekarang, supaya CLI tetap
kompatibel):

```php
[
    'name_aliases' => ['Nama Di Excel' => 'Nama Di Sistem'],
    'skip_names' => ['Nama Yang Dilewatin'],
    'package_code_typo_fixes' => ['KodeTypo' => 'KodeBenar'],
    'manual_overrides' => ['CID' => 'KodePaketBenar'],
]
```

`CustomerPackageReconcileFromExcelCommand` jadi wrapper tipis: load
`--config` JSON (kalau ada) → panggil Service → cetak `$this->table()`.
**Perilaku CLI harus identik** sebelum/sesudah refactor — `tests/Feature/
CustomerPackageReconcileFromExcelCommandTest.php` (17 test) jadi regression
gate, wajib hijau tanpa diubah assersinya.

### 5.2 RBAC

Feature baru `customers.package_reconcile`, action `view` + `apply`,
ditambahkan ke seeder features/actions yang sudah ada (ikut
`PermissionGeneratorService`, bukan hardcode). Setelah deploy wajib
`php artisan rbac:generate-permissions` — catat di `docs/RUNBOOK_COMMANDS.md`.

### 5.3 Routes (`routes/web.php`, static dulu, grup permission)

```php
Route::middleware('permission:customers.package_reconcile.view|customers.package_reconcile.apply')->group(function () {
    Route::get('/customers/package-reconcile', [CustomerPackageReconcileController::class, 'create'])
        ->name('customers.package-reconcile.create');
    Route::post('/customers/package-reconcile/preview', [CustomerPackageReconcileController::class, 'preview'])
        ->middleware('permission:customers.package_reconcile.view')
        ->name('customers.package-reconcile.preview');
    Route::post('/customers/package-reconcile/apply', [CustomerPackageReconcileController::class, 'apply'])
        ->middleware('permission:customers.package_reconcile.apply')
        ->name('customers.package-reconcile.apply');
});
```

### 5.4 Controller

`app/Http/Controllers/CustomerPackageReconcileController.php` — tipis,
delegasi penuh ke Service:

- `create()` — render form. Dropdown POP dari `EffectiveAccessService`
  (daftar POP yang di-allow user; Owner/Admin lihat semua karena scope
  `all_pop`). Dropdown Paket (buat blok `manual_overrides`) dari
  `InternetPackage` katalog asli — bukan hardcode.
- `preview(Request $request)` — ambil file upload + POP id + 4 array config
  dari form, rakit jadi `$config`, panggil `CustomerPackageReconcileService::preview()`,
  balikin JSON (changed/skipped/unchanged + detail baris).
- `apply(Request $request)` — sama rakitan, panggil `::apply()` di dalam
  `DB::transaction()` (sudah ada di Service), balikin JSON ringkasan hasil.

File Excel ikut dikirim ulang browser di request `apply` (bukan disimpan
server-side di antara dua panggilan) — konsisten dengan pola Customer Import
(§4), lebih simpel daripada kelola file sementara + cleanup-nya.

### 5.5 View & 4 blok config form

`resources/views/customers/package_reconcile/create.blade.php` — satu
halaman:

1. Dropdown POP, input file Excel.
2. 4 section **collapsible** (default collapsed — mayoritas run gak butuh
   ini), Alpine repeatable-row (pola yang sama dengan modal/section lain di
   project), tiap section ada kalimat deskripsi di atasnya:
   - **Alias Nama** — baris "Nama di Excel" ↔ "Nama di Sistem". *"Buat nama
     pelanggan yang ditulis beda format sama kolektor di Excel dibanding
     nama yang kesimpen di sistem."*
   - **Nama Dilewati** — list nama tunggal. *"Nama yang SENGAJA dilewati
     dari pencocokan otomatis — biasanya ambigu atau belum ditelusuri
     identitasnya."*
   - **Perbaikan Kode Paket Typo** — baris "Kode Salah" ↔ "Kode Benar".
     *"Kode paket di Excel yang salah ketik (misal Net136 seharusnya
     Net138), dibetulin sebelum dicocokkan ke katalog paket."*
   - **Koreksi Manual per Pelanggan** — baris CID (text) ↔ Paket (dropdown
     katalog). *"Koreksi manual pakai CID buat pelanggan yang namanya gak
     bisa dicocokkan otomatis dari Excel. Diproses jalur yang SAMA kayak
     baris Excel — TANPA prorate invoice (lihat alasan di docblock
     command)."*
3. Tombol **Pratinjau** — AJAX ke `.preview`, render tabel hasil (kolom sama
   kayak output CLI: Customer ID, Nama, Harga Lama, Harga Baru, Paket,
   Sumber) di bawah form, tanpa reload.
4. Setelah preview sukses, tombol **Terapkan** muncul — AJAX ke `.apply`
   (resubmit file + config + POP yang sama), render ringkasan hasil di
   tempat yang sama.

### 5.6 Test

`tests/Feature/CustomerPackageReconcileControllerTest.php` — baru:

- 403 tanpa permission `.view`/`.apply`.
- `preview()` return shape bener (changed/skipped/unchanged + detail).
- `apply()` nulis identik dengan command (bandingkan hasil DB).
- 4 blok config form terbaca bener jadi `$config` array yang dikirim ke
  Service (termasuk kasus kosong — semua blok optional).

---

## 6. Urutan Kerja

1. Ekstrak Service dari Command (§5.1) — **paling berisiko**, jalankan
   `CustomerPackageReconcileFromExcelCommandTest` sampai hijau tanpa ubah
   assersi sebelum lanjut.
2. RBAC: tambah feature/action, migrasi seeder, `rbac:generate-permissions`.
3. Routes + Controller.
4. View + JS (Alpine repeatable row + fetch).
5. Test controller baru.
6. Pint + verifikasi manual browser (upload Excel Sambit yang sudah ada,
   preview, terapkan, cek DB) + tambah link menu di sidebar.

---

## 7. Risiko

- Refactor Command→Service bisa diam-diam mengubah perilaku matching —
  makanya test CLI lama adalah regression gate wajib hijau duluan.
- Upload Excel sync di request HTTP — oke buat ukuran per-cabang sekarang
  (puluhan–ratusan baris berdasar kasus Sambit). Migrasi ribuan baris
  sekaligus mungkin butuh job queue — **di luar scope v1**, dicatat di sini
  biar gak lupa kalau kejadian.
- Dropdown Paket di blok "Koreksi Manual" harus baca katalog `InternetPackage`
  asli (bukan daftar statis) — biar gak ada kode paket yang gak eksis bisa
  dipilih dari UI.

---

## 8. Yang Belum Dibahas (di luar scope v1, jangan dikerjakan tanpa keputusan baru)

- Pola generik buat command Operational lain (`billing:apply-balance`,
  `billing:backfill-activation-date`, dst) jadi UI — ide awalnya muncul dari
  sini, tapi user sengaja mempersempit v1 ke reconcile-excel saja.
- POP scope selain `all_pop` (kalau nanti `pop_admin` dikasih akses fitur
  ini).
- Riwayat/jejak audit reconcile lewat UI (K4 — sengaja ditunda, bukan lupa).
