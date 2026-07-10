# Analisa Kesesuaian PRD

# Website Billing ISP Berbasis Master Data Pelanggan

Tanggal analisa: 2026-06-14

## Sumber Analisa

Analisa ini membandingkan implementasi project dengan dokumen PRD dan turunannya:

* `docs/Website Billing ISP PRD.pdf`
* `docs/MVP_SCOPE.md`
* `docs/ACCEPTANCE_CRITERIA.md`
* `docs/MVP_SUCCESS_CHECKLIST.md`
* `docs/TASKS.md`
* `docs/RBAC_MATRIX.md`
* `docs/BUSINESS_RULES.md`

Catatan teknis:

* Isi PDF tidak dapat diekstrak langsung di environment saat analisa karena `pdftotext` dan library Python PDF tidak tersedia.
* Analisa utama memakai dokumen turunan di `docs` yang sudah memecah PRD menjadi scope, acceptance criteria, checklist, dan task.
* Test suite dijalankan dengan `php artisan test`.

## Kesimpulan Umum

Project **belum sepenuhnya sesuai PRD/MVP**, tetapi core MVP sudah banyak terpenuhi.

Hasil test terakhir:

```txt
154 passed
798 assertions
```

Implementasi sudah mencakup banyak fitur inti:

* Login dan logout.
* Role dan permission dasar.
* Middleware permission.
* Pembatasan data berdasarkan POP pada banyak query utama.
* Master POP/Cabang.
* Master paket internet.
* Input pelanggan manual.
* Import pelanggan lama.
* Validasi kelengkapan data pelanggan.
* Aktivasi pelanggan.
* Tagihan manual.
* Pembayaran.
* Dashboard.
* Laporan pelanggan, tagihan, pembayaran, import.
* Audit log.

Namun masih ada gap penting yang perlu ditutup sebelum project dapat dianggap sesuai PRD/MVP secara penuh.

## Status Modul

| Modul | Status | Catatan |
| --- | --- | --- |
| Login | Sesuai sebagian besar | Login, logout, redirect guest, user nonaktif sudah tercakup test. |
| User Management | Belum lengkap | Baru ada daftar user dan assign POP. Belum ada CRUD user penuh dan assign role dari UI. |
| RBAC Dasar | Sebagian sesuai | Role, permission, pivot, middleware, dan seeder sudah ada. Belum ada CRUD role/permission UI. |
| POP/Cabang | Sesuai sebagian besar | CRUD POP, parent-child, tipe, status, dan scope sudah ada. |
| Paket Internet | Sesuai sebagian besar | CRUD paket, harga, speed, status aktif/nonaktif, snapshot layanan sudah ada. |
| Pelanggan Manual | Sebagian sesuai | Input, edit, detail, dokumen, layanan, teknis sudah ada. Ada risiko POP scope pada direct URL. |
| Import Pelanggan | Sesuai sebagian besar | Template, validasi, preview, konfirmasi, batch, error log sudah ada. |
| Validasi Kelengkapan Data | Sesuai sebagian besar | Service validasi dan status kelengkapan sudah ada. |
| Aktivasi Layanan | Sesuai sebagian besar | Aktivasi menolak data belum lengkap dan membuat status siap billing/aktif. |
| Tagihan Manual | Sesuai sebagian besar | Invoice manual, cek duplikasi periode, filter, status invoice sudah ada. |
| Pembayaran | Sesuai sebagian besar | Input pembayaran, bukti pembayaran, update status invoice, detail pelanggan sudah ada. |
| Dashboard | Sesuai sebagian besar | Ringkasan pelanggan, invoice, pembayaran, tunggakan, due invoice, filter POP/periode sudah ada. |
| Laporan | Sesuai sebagian besar | Laporan dan export CSV tersedia, controller melakukan permission dan POP scope check. |
| Audit Log | Sesuai sebagian besar | Model penting dan pembayaran tercatat, audit log bisa dilihat role berwenang. |
| Advanced RBAC | Belum sesuai | Masih roadmap Sprint 11+, belum implementasi database/kode. |

## Gap Prioritas Tinggi

### 1. Direct URL Pelanggan Belum Selalu Terkunci POP Scope

Controller pelanggan memakai route-model binding untuk detail/edit/update. Pada beberapa action belum terlihat pengecekan bahwa pelanggan tersebut masuk scope POP user.

File terkait:

* `app/Http/Controllers/CustomerController.php`
  * `show(Customer $customer)`
  * `edit(Customer $customer)`
  * `update(Request $request, Customer $customer)`

Risiko:

