# Rancangan: Hierarki & Pewarisan Permission Role Management

Status: **Analisa awal, belum diimplementasi.**

## Latar Belakang

Kebutuhan: role tertentu boleh menambah user + atur role & permission, tapi dibatasi
hanya untuk role di bawahnya. Contoh skema:

- **Owner**: kelola user, role, permission — semua role.
- **NOC**: kelola user, role & permission — hanya untuk `teknisi` dan `helpdesk`.
  Permission yang diwariskan ke teknisi/helpdesk dibatasi oleh permission yang NOC
  sendiri punya.
- **FOP**: kelola user, role & permission — hanya untuk `teknisi`. Sama, dibatasi
  permission milik FOP sendiri.

## Kondisi Existing (sudah ada, bukan mulai dari nol)

| Komponen | File | Fungsi |
|---|---|---|
| Scope role management | `config/rbac.php` → `role_management_scope` | `admin/noc → [teknisi, helpdesk]`, `fop → [teknisi]` |
| Gate kelola role | `Role::canBeManagedBy()` | Owner selalu bisa semua; role `owner` cuma bisa diubah owner; role lain cek scope config |
| Assignable role saat create/edit user | `Role::assignableRolesFor()` | Filter role yang boleh di-assign sesuai scope |
| Create role baru | `Role::canBeCreatedBy()` | Cuma Owner boleh bikin role baru |
| Protected role (tak bisa dihapus) | `config/rbac.php` → `protected_roles` | Saat ini hanya `['owner']` |
| Enforce di controller | `RolePermissionController@update` | Panggil `canBeManagedBy` sebelum `syncPermissions` |
| Effective access user | `App\Services\EffectiveAccessService` | Hitung permission efektif user (role + kemungkinan override personal via `UserRoleScope`) |

## Gap 1: Cap Pewarisan Permission

`RolePermissionController@update` → `RoleManagementService::syncPermissions()` menerima
daftar permission dari form matrix **tanpa validasi subset**. Artinya NOC/FOP secara
teknis bisa memberi role di bawahnya (teknisi/helpdesk) permission yang NOC/FOP sendiri
tidak punya. Ini melanggar prinsip "pewarisan" (inheritance cap) yang diminta.

## Gap 2: Scope Masih Static (requirement baru)

`role_management_scope` sekarang hidup di `config/rbac.php` — array PHP hardcode.
Konsekuensi:

- Ubah siapa-kelola-siapa = ubah file config + deploy/restart (`config:cache` clear).
  Tidak bisa di-adjust langsung dari UI oleh Owner saat ada kebutuhan baru
  (misal nanti nambah role baru, atau geser scope NOC juga megang `fop`).
  User minta ini **dinamis** — bisa disesuaikan tanpa sentuh kode.

**Opsi arah dinamis:**

- **A. Tabel `role_management_scopes`** (`manager_role_id`, `managed_role_id`),
  dikelola lewat halaman admin (Owner-only). `Role::canBeManagedBy()`,
  `assignableRolesFor()` baca dari DB (dengan cache) bukan `config()`.
  Migrasi seed data awal = isi existing config sebagai default row.
- **B. Kolom self-referencing di tabel `roles`** (mis. `parent_role_id` / level
  hierarki) — lebih kaku (1 parent), tapi lebih simpel kalau pola sebenarnya
  cuma pohon linear (Owner → NOC/FOP → Teknisi/Helpdesk), bukan graph bebas.
- **C. Hybrid**: tabel scope (opsi A) + tetap ada `config/rbac.php` sebagai
  fallback/default kalau tabel kosong (aman saat migrasi awal).

Opsi A/C lebih pas kalau pola relasinya bisa many-to-many bebas (role X bisa
punya lebih dari satu "atasan" pengelola, atau di masa depan role baru masuk
tanpa pola pohon rapi). Opsi B lebih pas kalau strukturnya emang selalu pohon.

## Jawaban User (Gap 1 & Gap 2) — Konfirmasi Pemahaman

