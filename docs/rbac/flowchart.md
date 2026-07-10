# Flowchart — RBAC

## 1. Cek Permission Runtime (`EffectiveAccessService::userCan()`)

```
Request masuk → middleware `permission:{code}` (CheckPermission)
        │
        ▼
   user login? ──── tidak ──▶ abort 403
        │ ya
        ▼
   user->hasPermission('*')? ──── ya ──▶ lolos (Owner bypass)
        │ tidak
        ▼
   split $permission by '|' (OR logic multi-permission)
        │
        ▼
   loop tiap kode: user->hasPermission($kode)?
        │
        ├─▶ EffectiveAccessService::getPermissions($user)  [cached 1 jam]
        │        │
        │        ├─ role null? → []
        │        ├─ role->code === 'owner'? → ['*']
        │        └─ else → role->permissions()->pluck('code')
        │
        ▼
   cek precedence:
   1. exact match code
   2. '*' ada di list
   3. feature wildcard "{feature}.*" atau nested "{code}.*"
   4. prefix match "{permission}." di awal salah satu kode user
        │
        ├─ match? ──▶ lolos, lanjut ke controller
        └─ semua kode gagal ──▶ abort 403
```

## 2. Generate Permission dari Config

```
Owner ubah config/rbac.php (tambah feature_code + action_code baru)
        │
        ▼
Pastikan Feature & Action-nya sudah ada di DB (seeder/manual)
        │
        ▼
php artisan (command yang panggil PermissionGeneratorService::generate())
        │
        ▼
Loop allowed_actions:
  feature_code ada di DB? ──── tidak ──▶ catat error, skip
        │ ya
        ▼
  loop action_code:
    action ada di DB? ──── tidak ──▶ catat error, skip
        │ ya
        ▼
    permission (feature_id, action_id) sudah ada? ──── ya ──▶ skip (atau update code kalau beda)
        │ tidak
        ▼
    Permission::create(code = "{feature_code}.{action_code}")
        │
        ▼
Commit — summary: total_features_processed, permissions_created, permissions_skipped, errors[]
```

## 3. Kelola Permission Role (Matrix)

```
Admin buka /roles/{role}/matrix
        │
        ▼
Role::canBeManagedBy($currentUser)? ──── tidak ──▶ abort 403
        │ ya
        ▼
Role::isOwner()? ──── ya ──▶ redirect, "Owner akses penuh, gak bisa diubah"
        │ tidak
        ▼
Tampilkan Feature::getTree() (root→sub→mini) + checkbox tiap Permission per Action
        │
        ▼
Admin centang/uncentang, submit → PUT /roles/{role}/matrix
        │
        ▼
canBeManagedBy() dicek ULANG (guard 1) + isOwner() dicek ULANG (guard 2)
        │
        ▼
RoleManagementService::syncPermissions($role, $permissionIds)
        │
        ├─ role->permissions()->sync() — replace total, bukan tambah
        ├─ clearCache() semua user yang punya role ini (permission cache lama gak nyantol)
        └─ AuditLog: module=Role Management, action=sync_permissions, old vs new permission ID
        │
        ▼
Redirect ke /roles + flash success
```

## 4. Resolve Scope POP (`getAllowedPopIds()`)

```
Query model pakai HasPopScope → applyUserScope()
        │
        ▼
user->hasRole('owner','atasan')? ──── ya ──▶ query TANPA filter (return apa adanya)
        │ tidak
        ▼
scopeType = EffectiveAccessService::getScopeType($user)   [cached]
        │
        ├─ ALL_POP → query TANPA filter
        │
        ├─ SELECTED_POP atau POP_TREE:
        │     basePopIds = user_role_scope_targets.pop_id
        │           │
        │           ▼
        │     resolvePopTree(basePopIds):
        │       queue = basePopIds
        │       while queue not empty:
        │         pop = shift(queue)
        │         resolvedIds[] = pop (kalau belum ada)
        │         children = Pop::where('parent_id', pop)->pluck('id')
        │         queue += children
        │       return resolvedIds
        │           │
        │           ▼
        │     query->whereIn('pop_id', resolvedIds)
        │
        └─ scope null/gak dikenali → query->whereRaw('1=0')  (deny-by-default)
```

## 5. Assign Scope ke User

```
Admin buka /users/{user}/pops (atau saat create/update user)
        │
        ▼
Pilih scope_type: all_pop | selected_pop
   (kalau selected_pop) → pilih POP target (checkbox tree)
        │
        ▼
UserScopeManagementService — simpan/update UserRoleScope + sync UserRoleScopeTarget
        │
        ▼
EffectiveAccessService::clearCache($user) — scope_type & allowed_pop_ids cache lama dibuang
        │
        ▼
AuditLog tercatat (module: User Role Scope)
```

## 6. Cek Kewenangan Kelola Role Lain (`Role::canBeManagedBy()`)

```
currentUser mau kelola $role (edit/delete/ubah matrix)
        │
        ▼
currentUser->role->code === 'owner'? ──── ya ──▶ boleh (selalu)
        │ tidak
        ▼
$role->code === 'owner'? ──── ya ──▶ TOLAK (role Owner cuma boleh dikelola Owner)
        │ tidak
        ▼
scope = config('rbac.role_management_scope')[currentUser->role->code] ?? []
        │
        ▼
in_array($role->code, scope)? ──── ya ──▶ boleh
                              └── tidak ──▶ TOLAK
```
