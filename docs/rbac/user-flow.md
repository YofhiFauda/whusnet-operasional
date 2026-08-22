# User Flow — RBAC

Aktor: **Owner** (kewenangan penuh), **Admin** (kewenangan terbatas sesuai `role_management_scope`).

## 1. Owner kelola Role

### Lihat daftar role (`/roles`)

1. Buka `/roles` → tabel semua role + jumlah permission & user per role.
2. Klik role → masuk ke Permission Matrix (`/roles/{role}/matrix`), kecuali role Owner (redirect + info "akses penuh, gak bisa diubah").

### Bikin role baru

1. Cuma Owner yang lihat tombol/form ini (`Role::canBeCreatedBy()`).
2. Isi nama, kode (`lowercase_underscore`, unique), deskripsi → submit.
3. Role baru otomatis `is_system=false` — kode-nya BOLEH diubah nanti (beda dari role bawaan sistem).

### Edit role (nama/kode/deskripsi)

1. Guard: `canBeManagedBy()` — Owner bebas, Admin cuma role di scope-nya (`teknisi`, `helpdesk`).
2. Kalau role `is_system=true` dan kode mau diubah → ditolak ("Kode role sistem tidak boleh diubah").

### Atur Permission Matrix

1. Buka `/roles/{role}/matrix` → tampil pohon Feature (root → sub → mini), tiap baris punya checkbox per Action yang valid buat feature itu (sesuai `config('rbac.allowed_actions')`).
2. Centang/uncentang kombinasi Feature×Action → submit.
3. Sistem replace total permission role itu (bukan tambah incremental) + otomatis clear cache semua user yang pakai role ini, jadi perubahan langsung berlaku tanpa perlu user logout.

### Hapus role

1. Guard: `canBeManagedBy()` + role gak boleh ada di `protected_roles` (`owner`) + gak ada user yang masih pakai role itu.
2. Kalau masih ada user terpasang → ditolak, muncul jumlah user yang harus dipindah dulu.

## 2. Owner/Admin kelola User & Scope

### Buat/edit user + assign Role

1. Form user (`/users/create` atau `/users/{user}/edit`) — pilih Role dari dropdown (cuma role yang boleh di-assign sesuai kewenangan pembuat).
2. Simpan → `UserScopeManagementService` juga proses assignment scope (kalau disertakan di form yang sama).

### Assign Scope POP (`/users/{user}/pops`)

1. Pilih tipe scope:
   - **Seluruh POP** (`all_pop`) — user lihat semua data tanpa filter wilayah.
   - **Cabang POP** (`selected_pop`) — pilih 1+ POP cabang dari tree; otomatis mencakup semua sub-POP/distribusi di bawah cabang yang dipilih.
2. Submit → `UserRoleScope` + `UserRoleScopeTarget` disimpan, cache akses user itu dibersihkan otomatis.

### Preview Access

1. Sebelum simpan, admin bisa klik "Preview Akses" (`POST /users/preview-access`) — lihat simulasi: fitur apa aja yang bakal kebuka & data POP mana yang bakal kelihatan, tanpa commit perubahan dulu.

## 3. Cek akses dari sisi developer/kode

### Guard di Controller/Route

```php
// Route middleware
Route::middleware('permission:invoices.create')->group(...);
Route::middleware('permission:roles.view|roles.update')->group(...); // OR logic

// Manual di controller
if (!auth()->user()->hasPermission('fop_tasks.update_sensitive')) { ... }
```

### Guard di Query (POP scope)

```php
// Model pakai trait HasPopScope
Invoice::query()->applyUserScope()->get(); // otomatis filter POP sesuai user login
Invoice::query()->applyUserScope($otherUser)->get(); // scope user spesifik
```

### Guard di Policy

```php
$this->authorize('viewAll', Task::class); // dipakai FopDashboardController, lihat docs/fop-task
```

## 4. Tambah Permission Baru (alur developer)

1. Tambah entry di `config/rbac.php` → `allowed_actions`: `'feature_code' => [ActionCode::VIEW->value, ...]`.
2. Pastikan `Feature` (kode itu) & `Action` (kode-kode itu) sudah ada di DB — kalau belum, tambah dulu lewat seeder/migrasi data.
3. Jalankan proses generate (`PermissionGeneratorService::generate()`, biasanya dipanggil dari command/seeder) — permission baru otomatis muncul di Permission Matrix, siap dicentang Owner/Admin.
4. **Jangan** insert manual ke tabel `permissions` — bakal ketimpa/gak konsisten waktu generator jalan ulang.

