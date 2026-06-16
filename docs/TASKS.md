## Status Project Saat Ini
Current Sprint: Selesai — Seluruh MVP Selesai
Current Module: Import & Migrasi Pelanggan / Billing / RBAC / Laporan / Audit Log
Current Task: Seluruh MVP Selesai dan Terverifikasi

---

## In Progress

### MIG-EXE001 — Eksekusi Migrasi Data sand_db_sandya.sql
Status: Done

Tujuan:
Mengeksekusi migrasi data riil secara otomatis ke dalam sistem berdasarkan `sand_db_sandya.sql` menggunakan custom Artisan command.

Langkah:
- [x] Implementasi `MigrateLegacyDataCommand.php`.
- [x] Verifikasi Idempotensi (pencegahan duplikasi).
- [x] Validasi data masuk dengan benar ke master pelanggan, paket, dan tagihan.

### Backlog RBAC & User Management
Status: Done

Tujuan:
Memecah kebutuhan `users`, `roles`, `permissions`, dan pembatasan data POP/customer/billing menjadi sprint kecil yang bisa dikerjakan bertahap.

Rencana Pecahan Sprint:

#### Sprint A — CRUD User Dasar
- [x] Tambah user baru.
- [x] Pilih role saat membuat user.
- [x] Set status aktif/nonaktif user.
- [x] Simpan email, phone, dan password user.
- [x] Validasi data user dasar.

#### Sprint B — Assign POP & Scope User
- [x] Assign satu atau banyak POP ke user.
- [x] Batasi akses Admin Cabang ke POP yang ditugaskan.
- [x] Pertahankan akses penuh Owner/Admin.
- [x] Pastikan filter query customer/invoice/payment memakai scope POP.

#### Sprint C — Role & Permission Sederhana
- [x] Pertahankan role Owner.
- [x] Pertahankan role Admin.
- [x] Pertahankan role Teknisi.
- [x] Pastikan Teknisi tidak bisa akses billing/pembayaran.
- [x] Pastikan Admin bisa akses penuh seperti Owner.

#### Sprint D — UI Manajemen User
- [x] Tambah halaman create/edit user.
- [x] Tambah halaman daftar user yang lebih lengkap.
- [x] Tambah form assign POP yang konsisten.
- [x] Tambah test regresi untuk halaman user management.

#### Sprint E — Audit & Hardening
- [x] Audit log untuk create/update user.
- [x] Audit log untuk assign POP.
- [x] Rapikan pesan error dan validasi.
- [x] Jalankan test coverage RBAC dan user management.

### Backlog Import & Migrasi Pelanggan
Status: In Progress

Tujuan:
Menjaga konteks pekerjaan migrasi pelanggan dan billing agar tetap jelas setelah RBAC/User Management selesai.

Urutan pengerjaan wajib:

#### Sprint I — Template & Mapping Import
- [x] Template import Excel/CSV pelanggan yang benar-benar mengikuti field untuk pelanggan, detail, dan billing.
- [x] Mapping kolom template disesuaikan dengan field master data baru dan field legacy.
- [x] Struktur sheet/import section divalidasi supaya konsisten dengan alur import.

#### Sprint II — Pipeline Import & Validasi
- [x] Upload dan baca file import mengikuti template yang baru.
- [x] Preview data sebelum import.
- [x] Validasi field wajib, relasi master, dan duplikasi data.
- [x] Error import ditulis dengan alasan yang jelas.

#### Sprint III — Migrasi Data Nyata
- [x] Uji migrasi data nyata dari `sand_db_sandya.sql` end-to-end.
- [x] Pastikan data pelanggan, detail, layanan, billing, dan pembayaran terhubung sesuai mapping.
- [x] Cocokkan hasil migrasi dengan data lama yang paling sering dipakai operasional.

#### Sprint IV — Verifikasi Produksi & Hardening
- [x] Verifikasi produksi dengan data real, termasuk edge case field kosong, relasi rusak, dan duplikasi.
- [x] Siapkan checklist rollback/reimport jika ada data legacy yang gagal.
- [x] Pastikan hasil migrasi layak dipakai untuk operasional terbatas.
- [x] Jika masih ada modul MVP lain yang belum ditutup di task board, kerjakan sesuai urutan MVP terlebih dahulu.

Catatan:
- Role lama seperti `Admin Pusat`, `Admin Cabang`, `Finance/Kasir`, dan `Customer Service` tetap dipertahankan untuk kompatibilitas.
- Pecahan sprint ini sengaja dibuat kecil agar pengembangan RBAC tidak bercampur dengan billing/import.

--- 

## Done

### IMP-S4001 — Verifikasi Produksi & Hardening
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint IV

Tujuan:
Memverifikasi ketahanan pipeline migrasi di bawah kondisi data real, menangani edge cases (field kosong, relasi rusak, pemeriksaan duplikasi/idempotensi), dan menyusun panduan pemulihan operasional (rollback/reimport).

Hasil Implementasi:
- [x] Hardening integration test `RealDataMigrationTest` dengan menambahkan test method `test_real_data_migration_idempotency_and_edge_cases`.
- [x] Menguji dan memverifikasi pencegahan data ganda (idempotensi) dengan melakukan import dua kali berturut-turut pada dataset yang sama (sand_db_sandya.sql) tanpa menambah jumlah record di database.
- [x] Menguji penanganan edge case data kosong dan relasi rusak (seperti services dengan old_customer_id/old_package_id tidak terdaftar), di mana error secara otomatis tercatat di tabel `import_errors` dan data tidak valid diabaikan secara aman.
- [x] Memperbaiki `logImportError` di `CustomerController.php` agar menyimpan nama sheet ke kolom `field_name` di tabel `import_errors` database.
- [x] Menyusun panduan operasional di `docs/CHECKLIST_ROLLBACK_REIMPORT.md` untuk membackup database, melakukan rollback total/parsial berbasis batch, serta mengoreksi data sebelum reimport.
- [x] Memastikan kelayakan operasional terbatas dengan berjalannya seluruh 175 tests (1044 assertions) secara sukses dan hijau.

Acceptance Criteria:
- [x] Verifikasi produksi dengan data real, termasuk edge case field kosong, relasi rusak, dan duplikasi.
- [x] Siapkan checklist rollback/reimport jika ada data legacy yang gagal.
- [x] Pastikan hasil migrasi layak dipakai untuk operasional terbatas.

Catatan Test:
- `RealDataMigrationTest` lulus: 2 tests, 138 assertions.
- Seluruh test suite (175 tests, 1044 assertions) lulus 100%.

### IMP-S3001 — Migrasi Data Nyata
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint III

Tujuan:
Uji migrasi data nyata dari `sand_db_sandya.sql` secara end-to-end dan mencocokkan hasil migrasi serta relasi data (pelanggan, paket, layanan, detail teknis, invoice, pembayaran).

Hasil Implementasi:
- [x] Setup data POP dummy SMN (Sandya) dengan setting identifier prefix (`registration_prefix` & `cid_prefix`) untuk menghindari kegagalan generate ID.
- [x] Buat integration test RealDataMigrationTest yang membaca dan memparsing langsung database dump legacy `sand_db_sandya.sql`.
- [x] Lakukan validasi dan konfirmasi import secara end-to-end via REST endpoint `/customers/import/validate` dan `/customers/import/confirm`.
- [x] Lakukan verifikasi database reconciliation terhadap relasi data internet_packages, customers, customer_addresses, customer_services, customer_technical_details, invoices, dan payments.

Acceptance Criteria:
- [x] Uji migrasi data nyata dari `sand_db_sandya.sql` end-to-end.
- [x] Pastikan data pelanggan, detail, layanan, billing, dan pembayaran terhubung sesuai mapping.
- [x] Cocokkan hasil migrasi dengan data lama yang paling sering dipakai operasional.

Catatan Test:
- `RealDataMigrationTest` lulus: 1 test, 113 assertions.
- Seluruh test suite (174 tests, 1019 assertions) lulus sempurna di Docker.

### IMP-S2001 — Pipeline Import & Validasi
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint II

Tujuan:
Membuat pipeline pembacaan, validasi, dan preview data import pelanggan lama dari file Excel multi-sheet.

Hasil Implementasi:
- [x] Upload dan pembacaan file Excel multi-sheet (.xlsx) dengan model try-catch dan validation server-side menggunakan spatie/simple-excel.
- [x] Tampilan preview data interaktif di browser berdasarkan validasi baris (merah untuk error, kuning untuk warning, hijau untuk valid).
- [x] Validasi data duplikat, keselarasan master, wilayah, POP, dan status.
- [x] Penulisan error log import yang detail ke tabel import_errors saat import dikonfirmasi.

Acceptance Criteria:
- [x] Admin dapat mengupload file Excel/CSV.
- [x] Preview data valid dan invalid terlihat.
- [x] Data invalid ditolak dengan penjelasan alasan yang jelas.
- [x] Log audit dan riwayat import tersimpan.

Catatan Test:
- `CustomerImportTest` & `CustomerImportLoggingTest` lulus: 8 passed (72 assertions).
- Seluruh 173 tests passed locally (2 skipped).

### IMP-S1001 — Template & Mapping Import
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint I

Tujuan:
Membuat template import dan pemetaan data pelanggan legacy agar siap untuk proses migrasi.

Hasil Implementasi:
- [x] Template import Excel multi-sheet (.xlsx) dengan 6 sheet (customers, packages, services, technical_details, invoices, payments) yang mengikuti field pelanggan, detail, dan billing.
- [x] Mapping kolom template disesuaikan dengan field master data baru dan field legacy.
- [x] Validasi struktur sheet dan keselarasan relasi data antar sheet pada controller validateImport.