* Admin cabang atau user terbatas dapat mencoba membuka URL pelanggan POP lain secara langsung.
* Acceptance criteria "Admin cabang hanya melihat data cabangnya" belum aman penuh jika hanya mengandalkan list/filter.

Rekomendasi:

* Tambahkan guard seperti:

```php
abort_unless(
    Customer::query()->forUser()->whereKey($customer->id)->exists(),
    403
);
```

* Terapkan pada `show`, `edit`, `update`, dan action lain berbasis `Customer $customer`.
* Tambahkan test direct URL untuk customer detail/edit/update lintas POP.

### 2. Input `pop_id` pada Create/Update Pelanggan Belum Dibatasi ke POP User

Form create/edit sudah mengambil POP dengan `Pop::forUser()`, tetapi validasi request masih memakai:

```php
'pop_id' => 'required|exists:pops,id'
```

Risiko:

* User bisa memanipulasi request dan menyimpan pelanggan ke POP lain.

Rekomendasi:

* Validasi `pop_id` harus memakai daftar POP yang boleh diakses user.
* Alternatif: buat custom rule atau `Rule::in(Pop::forUser()->pluck('id'))`.
* Tambahkan test user cabang tidak bisa create/update pelanggan pada POP lain.

### 3. User Management Belum CRUD Penuh

Dokumen `MVP_SCOPE.md` mensyaratkan:

* CRUD user.
* Assign role ke user.
* Assign POP ke user.

Implementasi saat ini baru mencakup:

* List user.
* Assign POP ke user.

File terkait:

* `app/Http/Controllers/UserController.php`
* `resources/views/users/edit_pops.blade.php`

Gap:

* Belum ada create user.
* Belum ada edit user.
* Belum ada delete/deactivate user.
* Belum ada assign role dari UI.

Rekomendasi:

* Tambahkan CRUD user minimal untuk MVP.
* Pastikan password di-hash.
* Pastikan user nonaktif tidak bisa login tetap terjaga.
* Catat perubahan user dan role ke audit log.

### 4. Role dan Permission Belum Ada CRUD UI

Dokumen `MVP_SCOPE.md` mensyaratkan:

* CRUD role.
* CRUD permission.
* Assign permission ke role.

Implementasi saat ini sudah memiliki:

* Model `Role`.
* Model `Permission`.
* Tabel pivot `role_permissions`.
* Seeder role dan permission.
* Helper `User::hasPermission()`.

Namun belum ditemukan:

* `RoleController`.
* `PermissionController`.
* Route CRUD role/permission.
* Halaman matrix assign permission.

Rekomendasi:

* Tambahkan halaman role-permission matrix minimal.
* Untuk MVP, permission bisa tetap dari seeder dan UI cukup assign permission ke role.
* Untuk Advanced RBAC, tunggu Sprint 12+ karena model permission akan berubah menjadi feature-action.

### 5. Advanced RBAC Belum Diimplementasi

`docs/TASKS.md` Sprint 11 menargetkan Advanced Hierarchical RBAC:

* Role utama: Owner, Atasan, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin.
* Feature Tree.
* Action Permission.
* User Scope: `all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`.
* Pemisahan role dan scope.

Implementasi saat ini masih role MVP:

* Owner.
* Admin Pusat.
* Admin Cabang.
* Finance/Kasir.
* Teknisi.
* Customer Service.

File terkait:

* `database/seeders/RoleSeeder.php`
* `database/seeders/PermissionSeeder.php`
* `database/seeders/RolePermissionSeeder.php`
* `app/Models/User.php`

Kesimpulan:

* Implementasi belum sesuai target Sprint 11+.
* Ini tidak otomatis menggagalkan MVP lama, tetapi belum sesuai roadmap Advanced RBAC.

Rekomendasi:

* Selesaikan normalisasi dokumen Sprint 11 terlebih dahulu.
* Jangan refactor RBAC kode sebelum dokumen Advanced RBAC final.
* Setelah itu lanjut Sprint 12 untuk database dan core engine.

### 6. Route Laporan Tidak Memakai Middleware Permission Khusus

Route laporan berada di dalam group `auth`, tetapi tidak dibungkus middleware `permission:*`.

File terkait:

* `routes/web.php`
  * `/reports/customers`
  * `/reports/invoices`
  * `/reports/payments`
  * `/reports/imports`

Controller sudah melakukan pengecekan manual:

```php
if (!$user->hasPermission('view_reports_all') && !$user->hasPermission('view_reports_own_pop')) {
    abort(403, 'Unauthorized action.');
}
```

Risiko:

