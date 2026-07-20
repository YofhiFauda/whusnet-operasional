# Business Logic — RBAC

## 1. Tiga Dimensi Independen

| Dimensi | Pertanyaan | Sumber Data |
|---------|-----------|-------------|
| **Role** | Siapa Anda (profil kerja)? | `users.role_id` → `roles` |
| **Permission** | Apa yang boleh Anda lakukan? | `roles` ↔ `permissions` (via `role_permissions`) |
| **Scope** | Data POP mana yang boleh Anda lihat? | `user_role_scopes` + `user_role_scope_targets` |

**Aturan tegas:** dilarang bikin Role per-cabang (e.g. "NOC Ponorogo"). Kombinasikan Role generik (`NOC`) + Scope (`selected_pop` → POP Ponorogo) — bukan proliferasi Role.

## 2. Hierarki & Kewenangan Kelola Role

Aturan hidup di `Role::canBeManagedBy()` + `config('rbac.role_management_scope')`:

1. **Owner** selalu bisa kelola semua role (hardcoded, gak butuh entry config).
2. **Role Owner** cuma bisa dikelola oleh user ber-role Owner — role lain gak bisa sentuh permission/data Owner sama sekali.
3. Role lain: cek `config/rbac.php` → `role_management_scope`. Saat ini cuma ada 1 aturan: `admin` boleh kelola `teknisi` dan `helpdesk`. Role yang gak terdaftar di map ini **gak punya wewenang kelola role lain** (default deny).
4. **Bikin role baru**: cuma Owner (`Role::canBeCreatedBy()`) — role lain gak bisa nambah role baru sama sekali, walau dia admin sekalipun.
5. **Role sistem** (`is_system=true`, 9 role bawaan: Owner, Atasan, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin) — kode (`code`) gak bisa diubah siapapun, walau nama/deskripsi boleh.
6. **Role terproteksi** (`config('rbac.protected_roles')` — cuma `owner`) — gak bisa dihapus siapapun, termasuk Owner sendiri.
7. Role gak bisa dihapus kalau masih dipakai user (guard count di `RolePermissionController::destroy`).

## 3. Permission — Generated, Bukan Manual

Permission **gak diketik satu-satu** di seeder. Sumber kebenaran: `config/rbac.php` → `allowed_actions`, map `{feature_code} => [action_code, ...]`. `PermissionGeneratorService::generate()` loop map ini, cocokkan ke `features`+`actions` yang udah ada di DB, bikin row `permissions` dengan `code = "{feature}.{action}"`.

**Implikasi:** nambah permission baru = edit `config/rbac.php` + pastikan Feature/Action-nya ada di DB, lalu jalankan generator — bukan insert manual ke tabel `permissions`.

Contoh kombinasi granular yang udah ada: `customers.detail.devices` punya action `view_sensitive`/`update_sensitive` — pola ini dipakai juga di `fop_tasks.update_sensitive` (kontrol siapa boleh ubah kategori & prioritas tiket FOP, lihat [docs/fop-task](../fop-task/README.md)).

### 3.1 Langkah Nambah Permission Baru (Fitur Existing) — contoh nyata: `customers.detail.devices.retrieve`

Studi kasus: fitur "Ambil Alat" di List Putus Langganan (`CustomerController::retrieveDevice()`) awalnya numpang di permission generik `customers.update` — gak granular, siapapun yang bisa edit data pelanggan otomatis bisa Ambil Alat juga, padahal itu 2 kewenangan beda (edit data vs aksi fisik di lapangan). Dipisah jadi permission sendiri (2026-07-20):

1. **Action baru (kalau action code-nya belum ada)** — tambah case di `App\Enums\ActionCode` (mis. `RETRIEVE = 'retrieve'`), lalu daftarkan di `database/seeders/ActionSeeder.php` (`Action::updateOrCreate(['code' => ...], ['name' => ..., 'description' => ...])` — idempotent, aman dijalanin ulang kapan aja, TIDAK menghapus action lain).
2. **Daftarkan action ke feature target** di `config/rbac.php` → `allowed_actions['customers.detail.devices']`, tambah `ActionCode::RETRIEVE->value` ke array-nya. Feature `customers.detail.devices` sendiri udah ada (gak perlu bikin Feature baru — cek dulu `database/seeders/FeatureSeeder.php`, cuma bikin Feature baru kalau fiturnya beneran belum terdaftar).
3. **Label tampilan (opsional tapi disarankan)** — tambah entry di `config/rbac.php` → `permission_name_overrides['customers.detail.devices.retrieve'] = 'Ambil Alat Pelanggan Putus Langganan'`, biar gak nongol sebagai label generik "Retrieve" di halaman matrix role.
4. **Generate permission row** — jalankan `php artisan db:seed --class=ActionSeeder` (kalau ada action baru) lalu `php artisan rbac:generate-permissions`. Dua-duanya **cuma nambah row baru** (`updateOrCreate`/skip-if-exists) — aman, gak nyentuh permission/action lain yang udah ada.
5. **Assign ke role** — ini satu-satunya langkah yang butuh hati-hati. `database/seeders/RolePermissionSeeder.php` daftarin permission per role dalam array PHP hardcoded, tapi eksekusinya `$role->permissions()->sync($permissionIds)` — **FULL SYNC, ngoverwrite semua permission role itu** ke persis isi array di kode, termasuk kalau ada perubahan manual lewat UI matrix role yang belum sempet disinkron balik ke seeder. **Jangan langsung `php artisan db:seed --class=RolePermissionSeeder`** di environment yang permission role-nya udah dikustom lewat UI — assign permission baru ke role tertentu secara **additive** aja:
   ```php
   $perm = Permission::where('code', 'customers.detail.devices.retrieve')->first();
   foreach (['admin', 'noc', 'fop', 'pop_admin'] as $code) {
       Role::where('code', $code)->first()?->permissions()->syncWithoutDetaching([$perm->id]);
   }
   ```
   Tetap update array di `RolePermissionSeeder.php` juga (biar seeder tetep jadi source-of-truth yang akurat buat environment baru/fresh install), tapi eksekusi assignment ke environment yang udah jalan pakai `syncWithoutDetaching` manual di atas, bukan re-run seeder penuh.