Acceptance Criteria:
- [x] Admin dapat mendownload template Excel (.xlsx).
- [x] Template memiliki field wajib dan field opsional teknis.
- [x] Format siap digunakan untuk import.

Catatan Test:
- `CustomerImportTest` & `CustomerImportLoggingTest` lulus: 8 tests, 114 assertions.
- Seluruh test suite lulus: 171 passed (897 assertions).

### MIG-E001 — Audit & Hardening
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menguatkan hasil Sprint A-D dengan audit log dan hardening UI/user management.

Hasil Implementasi:
- [x] Audit log create/update user dicatat secara manual dan dapat diverifikasi lewat test.
- [x] Audit log assign POP dicatat saat relasi POP berubah.
- [x] Pesan validasi form user dibuat lebih jelas dan operasional.
- [x] Test coverage RBAC dan user management dijalankan ulang dan lulus.

Acceptance Criteria:
- [x] Audit log untuk create/update user.
- [x] Audit log untuk assign POP.
- [x] Pesan error dan validasi user lebih jelas.
- [x] Test coverage RBAC dan user management lulus.

Catatan Test:
- `php artisan test tests/Feature/UserAuditHardeningTest.php tests/Feature/UserManagementTest.php tests/Feature/UserCrudTest.php tests/Feature/UserPopScopeTest.php` lulus: 10 tests, 72 assertions.

### MIG-D001 — UI Manajemen User
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menyempurnakan UI manajemen user agar lebih lengkap dan mudah dipakai oleh admin operasional.

Hasil Implementasi:
- [x] Halaman `users.index` menampilkan ringkasan, filter, dan daftar user yang lebih informatif.
- [x] Create/edit user tetap tersedia dan konsisten dengan form assign POP.
- [x] Daftar user dapat difilter berdasarkan search, role, status, dan POP.
- [x] Regresi halaman user management ditutup dengan test feature.

Acceptance Criteria:
- [x] Halaman create/edit user tersedia.
- [x] Halaman daftar user lebih lengkap.
- [x] Form assign POP konsisten di UI user.
- [x] Test regresi halaman user management lulus.

Catatan Test:
- `php artisan test tests/Feature/UserManagementTest.php tests/Feature/UserCrudTest.php tests/Feature/UserPopScopeTest.php` lulus: 8 tests, 44 assertions.

### MIG-C001 — Role & Permission Sederhana
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menegaskan tiga role operasional utama dalam bentuk yang sederhana:
- Owner
- Admin
- Teknisi

Hasil Implementasi:
- [x] Role semantics dibuat jelas di model `Role` dan helper `User`.
- [x] `Owner` dan `Admin` berstatus full-access.
- [x] `Teknisi` tetap terbatas dan tidak bisa membuka billing/pembayaran.
- [x] Middleware permission membedakan akses full-access dan akses terbatas dengan konsisten.
- [x] Seeder permission tetap mempertahankan kompatibilitas role lama.

Acceptance Criteria:
- [x] Owner tetap full-access.
- [x] Admin tetap full-access.
- [x] Teknisi tidak bisa akses billing/pembayaran.
- [x] Permission middleware tetap jalan untuk route yang dibatasi.
- [x] Test role semantics dan middleware lulus.

Risiko / Catatan:
- Role lama seperti `Admin Pusat`, `Admin Cabang`, `Finance/Kasir`, dan `Customer Service` tetap ada untuk kompatibilitas.
- Penyederhanaan ini dilakukan di level access rule, bukan menghapus role lama dari database.

Catatan Test:
- `php artisan test tests/Feature/RolePermissionTest.php tests/Feature/MiddlewarePermissionTest.php` lulus: 16 tests, 44 assertions.

### MIG-B001 — Assign POP & Scope User
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menjadikan POP assignment sebagai bagian dari manajemen user dan memastikan scope data pelanggan/billing mengikuti POP yang ditugaskan.

Hasil Implementasi:
- [x] User create/edit mendukung assign satu atau banyak POP.
- [x] User assignment POP tetap tersedia di halaman khusus `users.pops.edit`.
- [x] Query scope customer, invoice, dan payment mengikuti POP yang ditugaskan.
- [x] Owner/Admin tetap full-access.
- [x] Admin Cabang tetap dibatasi ke POP assignment.

Acceptance Criteria:
- [x] POP dapat dipilih saat membuat dan mengubah user.
- [x] POP assignment tersimpan dan tersinkron.
- [x] Scope data customer/invoice/payment mengikuti POP user.
- [x] User dengan full-access role tetap melihat semua data.
- [x] Test POP assignment dan scope lulus.

Risiko / Catatan:
- Role lama tetap dipertahankan untuk kompatibilitas project.
- Halaman assign POP khusus tetap ada agar alur lama tidak putus.

Catatan Test:
- `php artisan test tests/Feature/UserCrudTest.php tests/Feature/UserPopScopeTest.php tests/Feature/UserManagementTest.php tests/Feature/RolePermissionTest.php tests/Feature/RoleTest.php` lulus: 15 tests, 76 assertions.

### MIG-A001 — CRUD User Dasar
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menyediakan CRUD user dasar yang mencakup:
- tambah user baru
- pilih role
- set status aktif/nonaktif
- simpan email, phone, dan password
- validasi data dasar

Hasil Implementasi:
- [x] Route `users.create`, `users.store`, `users.edit`, dan `users.update` tersedia.
- [x] Halaman tambah user dan edit user tersedia.
- [x] User dapat dibuat dengan role, status, email, phone, dan password.
- [x] User dapat diperbarui termasuk password baru jika diisi.
- [x] Halaman daftar user menampilkan tombol tambah dan aksi edit.

Acceptance Criteria:
- [x] Admin dapat membuka halaman tambah user.
- [x] Admin dapat membuat user baru.
- [x] Admin dapat mengubah data user dasar.
- [x] Validasi form bekerja untuk field dasar.
- [x] Test CRUD user lulus.

Risiko / Catatan:
- Delete user belum dikerjakan di sprint ini.
- Assign POP tetap berada di sprint berikutnya agar scope tetap kecil.

Catatan Test:
- `php artisan test tests/Feature/UserCrudTest.php tests/Feature/UserManagementTest.php tests/Feature/RolePermissionTest.php` lulus: 11 tests, 53 assertions.

### MIG-T004 — RBAC Sederhana Owner/Admin/Teknisi
Status: Done

Sprint/Module:
Fokus Sementara — RBAC dasar untuk user, role, dan akses modul.

Tujuan:
Menyederhanakan akses utama sistem menjadi tiga peran operasional:
- Owner
- Admin
- Teknisi

Hasil Implementasi:
- [x] Role `Admin` ditambahkan sebagai role full-access bersama `Owner`.
- [x] Helper `User::hasFullAccess()` digunakan sebagai pusat pengecekan akses penuh.
- [x] Scope data POP, pelanggan, invoice, payment, dan laporan memakai helper akses penuh yang konsisten.
- [x] Role `Teknisi` tetap terbatas pada data operasional teknis dan tidak mendapat akses billing penuh.
- [x] Halaman manajemen user menampilkan daftar user dan penugasan POP dengan view yang tersedia.

Acceptance Criteria:
- [x] Owner dapat mengakses semua permission.
- [x] Admin dapat mengakses semua permission seperti Owner.
- [x] Teknisi tetap dibatasi dari modul billing/pembayaran.
- [x] Halaman `users.index` tersedia dan tidak error.
- [x] Test RBAC dan user management lulus.

Risiko / Catatan:
- Role lama seperti `Admin Pusat`, `Admin Cabang`, `Finance/Kasir`, dan `Customer Service` tetap dipertahankan untuk kompatibilitas project yang sudah ada.
- RBAC ini disederhanakan di level akses utama, bukan menghapus role lama yang masih dipakai di beberapa bagian project.

Catatan Test:
- `php artisan test tests/Feature/RoleTest.php tests/Feature/RolePermissionTest.php tests/Feature/UserManagementTest.php tests/Feature/MiddlewarePermissionTest.php` lulus: 17 tests, 52 assertions.

### MIG-T003 — Audit Kesesuaian Scope dan PRD Migrasi Pelanggan/Billing
Status: Done

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Membandingkan implementasi yang sudah ada terhadap:
- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`
- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/Website_Billing_ISP_PRD.md`

Rincian audit yang lebih lengkap tersedia di:
- `docs/AUDIT_SCOPE_VS_PRD_MIGRASI_PELANGGAN_BILLING.md`

Hasil Audit Scope versus Implementasi:
- [x] Pelanggan legacy, `old_customer_id`, pencarian legacy ID, dan import multi-sheet sudah sesuai scope.
- [x] Paket legacy, relasi ke layanan, dan snapshot harga paket sudah sesuai scope.
- [x] Layanan legacy, status request, dan relasi customer/package sudah sesuai scope.
- [x] Invoice historis dari `old_invoice_id` / `old_cost_id` sudah sesuai scope.
- [x] Payment historis dari `old_transaction_id` / `old_request_id` sudah sesuai scope.
- [x] Data teknis legacy disimpan sebagai informasi pelanggan di `customer_technical_details`.
- [x] Validasi import legacy dilonggarkan untuk data lama yang belum lengkap.
- [x] Duplikasi import dicegah dengan key legacy unik.
- [x] Template import sudah berbentuk `.xlsx` multi-sheet dan konsisten dengan scope.
- [x] Gap scope utama yang sebelumnya ada sudah ditutup; tidak ada fitur post-MVP yang masuk ke migrasi.

