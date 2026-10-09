# Analisa: Permission Matrix — Banyak Permission Belum Terimplementasi

**Sumber temuan:** BUG 10 (laporan user). Matrix Hak Akses (`resources/views/roles/matrix.blade.php`) tampilkan permission generated `PermissionGeneratorService` (features × actions), tapi banyak checkbox **tidak menggerbangi route/guard apa pun**. Admin bisa centang permission ini merasa sudah membatasi sesuatu — padahal tidak ngefek.

Peta ini sudah ada parsial di `$permissionDescMap` (matrix.blade.php baris 181-356), ditandai `[Belum aktif]` / `[Nonaktif]`. Dokumen ini kumpulkan jadi satu rancangan supaya ditangani sistematis, bukan nebeng komentar blade.

## Daftar Permission Belum Aktif (tersisa setelah Keputusan 1 untuk 6 fitur — `master_wilayah.*` & sisa daftar di bawah masih terbuka)

Catatan: 6 baris `*.delete` (pops, packages, item_categories, items, work_tools, ticket_issue_categories) sudah aktif per Keputusan 1 dan dihapus dari daftar ini.

| Kode | Alasan belum aktif |
|---|---|
| `users.delete` | User dinonaktifkan lewat status, bukan dihapus |
| `audit_logs.export` | Belum ada route ekspor audit log |
| `master_wilayah.create` | Data wilayah diisi seeder, belum ada form tambah |
| `master_wilayah.update` | Data wilayah diisi seeder, belum ada form ubah |
| `master_wilayah.delete` | Tidak menggerbangi route apa pun |
| `master_status_pelanggan.create` | Di-seed sistem, belum ada form tambah |
| `master_status_pelanggan.update` | Di-seed sistem, belum ada form ubah |
| `master_status_pelanggan.delete` | Status pelanggan tidak dihapus dari UI |
| `customers.detail.identity.update` | Masih ikut `customers.update`, belum dipisah per tab |
| `customers.detail.address.update` | Masih ikut `customers.update`, belum dipisah per tab |
| `customers.detail.packages.update` | Masih ikut `customers.update` |
| `customers.detail.documents.download` | Unduhan digerbangi `customers.detail.documents.view`, bukan permission ini |
| `customers.detail.documents.delete` | Dokumen pelanggan belum bisa dihapus dari UI |
| `invoices.update` | Nominal tagihan terbit sengaja tidak diedit dari UI |
| `invoices.delete` | Tagihan lunas tidak boleh hilang dari jejak |
| `invoices.print` | Belum ada route sendiri; struk ikut `payments.view` |
| `payments.delete` | Jejak kas tidak dihapus |
| `payments.validate` | Verifikasi kas kolektor pakai `collector_worksheet.validate` |
| `payments.approve` | Belum dipakai route mana pun |
| `reports.export` | Tombol ekspor sudah digerbangi `reports.view` |
| `reports.print` | Belum ada route cetak laporan terpisah |

## Kategori Akar Masalah

1. **Delete diganti soft-disable** (user, pops, packages, master_status_pelanggan, item_categories, items, work_tools, ticket_issue_categories, master_wilayah) — pola "arsip tidak dihapus" konsisten di banyak modul, tapi permission generator tetap bikin aksi `delete` buat tiap fitur tanpa cek apakah fiturnya betulan support hapus.
2. **Update per-tab belum granular** (customers.detail.{identity,address,packages}.update) — permission generator bikin child-feature `customers.detail.*` lengkap dengan aksi `update` sendiri, tapi implementasi masih satu gerbang besar `customers.update`.
3. **Aksi yang sudah dipindah ke permission lain** (payments.validate, documents.download, reports.export, invoices.print) — nama permission menyiratkan fungsi yang sebenarnya digerbangi kode lain.
4. ~~**Peninggalan fitur yang sudah dihapus**~~ — SELESAI (Keputusan 2, 2026-10-02).
5. **Belum pernah diberi route** (audit_logs.export, payments.approve, reports.print, invoices.update/delete, users.delete).

## KEPUTUSAN 1 — Delete kondisional untuk 7 fitur master data (2026-10-01)

Berlaku untuk: `pops`, `packages`, `item_categories`, `items`, `work_tools`, `ticket_issue_categories`, `master_wilayah`.

**Logic:** satu tombol "Hapus", bukan dua tombol.
- Record **punya data terkait** (relasi anak masih ada) → tombol hapus otomatis jadi **Nonaktifkan** (soft, status flag). `delete` permission tidak berfungsi pada state ini.
- Record **tanpa data terkait** (relasi anak kosong) → tombol hapus jadi **Delete permanen** (hard delete dari DB). `deactivate`/nonaktif tidak relevan pada state ini (gak ada gunanya nonaktifkan record kosong).