**Gap 1 — cap pewarisan permission (dikonfirmasi user):**
Ceiling pewarisan = permission **role granter itu sendiri**. Contoh: FOP punya akses
fitur A, B, C, D di matrix role & permission miliknya sendiri → FOP hanya boleh
wariskan (assign) permission A/B/C/D itu ke Teknisi, tidak boleh lebih. Batas ini
otomatis mengikuti permission matrix FOP yang sudah ada — tidak perlu setting baru
terpisah untuk "apa yang boleh FOP wariskan", cukup validasi subset terhadap
permission matrix milik role granter.

**Gap 2 — scope dinamis (dikonfirmasi user):** dipilih **Opsi A** (tabel
`role_management_scopes`, many-to-many, dikelola Owner lewat UI dropdown dari
list role yang sudah ada). Detail perilaku yang diinginkan:

1. Owner mengatur, per role manager, role apa saja yang boleh dia create-user-as
   dan kelola permission-nya — lewat dropdown (bukan edit config/kode).
   Contoh: Owner set FOP → hanya boleh kelola `teknisi`.
2. **Role tidak boleh kelola dirinya sendiri** — FOP tidak bisa menambah FOP baru,
   tidak bisa mengubah permission matrix FOP. Ini harus jadi **hard rule di kode**
   (safety net), bukan sekadar konsekuensi dari isi tabel scope — supaya Owner pun
   tidak bisa keliru men-set self-loop yang membuka eskalasi privilese.
3. Fitur/permission yang boleh diwariskan FOP ke Teknisi otomatis dibatasi oleh
   permission matrix FOP sendiri (mekanisme Gap 1) — bukan pengaturan terpisah.
4. Permission matrix milik FOP sendiri tetap diatur oleh siapa pun yang punya scope
   mengelola FOP (biasanya Owner) — bukan oleh FOP sendiri (lihat poin 2).

Jadi ada 2 dimensi independen yang saling melengkapi:
- **Tabel scope (baru, dinamis)** → siapa boleh kelola role apa (create user +
  buka halaman matrix role itu).
- **Permission matrix (sudah ada)** → isi hak akses tiap role, otomatis jadi
  plafon/ceiling warisan ke role yang ada di scope-nya.

## Poin Teknis yang Masih Perlu Diputuskan

1. **Struktur tabel**: `role_management_scopes(manager_role_id, managed_role_id)`
   many-to-many — cukup, atau perlu kolom tambahan (mis. `created_by`, catatan)?
2. **UI pengaturan scope**: halaman terpisah ("Hierarki Role") atau nempel di
   halaman edit role existing (`roles.matrix`/`roles.index`) sebagai section baru?
3. **Siapa boleh ubah tabel scope itu sendiri**: Owner-only (rekomendasi, karena ini
   "siapa berkuasa atas siapa" — sensitif), atau bisa didelegasikan ke role lain?
4. **UX penolakan cap pewarisan**: checkbox permission yang granter sendiri tidak
   punya di-disable langsung di view `roles.matrix` + backend tetap validasi ulang
   (defense-in-depth) — setuju pola ini?
5. **Scope aksi `roles` selain permission**: create role baru tetap Owner-only
   (`canBeCreatedBy`, sudah ada). Delete role — apakah manager role (mis. Owner
   mengelola FOP) boleh hapus role yang ada di scope-nya, atau delete role tetap
   Owner-only juga?
6. **Protected roles list**: masih static di config — ikut dipindah ke DB (bisa
   diatur Owner), atau tetap hardcode karena jarang berubah? Apakah `admin` perlu
   ditambahkan ke protected supaya tidak ada skenario semua admin terhapus?
7. **Migrasi data awal**: isi `role_management_scopes` dari `config('rbac.role_management_scope')`
   existing (admin/noc→teknisi,helpdesk; fop→teknisi) sebagai seed awal — lalu
   config lama dihapus total (bukan fallback ganda), setuju?

## Next Step

Setelah poin teknis di atas dijawab/dikonfirmasi user → susun rencana implementasi
teknis detail (migration, model, service, controller, view) — baru masuk tahap
coding, belum sekarang.

# JAWAB
### 1. Gap 1 : sudah ssaya tebak ini nanti akan salah pengartian yang seharusnya itu adalah contoh FOP mempunyai akses ke fitur A,b,c dan D maka FOP tersebut hanya bisa mewarisi teknisi untuk fitu a,b,c dan d tersebut dan tidak boleh lebih, karena fop sebelumnya sudah di batasi oleh RBAC di matrix role & permission