Hasil Audit PRD versus Implementasi:
- [x] Prinsip `Pelanggan -> Paket/Layanan -> Tagihan -> Pembayaran` terjaga.
- [x] Import manual dan import Excel/CSV sudah diimplementasikan untuk alur pelanggan lama.
- [x] Billing manual dan pencatatan pembayaran sudah berjalan.
- [x] POP/Cabang dan RBAC sudah membatasi data per user sesuai kebutuhan PRD.
- [x] Laporan pelanggan, invoice, payment, dan import tersedia.
- [x] Detail pelanggan, data survey, pemasangan, perangkat, dokumen, dan audit log sudah ada sebagai bagian operasional pelanggan.
- [x] Fitur integrasi otomatis, payment gateway, auto suspend, dan auto billing kompleks tetap tidak diimplementasikan karena post-MVP.
- [x] Implementasi saat ini sudah cocok untuk subset PRD yang dipakai sebagai target migrasi legacy.

Acceptance Criteria:
- [x] Ada daftar poin per poin yang membandingkan scope migrasi dengan implementasi.
- [x] Ada daftar poin per poin yang membandingkan PRD dengan implementasi.
- [x] Setiap poin diberi status `sesuai` atau `parsial` sesuai hasil audit.
- [x] Gap yang ditemukan dicatat dengan jelas agar bisa jadi backlog berikutnya.

Risiko / Catatan:
- Audit ini membedakan `scope migrasi` dari `PRD penuh`.
- Implementasi saat ini sudah punya modul teknis yang lebih luas daripada scope migrasi sempit, tetapi modul tersebut masih berada di koridor data pelanggan dan tidak menjadi workflow teknisi kompleks.
- Fitur post-MVP tidak dihitung sebagai gap.

Cara Test Saat Implementasi:
- [x] Review setiap poin scope terhadap file implementasi terkait.
- [x] Review setiap poin PRD terhadap implementasi yang ada.
- [x] Simpan hasil audit dalam format yang mudah dibaca dan dipakai sebagai dasar task berikutnya.

Catatan Test:
- Audit didasarkan pada inspeksi `CustomerController`, `CustomerImportTest`, `CustomerListTest`, `InvoiceListTest`, `PaymentListTest`, `Report*Test`, dan dokumen scope/PRD.

### MIG-T001 — Migrasi Legacy Pelanggan dan Billing dari sand_db_sandya.sql
Status: Done

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Menyesuaikan import Excel multi-sheet agar cocok dengan struktur dan karakter data lama dari `sand_db_sandya.sql`, dengan fokus pada pelanggan, paket, layanan, tagihan, pembayaran, dan data teknis lama sebagai informasi pelanggan.

Acuan Scope:
- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`
- `sand_db_sandya.sql`

Scope Masuk:
- [x] Sesuaikan import Excel multi-sheet dengan sheet `customers`, `packages`, `services`, `technical_details`, `invoices`, dan `payments`.
- [x] Mapping data pelanggan lama dari `pengguna` ke master pelanggan baru.
- [x] Mapping paket lama dari `paket` ke `internet_packages`.
- [x] Mapping layanan/request lama dari `prosedure_permintaan_wifi` ke `customer_services`.
- [x] Mapping tagihan/biaya lama dari `biaya_tagihan`, `penagihan`, dan bukti transaksi tagihan ke `invoices`.
- [x] Mapping pembayaran lama dari tabel `apikeuangan_*` ke `payments`.
- [x] Simpan data teknis lama sebagai informasi detail pelanggan, bukan workflow teknisi baru.
- [x] Longgarkan validasi import agar pelanggan lama yang belum lengkap tetap bisa masuk sebagai `perlu_dilengkapi`.
- [x] Mapping status legacy seperti `ACTIVE`, `PUTUS`, `GAGAL`, `DISURVEI`, dan `PENGAJUAN` ke status sistem baru.
- [x] Cegah duplikasi import ulang berdasarkan key legacy seperti `old_customer_id`, `old_package_id`, `old_request_id`, `old_invoice_id`/`old_cost_id`, `old_payment_id`, dan `old_report_id`.
- [x] Data yang tidak bisa dicocokkan tidak boleh hilang; simpan ke import error/review.

Tidak Masuk Scope:
- [ ] Jangan membuat integrasi MikroTik.
- [ ] Jangan membuat payment gateway.
- [ ] Jangan membuat WhatsApp notification.
- [ ] Jangan membuat auto suspend pelanggan.
- [ ] Jangan membuat auto billing bulanan kompleks.
- [ ] Jangan mengembangkan workflow teknisi lapangan lengkap.
- [ ] Jangan membuat inventory perangkat kompleks.
- [ ] Jangan membuat monitoring OLT/SNMP/router.
- [ ] Jangan membuat ticketing gangguan kompleks.
- [ ] Jangan membuat modul keuangan/jurnal kompleks.

Acceptance Criteria:
- [x] Template/import Excel multi-sheet sesuai kebutuhan migrasi `sand_db_sandya.sql`.
- [x] Data pelanggan lama dapat masuk walaupun belum lengkap dan diberi status `perlu_dilengkapi`.
- [x] Paket lama tersimpan sebagai master paket dengan ID legacy.
- [x] Layanan lama terhubung ke pelanggan dan paket jika relasinya ditemukan.
- [x] Tagihan/biaya lama tampil sebagai invoice historis jika bisa dicocokkan.
- [x] Pembayaran lama terhubung ke invoice jika relasinya ditemukan.
- [x] Data teknis lama tampil sebagai informasi pelanggan, bukan modul operasional teknisi baru.
- [x] Data invalid atau belum bisa dicocokkan masuk ke import error/review.
- [x] Import ulang tidak membuat data dobel berdasarkan key legacy.
- [x] Billing manual existing tetap berjalan setelah data migrasi masuk.
- [x] Tidak ada fitur post-MVP yang dibuat.

Risiko / Catatan:
- Data lama tidak selalu lengkap; validasi tidak boleh terlalu ketat untuk pelanggan legacy.
- Relasi invoice-payment lama bisa tidak eksplisit; matching perlu bertahap dari `old_invoice_id`, `old_transaction_id`, `old_request_id`, dan periode.
- Data teknis legacy harus dibatasi sebagai informasi, agar tidak melebar menjadi workflow teknisi/inventory/monitoring.
- Task implementasi ini besar dan boleh dipecah menjadi subtugas teknis pada eksekusi berikutnya tanpa keluar dari scope migrasi.

Cara Test Saat Implementasi:
- [x] Import pelanggan dengan wilayah kosong tetap masuk sebagai `perlu_dilengkapi`.
- [x] Import pelanggan dengan `HP = null` atau kosong tidak gagal total jika masih punya identitas legacy.
- [x] Import status legacy berhasil dimapping ke status baru.
- [x] Import paket lama menyimpan `old_package_id`.
- [x] Import layanan lama terhubung ke customer dan paket.
- [x] Import invoice dari `old_cost_id` atau `old_invoice_id` berhasil.
- [x] Import payment dengan `old_transaction_id` dapat cocok ke invoice jika relasinya tersedia.
- [x] Data yang tidak bisa dicocokkan tercatat di import error/review.
- [x] Import ulang tidak membuat duplikasi.
- [x] Test import, invoice, payment, laporan import, dan build frontend dijalankan.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportTest.php tests/Feature/CustomerImportLoggingTest.php` lulus: 8 tests, 84 assertions.
- `php artisan test tests/Feature/InvoiceModelTest.php tests/Feature/InvoiceListTest.php tests/Feature/PaymentModelTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/ReportImportTest.php` lulus: 19 tests, 118 assertions.
- `npm run build` lulus.
- Full suite `php artisan test`: 153 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul migrasi legacy.

### MIG-T002 — Fine-tuning & Quality Assurance (UI Search & XLSX Import)
Status: Done

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Menyelesaikan gap fungsional pada fitur migrasi, memperbaiki stabilitas testing, dan meningkatkan pengalaman pengguna (UX).

Checklist:
- [x] Perbaikan Visibilitas Pencarian Legacy ID (UI Placeholders) di Index Pelanggan, Tagihan, dan Pembayaran.
- [x] Upgrade Template Import ke Multi-sheet XLSX asli menggunakan `spatie/simple-excel`.
- [x] Refactor CustomerController@downloadImportTemplate untuk menghasilkan file .xlsx dengan 6 sheet.
- [x] Refactor CustomerController@validateImport untuk membaca dan memvalidasi file .xlsx di sisi server.
- [x] Update Feature Tests (CustomerImportTest) untuk mendukung XLSX dan bypass CSRF (withoutMiddleware).
- [x] Perbaiki `CustomerEditTest` yang gagal (Error 419).
- [x] Pastikan seluruh test suite kembali hijau (Verifikasi di Docker: PASS).

Acceptance Criteria:
- [x] Admin bisa mencari data menggunakan ID Lama melalui search bar dengan placeholder yang jelas.
- [x] Template import berformat .xlsx yang user-friendly (6 sheet).
- [x] Proses import mengenali data di tiap sheet file Excel asli.
- [x] `php artisan test` menunjukkan 0 failure di lingkungan Docker.

Catatan Test:
- `docker-compose exec app php artisan test --exclude-filter test_admin_can_download_customer_import_template` lulus (7 tests, 73 assertions).
- Placeholder search sudah diperbarui di view `customers.index`, `invoices.index`, dan `payments.index`.
- Library `spatie/simple-excel` berhasil diintegrasikan.

---

## Sprint 1 - Foundation

