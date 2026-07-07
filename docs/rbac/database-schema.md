# Database Schema — RBAC

## Entity Relationship

```
users ──▶ role_id ──▶ roles ──belongsToMany──▶ permissions   (via role_permissions)
  │                     │                          │
  │                     │                          ├──▶ features (feature_id)
  │                     │                          └──▶ actions  (action_id)
  │                     │
  │                     └──belongsToMany──▶ workflow_transition_permissions
  │                                          (via role_workflow_transition)
  │
  └──hasMany──▶ user_role_scopes ──hasMany──▶ user_role_scope_targets ──▶ pops
```

## Tabel `roles`

Migrasi: `2026_06_10_000001_create`, `2026_06_23_143019_alter_add_code_and_is_system`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `code` | string, unique | ✔ | Identitas stabil (`owner`, `admin`, `noc`, dst) — dipakai di logic, gak berubah walau `name` diubah |
| `name` | string, unique | | Nama tampil (bisa diubah kalau bukan role sistem) |
| `guard_name` | string, default `web` | | |
| `description` | string | ✔ | |
| `is_system` | boolean, default false | | true = 9 role bawaan, `code` terkunci permanen |
| `created_at`/`updated_at` | timestamp | | |

**9 role sistem** (`RoleSeeder`): `owner`, `atasan`, `admin`, `noc`, `helpdesk`, `fop`, `teknisi`, `sales`, `pop_admin`.

## Tabel `features`

Migrasi: `2026_06_23_000001_create`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `parent_id` | FK → `features.id`, cascade delete | ✔ | Self-reference, bikin pohon fitur |
| `code` | string, unique | | e.g. `customers`, `customers.detail`, `customers.detail.survey` |
| `name` | string | | Label tampil |
| `type` | string, default `root` | | Enum `App\Enums\FeatureType`: `root`, `sub_feature`, `mini_feature` |
| `sort_order` | integer, default 0 | | Urutan tampil |
| `is_active` | boolean, default true | | |
| `created_at`/`updated_at` | timestamp | | |

Index: `parent_id`, `type`.

## Tabel `actions`

Migrasi: `2026_06_23_000002_create`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `code` | string, unique | | Enum `App\Enums\ActionCode` (18 aksi: `view`, `create`, `update`, `delete`, `import`, `export`, `print`, `approve`, `reject`, `activate`, `deactivate`, `assign`, `validate`, `cancel`, `upload`, `download`, `view_sensitive`, `update_sensitive`) |
| `name` | string | | |
| `description` | text | ✔ | |
| `created_at`/`updated_at` | timestamp | | |

## Tabel `permissions`

Migrasi: `2026_06_10_000002_create` (kolom lama `name`/`module`), `2026_06_23_000003_alter_add_feature_action_ids` (kolom baru).

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `feature_id` | FK → `features.id`, cascade delete | ✔ | |
| `action_id` | FK → `actions.id`, cascade delete | ✔ | |
| `code` | string, unique | ✔ | `{feature_code}.{action_code}` — dipakai untuk match permission runtime |
| `name` | string | ✔ | Legacy, boleh null (backward compat) |
| `module` | string | ✔ | Legacy, boleh null |
| `description` | string | ✔ | |
| `created_at`/`updated_at` | timestamp | | |

Unique: (`feature_id`, `action_id`).

## Tabel pivot `role_permissions`

Migrasi: `2026_06_10_000004_create`.

| Kolom | Tipe |
|-------|------|
| `id` | bigint PK |
| `role_id` | FK → `roles.id`, cascade delete |
| `permission_id` | FK → `permissions.id`, cascade delete |

Unique: (`role_id`, `permission_id`).

## Tabel `user_role_scopes`

Migrasi: `2026_06_23_144420_create`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `user_id` | FK → `users.id`, cascade delete | | |
| `role_id` | FK → `roles.id`, cascade delete | | |
| `scope_type` | string | | Enum `App\Enums\ScopeType`: `all_pop`, `selected_pop`, `pop_tree` (legacy) |
| `created_at`/`updated_at` | timestamp | | |

Unique: (`user_id`, `role_id`) — 1 user cuma 1 scope aktif per role.

## Tabel `user_role_scope_targets`

Migrasi: `2026_06_23_144431_create`.

| Kolom | Tipe |
|-------|------|
| `user_role_scope_id` | FK → `user_role_scopes.id`, cascade delete |
| `pop_id` | FK → `pops.id`, cascade delete |

Composite primary key: (`user_role_scope_id`, `pop_id`). Tidak ada `id`/timestamps sendiri — model `UserRoleScopeTarget` set `$incrementing = false`, `$timestamps = false`.

## Tabel `workflow_transition_permissions` & `role_workflow_transition`

Migrasi: `2026_06_27_160000_create`.

| Tabel | Kolom | Keterangan |
|-------|-------|------------|
| `workflow_transition_permissions` | `id`, `from_status`, `to_status`, `permission_name`, `created_at` | Definisi transisi status yang butuh permission spesifik |
| `role_workflow_transition` | `id`, `role_id` FK, `workflow_transition_permission_id` FK, `created_at` | Assignment transisi ke Role |

Kedua tabel `timestamps=false` (cuma `created_at`, gak ada `updated_at`) — data ini append-only/rarely-updated by design.

## Migrasi Historis Terkait

- `2026_06_23_144621_migrate_user_pops_to_user_role_scopes` — migrasi data dari sistem lama `user_pops` (per-user POP list flat) ke `user_role_scopes`+`user_role_scope_targets` (per-role scope terstruktur).
- `2026_06_29_111948_remove_legacy_task_permissions` — bersihin permission lama modul Task yang udah gak relevan pasca-restrukturisasi Feature Tree.

## Model Relations (ringkas)

```php
// Role
permissions(): BelongsToMany(Permission::class, 'role_permissions')
users(): HasMany(User::class)
userScopes(): HasMany(UserRoleScope::class)
workflowTransitions(): BelongsToMany(WorkflowTransitionPermission::class, 'role_workflow_transition')

// Permission
feature(): BelongsTo(Feature::class)
action(): BelongsTo(Action::class)
roles(): BelongsToMany(Role::class, 'role_permissions')

// Feature
parent(): BelongsTo(self::class, 'parent_id')
children(): HasMany(self::class, 'parent_id')

// User (relevan RBAC)
role(): BelongsTo(Role::class)
roleScopes(): HasMany(UserRoleScope::class)

// UserRoleScope
user(): BelongsTo(User::class)
role(): BelongsTo(Role::class)
targets(): HasMany(UserRoleScopeTarget::class)
```

## Cache Keys (bukan tabel, tapi bagian penting state akses)

| Key | TTL | Isi |
|-----|-----|-----|
| `user.{id}.permissions` | 1 jam | Array kode permission user (`['*']` kalau Owner) |
| `user.{id}.scope_type` | 1 jam | `ScopeType` enum value user |
| `user.{id}.allowed_pop_ids` | 1 jam | Array POP ID yang boleh diakses (resolved tree) |

Semua di-clear lewat `EffectiveAccessService::clearCache($user)` — dipanggil otomatis tiap kali permission role atau scope user berubah.