### 2. Gap 2 = saya kurang faham dengan penjelasan anda tapi yang saya inginkan itu seperti ini contoh Owner ingin membatasi FOP yang hanya bisa menambahkan role teknisi (sebelumnya sudah ada list role jadi tinggal dropdown) dan owner juga membatasi apa saja yang hanya boleh di lakukan dan apa yang boleh di akses dari FOP tersebut. Jadi kedepannya FOP itu hanya bisa menambahkan pengguna dengan role teknisi ddengan fitur yang FOP punyai sendiri dan FOP bisa mengatur juga bahwa teknisi nanti bisa akses atau membatasi apa yang di lakukan oleh teknisi. dan Owner juga bisa mengatur FOP tersebut tidak bisa menambahkan role FOP lainnya dan juga tidak bisa mengubah permission FOP 


## Poin Teknis — Final (dikonfirmasi user)

Prinsip menyeluruh yang ditekankan user: **semua harus dinamis, tidak boleh ada yang
hardcode/statis** (config array, role-code check langsung, dll) — kecuali default
data awal (seeder) yang tetap bisa diubah lewat UI setelahnya.

1. **Struktur tabel**: `role_management_scopes` — kolom `manager_role_id`,
   `managed_role_id`, `created_by`, `note` (nullable), timestamps.
2. **UI pengaturan scope**: nempel di halaman role existing (bukan halaman
   terpisah) — tambah section baru di halaman edit/detail role.
3. **Gate ubah tabel scope**: **permission dinamis baru**, mis. `roles.manage_scope`.
   Default hanya di-assign ke role Owner lewat seeder, tapi bisa dipindah/ditambah
   ke role lain kapan saja lewat matrix permission biasa — bukan hardcode
   `code === 'owner'`.
4. **UX penolakan cap pewarisan**: checkbox permission yang granter sendiri tidak
   punya di-disable di view `roles.matrix` + backend tetap validasi ulang
   (defense-in-depth).
5. **Delete role**: Owner-only.
6. **Protected roles**: pindah ke DB — kolom `is_protected` (boolean) di tabel
   `roles`, bukan array di config. Owner bisa toggle dinamis role mana yang
   dilindungi dari hapus (tidak hardcode nama role tertentu di kode).
7. **Migrasi data awal**: seed `role_management_scopes` dari isi
   `config('rbac.role_management_scope')` yang sekarang (admin/noc→teknisi,helpdesk;
   fop→teknisi) sebagai starting data lewat seeder — setelah itu **config array
   dihapus total**, semua baca dari DB, tidak ada fallback statis.

## Ringkasan Desain Final

Entity baru:
- `role_management_scopes` (manager_role_id, managed_role_id, created_by, note) —
  many-to-many, sumber kebenaran untuk "siapa boleh create-user-as & kelola
  permission role apa".
- `roles.is_protected` (boolean, default false, `true` untuk Owner) — ganti
  `config('rbac.protected_roles')`.
- Permission baru `roles.manage_scope` — gate akses ke halaman/aksi atur tabel
  scope di atas.

Aturan hard-code (tetap di kode, bukan data, karena ini invariant keamanan inti,
bukan preferensi bisnis yang berubah-ubah):
- Role tidak pernah boleh mengelola dirinya sendiri (no self-loop), walau ada
  baris scope yang menyatakan demikian.
- Role `owner` selalu bisa kelola semua role, dan hanya bisa dikelola oleh `owner`.
- Cap pewarisan permission = subset dari permission matrix milik role granter.

Komponen existing yang perlu disesuaikan (bukan dibuat baru):
- `Role::canBeManagedBy()`, `Role::assignableRolesFor()` → baca dari
  `role_management_scopes` (query DB / relasi), bukan `config()`.
- `Role::isProtected()` → baca `$this->is_protected`, bukan `config('rbac.protected_roles')`.
- `RoleManagementService::syncPermissions()` → tambah validasi subset (Gap 1)
  terhadap permission milik role granter sebelum simpan.