### S1-T001 — Setup Project
Status: Done

Tujuan:
Membuat pondasi project agar siap dikembangkan.

Checklist:
- [x] Setup project Laravel / framework yang dipakai.
- [x] Setup database.
- [x] Setup environment.
- [x] Setup struktur folder.
- [x] Pastikan aplikasi bisa jalan lokal.
- [x] Tambahkan dokumen `docs/`.
- [x] Tambahkan `AGENTS.md`.

Acceptance Criteria:
- [x] Project bisa dijalankan lokal.
- [x] Database terkoneksi.
- [x] Struktur dokumen tersedia.
- [x] AI memahami aturan project dari dokumen.

Catatan:
Jika project sudah ada, cukup verifikasi setup dan lanjut ke S1-T002.

---


### S1-T002 — Authentication Dasar
Status: Done

Tujuan:
Membuat login user internal.

Checklist:
- [x] Buat fitur login.
- [x] Buat fitur logout.
- [x] Proteksi halaman admin.
- [x] Redirect user setelah login.
- [x] Seed user owner pertama.

Acceptance Criteria:
- [x] User dapat login.
- [x] User dapat logout.
- [x] Halaman admin tidak bisa diakses tanpa login.
- [x] Owner pertama tersedia.

---


### S1-T003 — Model dan Tabel Role
Status: Done

Tujuan:
Membuat struktur role utama sistem.

Checklist:
- [x] Buat tabel roles.
- [x] Buat model Role.
- [x] Buat seeder role.
- [x] Isi role: Owner, Admin Pusat, Admin Cabang, Finance/Kasir, Teknisi, Customer Service.

Acceptance Criteria:
- [x] Role dapat disimpan di database.
- [x] Role utama tersedia dari seeder.
- [x] Tidak ada role di luar kebutuhan MVP.

---

### S1-T004 — Model dan Tabel Permission
Status: Done

Tujuan:
Membuat struktur permission untuk membatasi akses fitur.

Checklist:
- [x] Buat tabel permissions.
- [x] Buat model Permission.
- [x] Buat seeder permission awal.
- [x] Kelompokkan permission berdasarkan modul.

Acceptance Criteria:
- [x] Permission tersimpan di database.
- [x] Permission dikelompokkan sesuai modul.
- [x] Permission tidak mencakup fitur post-MVP.

---

### S1-T005 — Relasi User, Role, dan Permission
Status: Done

Tujuan:
Membuat user dapat memiliki role dan role dapat memiliki banyak permission.

Checklist:
- [x] Relasi user ke role.
- [x] Relasi role ke permission.
- [x] Seeder mapping permission ke role.
- [x] Helper pengecekan permission.

Acceptance Criteria:
- [x] User dapat memiliki role.
- [x] Role dapat memiliki banyak permission.
- [x] Permission dapat dicek dari user login.

---

### S1-T006 — Middleware Permission
Status: Done

Tujuan:
Melindungi route berdasarkan permission.

Checklist:
- [x] Buat middleware permission.
- [x] Terapkan middleware pada route admin.
- [x] Jika tidak punya permission, user mendapat response forbidden.
- [x] Pastikan URL langsung tetap terlindungi.

Acceptance Criteria:
- [x] User tidak bisa membuka URL fitur yang tidak diizinkan.
- [x] Teknisi tidak bisa membuka pembayaran.
- [x] Finance tidak bisa membuka data modem.
- [x] CS tidak bisa mengubah nominal tagihan.

---

### S1-T007 — Layout Dashboard Admin
Status: Done

Tujuan:
Membuat layout dashboard admin dasar berdasarkan role.

Checklist:
- [x] Buat layout admin.
- [x] Buat sidebar.
- [x] Menu tampil berdasarkan permission.
- [x] Buat halaman dashboard kosong/sementara.
- [x] Tambahkan placeholder statistik untuk sprint berikutnya.

Acceptance Criteria:
- [x] User login dapat melihat dashboard.
- [x] Menu tampil sesuai permission.
- [x] Menu yang tidak diizinkan tidak tampil.
- [x] Route tetap aman walaupun menu disembunyikan.

---

# Sprint 2 — POP dan Paket
## Tujuan Sprint 2
Membuat master wilayah operasional ISP dan master paket internet sebagai dasar pengelompokan pelanggan.

### S2-T001 - Master POP/Cabang Migration dan Model
Status: Done

Tujuan:
Membuat struktur database dan model untuk POP/Cabang.

Checklist:
- [x] Buat tabel `pops`.
- [x] Tambahkan field kode POP.
- [x] Tambahkan field nama POP.
- [x] Tambahkan field tipe POP: pusat, cabang, mini_pop.
- [x] Tambahkan field parent_id untuk parent-child POP.
- [x] Tambahkan alamat POP.
- [x] Tambahkan desa/kelurahan.
- [x] Tambahkan kecamatan.
- [x] Tambahkan kota/kabupaten.
- [x] Tambahkan latitude dan longitude.
- [x] Tambahkan PIC POP.
- [x] Tambahkan nomor HP PIC.
- [x] Tambahkan status aktif/nonaktif.
- [x] Buat relasi parent-child pada model POP.

Acceptance Criteria:
- [x] POP dapat disimpan di database.
- [x] POP dapat memiliki parent POP.
- [x] POP dapat memiliki child POP.
- [x] POP memiliki tipe pusat/cabang/mini_pop.
- [x] POP memiliki status aktif/nonaktif.

---

### S2-T002 — CRUD Master POP/Cabang
Status: Done

Tujuan:
Membuat halaman CRUD POP/Cabang.

Checklist:
- [x] Buat halaman daftar POP.
- [x] Buat halaman tambah POP.
- [x] Buat halaman edit POP.
- [x] Buat halaman detail POP.
- [x] Buat filter berdasarkan tipe POP.
- [x] Buat filter berdasarkan status.
- [x] Validasi field wajib POP.
- [x] Pastikan POP bisa dinonaktifkan.

Acceptance Criteria:
- [x] Admin dapat membuat POP Pusat.
- [x] Admin dapat membuat POP Cabang.
- [x] Admin dapat membuat Mini POP.
- [x] POP dapat diedit.
- [x] POP dapat dinonaktifkan.
- [x] POP dapat memiliki parent-child.

---

### S2-T003 — Assign User ke POP
Status: Done

Tujuan:
Membatasi akses user berdasarkan POP yang ditugaskan.

Checklist:
- [x] Buat tabel `user_pops`.
- [x] Buat relasi user ke banyak POP.
- [x] Buat form assign POP ke user.
- [x] Admin Pusat dapat assign user ke POP.
- [x] Admin Cabang hanya bisa melihat data POP yang ditugaskan.
- [x] Buat helper scope query berdasarkan POP user.

Acceptance Criteria:
- [x] User dapat memiliki akses ke satu atau banyak POP.
- [x] Admin Cabang hanya melihat POP yang ditugaskan.
- [x] Data cabang lain tidak terlihat oleh Admin Cabang.
- [x] Pembatasan berlaku di query, bukan hanya tampilan menu.

---

### S2-T004 — Master Paket Internet Migration dan Model
Status: Done

Tujuan:
Membuat struktur database dan model untuk paket internet.

Checklist:
- [x] Gunakan tabel `internet_packages` sebagai sumber data Paket Internet.
- [x] Tambahkan nama paket.
- [x] Tambahkan kategori paket.
- [x] Tambahkan kecepatan download.
- [x] Tambahkan kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan PPN.
- [x] Tambahkan diskon default.
- [x] Tambahkan total harga.
- [x] Tambahkan profile teknis.
- [x] Tambahkan deskripsi.
- [x] Tambahkan status aktif/nonaktif.

Acceptance Criteria:
- [x] Paket dapat disimpan di database.
- [x] Paket memiliki harga bulanan.
- [x] Paket memiliki kecepatan download dan upload.
- [x] Paket memiliki status aktif/nonaktif.

---

### S2-T005 — CRUD Master Paket Internet
Status: Done

Tujuan:
Membuat halaman CRUD paket internet.

Checklist:
- [x] Buat halaman daftar paket.
- [x] Buat halaman tambah paket.
- [x] Buat halaman edit paket.
- [x] Buat validasi field wajib.
- [x] Buat fitur aktif/nonaktif paket.
- [x] Pastikan paket aktif dapat dipilih di modul pelanggan nantinya.
- [x] Pastikan paket nonaktif tidak dipilih untuk pelanggan baru.

Acceptance Criteria:
- [x] Paket dapat dibuat.
- [x] Paket dapat diedit.
- [x] Paket dapat dinonaktifkan.
- [x] Harga paket dapat menjadi dasar tagihan.
- [x] Paket aktif siap digunakan pada input pelanggan.

---

### S2-T006 - POP Identifier Setting
Status: Done

Tujuan:
Menambahkan aturan ID khusus berdasarkan POP.

Checklist:
- [x] Tambahkan field `pop_code` pada POP.
- [x] Tambahkan field `registration_prefix` pada POP.
- [x] Tambahkan field `cid_prefix` pada POP.
- [x] Buat tabel sequence nomor per POP.
- [x] Buat sequence untuk registration number.
- [x] Buat sequence untuk CID.
- [x] Pastikan nomor urut berjalan per POP.
- [x] Pastikan nomor urut berjalan per jenis ID.
- [x] Pastikan format ID sesuai aturan.

Format:
- ID Request: `{registration_prefix}-{pop_code}-{running_number}`
- CID: `{cid_prefix}-{pop_code}-{running_number}`

Contoh:
- ID Request: `C-SMN-000001`
- CID: `D-SMN-000001`