6. **Clear cache permission** — `app(EffectiveAccessService::class)->clearCache($user)` per user yang role-nya diubah (§4), atau tunggu TTL 1 jam abis sendiri.
7. **Pasang guard-nya** — route (`Route::middleware('permission:customers.detail.devices.retrieve')`), controller (`abort_unless(auth()->user()->hasPermission('customers.detail.devices.retrieve'), 403)`), dan view (`@if(auth()->user()->hasPermission('customers.detail.devices.retrieve'))`) — TIGA-tiganya, satu aja kelewat = ada celah (guard client-side doang, atau guard server doang tapi UI tetep nampilin tombol ke yang gak berhak).

Role yang di-assign buat `customers.detail.devices.retrieve`: `admin`, `noc`, `fop` (eksplisit) + `pop_admin` (otomatis lewat wildcard `customers.detail.*`, gak perlu eksplisit) + `owner` (bypass `*`, gak lewat DB). `teknisi`/`sales`/`atasan`/`helpdesk` sengaja TIDAK dapet — Ambil Alat itu keputusan admin/FOP/NOC, bukan kerjaan teknisi lapangan atau sales.

## 4. Precedence Cek Permission (`EffectiveAccessService::userCan()`)

Urutan match, berhenti di match pertama:

1. **Exact match** — `code` persis ada di daftar permission user.
2. **Global wildcard `*`** — khusus Owner (`getPermissions()` return `['*']` kalau `role->code === 'owner'`, gak query DB sama sekali).
3. **Feature wildcard** — user punya `customers.*` → lolos semua `customers.<apapun>`. Termasuk nested: `customers.import.*` meng-cover `customers.import.view`.
4. **Prefix match** — user punya permission apapun yang diawali `{code}.` — dipakai buat cek "apakah user punya akses ke fitur ini secara umum" tanpa action spesifik.

**Cache:** hasil `getPermissions()` disimpan 1 jam (`Cache::remember`, key `user.{id}.permissions`). Ubah permission role → wajib `EffectiveAccessService::clearCache($user)` per user (`RoleManagementService::syncPermissions()` udah handle ini otomatis tiap kali matrix disimpan).

## 5. Full-Access Bypass

- `User::hasFullAccess()` ≡ `hasPermission('*')` — cuma true untuk Owner.
- `CheckPermission` middleware cek bypass ini duluan sebelum evaluasi permission string apapun.
- `HasPopScope::scopeApplyUserScope()` juga bypass filter POP untuk role `owner` DAN `atasan` (Atasan dapat previlege lihat-semua-data walau bukan permission `*` penuh — dia masih bisa dibatasi permission fitur, cuma gak dibatasi POP).

## 6. Scope POP — 3 Tipe

| Tipe | Perilaku |
|------|----------|
| `all_pop` | Query gak difilter — user lihat semua POP. |
| `selected_pop` | Filter ke POP yang jadi target (`user_role_scope_targets`) **+ semua sub-POP di bawahnya** (resolve tree turun rekursif lewat `parent_id`). |
| `pop_tree` | Legacy — perilaku identik `selected_pop`, dipertahankan buat data lama yang belum di-migrasi, gak lagi jadi pilihan di form. |

**Kunci penting:** `getAllowedPopIds()` return array kosong `[]` untuk 2 kasus yang beda maknanya — (a) `ALL_POP` (memang gak perlu filter) dan (b) user yang belum punya scope sama sekali. `hasAllPopAccess()` dipakai buat membedakan keduanya secara eksplisit — jangan pernah infer "akses semua POP" dari `empty(getAllowedPopIds())` doang, karena kasus (b) harus deny-by-default bukan allow-all.

**Cache:** `scope_type` dan `allowed_pop_ids` di-cache 1 jam per user, key `user.{id}.scope_type` / `user.{id}.allowed_pop_ids`. Ubah scope user → wajib clear cache (ditangani `UserScopeManagementService`).

**1 role aktif per user:** `user_role_scopes` unique di (`user_id`, `role_id`) — desain saat ini user cuma punya 1 baris scope aktif (role utama dia), bukan multi-role bertumpuk.

## 7. Workflow Transition Permission

Selain permission fitur biasa, ada lapis khusus buat transisi status workflow pelanggan/task (`workflow_transition_permissions`: `from_status` → `to_status` → `permission_name`, di-assign ke Role lewat `role_workflow_transition`). Dipakai buat kontrol siapa yang boleh trigger transisi status tertentu (e.g. FOP approve survey → verifikasi lapangan) — independen dari CRUD permission biasa, karena satu Role bisa punya akses CRUD fitur tapi belum tentu berhak approve transisi status kritikal.

## 8. Audit

Semua perubahan RBAC diaudit:
- `Role`, `Permission`, `Feature`, `Action` — trait `RecordsAuditLogs`, event `created`/`updated`/`deleted`.
- `UserRoleScope` — sama, full CRUD audit (perubahan scope POP user tercatat).
- Sync permission matrix role — audit custom di `RoleManagementService::syncPermissions()`, module `Role Management`, action `sync_permissions`, simpan old vs new permission ID array.
