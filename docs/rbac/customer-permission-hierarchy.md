# Customer Permission Hierarchy — Segregation by Feature (2026-07-28)

## Overview

Customer data di sistem terbagi 4 halaman/scope dengan permission independen:

1. **List Data Pelanggan** — data normal aktif/isolir
2. **List Pelanggan Putus** — data layanan terminated
3. **List Pelanggan Gagal** — data verifikasi rejected
4. **Detail Pelanggan** — profil lengkap identitas/alamat/billing/dokumen

Plus **Fieldwork Page** (tab Perangkat & Pemasangan) — teknisi-only, diakses via permission teknis (bukan `customers.detail.view`).

### Prinsip Desain

- **No bundling:** setiap halaman/fitur = 1 permission di config/rbac.php
- **Role Matrix independent:** tiap permission bisa di-toggle terpisah di UI
- **Role-specific scoping:** role yang gak butuh, gak dikasih — permission deny-by-default
- **Teknisi fieldwork:** genuine akses teknis, tapi blok data umum pelanggan

---

## Permission Map

```
Route                          Controller                      Permission         Access Scope
─────────────────────────────────────────────────────────────────────────────────────────────────
/customers                     CustomerController::index()     customers.view     List aktif/isolir
/customers/terminated          CustomerTerminatedController    customers.terminated.view
/customers/failed              CustomerFailedController       customers.failed.view
/customers/{id}                CustomerController::show()      customers.detail.view
/customers/{id}/perangkat-     CustomerFieldworkController    customers.detail.devices.view
pemasangan                                                      OR install.view
```

---

## Role-Permission Assignment

### Admin
```
✓ customers.view
✓ customers.terminated.view
✓ customers.failed.view
✓ customers.detail.view
✓ customers.detail.devices.*  (wildcard untuk isi/ubah device)
✓ customers.detail.installation.*
```
**Reason:** admin manage semua aspek customer lifecycle.

### NOC
```
✓ customers.view
✓ customers.terminated.view
✓ customers.failed.view
✓ customers.detail.view
✓ customers.detail.survey.*
✓ customers.detail.installation.* (validate, activate, reject)
```
**Reason:** NOC verifikasi survey & pemasangan, kelola reject/approve, pantau list putus/gagal.

### FOP
```
✓ customers.view
✓ customers.terminated.view
✓ customers.failed.view
✓ customers.detail.view
✓ customers.detail.survey.*
✓ customers.detail.installation.*
```
**Reason:** FOP similar-ish scope NOC, assign/schedule task, verify hasil.

### POP Admin
```
✓ customers.view (dalam scope POP-nya via user_role_scope)
✓ customers.terminated.view (dalam scope)
✓ customers.failed.view (dalam scope)
✓ customers.detail.view (dalam scope)
✓ customers.detail.devices.*
✓ customers.detail.installation.*
```
**Reason:** admin lokal per-cabang, akses semuanya tapi hanya POP-nya sendiri.

### Atasan
```
✓ customers.view
✓ customers.terminated.view
✓ customers.failed.view
✓ customers.detail.view (bypass scope POP — lihat semua)
✗ customers.detail.devices.*
✗ customers.detail.installation.*
```
**Reason:** leadership lihat data keseluruhan, tapi bukan eksekutor teknis.

### Teknisi
```
✗ customers.view
✗ customers.terminated.view
✗ customers.failed.view
✗ customers.detail.view
✓ customers.detail.devices.view (fieldwork page only)
✓ customers.detail.devices.update
✓ customers.detail.installation.view (fieldwork page only)
✓ customers.detail.installation.update
✓ customers.detail.installation.activate
```
**Reason:** teknisi lapangan isi data teknis via fieldwork page, BLOK akses list pelanggan & detail umum.

**Fieldwork page** (`/customers/{id}/perangkat-pemasangan`):
- Load 3 relasi only: `customerDevice`, `customerTechnicalDetail`, `installations.technician`
- Render tab `_device` + `_installation` (reuse partial dari halaman Detail)
- Modal form isi perangkat & pemasangan (sama persis UI-nya)
- **Teknisi gak perlu `customers.detail.view`** — cukup `customers.detail.devices.view|installation.view`

### Helpdesk
```
✓ customers.view
✓ customers.detail.view (lihat identitas/alamat/paket customer yang registrasi)
✗ customers.terminated.view
✗ customers.failed.view
✗ customers.detail.devices.*
✗ customers.detail.installation.*
```
**Reason:** helpdesk register & edit profil pelanggan, bukan kerjakan teknis/verifikasi.

### Sales
```
✓ customers.view
✓ customers.detail.view (identitas/alamat)
✗ customers.terminated.view
✗ customers.failed.view
✗ customers.detail.devices.*
✗ customers.detail.installation.*
```
**Reason:** sales kelola prospek/registered customer, bukan teknis/verifikasi/billing.

---

## Controller Implementation

### Concerns\RendersCustomerList (trait)
Query builder + render bersama tiga halaman daftar pelanggan.

```php
protected function renderCustomerList(
    Request $request,
    ?string $forcedStatusGroup = null,
    string $view = 'customers.index'
): View
```

- `$forcedStatusGroup` dikunci dari controller, **bukan** dari query string — halaman
  Putus/Gagal tidak bisa "dipaksa ganti grup" lewat URL di route yang permission-nya beda.