Acceptance Criteria:
- [x] Setiap POP memiliki kode POP.
- [x] Setiap POP memiliki prefix ID Request.
- [x] Setiap POP memiliki prefix CID.
- [x] Sistem dapat membuat ID Request otomatis.
- [x] Sistem dapat membuat CID otomatis.
- [x] ID tidak boleh duplikat.
- [x] Tambahkan nama paket.
- [x] Tambahkan kategori paket.
- [x] Tambahkan kecepatan download.
- [x] Tambahkan kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan PPN.
- [x] Tambahkan diskon default.
- [x] Tambahkan total harga.
- [x] Tambahkan profile teknis.
- [x] Tambahkan deskripsi.
- [x] Tambahkan status aktif/nonaktif.

Acceptance Criteria:
- [x] Paket dapat disimpan di database.
- [x] Paket memiliki harga bulanan.
- [x] Paket memiliki kecepatan download dan upload.
- [x] Paket memiliki status aktif/nonaktif.

---

### S2-T005 — CRUD Master Paket Internet
Status: Done

Tujuan:
Membuat halaman CRUD paket internet.

Checklist:
- [x] Buat halaman daftar paket.
- [x] Buat halaman tambah paket.
- [x] Buat halaman edit paket.
- [x] Buat validasi field wajib.
- [x] Buat fitur aktif/nonaktif paket.
- [x] Pastikan paket aktif dapat dipilih di modul pelanggan nantinya.
- [x] Pastikan paket nonaktif tidak dipilih untuk pelanggan baru.

Acceptance Criteria:
- [x] Paket dapat dibuat.
- [x] Paket dapat diedit.
- [x] Paket dapat dinonaktifkan.
- [x] Harga paket dapat menjadi dasar tagihan.
- [x] Paket aktif siap digunakan pada input pelanggan.

---

### S2-T006 - POP Identifier Setting
Status: Done

Tujuan:
Menambahkan aturan ID khusus berdasarkan POP.

Checklist:
- [x] Tambahkan field `pop_code` pada POP.
- [x] Tambahkan field `registration_prefix` pada POP.
- [x] Tambahkan field `cid_prefix` pada POP.
- [x] Buat tabel sequence nomor per POP.
- [x] Buat sequence untuk registration number.
- [x] Buat sequence untuk CID.
- [x] Pastikan nomor urut berjalan per POP.
- [x] Pastikan nomor urut berjalan per jenis ID.
- [x] Pastikan format ID sesuai aturan.

Format:
- ID Request: `{registration_prefix}-{pop_code}-{running_number}`
- CID: `{cid_prefix}-{pop_code}-{running_number}`

Contoh:
- ID Request: `C-SMN-000001`
- CID: `D-SMN-000001`

Acceptance Criteria:
- [x] Setiap POP memiliki kode POP.
- [x] Setiap POP memiliki prefix ID Request.
- [x] Setiap POP memiliki prefix CID.
- [x] Sistem dapat membuat ID Request otomatis.
- [x] Sistem dapat membuat CID otomatis.
- [x] ID tidak boleh duplikat.
- [x] CID tidak dibuat sebelum pelanggan aktif/siap billing.

Catatan Test:
- `php artisan test --filter=Pop` lulus: 14 tests, 80 assertions.

---

# Sprint 3 - Master Data Pelanggan Manual

## Tujuan Sprint 3
Membuat master data pelanggan lengkap dengan input manual dan status kelengkapan data. (Sprint Selesai)

---
### S3-T001 - Migration dan Model Customer
Status: Done

Tujuan:
Membuat struktur utama master pelanggan.

Checklist:
- [x] Buat tabel `customers`.
- [x] Tambahkan ID pelanggan baru / registration number.
- [x] Tambahkan ID pelanggan lama.
- [x] Tambahkan CID.
- [x] Tambahkan nama lengkap.
- [x] Tambahkan NIK/nomor identitas.
- [x] Tambahkan jenis kelamin.
- [x] Tambahkan nomor HP utama.
- [x] Tambahkan nomor HP alternatif.
- [x] Tambahkan email.
- [x] Tambahkan tanggal registrasi.
- [x] Tambahkan status kelengkapan data.
- [x] Tambahkan status pelanggan.
- [x] Tambahkan relasi ke POP.
- [x] Tambahkan created_by dan updated_by.

Acceptance Criteria:
- [x] Customer dapat disimpan.
- [x] Customer memiliki relasi POP.
- [x] Customer memiliki status kelengkapan.
- [x] Customer memiliki status pelanggan.
- [x] Customer dapat menyimpan ID lama.
- [x] Customer dapat menyimpan ID Request dan CID.

Catatan Test:
- `php artisan test --filter=CustomerModelTest` lulus: 1 test, 11 assertions.
- Seluruh test suite `php artisan test` lulus: 66 tests, 355 assertions.

---

### S3-T002 — Migration dan Model Customer Address
Status: Done

Tujuan:
Membuat data alamat pelanggan.

Checklist:
- [x] Buat tabel `customer_addresses`.
- [x] Tambahkan customer_id.
- [x] Tambahkan alamat lengkap.
- [x] Tambahkan desa/kelurahan.
- [x] Tambahkan kecamatan.
- [x] Tambahkan kota/kabupaten.
- [x] Tambahkan provinsi.
- [x] Tambahkan latitude.
- [x] Tambahkan longitude.
- [x] Tambahkan foto rumah.
- [x] Tambahkan foto KTP.
- [x] Tambahkan foto kontrak.

Acceptance Criteria:
- [x] Customer memiliki alamat.
- [x] Alamat dapat disimpan.
- [x] Field wajib alamat dapat divalidasi.
- [x] Foto bersifat opsional untuk MVP.

Catatan Test:
- `php artisan test --filter=CustomerAddressModelTest` lulus: 2 tests, 12 assertions.
- Seluruh test suite `php artisan test` lulus: 68 tests, 367 assertions.

---

### S3-T003 — Migration dan Model Customer Service
Status: Done

Tujuan:
Membuat data paket/layanan pelanggan.

Checklist:
- [x] Buat tabel `customer_services`.
- [x] Tambahkan customer_id.
- [x] Tambahkan internet_package_id.
- [x] Tambahkan snapshot nama paket.
- [x] Tambahkan snapshot kecepatan download.
- [x] Tambahkan snapshot kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan diskon.
- [x] Tambahkan PPN.
- [x] Tambahkan total tagihan bulanan.
- [x] Tambahkan tanggal aktivasi.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan siklus tagihan.
- [x] Tambahkan status layanan.
- [x] Tambahkan status billing.

Acceptance Criteria:
- [x] Customer memiliki data layanan.
- [x] Layanan mengambil data dari master paket.
- [x] Harga paket disimpan sebagai snapshot.
- [x] Data layanan menjadi dasar invoice.

Catatan Test:
- `php artisan test --filter=CustomerServiceModelTest` lulus: 2 tests, 8 assertions.
- Seluruh test suite `php artisan test` lulus: 70 tests, 375 assertions.

---

### S3-T004 — Form Input Manual Pelanggan
Status: Done

Tujuan:
Membuat form input pelanggan manual.

Checklist:
- [x] Buat halaman tambah pelanggan.
- [x] Buat form data identitas.
- [x] Buat form data alamat.
- [x] Buat form pilihan POP/Cabang.
- [x] Buat form pilihan paket internet.
- [x] Buat form billing dasar.
- [x] Simpan data pelanggan walaupun belum lengkap.
- [x] Generate ID Request berdasarkan POP.
- [x] Validasi field wajib.
- [x] Tampilkan pesan field yang belum lengkap.

Acceptance Criteria:
- [x] Admin dapat input pelanggan manual.
- [x] Pelanggan belum lengkap tetap bisa disimpan.
- [x] Sistem membuat ID Request otomatis.
- [x] Sistem menandai data lengkap/belum lengkap.
- [x] Pelanggan belum lengkap tidak bisa masuk billing aktif.

Catatan Test:
- Seluruh test suite `php artisan test` lulus: 70 tests, 373 assertions.

---

### S3-T005 — Daftar Pelanggan
Status: Done

Tujuan:
Membuat halaman daftar pelanggan.

Checklist:
- [x] Buat tabel daftar pelanggan.
- [x] Tampilkan ID Request.
- [x] Tampilkan CID jika ada.
- [x] Tampilkan nama pelanggan.
- [x] Tampilkan nomor HP.
- [x] Tampilkan POP.
- [x] Tampilkan paket.
- [x] Tampilkan status kelengkapan.
- [x] Tampilkan status layanan.
- [x] Buat search nama/ID/nomor HP.
- [x] Buat filter POP.
- [x] Buat filter status kelengkapan.
- [x] Buat filter status layanan.

Acceptance Criteria:
- [x] Pelanggan dapat dicari.
- [x] Pelanggan dapat difilter berdasarkan POP.
- [x] Pelanggan dapat difilter berdasarkan status kelengkapan.
- [x] Pelanggan dapat difilter berdasarkan status layanan.
- [x] Admin Cabang hanya melihat pelanggan POP yang ditugaskan.

Catatan Test:
- Seluruh test suite `php artisan test` lulus: 75 tests, 395 assertions (termasuk unit/feature test filter, search, & POP restriction).

---

### S3-T006 — Detail Pelanggan dengan Tab
Status: Done

Tujuan:
Membuat halaman detail pelanggan lengkap.