* Secara behavior saat ini masih terlindungi oleh controller.
* Namun secara Definition of Done dan standar route security, route belum eksplisit memakai middleware permission.

Rekomendasi:

* Bungkus route laporan dengan middleware permission yang sesuai.
* Bisa tetap mempertahankan controller check sebagai defense-in-depth.

## Gap Prioritas Menengah

### 1. Nama Permission di Dokumen dan Seeder Tidak Sepenuhnya Sama

`docs/RBAC_MATRIX.md` mencantumkan permission granular seperti:

* `create_pop`
* `update_pop`
* `delete_pop`
* `assign_permissions`
* `update_customers`
* `activate_customer_service`
* `export_reports`

Seeder saat ini memakai permission lebih ringkas seperti:

* `manage_pop`
* `manage_users`
* `manage_roles`
* `manage_packages`
* `edit_customers`
* `import_customers`

Risiko:

* Developer/AI bisa salah memakai nama permission.
* Dokumentasi dan implementasi tidak satu sumber kebenaran.

Rekomendasi:

* Untuk MVP, selaraskan dokumen ke permission existing atau sebaliknya.
* Untuk Advanced RBAC, migrasi ke format feature-action sesuai Sprint 12.

### 2. Aktivasi Layanan Perlu Dipastikan Menyimpan Riwayat yang Konsisten

Acceptance criteria menyebut sistem menyimpan riwayat aktivasi.

Implementasi memiliki model dan tabel teknis seperti:

* `CustomerService`
* `CustomerInstallation`
* `Invoice`
* `AuditLog`

Perlu dipastikan apakah "riwayat aktivasi" sudah eksplisit atau hanya tercermin dari perubahan status dan audit log.

Rekomendasi:

* Pastikan event aktivasi tercatat di audit log.
* Jika PRD butuh riwayat terpisah, tambahkan record khusus atau standardisasi pemakaian `CustomerInstallation`/`CustomerService`.

### 3. Import Excel Aktual Perlu Dipastikan dari UI

Kode import memvalidasi rows dari request dan menyediakan template CSV.

Dokumen menyebut Excel/CSV dan mapping kolom.

Rekomendasi:

* Pastikan UI benar-benar bisa membaca file Excel/CSV, bukan hanya copy-paste/parsed rows.
* Jika MVP cukup CSV, dokumen perlu memperjelas batasannya.

## Hal yang Sudah Baik

### POP Scope Sudah Banyak Dipakai

Model utama sudah memiliki scope:

* `Customer::forUser()`
* `Invoice::forUser()`
* `Payment::forUser()`

Ini sudah digunakan di banyak daftar, dashboard, laporan, invoice, dan pembayaran.

### Test Coverage Cukup Luas

Test yang sudah ada mencakup:

* Auth.
* RBAC middleware.
* Customer create/edit/list/detail.
* Import pelanggan.
* POP CRUD.
* Paket internet.
* Invoice create/list.
* Payment input/list/audit.
* Dashboard.
* Report customer/invoice/payment/import.
* Audit log.

Hasil terakhir:

```txt
154 passed
```

### Modul Billing Dasar Sudah Berjalan

Tagihan manual:

* Hanya untuk pelanggan eligible.
* Mengambil harga dari layanan pelanggan.
* Mencegah duplikasi invoice per periode.
* Mendukung status invoice.

Pembayaran:

* Mencatat nominal.
* Mendukung bukti pembayaran.
* Mengubah status invoice menjadi `sebagian` atau `lunas`.
* Tampil di detail pelanggan.

## Rekomendasi Urutan Perbaikan

1. Kunci POP scope di semua action detail/edit/update pelanggan.
2. Validasi `pop_id` create/update pelanggan harus sesuai POP user.
3. Tambahkan test direct URL lintas POP untuk pelanggan.
4. Lengkapi User CRUD dan assign role dari UI.
5. Tambahkan Role/Permission management minimal atau matrix assign permission.
6. Selaraskan nama permission antara dokumen dan seeder.
7. Bungkus route laporan dengan middleware permission.
8. Finalisasi dokumen Sprint 11 Advanced RBAC sebelum refactor RBAC besar.
9. Implementasikan Advanced RBAC sesuai Sprint 12+ setelah dokumen final.

## Status Akhir

Status saat ini:

```txt
Core MVP: sebagian besar sudah ada
MVP PRD penuh: belum selesai
Advanced RBAC roadmap: belum diimplementasi
Test suite: hijau
```

Kesimpulan operasional:

Project sudah layak dilanjutkan sebagai basis MVP, tetapi belum boleh dianggap selesai sesuai PRD sampai gap prioritas tinggi ditutup.