Jadi bukan dua permission terpisah `delete` vs `nonaktif` yang dicentang manual di Matrix — cukup **satu action per fitur**, keputusan hard/soft ditentukan runtime oleh service berdasarkan cek dependency. Permission `{fitur}.delete` yang sudah ada di Matrix dipakai buat gate tombol itu (baik hasil akhirnya hard delete atau soft deactivate).

**Definisi "ada data terkait" per fitur (perlu dicek satu-satu saat implementasi):**

| Fitur | Cek dependency ke |
|---|---|
| `pops` | `customers.pop_id`, `tasks`/`fop_tasks` di POP itu, `users` dengan scope POP itu, `pop_sequences`/CID |
| `packages` | `customer_services.package_id`, `invoices` (histori tagihan refer ke package) |
| `item_categories` | `items.item_category_id` |
| `items` | `inventory_serials`, baris stok gudang (`warehouse_stock` / sejenis), riwayat transaksi gudang |
| `work_tools` | riwayat peminjaman/assignment alat kerja ke teknisi |
| `ticket_issue_categories` | `tickets.issue_category_id` |
| `master_wilayah` (desa/kec/kota/provinsi) | `customers.desa_id`/level wilayah terkait |

**Gap tambahan ketauan dari permintaan ini:** `master_wilayah` sekarang **cuma punya `view`** — create/update-nya belum ada form UI sama sekali (data cuma dari seeder). Logic delete-kondisional butuh create+update dulu biar wilayah punya siklus hidup lengkap (tambah wilayah baru → bisa dihapus kalau belum dipakai). Scope kerjanya jadi: **bangun CRUD penuh `master_wilayah` dulu, baru pasang delete-kondisional di 7 fitur ini sekaligus.**

Ini kerja lintas 7 modul (migration kolom status kalau belum ada, service layer, controller, view, test per modul) — **di luar Sprint 8.10 aktif**, perlu didaftarkan sebagai ADHOC terpisah (pola `ADHOC-120`) sebelum dieksekusi, bukan nebeng task Matrix ini.

## KEPUTUSAN 2 — SELESAI DIKERJAKAN (2026-10-02)

Dieksekusi persis sesuai rencana: migration `2026_10_02_084341_remove_retired_noc_worksheet_tab_features` hapus baris `features` (`noc_worksheet.masuk`/`noc_worksheet.diproses`) — `permissions.feature_id` & `role_permissions.permission_id` sama-sama `cascadeOnDelete()` jadi permission + pivot role ikut tersapu otomatis, gak perlu migration pivot manual terpisah seperti draf awal. Definisi dihapus dari `TicketFeatureSeeder`, `RolePermissionSeeder`, `config/rbac.php` (action + desc map), `matrix.blade.php` (desc map), komentar `NocWorksheetController`.

Audit log aman: `audit_logs` gak punya FK ke `permissions` (cuma `auditable_type`/`auditable_id` polymorphic + JSON `old_values`/`new_values`), jadi baris lama kalaupun nyimpen kode permission ini di JSON tetap aman dibaca sebagai teks.

**Bug lepas ketemu saat verifikasi** (bukan dari rancangan ini, pre-existing): `TicketingRbacTest::test_revoking_one_permission_only_closes_that_page` & `test_forbidden_archive_page_is_not_rendered_in_navigation` pakai role `admin` buat cek "halaman lain tetap kebuka" — tapi snapshot UI 2026-09-29 di `RolePermissionSeeder` sudah nyabut SEMUA permission `tickets.*`/`noc_*` dari `admin` (disengaja, ada di komentar seeder). Diperbaiki: ganti ke role `noc` (satu-satunya role non-wildcard yang masih pegang kelima permission ticketing sekaligus). 25/25 test lolos setelah perbaikan.

## KEPUTUSAN 3 — SELESAI DIKERJAKAN (2026-10-02)

Koreksi temuan awal: `warehouse`, `warehouse_transfer`, `warehouse_issue`, `warehouse_custody`, `warehouse_traceability` **SUDAH** terdaftar di `functionalCategories['group_warehouse']['features']` dan di `$featureMeta` — klaim sebelumnya ("gak ketangkep kategori") salah, cuma `$permissionDescMap`-nya yang kosong (jatuh ke teks generik `$actionMetaMap`). Ditambahkan 9 baris desc per-kode (`warehouse.view`, `warehouse_transfer.view/create/receive`, `warehouse_issue.view/create`, `warehouse_custody.view`, `warehouse_traceability.view`).

`warehouse_transfer_invoice.view` ternyata BENERAN gak kecatet di mana pun (features list, featureMeta, permissionDescMap) — satu-satunya temuan yang akurat dari breakdown sebelumnya. Permission ini nyata & punya alasan desain sendiri (root terpisah dari `warehouse_transfer` karena invoice py harga satuan barang, lihat komentar `config/rbac.php:569-577`). Ditambahkan ke `group_warehouse`, `$featureMeta`, & `$permissionDescMap`.