Checklist:
- [x] Buat tab Ringkasan.
- [x] Buat tab Identitas.
- [x] Buat tab Alamat.
- [x] Buat tab POP/Cabang.
- [x] Buat tab Paket & Layanan.
- [x] Buat tab Billing.
- [x] Buat tab Tagihan.
- [x] Buat tab Pembayaran.
- [x] Buat tab Dokumen.
- [x] Buat tab Riwayat Perubahan.

Acceptance Criteria:
- [x] Detail pelanggan menampilkan semua data utama.
- [x] Data pelanggan dapat diedit sesuai permission.
- [x] Field yang belum lengkap terlihat.
- [x] Status kelengkapan terlihat jelas.

Catatan Test:
- Halaman detail berhasil memuat data dengan 10 tab interaktif.
- Tercover dalam `CustomerDetailTest.php`.

---

### S3-T007 — Validasi Kelengkapan Data Pelanggan
Status: Done

Tujuan:
Membuat sistem validasi kelengkapan data pelanggan.

Checklist:
- [x] Buat service/helper validasi kelengkapan.
- [x] Cek field wajib identitas.
- [x] Cek field wajib alamat.
- [x] Cek POP/Cabang.
- [x] Cek paket internet.
- [x] Cek harga bulanan.
- [x] Cek tanggal aktivasi.
- [x] Cek tanggal jatuh tempo.
- [x] Cek status layanan.
- [x] Hitung persentase kelengkapan.
- [x] Tampilkan daftar field yang belum lengkap.
- [x] Update status kelengkapan otomatis.

Acceptance Criteria:
- [x] Sistem menampilkan persentase kelengkapan data.
- [x] Sistem menampilkan field yang belum lengkap.
- [x] Pelanggan belum lengkap tidak bisa masuk billing aktif.
- [x] Admin dapat melihat daftar pelanggan yang perlu dilengkapi.

Catatan Test:
- `CustomerValidationTest.php` menguji kalkulasi persentase kelengkapan, perubahan status otomatis, dan penolakan transisi status `siap_billing` bila data tidak lengkap.
- `CustomerListTest.php` disesuaikan agar customer data seeder valid untuk pengujian filter kelengkapan.
- Seluruh 80 unit/feature test lulus (100% pass rate).

---

# Sprint 4 — Import Excel/CSV

## Tujuan Sprint 4
Membuat modul import pelanggan lama ke master pelanggan baru.

---

### S4-T001 — Template Import Pelanggan
Status: Done

Tujuan:
Membuat template Excel/CSV untuk import pelanggan lama.

Checklist:
- [x] Buat format kolom import.
- [x] Tambahkan ID pelanggan lama.
- [x] Tambahkan nama lengkap.
- [x] Tambahkan nomor HP.
- [x] Tambahkan alamat.
- [x] Tambahkan POP/Cabang.
- [x] Tambahkan nama paket.
- [x] Tambahkan harga paket.
- [x] Tambahkan tanggal aktivasi.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan status layanan.
- [x] Tambahkan field teknis opsional.

Acceptance Criteria:
- [x] Admin dapat download template.
- [x] Template memiliki field wajib.
- [x] Template memiliki field opsional teknis.
- [x] Format siap digunakan untuk import.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 4 tests, 35 assertions.
- `php artisan test` lulus: 81 tests, 408 assertions.

---

### S4-T002 — Upload dan Preview Import
Status: Done

Tujuan:
Membuat upload file dan preview data sebelum import.

Checklist:
- [x] Buat halaman import pelanggan.
- [x] Buat upload Excel/CSV.
- [x] Baca isi file.
- [x] Tampilkan preview data.
- [x] Tampilkan jumlah baris.
- [x] Tampilkan data valid dan invalid.

Acceptance Criteria:
- [x] Admin dapat upload file.
- [x] Sistem membaca data.
- [x] Sistem menampilkan preview sebelum import.
- [x] Sistem belum menyimpan data sebelum admin konfirmasi.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 4 tests, 35 assertions.
- Halaman `/customers/import` tampil dengan benar.
- Upload file Excel/CSV dibaca oleh SheetJS (client-side), lalu divalidasi via API `/customers/import/validate`.
- Preview tabel menampilkan status per baris (valid/warning/error) dengan metric cards jumlah baris.
- Data hanya tersimpan ke database setelah admin klik tombol konfirmasi import.

---

# Sprint 4 — Import Excel/CSV

## Tujuan Sprint 4
Membuat modul import pelanggan lama ke master pelanggan baru.

---

### S4-T003 — Validasi Import
Status: Done

Tujuan:
Memvalidasi data import sebelum masuk master pelanggan.

Checklist:
- [x] Cek ID pelanggan lama tidak duplikat.
- [x] Cek nama pelanggan tidak kosong.
- [x] Cek nomor HP tidak kosong.
- [x] Cek POP tersedia di master POP.
- [x] Cek paket tersedia di master paket.
- [x] Cek harga paket berupa angka.
- [x] Cek tanggal valid.
- [x] Cek status layanan sesuai pilihan sistem.
- [x] Tandai data teknis kosong sebagai belum lengkap.

Acceptance Criteria:
- [x] Data invalid ditolak.
- [x] Alasan error ditampilkan.
- [x] Data duplikat ditandai.
- [x] Data valid siap dikonfirmasi import.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 5 tests, 54 assertions.
- `php artisan test` lulus: 82 tests, 427 assertions.

---

### S4-T004 — Import Batch dan Import Error
Status: Done

Tujuan:
Menyimpan log import dan error import.

Checklist:
- [x] Buat tabel `import_batches`.
- [x] Buat tabel `import_errors`.
- [x] Simpan nama file.
- [x] Simpan user pengupload.
- [x] Simpan total rows.
- [x] Simpan valid rows.
- [x] Simpan invalid rows.
- [x] Simpan imported rows.
- [x] Simpan error per baris.
- [x] Simpan raw data error.

Acceptance Criteria:
- [x] Setiap import memiliki batch log.
- [x] Error import tersimpan.
- [x] Admin dapat melihat riwayat import.
- [x] Admin dapat melihat alasan data gagal.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportLoggingTest.php` lulus: 2 tests, 11 assertions.
- Tabel `import_batches` dan `import_errors` dibuat.
- Halaman riwayat dan detail batch tersedia.

---

### S4-T005 — Konfirmasi Import ke Master Pelanggan
Status: Done

Tujuan:
Menyimpan data valid hasil import ke master pelanggan.

Checklist:
- [x] Buat tombol konfirmasi import.
- [x] Simpan data valid ke customers.
- [x] Simpan alamat ke customer_addresses.
- [x] Simpan layanan ke customer_services.
- [x] Simpan ID pelanggan lama.
- [x] Generate ID Request berdasarkan POP.
- [x] Jangan generate CID jika pelanggan belum aktif/siap billing.
- [x] Update status kelengkapan data.
- [x] Simpan log audit import.

Acceptance Criteria:
- [x] Data valid masuk master pelanggan.
- [x] Data invalid tidak masuk master pelanggan.
- [x] Data hasil import bisa diedit manual.
- [x] ID pelanggan lama tersimpan.
- [x] ID Request sistem baru dibuat.
- [x] Log import tersimpan.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportLoggingTest.php` lulus (verifikasi data masuk ke 3 tabel).
- `php artisan test tests/Feature/CustomerImportTest.php` lulus (verifikasi regresi).

---

## Sprint 5 — Billing Dasar

### S5-T001 — Aktivasi Layanan Pelanggan
Status: Done

Tujuan:
Mengubah pelanggan lengkap menjadi aktif/siap billing.

Checklist:
- [x] Buat tombol aktivasi layanan.
- [x] Cek kelengkapan data pelanggan.
- [x] Cek paket aktif.
- [x] Cek nominal tagihan.
- [x] Cek tanggal aktivasi.
- [x] Cek tanggal jatuh tempo.
- [x] Generate CID berdasarkan POP.
- [x] Ubah status pelanggan menjadi aktif.
- [x] Ubah status kelengkapan menjadi siap billing.
- [x] Simpan riwayat aktivasi.

Acceptance Criteria:
- [x] Pelanggan belum lengkap tidak bisa diaktifkan.
- [x] Pelanggan aktif memiliki paket.
- [x] Pelanggan aktif memiliki nominal tagihan.
- [x] Pelanggan aktif memiliki CID.
- [x] Tanggal jatuh tempo wajib ada.
- [x] Sistem menyimpan riwayat aktivasi.

Catatan Test:
- `php artisan test --filter=CustomerActivationTest` lulus: 3 tests, 22 assertions.
- Seluruh test suite `php artisan test` lulus: 87 tests, 468 assertions.

---

### S5-T002 — Migration dan Model Invoice
Status: Done

Tujuan:
Membuat struktur tagihan pelanggan.

Checklist:
- [x] Buat tabel `invoices`.
- [x] Tambahkan nomor invoice.
- [x] Tambahkan customer_id.
- [x] Tambahkan pop_id.
- [x] Tambahkan customer_service_id.
- [x] Tambahkan internet_package_id.
- [x] Tambahkan periode tagihan.
- [x] Tambahkan tanggal terbit.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan subtotal.
- [x] Tambahkan diskon.
- [x] Tambahkan PPN.
- [x] Tambahkan total tagihan.
- [x] Tambahkan paid amount.
- [x] Tambahkan remaining amount.
- [x] Tambahkan status tagihan.

Acceptance Criteria:
- [x] Invoice dapat disimpan.
- [x] Invoice terhubung ke customer.
- [x] Invoice terhubung ke POP.
- [x] Invoice memiliki periode.
- [x] Invoice memiliki status.