- `RolePermissionController` → tambah middleware/gate permission `roles.manage_scope`
  untuk endpoint atur tabel scope (baru).
- View `roles.matrix` / halaman role existing → tambah section pengaturan scope +
  disable checkbox permission yang granter tidak punya.
- Seeder baru/`RolePermissionSeeder` → migrasi data scope awal dari config lama.

## Next Step

Desain sudah final secara konsep. Lanjut ke rencana implementasi teknis rinci
(daftar file migration/model/service/controller/view + urutan kerja) — baru masuk
tahap coding setelah rencana itu di-review user.

## Rencana Implementasi Teknis (urutan kerja)

Catatan: permission baru (`role_scope.*`) tetap didefinisikan lewat mekanisme
katalog permission yang sudah ada (`config/rbac.php` → `allowed_actions` +
`PermissionGeneratorService`) — ini beda dari Gap 2. Yang harus dinamis itu
**data hierarki** (siapa kelola siapa), bukan **katalog fitur/permission apa saja
yang ada di sistem** (itu memang wajar didefinisikan developer saat nambah fitur).

### 1. Migration

1. `xxxx_create_role_management_scopes_table.php`
   - `id`, `manager_role_id` (FK → `roles.id`, cascade delete), `managed_role_id`
     (FK → `roles.id`, cascade delete), `created_by` (FK → `users.id`, nullable),
     `note` (nullable string), timestamps.
   - Unique constraint `(manager_role_id, managed_role_id)` — cegah duplikat.
   - Check/validasi di level aplikasi: `manager_role_id !== managed_role_id`
     (self-loop tetap dicegah juga di DB kalau perlu, tapi enforce utama di model).
2. `xxxx_add_is_protected_to_roles_table.php`
   - Tambah kolom `is_protected` boolean default `false`.
   - Data migration di dalam file yang sama (atau seeder terpisah): set `true`
     untuk role `owner` (baca `config('rbac.protected_roles')` lama sekali jalan
     lalu isi kolom, biar behavior gak berubah pas rilis).

### 2. Config & Seeder

3. Tambah entri baru di `config/rbac.php` → `allowed_actions`:
   `'role_scope' => [ActionCode::VIEW->value, ActionCode::UPDATE->value]`
   (feature baru khusus untuk endpoint atur hierarki, terpisah dari `roles`
   supaya permission `roles.update` yang sudah ada — dipakai buat edit
   nama/deskripsi role — gak ketuker maknanya dengan hak atur hierarki).
4. Tambah row `Feature` baru `role_scope` di seeder feature (ikut pola
   `DatabaseSeeder`/seeder feature yang sudah ada), lalu jalankan
   `PermissionGeneratorService` supaya permission `role_scope.view` &
   `role_scope.update` otomatis kebentuk.
5. **Seeder migrasi data** (`RoleManagementScopeSeeder`, dijalankan sekali):
   baca `config('rbac.role_management_scope')` existing
   (`admin/noc→[teknisi,helpdesk]`, `fop→[teknisi]`), insert ke tabel
   `role_management_scopes` sebagai starting data. Assign permission
   `role_scope.view` + `role_scope.update` ke role `Owner` by default.
6. Hapus `role_management_scope` dan `protected_roles` dari `config/rbac.php`
   setelah data dipindah (tidak ada fallback statis, sesuai keputusan poin 7).

### 3. Model

7. `RoleManagementScope` (model baru) — relasi `belongsTo(Role, 'manager_role_id')`,
   `belongsTo(Role, 'managed_role_id')`, `belongsTo(User, 'created_by')`.
8. `Role` (edit, bukan buat baru):
   - `managedScopes()` / `managingScopes()` — relasi ke `RoleManagementScope`.
   - `canBeManagedBy(User $user)` → ganti baca `config('rbac.role_management_scope')`
     jadi query `RoleManagementScope::where('manager_role_id', $user->role_id)
     ->where('managed_role_id', $this->id)->exists()`. Hard rule: kalau
     `$this->id === $user->role_id` → langsung `false` (no self-management),
     dicek sebelum query DB, override apa pun isi tabel.
   - `assignableRolesFor(User $user)` → ganti sumber ke query
     `RoleManagementScope::where('manager_role_id', ...)->pluck('managed_role_id')`.
   - `isProtected()` → baca `$this->is_protected` (kolom baru), bukan config.