## KEPUTUSAN 1 — SELESAI (6 fitur 2026-10-05; `master_wilayah` 2026-10-05)

`master_wilayah` (Kota/Kecamatan/Desa):
- CRUD di halaman "Kelola Wilayah" (`/master/wilayah/kelola`), gate `master_wilayah.create|update|delete`. Induk tidak bisa diubah saat edit.
- Hapus DITOLAK (tidak ada nonaktifkan — wilayah tidak punya status) selama masih dipakai: pelanggan/alamat/tiket/FOP di dalamnya, atau wilayah anak. Cek lewat `MasterRecordRemovalService::hasDependents`.
- Bug yang tertangkap test dan diperbaiki: `validate()` mengembalikan `city_id`/`district_id` walau rule-nya kosong saat edit, sehingga induk kecamatan bisa berpindah. Sekarang dibuang sebelum `update`.
- Test: `RegionMasterCrudTest` (8). Total 47 test lolos (+ TicketingRbacTest, RolePermissionMatrixTest, MasterRecordRemovalTest).
- Provinsi tidak dibuat sebagai level tersendiri (saran: tunda, provinsi tetap string di `customer_addresses`).

Diimplementasi:
- `App\Services\MasterRecordRemovalService` — `remove()` cek dependensi lewat **FK introspection** (`information_schema.KEY_COLUMN_USAGE` di MySQL, `PRAGMA foreign_key_list` di SQLite), bukan daftar relasi manual. Ada referensi (apa pun, termasuk `nullOnDelete`/`cascadeOnDelete`) → nonaktifkan; tidak ada → hard delete.
- `destroy()` + route `DELETE` di `PopController`, `ItemController`, `ItemCategoryController`, `WorkToolController`, `TicketIssueCategoryController`, `InternetPackageController`; gate `{fitur}.delete`. POP wajib lewat `authorizePopScope`. `ItemCategory::CODE_LAINNYA` tidak bisa dihapus.
- Tombol Hapus di 6 view index (hanya tampil bila punya `{fitur}.delete`). Label tombol generik "Hapus" — label state (Hapus/Nonaktifkan) tidak dihitung per baris karena cek dependensi per baris = N+1 query; hasil akhir dijelaskan lewat flash message & teks konfirmasi.
- Deskripsi `[Belum aktif]` untuk 6 kode `*.delete` di Matrix sudah diperbarui.
- Test: `tests/Feature/MasterRecordRemovalTest.php` (9 test, lolos).

Belum: `master_wilayah` (create/update/delete). Tetap butuh keputusan desain — lihat bagian di bawah.

## KEPUTUSAN 1 — rincian awal (historis)

Investigasi kode (2026-10-02) sebelum eksekusi:
- `pops`, `packages`, `item_categories`, `items`, `work_tools`, `ticket_issue_categories` — SEMUA sudah punya Controller + Model (`Master/PopController`, `Master/PackageCategoryController`? perlu recek per fitur) dengan pola toggle-status aktif. **Tidak satu pun punya route `destroy`** (`grep routes/web.php` nol hasil) — jadi delete-kondisional di sini murni nambah endpoint baru + service dependency-check, gak perlu bongkar yang ada.
- `master_wilayah` — **beda kelas sama sekali**. Gak ada Model `Wilayah` apa pun; cuma `City`/`District`/`Village` (model Provinsi gak ada — kemungkinan provinsi cuma field, perlu dicek). Controller-nya `Master/RegionController@index` — CUMA method itu, gak ada store/update/destroy. Ini bukan "lupa pasang guard delete", ini **belum ada CRUD sama sekali** — data wilayah dipakai luas di alamat pelanggan & import legacy (`docs/IMPORT_SPEC.md`), jadi nambah create/update/delete di sini berisiko ke integritas data rujukan kalau dikerjakan buru-buru ikut rancangan 7-fitur yang sama.

**Rekomendasi:** pisah jadi 2 langkah, bukan 1 batch:
1. Delete-kondisional untuk 6 fitur yang sudah punya CRUD penuh (`pops`, `packages`, `item_categories`, `items`, `work_tools`, `ticket_issue_categories`) — bisa dikerjakan sekarang, scope jelas & kontenporer dengan apa yang sudah ada.
2. CRUD penuh `master_wilayah` (City/District/Village, provinsi kalau perlu) — didaftarkan ADHOC terpisah, perlu keputusan desain sendiri (apakah provinsi jadi level sendiri, apakah ini re-pakai struktur Village/District/City yang sudah ada atau butuh model baru) sebelum ditulis.

## Yang TIDAK termasuk bug ini

Deskripsi `[Belum aktif]` di `$permissionDescMap` **sudah benar dan sudah transparan ke admin** — bukan sumber bug, justru dokumentasi yang mencegah salah kira. Bug sebenarnya ada di tingkat *permission generator* (men-generate kombinasi feature×action yang sebagian tidak punya implementasi), bukan di halaman matrix itu sendiri.