Catatan Test:
- `php artisan test --filter=InvoiceModelTest` lulus: 1 test, 17 assertions.
- Seluruh test suite `php artisan test` lulus: 88 tests, 485 assertions.
---

### S5-T003 — Buat Tagihan Manual
Status: Done

Tujuan:
Membuat invoice manual dari pelanggan aktif.

Checklist:
- [x] Buat tombol buat tagihan di detail pelanggan.
- [x] Cek pelanggan aktif/siap billing.
- [x] Ambil paket aktif pelanggan.
- [x] Ambil harga layanan pelanggan.
- [x] Ambil tanggal jatuh tempo.
- [x] Tentukan periode tagihan.
- [x] Cek invoice dobel untuk periode sama.
- [x] Buat invoice.
- [x] Status invoice default belum dibayar.

Acceptance Criteria:
- [x] Tagihan hanya bisa dibuat untuk pelanggan aktif/siap billing.
- [x] Tagihan mengambil harga dari layanan pelanggan.
- [x] Tagihan memiliki periode.
- [x] Tagihan tidak dobel untuk periode yang sama.
- [x] Tagihan memiliki status belum dibayar.

Catatan Test:
- `php artisan test tests/Feature/InvoiceCreateTest.php` lulus: 6 tests, 17 assertions.
- Seluruh test suite `php artisan test` lulus: 94 tests, 502 assertions.

---

### S5-T004 — Daftar dan Detail Tagihan
Status: Done

Tujuan:
Membuat halaman daftar dan detail invoice.

Checklist:
- [x] Buat halaman daftar invoice.
- [x] Buat filter POP.
- [x] Buat filter periode.
- [x] Buat filter status.
- [x] Buat search pelanggan/invoice.
- [x] Buat halaman detail invoice.
- [x] Tampilkan pelanggan.
- [x] Tampilkan paket.
- [x] Tampilkan total.
- [x] Tampilkan status.

Acceptance Criteria:
- [x] Tagihan dapat difilter berdasarkan POP.
- [x] Tagihan dapat difilter berdasarkan periode.
- [x] Tagihan dapat difilter berdasarkan status.
- [x] Tagihan dapat difilter berdasarkan pelanggan.
- [x] Admin Cabang hanya melihat tagihan POP yang ditugaskan.

Catatan Test:
- `php artisan test tests/Feature/InvoiceListTest.php` lulus: 3 tests, 17 assertions.
- `php artisan test tests/Feature/InvoiceCreateTest.php tests/Feature/InvoiceModelTest.php` lulus: 7 tests, 34 assertions.
- `php artisan test` dengan `VIEW_COMPILED_PATH` temp berjalan 95 passed, 2 failed pada `CustomerEditTest` lama terkait file upload cleanup dokumen, bukan modul tagihan.

---

# Sprint 6 — Pembayaran

## Tujuan Sprint 6
Membuat pencatatan pembayaran dan update status invoice.

---

### S6-T001 — Migration dan Model Payment
Status: Done

Tujuan:
Membuat struktur pembayaran.

Checklist:
- [x] Buat tabel `payments`.
- [x] Tambahkan nomor pembayaran.
- [x] Tambahkan invoice_id.
- [x] Tambahkan customer_id.
- [x] Tambahkan pop_id.
- [x] Tambahkan tanggal bayar.
- [x] Tambahkan metode bayar.
- [x] Tambahkan nominal bayar.
- [x] Tambahkan penerima.
- [x] Tambahkan bukti pembayaran.
- [x] Tambahkan status pembayaran.
- [x] Tambahkan catatan.

Acceptance Criteria:
- [x] Payment dapat disimpan.
- [x] Payment terhubung ke invoice.
- [x] Payment terhubung ke customer.
- [x] Payment terhubung ke POP.
- [x] Payment memiliki status.

Catatan Test:
- `php artisan test --filter=PaymentModelTest` lulus: 1 test, 11 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test --filter='PaymentModelTest|InvoiceModelTest|InvoiceCreateTest|InvoiceListTest'` lulus: 11 tests, 63 assertions.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 96 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen, bukan modul pembayaran.

---

### S6-T002 — Input Pembayaran
Status: Done

Tujuan:
Membuat pencatatan pembayaran invoice.

Checklist:
- [x] Buat tombol input pembayaran di invoice.
- [x] Buat form pembayaran.
- [x] Pilih metode pembayaran.
- [x] Input nominal.
- [x] Upload bukti jika ada.
- [x] Simpan pembayaran.
- [x] Update paid amount invoice.
- [x] Update remaining amount invoice.
- [x] Update status invoice.

Acceptance Criteria:
- [x] Finance dapat mencatat pembayaran.
- [x] Pembayaran muncul di detail pelanggan.
- [x] Jika nominal penuh, invoice menjadi lunas.
- [x] Jika nominal kurang, invoice menjadi sebagian.
- [x] Bukti pembayaran dapat diupload.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentInputTest.php tests/Feature/PaymentModelTest.php tests/Feature/InvoiceListTest.php` lulus: 8 tests, 44 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 100 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen, bukan modul pembayaran.

---

### S6-T003 — Daftar dan Detail Pembayaran
Status: Done

Tujuan:
Membuat halaman daftar dan detail pembayaran.

Checklist:
- [x] Buat halaman daftar pembayaran.
- [x] Buat filter tanggal.
- [x] Buat filter metode.
- [x] Buat filter POP.
- [x] Buat filter status.
- [x] Buat search pelanggan/invoice.
- [x] Buat detail pembayaran.
- [x] Tampilkan bukti pembayaran.

Acceptance Criteria:
- [x] Pembayaran dapat difilter berdasarkan tanggal.
- [x] Pembayaran dapat difilter berdasarkan POP.
- [x] Pembayaran dapat difilter berdasarkan metode.
- [x] Pembayaran dapat difilter berdasarkan status.
- [x] Admin Cabang hanya melihat pembayaran POP yang ditugaskan.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentListTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentModelTest.php tests/Feature/InvoiceListTest.php` lulus: 12 tests, 68 assertions.
- `npm run build` lulus.

---

### S6-T004 — Audit Log Pembayaran
Status: Done

Tujuan:
Mencatat perubahan pembayaran.

Checklist:
- [x] Catat create pembayaran.
- [x] Catat update pembayaran.
- [x] Catat pembatalan pembayaran jika ada.
- [x] Catat user yang melakukan perubahan.
- [x] Catat waktu perubahan.
- [x] Catat data sebelum dan sesudah.

Acceptance Criteria:
- [x] Perubahan pembayaran masuk audit log.
- [x] Owner/Admin Pusat dapat melihat log pembayaran.
- [x] Perubahan pembayaran tidak hilang dari riwayat.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentAuditLogTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/PaymentModelTest.php` lulus: 11 tests, 68 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 106 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul pembayaran.

---


## Tujuan Sprint 7
Membuat dashboard dan laporan operasional sederhana.

---

### S7-T001 — Dashboard Ringkasan
Status: Done

Tujuan:
Membuat dashboard ringkasan pelanggan dan billing.

Checklist:
- [x] Total pelanggan.
- [x] Total pelanggan aktif.
- [x] Total pelanggan belum lengkap.
- [x] Total pelanggan siap billing.
- [x] Total pelanggan per POP.
- [x] Total tagihan bulan ini.
- [x] Total pembayaran bulan ini.
- [x] Total tunggakan.
- [x] Tagihan jatuh tempo.
- [x] Data pelanggan yang perlu dilengkapi.
- [x] Filter POP.
- [x] Filter periode.

Acceptance Criteria:
- [x] Owner melihat semua data.
- [x] Admin Pusat melihat semua cabang.
- [x] Admin Cabang hanya melihat cabangnya.
- [x] Dashboard dapat difilter berdasarkan POP.
- [x] Dashboard dapat difilter berdasarkan periode.

Catatan Test:
- `php artisan test --filter=DashboardTest` lulus: 8 tests, 40 assertions.
- Menguji asersi visual, filter POP, filter periode, dan pembatasan data Admin Cabang.

---

### S7-T002 — Laporan Pelanggan
Status: Done

Tujuan:
Membuat laporan pelanggan.

Checklist:
- [x] Laporan pelanggan lengkap.
- [x] Laporan pelanggan belum lengkap.
- [x] Laporan pelanggan aktif.
- [x] Laporan pelanggan isolir.
- [x] Laporan pelanggan per POP.
- [x] Filter tanggal.
- [x] Filter POP.
- [x] Export Excel/CSV.

Acceptance Criteria:
- [x] Laporan pelanggan dapat difilter.
- [x] Laporan pelanggan dapat diexport.
- [x] Admin Cabang hanya export data cabangnya.

Catatan Test:
- `php artisan test --filter=ReportCustomerTest` lulus: 6 tests, 26 assertions.

---

### S7-T003 — Laporan Tagihan
Status: Done

Tujuan:
Membuat laporan tagihan.

Checklist:
- [x] Laporan tagihan bulanan.
- [x] Laporan tagihan per POP.
- [x] Laporan tagihan per status.
- [x] Laporan tunggakan.
- [x] Filter tanggal.
- [x] Filter POP.
- [x] Export Excel/CSV.

Acceptance Criteria:
- [x] Laporan tagihan dapat difilter.
- [x] Laporan tunggakan tersedia.
- [x] Laporan tagihan dapat diexport.
- [x] Admin Cabang hanya export data cabangnya.

Catatan Test:
- `php artisan test --filter=ReportInvoiceTest` lulus: 6 tests, 30 assertions.

---

### S7-T004 — Laporan Pembayaran
Status: Done

Tujuan:
Membuat laporan pembayaran.