9. `Permission`/`Role` — tambah helper `permissionCodesFor(Role $role): array`
   kalau belum ada cara ambil kode permission suatu role (dipakai buat cap
   pewarisan, Gap 1) — cek dulu apa `role->permissions()->pluck('code')` sudah
   cukup sebelum bikin helper baru (hindari duplikasi, `permissions()` relasi
   sudah ada di `Role.php:117`).

### 4. Service

10. `RoleManagementService::syncPermissions()` (edit) — tambah param/langkah
    validasi: sebelum `$role->permissions()->sync(...)`, hitung
    `$allowedCeiling = auth()->user()->role->permissions()->pluck('id')` (basis:
    permission role granter yang login, sesuai Gap 1), lalu
    `$sanitizedPermissions = array_intersect($sanitizedPermissions, $allowedCeiling)`
    — permission di luar ceiling otomatis di-drop, bukan cuma ditolak silent;
    log/flash pesan kalau ada yang di-drop supaya user tahu.
    - Perlakuan khusus: kalau granter adalah Owner, ceiling = semua permission
      (skip filter), karena Owner defaultnya full access (`isFullAccessRole`).
11. `RoleScopeService` (service baru) — method `assign(Role $manager, Role $managed, User $actor)`
    dan `revoke(Role $manager, Role $managed)`. Validasi di sini:
    - `$manager->id !== $managed->id` (no self-loop).
    - Role `owner` tidak perlu di-assign manual (selalu implicit full access) —
      tolak kalau ada yang coba insert scope dengan manager/managed = owner
      secara eksplisit (opsional, biar tabel gak kotor data yang gak dipakai).

### 5. Controller & Route

12. `RolePermissionController` (edit) — tambah 2 action baru:
    - `scopeIndex(Role $role)` → tampilkan role apa saja yang sedang dikelola
      role ini + form tambah (dropdown role lain).
    - `scopeUpdate(Role $role, Request $request)` → terima `managed_role_ids[]`,
      panggil `RoleScopeService`, replace/sync baris scope untuk `manager_role_id
      = $role->id`.
    - Gate kedua action pakai middleware `permission:role_scope.update` (route)
      + cek tambahan `!$role->isProtected()`-style guard kalau perlu (opsional).
13. `routes/web.php` (edit) — tambah di dalam grup route roles existing
    (`routes/web.php:50-56`):
    ```
    Route::get('/roles/{role}/scope', [...])->name('roles.scope')->middleware('permission:role_scope.view');
    Route::put('/roles/{role}/scope', [...])->name('roles.scope.update')->middleware('permission:role_scope.update');
    ```

### 6. View

14. Halaman role existing (kemungkinan `resources/views/roles/matrix.blade.php`
    atau `roles/index.blade.php` — cek struktur tab yang sudah ada) — tambah
    section/tab baru "Hierarki Pengelolaan": daftar role yang sedang dikelola +
    dropdown tambah role baru dari `Role::assignableRolesFor` versi Owner
    (semua role kecuali diri sendiri & owner).
15. `roles/matrix.blade.php` (edit, existing) — checkbox permission yang tidak
    dimiliki granter (login user) di-disable (`disabled` attribute + style muted),
    supaya selaras sama validasi backend di langkah 10.

### 7. Test

16. Update/`tests/Feature/RolePermissionTest.php`,
    `tests/Feature/MiddlewarePermissionTest.php`,
    `tests/Feature/Seeders/RolePermissionSeederTest.php` — sesuaikan expectation
    yang masih assert baca dari `config('rbac.role_management_scope')`.
17. Test baru: cap pewarisan permission (FOP gak bisa kasih Teknisi permission
    di luar milik FOP), self-loop scope ditolak, scope dinamis bisa
    ditambah/dicabut runtime tanpa restart/deploy.

## Urutan Eksekusi (ringkas)

Migration (1,2) → Config+Feature+Permission generate (3,4) → Model (7,8,9) →
Seeder migrasi data (5) + hapus config lama (6) → Service (10,11) → Controller+Route
(12,13) → View (14,15) → Test (16,17).