## 5. Customer List Pages — Role-specific Access (2026-07-28)

### Admin / NOC / FOP / POP Admin / Atasan
- Akses **List Data Pelanggan** (`/customers`, status Aktif/Isolir/Semua) via permission `customers.view`
- Akses **List Pelanggan Putus** (`/customers/terminated`) via `customers.terminated.view`
- Akses **List Pelanggan Gagal** (`/customers/failed`) via `customers.failed.view`
- Akses **Detail Pelanggan** (`/customers/{id}`, semua tab: identitas/alamat/paket/billing/dokumen/riwayat) via `customers.detail.view`
- Sidebar item semua halaman di atas ditampilkan sesuai permission yang dimiliki

### Teknisi
- **TIDAK punya** `customers.view` → List Data Pelanggan disembunyikan sidebar + URL return 403
- **TIDAK punya** `customers.terminated.view` → List Pelanggan Putus disembunyikan sidebar + URL return 403
- **TIDAK punya** `customers.failed.view` → List Pelanggan Gagal disembunyikan sidebar + URL return 403
- **TIDAK punya** `customers.detail.view` → Detail Pelanggan disembunyikan sidebar + URL return 403
- **PUNYA** `customers.detail.devices.view` + `customers.detail.installation.view` → Akses **Perangkat & Pemasangan** (`/customers/{id}/perangkat-pemasangan`) — halaman fieldwork khusus teknisi, HANYA buat isi/lihat data teknis device & installation, bukan data identitas/billing/dokumen

**Note:** Queue halaman Survey & Verif (di tab lain, bukan bagian customer list/detail) tetap diakses teknisi via `customers.detail.survey.view` / `customers.detail.installation.view` — itu queue task, bukan data pelanggan umum.

### Sales
- Akses **List Data Pelanggan** via `customers.view`
- Akses **Detail Pelanggan** via `customers.detail.view` (buat view identitas/alamat pelanggan yang bersangkutan)
- **TIDAK punya** `customers.terminated.view` / `customers.failed.view` — list putus/gagal gak relevan buat sales
- **TIDAK punya** `customers.detail.devices.view` / `customers.detail.installation.view` — data teknis gak perlu sales lihat
- **PUNYA** `customers.registration.skip_survey` (2026-08-21) → di form Registrasi (`customers.create`) muncul checkbox **"Skip Survey"**; user role lain gak lihat checkbox ini sama sekali. Dicentang → lewati tahap survey teknisi, input data survey (ODP, koordinat, foto) langsung, pelanggan lompat ke `waiting_acc`. Detail: `docs/customer-lifecycle/business-logic.md` § Skip Survey.

### Helpdesk
- Akses **List Data Pelanggan** via `customers.view`
- Akses **Detail Pelanggan** via `customers.detail.view`
- **TIDAK punya** `customers.terminated.view` / `customers.failed.view` — cuma kelola pelanggan aktif/dalam survey/pemasangan
- **TIDAK punya** `customers.detail.devices.view` / `customers.detail.installation.view` — data teknis adalah kewenangan teknisi/admin

## Guard / Permission Ringkas

| Aksi | Siapa boleh |
|------|-------------|
| Lihat/kelola Role, Permission Matrix | `roles.view`/`roles.update` DAN `canBeManagedBy()` lolos |
| Bikin Role baru | Owner saja |
| Hapus Role | `canBeManagedBy()` lolos + bukan protected role + gak dipakai user |
| Ubah kode Role sistem | Tidak ada — permanen terkunci |
| Kelola User & Scope | `users.create`/`users.update` |
| Bypass semua permission & scope POP | Owner saja (`hasPermission('*')`) |
| Bypass scope POP saja (bukan permission) | Owner + Atasan |
| Lihat List Pelanggan | `customers.view` |
| Lihat List Pelanggan Putus | `customers.terminated.view` |
| Lihat List Pelanggan Gagal | `customers.failed.view` |
| Lihat Detail Pelanggan (identitas/billing/dokumen) | `customers.detail.view` |
| Isi data Perangkat & Pemasangan (fieldwork page) | `customers.detail.devices.view` OR `customers.detail.installation.view` |
| Teknisi kerja lapangan tanpa lihat data pelanggan umum | Punya `customers.detail.devices.*` + `customers.detail.installation.*`, TANPA `customers.view`/`customers.detail.view` |
| Lewati tahap survey saat Registrasi (Skip Survey) | `customers.registration.skip_survey` (default Sales) |