- `$view` menentukan Blade mana yang dirender; tiap halaman punya view sendiri.
- Ditaruh di trait, **bukan** di `CustomerController`: sebelumnya dua controller halaman-daftar
  `extends CustomerController` demi satu method protected, ikut mewarisi ~3.400 baris method
  tulis (store/update/destroy/import/aktivasi) yang bukan urusannya.

### CustomerController
- **`index(Request $request)`** — List Data Pelanggan
  - Guard: `permission:customers.view`
  - Redirect lama URL `?status_group=terminated|failed` ke route baru
  - `use RendersCustomerList` → `$this->renderCustomerList($request)` (view `customers.index`)

- **`show(Customer $customer)`** — Detail Pelanggan
  - Guard: `permission:customers.detail.view`
  - Load 17 relasi (city, district, village, packages, services, pop, invoices, payments, dll)
  - Render semua tab

### CustomerTerminatedController (extends Controller, use RendersCustomerList)
- **`index(Request $request)`** — List Pelanggan Putus
  - Guard: `permission:customers.terminated.view`
  - Call `$this->renderCustomerList($request, 'terminated', 'customers.terminated')`

### CustomerFailedController (extends Controller, use RendersCustomerList)
- **`index(Request $request)`** — List Pelanggan Gagal
  - Guard: `permission:customers.failed.view`
  - Call `$this->renderCustomerList($request, 'failed', 'customers.failed')`

### CustomerFieldworkController (NEW, extends Controller)
- **`show(Customer $customer)`** — Perangkat & Pemasangan
  - Guard: `permission:customers.detail.devices.view|customers.detail.installation.view`
  - Load 3 relasi only: `customerDevice`, `customerTechnicalDetail`, `installations.technician`
  - Render view `customers.fieldwork` (tab _device + _installation, omit other tabs)

---

## Migration Path (Existing Systems)

Jika sebelumnya teknisi punya `customers.view` (old design):

1. **Generate permission:** `php artisan rbac:generate-permissions`
   - Buat 3 permission baru: `customers.terminated.view`, `customers.failed.view`, `customers.detail.view`

2. **Run seeder:** `php artisan db:seed --class=RolePermissionSeeder`
   - Assign permission ke role sesuai map di bagian ini
   - **Teknisi:** REMOVE `customers.view`, keep `customers.detail.devices.*` + `customers.detail.installation.*`

3. **Clear permission cache:**
   ```php
   User::where('roles.code', 'teknisi')
       ->each(fn($u) => app(EffectiveAccessService::class)->clearCache($u));
   ```

4. **Manual check**: akses `/customers` as Teknisi → harus 403

---

## Backward Compatibility

Old URLs `?status_group=terminated|failed` redirect ke route baru:

```php
// CustomerController::index()
$statusGroup = trim((string) $request->query('status_group', ''));
if ($statusGroup === 'terminated') {
    return redirect()->route('customers.terminated');
}
if ($statusGroup === 'failed') {
    return redirect()->route('customers.failed');
}
```

Bookmarks & external links lama tetap work, cuma dapat redirect.

---

## Testing

Test files updated (2026-07-28):

| Test | Change |
|------|--------|
| `CustomerDeviceTest::test_device_data_is_visible_on_customer_detail()` | Use `route('customers.fieldwork')` instead `customers.show` |
| `CustomerDeviceSensitiveFieldTest::test_teknisi_can_see_and_update_sensitive_fields()` | Use fieldwork route |
| `CustomerInstallationTest::test_installation_data_is_visible_on_customer_detail()` | Use fieldwork route |
| `CustomerListFilterKeepsStatusGroupTest` | Use `route('customers.terminated')` / `route('customers.failed')` |

Tambahan (2026-08-12) — pemisahan view:

| Test | Isi |
|------|-----|
| `CustomerListSeparateViewsTest` | `assertViewIs` per halaman (`customers.index` / `customers.failed` / `customers.terminated`), tabel arsip tidak bocor ke List Pelanggan, isolasi status per halaman, query string tidak bisa menimpa `$forcedStatusGroup` |
| `RestoredFailedCustomerStaysVisibleTest` | Use `route('customers.failed')` |
| `CustomerListStatusTimestampOrderingTest::test_tab_gagal_*` | Use `route('customers.failed')` |

**Test result:** 655 passed, 29 failed (pre-existing storage permission env issue, not caused by RBAC changes).

---

## Database Schema

### New Features (inserted by FeatureSeeder 2026-07-28)

```sql
INSERT INTO features (code, parent_code, name, type, sort_order, ...)
VALUES
  ('customers.terminated', 'customers', 'List Pelanggan Putus', 'SUB_FEATURE', 3),
  ('customers.failed', 'customers', 'List Pelanggan Gagal', 'SUB_FEATURE', 4);
```

### New Permissions (generated by PermissionGeneratorService)

```sql
INSERT INTO permissions (code, name, feature_id, action_id, ...)
VALUES
  ('customers.terminated.view', 'Lihat List Pelanggan Putus', <feature_id>, <view_action_id>),
  ('customers.failed.view', 'Lihat List Pelanggan Gagal', <feature_id>, <view_action_id>),
  ('customers.detail.view', 'Lihat Detail Pelanggan', <feature_id>, <view_action_id>);
```

No table schema changes — purely permission layer split.
