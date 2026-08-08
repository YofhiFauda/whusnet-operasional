> **Arsip.** Dokumen historis, sebagian sudah tidak sesuai kode aktif (lihat [../README.md](../README.md) untuk dokumentasi terkini).

# Analisa: Dualisme Sistem Assign-POP (user_pops vs user_role_scopes)

## Konteks — Kenapa Ini Ketemu

Investigasi awal: FOP Rian jadwalin teknisi lewat `/fop-tasks`, tapi Dashboard FOP-nya kosong (stat cards 0, Team Card gak muncul, padahal data beneran ada di DB — confirmed lewat tinker).

## Temuan 1: Dua Sistem Assign-POP yang Gak Nyambung

Ada **2 tabel + 2 halaman** buat "assign POP ke user", isinya keliatan sama tapi FUNGSINYA BEDA dan GAK SALING BACA:

| | `user_pops` (pivot) | `user_role_scopes` + `scope_targets` |
|---|---|---|
| Halaman | `/users/{id}/pops` (`editPops`/`updatePops`) | `/users/{id}/edit` (field "Scope Wilayah Data") |
| Ditulis lewat | `$user->pops()->sync()` | `UserScopeManagementService` |
| Dibaca oleh | `Pop::forUser()` scope — isi dropdown "Cabang" di form Task/Customer (12 file controller) | `EffectiveAccessService::getAllowedPopIds()`/`getScopeType()` — gate DATA di dashboard, listing, query `applyUserScope()` |
| Efek kalau di-assign | Nambah opsi di dropdown doang | Nentuin data apa yang KELIATAN buat user itu |

**Akibat konkret:** assign "semua POP" ke FOP Rian lewat `/users/{id}/pops` **gak ngefek APAPUN** ke Dashboard FOP, Team Card, atau listing manapun — karena itu nulis ke tabel yang gak dibaca sistem RBAC. Yang bener harus lewat `/users/{id}/edit` → field "Scope Wilayah Data".

**Sudah dikonfirmasi via tinker:** scope FOP Rian tetep `NULL` di `user_role_scopes` meski udah di-assign "semua POP" lewat `/pops`.

### Fix Sementara (Non-Kode)
Assign scope FOP Rian lewat halaman **Edit User** (`/users/{id}/edit`), bukan `/users/{id}/pops`.

## Temuan 2: Redundansi Ini Kemungkinan Bukan Disengaja

Indikasi ini drift arsitektur (2 developer/era beda), bukan desain sengaja:
- `Pop::forUser()` cek `$user->role?->name` (string label kayak `'Admin Pusat'`) — beda pola sama sisa app yang konsisten pake `role->code` (`'owner'`, `'admin'`, dst).
- Gak masuk akal user bisa PILIH cabang X di dropdown tapi datanya sendiri gak keliatan di dashboard (atau sebaliknya) — dua sumber kebenaran buat 1 konsep ("POP mana yang boleh diakses user ini").

## Rekomendasi

**Satuin ke `user_role_scopes` sebagai satu-satunya sumber kebenaran:**
1. Ganti `Pop::scopeForUser()` biar baca dari `EffectiveAccessService::getAllowedPopIds()` (atau `hasAllPopAccess()`) — bukan dari `user_pops` lagi.
2. Deprecate/hapus halaman `/users/{id}/pops` (`editPops`/`updatePops`) + route-nya.
3. Migrasi data: user yang udah punya `user_pops` tapi belum punya `user_role_scopes` — perlu di-backfill (generate `UserRoleScope` scope_type=selected_pop dari data `user_pops` yang ada), biar gak ada yang tiba-tiba kehilangan akses dropdown pas `user_pops` dihapus.
4. Hapus tabel `user_pops` + relasi `User::pops()` setelah migrasi data aman.

## Tradeoff / Kenapa Belum Dikerjain Sekarang

- Nyentuh **12 file** yang manggil `Pop::forUser()`: `CustomerController`, `TaskController`, `FopDashboardController`, `PaymentController`, `InvoiceController`, `DashboardController`, `UserController`, `UserScopeManagementService`, `InvoiceReportController`, `CustomerReportController`, `Pop.php`, `User.php`.
- Butuh strategi migrasi data biar user existing gak collateral damage (kehilangan akses dropdown POP yang udah biasa dipake).
- Perlu regression test tiap halaman yang pake dropdown "Cabang" (Task create, Customer create/edit, dst) buat mastiin listnya tetep bener pasca migrasi.

## Checklist Eksekusi (Nanti)
- [ ] Assign scope FOP Rian lewat `/users/{id}/edit` (quick fix non-kode, bisa langsung sekarang, gak perlu tunggu refactor)
- [ ] Backfill `user_role_scopes` dari data `user_pops` existing (script sekali jalan)
- [x] Ganti `Pop::scopeForUser()` pake `EffectiveAccessService` — sudah beres (`Pop::scopeForUser()` di `app/Models/Pop.php` sudah baca `EffectiveAccessService::hasAllPopAccess()`/`getAllowedPopIds()`, bukan `user_pops` lagi).
- [ ] Regression test semua dropdown "Cabang/POP" di form yang kepengaruh (12 file di atas)
- [x] Hapus halaman `/users/{id}/pops`, route `users.pops.edit`/`users.pops.update` — beres 2026-08-07, lihat `docs/plan/analisa-celah-scope-pop.md` temuan #6. `user_pops`/`User::pops()` SENGAJA belum dihapus (masih dipakai `UserScopeManagementService::syncUserRoleScope()` sebagai tabel backward-compat) — item checklist di bawah masih berlaku.
- [ ] Hapus relasi `User::pops()` + tabel `user_pops` (migration drop, paling akhir setelah semua aman)
