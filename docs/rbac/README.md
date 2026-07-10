# Modul RBAC (Role-Based Access Control)

Sistem akses 3 dimensi: **Role** (siapa Anda), **Permission** (`{feature}.{action}`, apa yang boleh dilakukan), **Scope** (data POP mana yang boleh dilihat). Ketiganya independen — jangan bikin Role per-cabang, cukup kombinasikan Role + Scope.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Aturan bisnis: hierarki role, siapa boleh kelola siapa, precedence permission, aturan scope POP |
| [flowchart.md](flowchart.md) | Alur cek permission, generate permission, resolve scope POP, sync role-permission |
| [user-flow.md](user-flow.md) | Langkah Owner/Admin kelola role, permission matrix, assign scope user |
| [database-schema.md](database-schema.md) | Tabel `roles`, `permissions`, `features`, `actions`, `user_role_scopes`, dll |
| [field-lock-verifikasi.md](field-lock-verifikasi.md) | Analisa RBAC Registrasi/Survey/Pemasangan/Verifikasi Admin + field-lock pasca-verifikasi |
| [archive/](archive/) | Dokumen desain & rencana historis (RBAC_MATRIX awal, analisa dinamis, rancangan per-fitur) |

## Konsep Inti

```
User ──belongsTo──▶ Role ──belongsToMany──▶ Permission ──belongsTo──▶ Feature + Action
  │                                              (via role_permissions)
  └──hasMany──▶ UserRoleScope ──hasMany──▶ UserRoleScopeTarget ──▶ Pop
                  (scope_type: all_pop / selected_pop / pop_tree)
```

- **Role** — profil kerja (Owner, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin, Atasan). 9 role sistem, semua `is_system=true` kecuali role custom yang Owner buat sendiri.
- **Permission** — kode `{feature_code}.{action_code}`, e.g. `fop_tasks.update_sensitive`, `invoices.create`. Digenerate otomatis dari `config/rbac.php` (`allowed_actions` map), bukan diketik manual satu-satu.
- **Feature** — pohon fitur (root → sub_feature → mini_feature), e.g. `customers` → `customers.detail` → `customers.detail.survey`.
- **Action** — 18 kode aksi generik (`view`, `create`, `update`, `delete`, `approve`, `validate`, `update_sensitive`, dst) — dipakai lintas fitur.
- **Scope** — pembatasan data per-POP, independen dari Role/Permission. 3 tipe: `all_pop` (semua data), `selected_pop` (POP terpilih + sub-POP di bawahnya), `pop_tree` (legacy, perilaku sama dengan `selected_pop`).

## File Kode Terkait

| Area | File |
|------|------|
| Model | `app/Models/{Role,Permission,Feature,Action,UserRoleScope,UserRoleScopeTarget,WorkflowTransitionPermission}.php` |
| Cek akses runtime | `app/Services/EffectiveAccessService.php` |
| Generate permission dari config | `app/Services/PermissionGeneratorService.php` |
| Sync permission ke role + audit | `app/Services/RoleManagementService.php` |
| Assign scope ke user | `app/Services/UserScopeManagementService.php` |
| Middleware route-level | `app/Http/Middleware/CheckPermission.php` |
| Query scope POP | `app/Traits/HasPopScope.php` (dipakai `Invoice`, `Payment`, `Customer`, dll) |
| Controller kelola Role & Matrix | `app/Http/Controllers/RolePermissionController.php` |
| Controller kelola User & Scope | `app/Http/Controllers/UserController.php` |
| Config sumber permission | `config/rbac.php` |
| Enum | `app/Enums/{ScopeType,ActionCode,FeatureType}.php` |
| Seeder | `database/seeders/{RoleSeeder,ActionSeeder,PermissionSeeder,RolePermissionSeeder,WorkflowTransitionPermissionSeeder}.php` |

## Routes

| Route | Permission | Controller |
|-------|------------|------------|
| `GET /roles` | `roles.view\|roles.update` | `RolePermissionController@index` |
| `POST /roles` | `roles.create` (+ Owner only guard) | `RolePermissionController@store` |
| `PUT /roles/{role}` | `roles.update` | `RolePermissionController@updateRole` |
| `DELETE /roles/{role}` | `roles.delete` | `RolePermissionController@destroy` |
| `GET /roles/{role}/matrix` | `roles.view\|roles.update` | `RolePermissionController@matrix` |
| `PUT /roles/{role}/matrix` | `roles.view\|roles.update` | `RolePermissionController@update` |
| `GET /users` | `users.view` | `UserController@index` |
| `GET,POST /users(/create,/store)` | `users.create\|users.update` | `UserController@create,store` |
| `GET,PUT /users/{user}/edit,/update` | `users.create\|users.update` | `UserController@edit,update` |
| `GET,PUT /users/{user}/pops` | `users.create\|users.update` | `UserController@editPops,updatePops` |
| `POST /users/preview-access` | `users.create\|users.update` | `UserController@previewAccess` |

---

**Last updated:** 2026-07-07