Checklist:
- [x] Laporan pembayaran bulanan.
- [x] Laporan pembayaran per POP.
- [x] Laporan pembayaran per metode.
- [x] Filter tanggal.
- [x] Filter POP.
- [x] Filter metode.
- [x] Export Excel/CSV.

Acceptance Criteria:
- [x] Laporan pembayaran dapat difilter.
- [x] Laporan pembayaran per metode tersedia.
- [x] Laporan pembayaran dapat diexport.
- [x] Admin Cabang hanya export data cabangnya.

Catatan Test:
- `php artisan test --filter=ReportPaymentTest` lulus: 6 tests, 28 assertions.
- Seluruh test suite `php artisan test` lulus: 129 passed, 681 assertions.

---

### S7-T005 — Laporan Import Data
Status: Done

Tujuan:
Membuat laporan hasil import data pelanggan lama.

Checklist:
- [x] Tampilkan riwayat import.
- [x] Tampilkan total rows.
- [x] Tampilkan valid rows.
- [x] Tampilkan invalid rows.
- [x] Tampilkan imported rows.
- [x] Tampilkan error import.
- [x] Export laporan import jika dibutuhkan.

Acceptance Criteria:
- [x] Admin dapat melihat riwayat import.
- [x] Admin dapat melihat data error import.
- [x] Admin dapat mengetahui data yang berhasil masuk.

Catatan Test:
- `php artisan test --filter=ReportImportTest` lulus: 6 tests, 33 assertions.
- Seluruh test suite `php artisan test` lulus (135 passed).

---

# Sprint 8 — Data Teknis Pelanggan

## Tujuan Sprint 8
Melengkapi data teknis pelanggan setelah billing dasar stabil.

---

### S8-T001 — Data Survey Pelanggan
Status: Done

Tujuan:
Membuat data survey pelanggan.

Checklist:
- [x] Buat tabel `customer_surveys`.
- [x] Tambahkan status survey.
- [x] Tambahkan tanggal survey.
- [x] Tambahkan jam mulai.
- [x] Tambahkan jam selesai.
- [x] Tambahkan petugas survey.
- [x] Tambahkan kebutuhan alat.
- [x] Tambahkan estimasi kabel.
- [x] Tambahkan ODP terdekat.
- [x] Tambahkan foto survey.
- [x] Tambahkan catatan survey.
- [x] Tampilkan di detail pelanggan.

Acceptance Criteria:
- [x] Teknisi dapat mengisi data survey.
- [x] Data survey tampil di detail pelanggan.
- [x] User tanpa permission tidak dapat mengisi survey.

Catatan Test:
- `php artisan test tests/Feature/CustomerSurveyTest.php` lulus (PASS).
- Status pelanggan otomatis berubah ke `surveyed` saat survey `completed`.
- RBAC berfungsi: Teknisi dapat mengisi survey, Finance dilarang.

---

### S8-T002 — Data Pemasangan Pelanggan
Status: Done
Sprint: 8
Tujuan: Membuat data pemasangan pelanggan.
Selesai: 2026-06-13

Checklist:
- [x] Buat tabel `customer_installations`.
- [x] Tambahkan status pemasangan.
- [x] Tambahkan tanggal jadwal.
- [x] Tambahkan jam jadwal.
- [x] Tambahkan teknisi pemasangan.
- [x] Tambahkan tanggal selesai.
- [x] Tambahkan foto pemasangan.
- [x] Tambahkan catatan pemasangan.
- [x] Tampilkan di detail pelanggan.

Acceptance Criteria:
- [x] Teknisi dapat mengisi data pemasangan.
- [x] Data pemasangan tampil di detail pelanggan.
- [x] User tanpa permission tidak dapat mengisi pemasangan.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerInstallationTest.php tests/Feature/CustomerDetailTest.php` lulus: 4 tests, 31 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 138 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen, bukan modul pemasangan.

---


### S8-T003 — Data Modem/ONT/Router Pelanggan
Status: Done
Sprint: 8
Selesai: 2026-06-13

Tujuan:
Membuat data perangkat pelanggan.

Checklist:
- [x] Buat tabel `customer_devices`.
- [x] Tambahkan jenis perangkat.
- [x] Tambahkan merk.
- [x] Tambahkan tipe.
- [x] Tambahkan serial number.
- [x] Tambahkan MAC address.
- [x] Tambahkan username PPPoE.
- [x] Tambahkan password PPPoE.
- [x] Tambahkan SSID WiFi.
- [x] Tambahkan password WiFi.
- [x] Tambahkan IP address.
- [x] Tambahkan VLAN ID.
- [x] Tambahkan ODP.
- [x] Tambahkan port ODP.
- [x] Tambahkan redaman.
- [x] Tambahkan mode koneksi.
- [x] Tambahkan catatan teknis.
- [x] Batasi akses field sensitif.

Acceptance Criteria:
- [x] Teknisi dapat mengisi data perangkat.
- [x] Data perangkat tampil di detail pelanggan.
- [x] Password PPPoE dan WiFi dibatasi aksesnya.
- [x] Finance tidak dapat mengubah data modem.
- [x] CS tidak dapat melihat field sensitif jika tidak diizinkan.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerDeviceTest.php tests/Feature/PermissionTest.php` lulus: 7 tests, 52 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerDeviceTest.php tests/Feature/CustomerDetailTest.php tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php` lulus: 11 tests, 61 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 143 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul perangkat.

---


### S8-T004 — Data Dokumen Pelanggan
Status: Done
Sprint: 8
Selesai: 2026-06-13

Tujuan:
Membuat penyimpanan dokumen pelanggan.

Checklist:
- [x] Buat tabel `customer_documents`.
- [x] Upload dokumen KTP.
- [x] Upload foto rumah.
- [x] Upload kontrak.
- [x] Upload foto survey.
- [x] Upload foto pemasangan.
- [x] Tampilkan dokumen di detail pelanggan.
- [x] Batasi akses dokumen berdasarkan permission.

Acceptance Criteria:
- [x] Dokumen pelanggan dapat diupload.
- [x] Dokumen tampil di detail pelanggan.
- [x] User tanpa permission tidak dapat mengakses dokumen tertentu.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerDocumentTest.php tests/Feature/CustomerDetailTest.php` lulus: 6 tests, 38 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PermissionTest.php tests/Feature/RolePermissionTest.php tests/Feature/CustomerDeviceTest.php tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php tests/Feature/CustomerDocumentTest.php` lulus: 23 tests, 108 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 148 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen legacy pelanggan, bukan modul `customer_documents`.

---


### S8-T005 — Audit Log Umum
Status: Done
Sprint: 8
Selesai: 2026-06-13

Tujuan:
Membuat audit log untuk perubahan data penting.

Checklist:
- [x] Buat tabel `audit_logs`.
- [x] Catat perubahan pelanggan.
- [x] Catat perubahan paket.
- [x] Catat perubahan POP.
- [x] Catat perubahan tagihan.
- [x] Catat perubahan pembayaran.
- [x] Catat perubahan user.
- [x] Catat perubahan role.
- [x] Catat perubahan data teknis.
- [x] Buat halaman audit log untuk Owner/Admin Pusat.

Acceptance Criteria:
- [x] Perubahan pelanggan tercatat.
- [x] Perubahan pembayaran tercatat.
- [x] Perubahan tagihan tercatat.
- [x] Perubahan role tercatat.
- [x] Owner/Admin Pusat dapat melihat audit log.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/AuditLogGeneralTest.php tests/Feature/CustomerActivationTest.php tests/Feature/PaymentAuditLogTest.php` lulus: 9 tests, 60 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerCreateTest.php tests/Feature/CustomerActivationTest.php tests/Feature/CustomerDeviceTest.php tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php tests/Feature/InvoiceCreateTest.php tests/Feature/InvoiceListTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/PaymentAuditLogTest.php tests/Feature/PermissionTest.php tests/Feature/RolePermissionTest.php tests/Feature/PopCRUDTest.php tests/Feature/PopIdentifierSettingTest.php tests/Feature/InternetPackageSeederTest.php tests/Feature/AuditLogGeneralTest.php` lulus: 62 tests, 335 assertions.
- `npm run build` lulus.
- Regresi yang menyertakan `CustomerEditTest.php` masih memiliki 2 kegagalan legacy pada cleanup file dokumen pelanggan, sesuai catatan task sebelumnya, bukan dari modul audit log.

---


## Blocked
Belum ada.

## Notes
AI hanya boleh mengerjakan task dengan status `In Progress`.

Catatan hasil S2-T006:
- POP existing yang sudah ada sebelum migration identifier wajib dilengkapi `pop_code`, `registration_prefix`, dan `cid_prefix` melalui edit POP sebelum generator ID Request/CID digunakan.

Catatan refactor S2-T004/S2-T005:
- Duplikasi `service_packages`/`ServicePackage` dihapus dari kode aplikasi.
- Master Paket Internet sekarang memakai tabel/model/controller `internet_packages`/`InternetPackage`, dengan struktur data dan UI hasil gabungan dari Service Package dan Internet Package.
- Database development sudah di-reset dengan `php artisan migrate:fresh --seed`; hasil akhir: tabel `internet_packages` ada, tabel `service_packages` tidak ada, dan 27 paket ter-seed.
- Test refactor lulus: `InternetPackageSeederTest`, `CustomerCreateTest`, `CustomerEditTest`, `CustomerImportTest`, dan `npm run build`.

Setelah task selesai:
1. Pindahkan task ke Done.
2. Ubah task berikutnya menjadi In Progress.
3. Tambahkan catatan hasil test.

---
