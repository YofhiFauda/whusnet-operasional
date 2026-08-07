# SUDAH DIIMPLEMENTASIKAN (2026-08-07)

# Analisa Celah Scope POP

**Tanggal:** 2026-08-05 (audit), diimplementasikan 2026-08-07.
**Status:** Selesai — 11 dari 12 temuan di-fix (item #5 sudah ke-fix duluan lewat commit `5df4ab3` sebelum audit ini jalan; item #6 dihapus sesuai keputusan user, bukan disambungkan). Regresi baru: `tests/Feature/PopScopeGapFixesTest.php`. Full test suite: 1003 passed.
**Konteks:** User curiga fitur Scope POP belum optimal di beberapa tempat. Diverifikasi lewat audit kode (grep + baca langsung), bukan asumsi.

## Ringkasan

Scope POP secara umum jalan benar di jalur utama (`Customer`, `Ticket`, `Invoice`, `Payment`, report & dashboard controller) — semua lewat `applyUserScope()` / `EffectiveAccessService::getAllowedPopIds()` yang digerbangi `hasAllPopAccess()`. Tapi ada beberapa titik yang **bocor** (data lintas POP kebaca) dan beberapa yang masih pakai **jalur legacy** (`$user->pops()` pivot, tidak paham `pop_tree`).

Dua jalur scoping yang beda (lihat CLAUDE.md bagian RBAC):
- `EffectiveAccessService::getAllowedPopIds($user)` — jalur benar, dukung `pop_tree`.
- `$user->pops()` — pivot `user_pops` langsung, gak paham `pop_tree`, legacy.

## Temuan — Kebocoran Data Nyata (prioritas tinggi)

### 1. `FopTaskController::index()` dan `history()`
`app/Http/Controllers/FopTaskController.php:37-118` dan `:1011-1017`.

Query `FopTask::with([...])` gak pernah lewat `applyUserScope()` atau filter `pop_id` sama sekali, padahal `FopTask` punya kolom `pop_id`. `authorizeAccess()` cuma cek permission, bukan scope. User dengan `fop_task.view` bisa lihat semua FOP task lintas cabang, gak peduli scope-nya `selected_pop`/`pop_tree`.

### 2. `CustomerSurveyController::index()`
`app/Http/Controllers/CustomerSurveyController.php:30-66`.

Hanya role `teknisi` yang dibatasi (ke task miliknya sendiri). Role lain (CS/FOP staff dengan scope `selected_pop`) yang punya permission `customers.detail.survey.view` bakal lihat **semua** pelanggan `waiting_survey`/`survey_in_progress` lintas POP.

### 3. `CustomerVerificationController::index()`
`app/Http/Controllers/CustomerVerificationController.php:27-64`.

Pola sama persis dengan #2 — cuma `teknisi` yang dibatasi, role lain gak difilter POP. Ini antrian verifikasi (`waiting_acc`, `surveyed`, `waiting_installation`, dst.) — bocor lintas cabang.

### 4. `TaskController::getTeknisiForUser()`
`app/Http/Controllers/TaskController.php:547-563`.

```php
$allowedPopIds = $accessService->getAllowedPopIds($user);
$query = User::with('role')->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))->orderBy('name');
if (! empty($allowedPopIds)) {
    $query->whereHas('roleScopes.targets', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
}
return $query->get();
```

Anti-pattern: `empty($allowedPopIds)` dianggap "gak perlu filter", padahal itu ambigu — bisa berarti `ALL_POP` (benar, gak usah filter) atau scope belum di-setup (harusnya deny-by-default). Dokumentasi `EffectiveAccessService` sudah wanti-wanti soal ini secara eksplisit. Efeknya: user yang scope-nya belum di-setup malah lihat **semua** teknisi lintas POP di dropdown assign task.

## Temuan — Jalur Legacy (bukan bocor, tapi salah/rusak)

### 5. `routes/channels.php:89-97` — channel `fop.{pop_id}`
```php
Broadcast::channel('fop.{pop_id}', function ($user, $popId) {
    if (! $user->hasPermission('fop.dashboard')) return false;
    if ($user->hasFullAccess()) return true;
    return $user->pops()->where('pops.id', $popId)->exists();
});
```
Pakai pivot `user_pops` langsung, bukan `EffectiveAccessService`. Karena `user_pops` cuma nyimpen POP dasar (bukan hasil resolusi `pop_tree`), user dengan scope `pop_tree` yang cabangnya punya sub-POP bakal **ditolak** subscribe ke channel realtime `fop.{sub_pop_id}` — kebalikan dari bocor, ini malah nolak akses yang harusnya sah. Dashboard FOP realtime jadi gak update buat user begitu.

### 6. `UserController::updatePops()`
`app/Http/Controllers/UserController.php:301-330`, dipakai di `resources/views/users/index.blade.php:150` dan `edit_pops.blade.php:49`.

Cuma nulis ke pivot legacy `user_pops`, gak pernah nyentuh `UserRoleScope`/`user_role_scope_targets`. Efeknya: halaman admin "Edit POP" untuk user itu **gak ngaruh apa-apa** ke akses sesungguhnya (yang ditentukan `getAllowedPopIds()`). UI menyesatkan — keliatan berfungsi tapi silent no-op.

### 7. `CustomerController::paymentInfo()`
`app/Http/Controllers/CustomerController.php:1282-1287`.

```php
! in_array($customer->pop_id, auth()->user()->pops()->pluck('pops.id')->toArray())
```
Pakai pivot legacy juga, bukan `EffectiveAccessService`. Sama masalahnya dengan #5 — gak paham `pop_tree`.

## Temuan Putaran 2 — IDOR Per-Record (akses langsung lewat ID, bukan cuma list yang bocor)

Kelas beda dari putaran 1: di sini bahkan kalau **list**-nya sudah discope benar, endpoint `show`/`edit`/`update`/`destroy` per-record gak re-check scope. User tinggal tebak/iterasi ID di URL buat baca/ubah data lintas POP.

### 8. `CustomerController::show()/edit()/update()/destroy()`
`app/Http/Controllers/CustomerController.php:721,739,972,983`.

Gak ada cek POP scope sama sekali — cuma cek permission (`customers.update`, `customers.delete`). Bandingkan dengan `Invoice`/`Payment`/`Ticket` yang konsisten `applyUserScope()->whereKey($id)->exists()` sebelum serve single record. Customer — model paling sensitif — gak punya guard ini. **IDOR penuh lintas cabang.**

### 9. `TaskController` + `TaskPolicy::view()/edit()`
`app/Http/Controllers/TaskController.php:102,139,157`, `app/Policies/TaskPolicy.php`.

Policy cuma cek permission (`task.view.all`/`task.manage`), gak pernah cek `$task->pop_id`. Role apapun yang pegang permission itu (lumayan umum — FOP/Admin) bisa lihat/edit task dari POP manapun lewat URL langsung.

### 10. `Master\DistributionController`
`app/Http/Controllers/Master/DistributionController.php:23,39,49,87`.

`index()` nol scoping (`Distribution::query()->with('pop')...`), dan dropdown filter/create/edit pakai `Pop::where('status','active')` bukan `Pop::forUser()`. Bocor penuh lintas cabang di master data ini — kontras sama `PopController::index`/`InvoiceController`/`PaymentController`/report controller yang benar pakai `Pop::forUser()`.

### 11. `Master\PopController::show()/edit()/update()/toggleStatus()`
`app/Http/Controllers/Master/PopController.php:145,157,173,219`.

`index()` sudah benar (`Pop::query()->forUser()`), tapi endpoint per-record gak re-check scope pada `Pop` yang di-resolve dari route. User dengan scope terbatas bisa lihat/edit/nonaktifkan POP manapun by ID.

### 12. `FopTaskController::authorizeAccess()`
`app/Http/Controllers/FopTaskController.php:1023`, dipakai `update()`/`destroy()`/`showHistory()`.

Murni cek permission (`fop_tasks.*`), gak ada cek `pop_id`. IDOR per-record terpisah dari temuan #1 (list) di putaran 1.

### Sudah dicek, aman (putaran 2)
- Export (`InvoiceReportController`, `PaymentReportController`, `TicketHistoryController`) — benar, lewat `applyUserScope()`/`Pop::forUser()`.
- Search/autocomplete (`TaskController::searchCustomers`, `TicketController::lookupCustomer`/`duplicates`) — benar.
- Gak ada `routes/api.php` — gak ada permukaan API terpisah.
- `NotificationController` — filtering POP-aware sudah hati-hati, benar.
- Stat card/widget di `DashboardController`, `FopDashboardController`, `NocDashboardController` — konsisten dengan list di dekatnya.
- Console command/queued job — semua CLI-only, gak reachable dari actor lewat controller, di luar permukaan risiko ini.
- `Ticket` single-record endpoint (`show`, `download`, dst.) — konsisten pakai `authorizeTicketScope()`, aman.

## Yang Sudah Benar (verifikasi, bukan asumsi)

- `CustomerController::renderCustomerList`, `TicketController::index`, `InvoiceController::index`, `PaymentController::index`, `DashboardController` (`scopedCustomerQuery`/`scopedInvoiceQuery`/`scopedPaymentQuery`), `NocDashboardController`, `CustomerReportController`, `InvoiceReportController`, `PaymentReportController`, `FopDashboardController`, `NotificationController`, `CollectorController`, `TeknisiWorkloadService` — semua lewat `applyUserScope()`/`getAllowedPopIds()` yang digerbangi `hasAllPopAccess()` dengan benar.
- `Pop::scopeForUser()` dan `HasPopScope::scopeApplyUserScope()` — implementasi trait yang benar, jadi acuan pola yang harus diikuti di titik-titik yang bocor.
- `routes/channels.php` untuk channel `tickets.{popId}`, `invoices.{popId}`, `customers.{popId}`, `fop-tasks.{popId}` — sudah pakai `EffectiveAccessService` dengan benar.

## Status Perbaikan (dieksekusi 2026-08-07)

1. ✅ `FopTaskController::index()`/`history()` — `applyUserScope()` ditambah (trait `HasPopScope` baru di-attach ke model `FopTask`). Query `$switchTargetTasks` (dropdown "Task Tujuan" modal Switch Teknisi) ikut di-scope — kebocoran tambahan yang ketauan pas nulis test.
2. ✅ `CustomerSurveyController::index()` dan `CustomerVerificationController::index()` — `applyUserScope()` ditambah ke query utama. `CustomerVerificationController::row()`/`showAdmin()` (per-record, diakses langsung lewat ID) juga dapet guard `authorizeCustomerPopScope()` — gak ada di rencana awal, ketauan pas nulis skenario per-record.
3. ✅ `TaskController::getTeknisiForUser()` — kondisi diganti jadi `hasAllPopAccess()` eksplisit.
4. ✅ Sudah beres duluan lewat commit `5df4ab3` (2026-08-06, sebelum audit ini jalan) — `routes/channels.php` channel `fop.{pop_id}` sudah pakai `EffectiveAccessService`.
5. ✅ **Dihapus** (keputusan user) — `UserController::editPops()`/`updatePops()`, route `users.pops.edit`/`users.pops.update`, view `edit_pops.blade.php`, tombol "Atur Cabang" di `users/index.blade.php`. Fitur "Edit" user (lewat `UserScopeManagementService::syncUserRoleScope()`) sudah menutupi fungsi yang sama dengan jalur yang benar.
6. ✅ `CustomerController::paymentInfo()` — ganti ke `EffectiveAccessService`.
7. ✅ `CustomerController::show()/edit()/update()/destroy()` — guard `authorizeCustomerPopScope()` ditambah di keempatnya.
8. ✅ `TaskPolicy::view()/edit()` — cek `$task->pop_id` via `withinPopScope()`.
9. ✅ `Master\DistributionController` — `applyUserScope()` di `index()` (trait baru di model `Distribution`), dropdown `create()`/`edit()`/filter ganti ke `Pop::forUser()`, plus guard per-record `edit()/update()/destroy()` — di luar rencana awal yang cuma nyebut `index()`.
10. ✅ `Master\PopController::show()/edit()/update()/toggleStatus()` — guard `authorizePopScope()` ditambah di keempatnya.
11. ✅ `FopTaskController` — `authorizeFopTaskScope()` ditambah, dipasang di `update()`, `destroy()`, `showHistory()`, `assignToTeam()`, `switchTechnician()` (dua task sekaligus), dan `row()` (fragment endpoint diakses langsung lewat ID) — lebih luas dari rencana awal yang cuma nyebut update/destroy/showHistory.

Regresi baru: `tests/Feature/PopScopeGapFixesTest.php` (12 skenario, satu per temuan yang exploitable lewat HTTP). Test lama yang actor-nya `User::factory()->create()` tanpa `UserRoleScope` disesuaikan — helper `TestCase::giveAllPopScope()` ditambah buat fixture yang butuh akses lintas-POP penuh (bukan buat test yang justru menguji pembatasannya sendiri). Full suite: 1003 passed.
