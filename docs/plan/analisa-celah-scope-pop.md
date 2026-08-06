# BELUM DI IMPLEMENTASIKAN

# Analisa Celah Scope POP

**Tanggal:** 2026-08-05
**Status:** Belum dikerjakan — hasil audit, belum ada fix.
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

## Yang Sudah Benar (verifikasi, bukan asumsi)

- `CustomerController::renderCustomerList`, `TicketController::index`, `InvoiceController::index`, `PaymentController::index`, `DashboardController` (`scopedCustomerQuery`/`scopedInvoiceQuery`/`scopedPaymentQuery`), `NocDashboardController`, `CustomerReportController`, `InvoiceReportController`, `PaymentReportController`, `FopDashboardController`, `NotificationController`, `CollectorController`, `TeknisiWorkloadService` — semua lewat `applyUserScope()`/`getAllowedPopIds()` yang digerbangi `hasAllPopAccess()` dengan benar.
- `Pop::scopeForUser()` dan `HasPopScope::scopeApplyUserScope()` — implementasi trait yang benar, jadi acuan pola yang harus diikuti di titik-titik yang bocor.
- `routes/channels.php` untuk channel `tickets.{popId}`, `invoices.{popId}`, `customers.{popId}`, `fop-tasks.{popId}` — sudah pakai `EffectiveAccessService` dengan benar.

## Rencana Perbaikan (belum dieksekusi)

Prioritas:
1. `FopTaskController::index()`/`history()` — tambah `applyUserScope()` atau filter manual `whereIn('pop_id', $allowedPopIds)` gerbang `hasAllPopAccess()`.
2. `CustomerSurveyController::index()` dan `CustomerVerificationController::index()` — tambah cabang scope POP untuk role non-teknisi, ikuti pola `CollectorController`.
3. `TaskController::getTeknisiForUser()` — ganti kondisi `! empty($allowedPopIds)` jadi eksplisit cek `hasAllPopAccess()`.
4. `routes/channels.php` channel `fop.{pop_id}` — ganti ke `EffectiveAccessService::getAllowedPopIds()`/`hasAllPopAccess()`, samakan pola dengan channel lain di file yang sama.
5. `UserController::updatePops()` — tentukan apakah fitur ini mau dipertahankan (lalu disambungkan ke `UserRoleScope`) atau dihapus karena sudah digantikan matrix role scope. **Perlu keputusan user**, jangan diputuskan sepihak.
6. `CustomerController::paymentInfo()` — ganti ke `EffectiveAccessService`.

Setiap fix wajib disertai test regresi (nama sesuai gejala, bukan sesuai kelas — ikuti konvensi `docs/TASKS.md`/`CLAUDE.md`), dan `EffectiveAccessService::clearCache()` dipanggil di tempat yang relevan kalau ada perubahan scope terkait.

Belum dieksekusi — tunggu keputusan prioritas & konfirmasi item #5 dari user.
