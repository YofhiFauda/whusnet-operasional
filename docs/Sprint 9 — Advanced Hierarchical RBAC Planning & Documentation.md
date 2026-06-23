# Sprint 9 — Advanced Hierarchical RBAC Planning & Documentation

## Tujuan Sprint 9
Meningkatkan desain RBAC dari role-permission sederhana menjadi Advanced Hierarchical RBAC yang mendukung:
- **Role utama**: Owner, Atasan, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin.
- **Feature Tree**: fitur utama, cabang fitur, mini fitur.
- **Action Permission**: view, create, update, delete, import, export, print, validate, activate, cancel, upload, download, view_sensitive, update_sensitive.
- **User Scope**: all_pop, selected_pop, pop_tree, assigned_only, own_created.
- Pemisahan antara Role dan Scope agar tidak perlu membuat role per cabang.
- **Contoh target**: User: NOC Pusat | Role: NOC | Scope: Semua POP/ all_pop

**Catatan**: Sprint ini fokus memperbaiki dokumen, desain, dan task planning sebelum implementasi database/kode Advanced RBAC.

---

### S9-T001 — Normalisasi docs/TASKS.md dan Tambahkan Roadmap Advanced RBAC
**Status**: Todo  
**Tujuan**: Merapikan `docs/TASKS.md` agar tidak ada duplikasi sprint/task, format konsisten, dan roadmap Advanced RBAC masuk dengan urutan yang benar.  
**Checklist**:
- [ ] Hapus duplikasi Sprint 2 yang muncul berulang.
- [ ] Pastikan Sprint 1 sampai Sprint 8 tetap sesuai status terakhir.
- [ ] Pastikan hanya S8-T006 — Import Data Legacy sand_db_sandya.sql yang berstatus In Progress selama task tersebut belum selesai.
- [ ] Tambahkan Sprint 9 sampai Sprint 13 sebagai roadmap baru.
- [ ] Pastikan semua task Sprint 9+ berstatus Todo.
- [ ] Rapikan heading sprint.
- [ ] Rapikan format Checklist.
- [ ] Rapikan format Acceptance Criteria.
- [ ] Tambahkan catatan bahwa Advanced RBAC dikerjakan setelah S8-T006 selesai.
- [ ] Tambahkan catatan bahwa AI hanya boleh mengerjakan task In Progress.

**Acceptance Criteria**:
- [ ] Tidak ada task duplikat.
- [ ] Tidak ada sprint duplikat.
- [ ] Hanya satu task berstatus In Progress.
- [ ] Sprint 9+ tersedia sebagai roadmap lanjutan.
- [ ] AI dapat membaca task aktif dengan jelas.

---

### S9-T002 — Update docs/RBAC_MATRIX.md untuk Advanced RBAC
**Status**: Todo  
**Tujuan**: Mengubah dokumen RBAC dari role sederhana menjadi hierarchical feature-based RBAC.  
**Checklist**:
- [ ] Tambahkan role baru: Owner.
- [ ] Tambahkan role baru: Atasan.
- [ ] Tambahkan role baru: Admin.
- [ ] Tambahkan role baru: NOC.
- [ ] Tambahkan role baru: Helpdesk.
- [ ] Tambahkan role baru: FOP.
- [ ] Tambahkan role baru: Teknisi.
- [ ] Tambahkan role baru: Sales.
- [ ] Tambahkan role baru: POP Admin.
- [ ] Tambahkan konsep Feature Tree.
- [ ] Tambahkan konsep Action Permission.
- [ ] Tambahkan konsep User Scope.
- [ ] Tambahkan aturan bahwa Role tidak boleh dibuat per cabang.
- [ ] Tambahkan aturan bahwa Scope menentukan cakupan data.
- [ ] Tambahkan contoh NOC Pusat: role NOC, scope all_pop.
- [ ] Tambahkan contoh POP Admin Siman: role POP Admin, scope selected_pop.
- [ ] Tambahkan matrix permission per role.
- [ ] Tambahkan field-level permission untuk data sensitif.
- [ ] Tambahkan aturan route middleware.
- [ ] Tambahkan aturan query POP scope.

**Acceptance Criteria**:
- [ ] `docs/RBAC_MATRIX.md` menjelaskan role baru.
- [ ] `docs/RBAC_MATRIX.md` menjelaskan feature tree.
- [ ] `docs/RBAC_MATRIX.md` menjelaskan action permission.
- [ ] `docs/RBAC_MATRIX.md` menjelaskan user scope.
- [ ] Role dan scope dipisahkan dengan jelas.
- [ ] Tidak ada rekomendasi membuat role per cabang.
- [ ] Permission NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin tertulis jelas.

---

### S9-T003 — Update docs/DATABASE_RULES.md untuk Advanced RBAC
**Status**: Todo  
**Tujuan**: Menambahkan aturan database untuk feature tree, action, permission berbasis feature-action, dan user role scope.  
**Checklist**:
- [ ] Tambahkan aturan tabel `features`.
- [ ] Tambahkan aturan tabel `actions`.
- [ ] Tambahkan aturan perubahan tabel `permissions`.
- [ ] Tambahkan aturan tabel `role_permissions`.
- [ ] Tambahkan aturan tabel `user_role_scopes`.
- [ ] Tambahkan aturan optional `user_permission_overrides`.
- [ ] Tambahkan unique constraint `features.code`.
- [ ] Tambahkan unique constraint `actions.code`.
- [ ] Tambahkan unique constraint `permissions.code`.
- [ ] Tambahkan unique constraint kombinasi `feature_id` dan `action_id`.
- [ ] Tambahkan aturan scope type.
- [ ] Tambahkan aturan `all_pop`.
- [ ] Tambahkan aturan `selected_pop`.
- [ ] Tambahkan aturan `pop_tree`.
- [ ] Tambahkan aturan `assigned_only`.
- [ ] Tambahkan aturan `own_created`.
- [ ] Tambahkan larangan membuat ID permission tidak konsisten.
- [ ] Tambahkan larangan hardcode permission string sembarangan.

**Acceptance Criteria**:
- [ ] `docs/DATABASE_RULES.md` memuat tabel `features`.
- [ ] `docs/DATABASE_RULES.md` memuat tabel `actions`.
- [ ] `docs/DATABASE_RULES.md` memuat tabel `user_role_scopes`.
- [ ] Aturan permission `{feature_code}.{action_code}` tertulis jelas.
- [ ] Aturan scope user tertulis jelas.
- [ ] Aturan migrasi dari RBAC lama ke RBAC baru tertulis jelas.

---

### S9-T004 — Update docs/BUSINESS_RULES.md untuk Role dan Scope Baru
**Status**: Todo  
**Tujuan**: Memastikan aturan bisnis project memahami role baru dan batasan scope data.  
**Checklist**:
- [ ] Tambahkan aturan Owner.
- [ ] Tambahkan aturan Atasan.
- [ ] Tambahkan aturan Admin.
- [ ] Tambahkan aturan NOC.
- [ ] Tambahkan aturan Helpdesk.
- [ ] Tambahkan aturan FOP.
- [ ] Tambahkan aturan Teknisi.
- [ ] Tambahkan aturan Sales.
- [ ] Tambahkan aturan POP Admin.
- [ ] Tambahkan aturan NOC Pusat bisa scope semua POP.
- [ ] Tambahkan aturan NOC Cabang hanya selected POP.
- [ ] Tambahkan aturan POP Admin wajib selected POP.
- [ ] Tambahkan aturan Sales bisa own_created atau selected_pop.
- [ ] Tambahkan aturan Teknisi bisa selected_pop atau assigned_only.
- [ ] Tambahkan larangan Teknisi mengakses pembayaran.
- [ ] Tambahkan larangan Helpdesk mengubah nominal tagihan.
- [ ] Tambahkan larangan Sales mengakses laporan pembayaran.
- [ ] Tambahkan larangan POP Admin melihat POP lain.

**Acceptance Criteria**:
- [ ] Business rules role baru tersedia.
- [ ] Scope data per role tertulis jelas.
- [ ] Larangan role sensitif tertulis jelas.
- [ ] NOC Pusat, POP Admin, Teknisi, Sales memiliki aturan akses yang jelas.

---

### S9-T005 — Update docs/PAGE_STRUCTURE.md untuk UI Role, Feature, Permission, dan Scope
**Status**: Todo  
**Tujuan**: Menambahkan struktur halaman untuk Advanced RBAC.  
**Checklist**:
- [ ] Tambahkan halaman Feature Management.
- [ ] Tambahkan halaman Action Management.
- [ ] Tambahkan halaman Permission Matrix.
- [ ] Tambahkan halaman Role Permission Matrix.
- [ ] Tambahkan halaman User Role Scope.
- [ ] Tambahkan struktur form tambah user dengan role dan scope.
- [ ] Tambahkan struktur preview effective permission.
- [ ] Tambahkan struktur permission matrix tree.
- [ ] Tambahkan aturan menu berdasarkan feature permission.
- [ ] Tambahkan aturan tombol berdasarkan action permission.
- [ ] Tambahkan aturan field sensitif berdasarkan permission sensitive.
- [ ] Tambahkan empty state untuk feature tree.
- [ ] Tambahkan role akses halaman Advanced RBAC.

**Acceptance Criteria**:
- [ ] Struktur halaman Advanced RBAC tersedia.
- [ ] Struktur form tambah/edit user dengan role dan scope tersedia.
- [ ] Struktur permission matrix berbasis feature tree tersedia.
- [ ] Struktur preview effective permission tersedia.
- [ ] Role yang boleh mengelola RBAC tertulis jelas.

---

### S9-T006 — Update docs/DEFINITION_OF_DONE.md untuk Advanced RBAC
**Status**: Todo  
**Tujuan**: Menambahkan standar task selesai untuk Advanced RBAC.  
**Checklist**:
- [ ] Tambahkan DoD untuk Feature Tree.
- [ ] Tambahkan DoD untuk Action Permission.
- [ ] Tambahkan DoD untuk Permission Generator.
- [ ] Tambahkan DoD untuk Role Permission Matrix.
- [ ] Tambahkan DoD untuk User Role Scope.
- [ ] Tambahkan DoD untuk User Form Role Scope.
- [ ] Tambahkan DoD untuk Effective Permission Preview.
- [ ] Tambahkan DoD untuk Middleware Feature Action Permission.
- [ ] Tambahkan DoD untuk POP Scope Helper.
- [ ] Tambahkan DoD untuk Sidebar berbasis permission.
- [ ] Tambahkan DoD untuk test Advanced RBAC.
- [ ] Tambahkan larangan menandai task Done jika route belum aman.
- [ ] Tambahkan larangan menandai task Done jika POP scope bocor.
- [ ] Tambahkan larangan menandai task Done jika user bisa akses URL langsung tanpa permission.

**Acceptance Criteria**:
- [ ] DoD Advanced RBAC tersedia.
- [ ] Setiap task Advanced RBAC punya standar selesai.
- [ ] Route middleware menjadi syarat Done.
- [ ] POP scope menjadi syarat Done.
- [ ] Test RBAC menjadi syarat Done.

---

### S9-T007 — Update docs/MVP_SUCCESS_CHECKLIST.md untuk Advanced RBAC
**Status**: Todo  
**Tujuan**: Menambahkan checklist final MVP untuk Advanced RBAC.  
**Checklist**:
- [ ] Tambahkan checklist role baru tersedia.
- [ ] Tambahkan checklist feature tree tersedia.
- [ ] Tambahkan checklist action tersedia.
- [ ] Tambahkan checklist permission berbasis feature-action tersedia.
- [ ] Tambahkan checklist user role scope tersedia.
- [ ] Tambahkan checklist NOC Pusat all_pop.
- [ ] Tambahkan checklist POP Admin selected_pop.
- [ ] Tambahkan checklist Teknisi tidak bisa pembayaran.
- [ ] Tambahkan checklist Helpdesk tidak bisa ubah nominal tagihan.
- [ ] Tambahkan checklist Sales tidak bisa laporan pembayaran.
- [ ] Tambahkan checklist route direct access aman.
- [ ] Tambahkan checklist field sensitive aman.
- [ ] Tambahkan checklist POP scope tidak bocor.

**Acceptance Criteria**:
- [ ] MVP checklist memuat Advanced RBAC.
- [ ] Checklist role baru tersedia.
- [ ] Checklist scope baru tersedia.
- [ ] Checklist permission feature-action tersedia.
- [ ] Checklist keamanan RBAC tersedia.

---

### S9-T008 — Update docs/DAILY_PROMPTS.md untuk Advanced RBAC
**Status**: Todo  
**Tujuan**: Menambahkan prompt khusus Advanced RBAC agar AI tidak salah membangun role, permission, dan scope.  
**Checklist**:
- [ ] Tambahkan Prompt Advanced RBAC Scope Check.
- [ ] Tambahkan Prompt Feature Tree.
- [ ] Tambahkan Prompt Action Permission.
- [ ] Tambahkan Prompt Permission Generator.
- [ ] Tambahkan Prompt Role Matrix.
- [ ] Tambahkan Prompt User Role Scope.
- [ ] Tambahkan Prompt User Form Role Scope.
- [ ] Tambahkan Prompt Middleware Permission.
- [ ] Tambahkan Prompt POP Scope.
- [ ] Tambahkan Prompt RBAC Test.
- [ ] Tambahkan prompt cegah role per cabang.
- [ ] Tambahkan prompt cegah permission hardcode sembarangan.

**Acceptance Criteria**:
- [ ] Prompt Advanced RBAC tersedia.
- [ ] Prompt mengunci role dan scope tetap terpisah.
- [ ] Prompt melarang role per cabang.
- [ ] Prompt mewajibkan scope check sebelum coding.

---

### S9-T009 — Update AGENTS.md untuk Advanced RBAC
**Status**: Todo  
**Tujuan**: Memastikan AI Agent membaca dan mengikuti aturan Advanced RBAC.  
**Checklist**:
- [ ] Tambahkan Advanced RBAC ke Required Reading.
- [ ] Tambahkan aturan role tidak boleh dibuat per cabang.
- [ ] Tambahkan aturan role dan scope harus dipisah.
- [ ] Tambahkan aturan permission berbasis feature-action.
- [ ] Tambahkan aturan format permission.
- [ ] Tambahkan aturan scope `all_pop`.
- [ ] Tambahkan aturan scope `selected_pop`.
- [ ] Tambahkan aturan scope `pop_tree`.
- [ ] Tambahkan aturan scope `assigned_only`.
- [ ] Tambahkan aturan scope `own_created`.
- [ ] Tambahkan stop condition jika AI ingin membuat role cabang seperti NOC Siman.
- [ ] Tambahkan stop condition jika AI ingin memberi permission langsung ke user tanpa alasan.
- [ ] Tambahkan stop condition jika perubahan RBAC bisa membocorkan data POP.

**Acceptance Criteria**:
- [ ] `AGENTS.md` memahami Advanced RBAC.
- [ ] AI dilarang membuat role per cabang.
- [ ] AI wajib memakai role + scope.
- [ ] AI wajib memakai permission berbasis feature-action.
- [ ] Stop condition Advanced RBAC tersedia.

---
---

# Sprint 10 — Advanced RBAC Database & Core Engine

## Tujuan Sprint 10
Mengimplementasikan pondasi database dan core engine Advanced RBAC: feature tree, action, permission generator, role matrix, user role scope, dan helper effective permission.  
Sprint ini mulai menyentuh database dan core logic, tetapi belum fokus ke UI matrix penuh.

### S10-T001 — Migration dan Model Feature Tree
**Status**: Todo  
**Tujuan**: Membuat struktur fitur utama, cabang fitur, dan mini fitur.  
**Checklist**:
- [ ] Buat tabel `features`.
- [ ] Tambahkan field `parent_id`.
- [ ] Tambahkan field `code`.
- [ ] Tambahkan field `name`.
- [ ] Tambahkan field `type`.
- [ ] Tambahkan field `sort_order`.
- [ ] Tambahkan field `is_active`.
- [ ] Tambahkan unique constraint `code`.
- [ ] Tambahkan index `parent_id`.
- [ ] Tambahkan index `type`.
- [ ] Buat model `Feature`.
- [ ] Buat relasi parent.
- [ ] Buat relasi children.
- [ ] Buat helper membaca feature tree.

**Acceptance Criteria**:
- [ ] Feature dapat disimpan.
- [ ] Feature dapat bertingkat.
- [ ] Feature utama, sub feature, dan mini feature tersedia.
- [ ] Feature code unique.
- [ ] Relasi parent-child berjalan.

---

### S10-T002 — Seeder Feature Tree Awal
**Status**: Todo  
**Tujuan**: Mengisi data feature tree awal sesuai fitur MVP dan Advanced RBAC.  
**Checklist**:
- [ ] Seed feature Dashboard.
- [ ] Seed feature POP/Cabang.
- [ ] Seed feature User Management.
- [ ] Seed feature Role & Permission.
- [ ] Seed feature Paket Internet.
- [ ] Seed feature Pelanggan.
- [ ] Seed feature Pelanggan > Daftar Pelanggan.
- [ ] Seed feature Pelanggan > Detail Pelanggan.
- [ ] Seed feature Detail Pelanggan > Identitas.
- [ ] Seed feature Detail Pelanggan > Alamat.
- [ ] Seed feature Detail Pelanggan > POP/Cabang.
- [ ] Seed feature Detail Pelanggan > Paket & Layanan.
- [ ] Seed feature Detail Pelanggan > Billing.
- [ ] Seed feature Detail Pelanggan > Tagihan.
- [ ] Seed feature Detail Pelanggan > Pembayaran.
- [ ] Seed feature Detail Pelanggan > Survey.
- [ ] Seed feature Detail Pelanggan > Pemasangan.
- [ ] Seed feature Detail Pelanggan > Perangkat.
- [ ] Seed feature Detail Pelanggan > Dokumen.
- [ ] Seed feature Import Pelanggan.
- [ ] Seed feature Billing.
- [ ] Seed feature Billing > Tagihan.
- [ ] Seed feature Billing > Pembayaran.
- [ ] Seed feature Laporan.
- [ ] Seed feature Audit Log.

**Acceptance Criteria**:
- [ ] Feature tree awal tersedia dari seeder.
- [ ] Semua fitur MVP masuk feature tree.
- [ ] Detail pelanggan memiliki mini feature.
- [ ] Billing memiliki sub feature Tagihan dan Pembayaran.
- [ ] Tidak ada feature post-MVP yang aktif.

---

### S10-T003 — Migration, Model, dan Seeder Action Permission
**Status**: Todo  
**Tujuan**: Membuat daftar action yang bisa dipasang ke feature.  
**Checklist**:
- [ ] Buat tabel `actions`.
- [ ] Tambahkan field `code`.
- [ ] Tambahkan field `name`.
- [ ] Tambahkan field `description`.
- [ ] Tambahkan unique constraint `code`.
- [ ] Buat model `Action`.
- [ ] Seed action `view`.
- [ ] Seed action `create`.
- [ ] Seed action `update`.
- [ ] Seed action `delete`.
- [ ] Seed action `import`.
- [ ] Seed action `export`.
- [ ] Seed action `print`.
- [ ] Seed action `approve`.
- [ ] Seed action `reject`.
- [ ] Seed action `activate`.
- [ ] Seed action `deactivate`.
- [ ] Seed action `assign`.
- [ ] Seed action `validate`.
- [ ] Seed action `cancel`.
- [ ] Seed action `upload`.
- [ ] Seed action `download`.
- [ ] Seed action `view_sensitive`.
- [ ] Seed action `update_sensitive`.

**Acceptance Criteria**:
- [ ] Action CRUD tersedia.
- [ ] Action bisnis tersedia.
- [ ] Action sensitive tersedia.
- [ ] Action code unique.
- [ ] Action dapat digunakan untuk permission generator.

---

### S10-T004 — Refactor Permission Menjadi Feature-Action Permission
**Status**: Todo  
**Tujuan**: Mengubah permission agar berbasis kombinasi feature dan action.  
**Checklist**:
- [ ] Tambahkan `feature_id` ke tabel `permissions`.
- [ ] Tambahkan `action_id` ke tabel `permissions`.
- [ ] Pastikan field `code` tersedia dan unique.
- [ ] Format permission: `{feature_code}.{action_code}`.
- [ ] Contoh: `customers.view`.
- [ ] Contoh: `customers.detail.identity.update`.
- [ ] Contoh: `customers.detail.devices.view_sensitive`.
- [ ] Buat relasi permission ke feature.
- [ ] Buat relasi permission ke action.
- [ ] Buat generator permission.
- [ ] Cegah permission duplikat.
- [ ] Pastikan permission lama dapat dimigrasikan atau digantikan aman.

**Acceptance Criteria**:
- [ ] Permission terhubung ke feature.
- [ ] Permission terhubung ke action.
- [ ] Permission code konsisten.
- [ ] Permission tidak duplikat.
- [ ] Permission lama tidak merusak login/akses existing.

---

### S10-T005 — Permission Generator dari Feature dan Action
**Status**: Todo  
**Tujuan**: Membuat service/command untuk menghasilkan permission dari feature dan action.  
**Checklist**:
- [ ] Buat service `PermissionGeneratorService`.
- [ ] Buat command `php artisan rbac:generate-permissions`.
- [ ] Generate permission hanya untuk kombinasi feature-action yang valid.
- [ ] Jangan generate semua action untuk semua feature jika tidak relevan.
- [ ] Buat konfigurasi allowed actions per feature.
- [ ] Pastikan permission code unique.
- [ ] Pastikan generator idempotent.
- [ ] Tampilkan summary permission dibuat/dilewati.
- [ ] Tambahkan test generator.

**Acceptance Criteria**:
- [ ] Command generator berjalan tanpa error.
- [ ] Permission dibuat sesuai feature-action.
- [ ] Generator bisa dijalankan berulang tanpa duplikasi.
- [ ] Permission post-MVP tidak dibuat aktif.
- [ ] Test generator lulus.

---

### S10-T006 — Role Migration dan Seeder Role Baru
**Status**: Todo  
**Tujuan**: Menambahkan role baru dan mengatur migrasi dari role lama ke role baru.  
**Checklist**:
- [ ] Tambahkan role Owner.
- [ ] Tambahkan role Atasan.
- [ ] Tambahkan role Admin.
- [ ] Tambahkan role NOC.
- [ ] Tambahkan role Helpdesk.
- [ ] Tambahkan role FOP.
- [ ] Tambahkan role Teknisi.
- [ ] Tambahkan role Sales.
- [ ] Tambahkan role POP Admin.
- [ ] Mapping role lama Admin Pusat ke Admin dengan scope `all_pop`.
- [ ] Mapping role lama Admin Cabang ke POP Admin dengan scope `selected_pop`.
- [ ] Mapping role lama Customer Service ke Helpdesk.
- [ ] Mapping role lama Finance/Kasir ke role yang disepakati.
- [ ] Pastikan role lama tidak langsung dihapus sebelum migrasi aman.
- [ ] Tambahkan catatan migrasi role.

**Acceptance Criteria**:
- [ ] Role baru tersedia.
- [ ] Role lama memiliki strategi migrasi.
- [ ] User existing tidak kehilangan akses login.
- [ ] Mapping role lama terdokumentasi.
- [ ] Tidak ada role per cabang.

**Catatan**: Untuk Finance/Kasir, tentukan keputusan:
- Opsi A: dimasukkan ke role Admin dengan permission pembayaran.
- Opsi B: tetap dipertahankan sebagai role tambahan.
- Opsi C: dibuat role Kasir jika bisnis masih butuh pemisahan pembayaran.
*AI wajib meminta konfirmasi sebelum menghapus atau mengganti total role Finance/Kasir.*

---

### S10-T007 — Role Permission Matrix Seeder
**Status**: Todo  
**Tujuan**: Membuat mapping permission default untuk setiap role baru.  
**Checklist**:
- [ ] Buat mapping permission Owner.
- [ ] Buat mapping permission Atasan.
- [ ] Buat mapping permission Admin.
- [ ] Buat mapping permission NOC.
- [ ] Buat mapping permission Helpdesk.
- [ ] Buat mapping permission FOP.
- [ ] Buat mapping permission Teknisi.
- [ ] Buat mapping permission Sales.
- [ ] Buat mapping permission POP Admin.
- [ ] Pastikan Owner memiliki semua permission.
- [ ] Pastikan Atasan fokus dashboard/laporan/audit terbatas.
- [ ] Pastikan Admin fokus operasional.
- [ ] Pastikan NOC fokus monitoring dan teknis jaringan.
- [ ] Pastikan Helpdesk fokus layanan pelanggan.
- [ ] Pastikan FOP fokus survey/pemasangan lapangan.
- [ ] Pastikan Teknisi fokus survey/pemasangan/perangkat.
- [ ] Pastikan Sales fokus registrasi/follow-up.
- [ ] Pastikan POP Admin fokus operasional POP.
- [ ] Pastikan Teknisi tidak mendapat payment permission.
- [ ] Pastikan Helpdesk tidak mendapat update nominal tagihan.
- [ ] Pastikan Sales tidak mendapat laporan pembayaran.

**Acceptance Criteria**:
- [ ] Setiap role memiliki permission default.
- [ ] Permission role sesuai matrix.
- [ ] Tidak ada permission berlebihan pada role teknis/sales/helpdesk.
- [ ] Seeder role permission idempotent.
- [ ] Test role permission lulus.

---

### S10-T008 — Migration dan Model User Role Scope
**Status**: Todo  
**Tujuan**: Memisahkan role dari cakupan data user.  
**Checklist**:
- [ ] Buat tabel `user_role_scopes`.
- [ ] Tambahkan `user_id`.
- [ ] Tambahkan `role_id`.
- [ ] Tambahkan `scope_type`.
- [ ] Tambahkan `pop_id` nullable.
- [ ] Tambahkan index `user_id`.
- [ ] Tambahkan index `role_id`.
- [ ] Tambahkan index `pop_id`.
- [ ] Tambahkan validasi scope type.
- [ ] Buat model `UserRoleScope`.
- [ ] Buat relasi user ke user role scope.
- [ ] Buat relasi role ke user role scope.
- [ ] Buat relasi POP ke user role scope.
- [ ] Migrasikan `user_pops` lama jika diperlukan.

**Scope Type**:
`all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`

**Acceptance Criteria**:
- [ ] User dapat memiliki role dengan scope.
- [ ] Role dan scope terpisah.
- [ ] NOC Pusat dapat dibuat dengan role NOC dan scope `all_pop`.
- [ ] POP Admin dapat dibuat dengan role POP Admin dan scope `selected_pop`.
- [ ] Tidak perlu membuat role per cabang.

---

### S10-T009 — Effective Permission dan Effective Scope Service
**Status**: Todo  
**Tujuan**: Membuat service untuk menghitung permission dan scope efektif user.  
**Checklist**:
- [ ] Buat `EffectiveAccessService`.
- [ ] Buat method membaca role user.
- [ ] Buat method membaca permission role.
- [ ] Buat method membaca scope user.
- [ ] Buat method `userCan($permissionCode)`.
- [ ] Buat method `userCan($featureCode, $actionCode)`.
- [ ] Buat method `getAllowedPopIds($user)`.
- [ ] Dukung scope `all_pop`.
- [ ] Dukung scope `selected_pop`.
- [ ] Dukung scope `pop_tree`.
- [ ] Dukung scope `assigned_only` jika data assignment tersedia.
- [ ] Dukung scope `own_created` jika data `created_by` tersedia.
- [ ] Tambahkan cache jika diperlukan.
- [ ] Tambahkan test service.

**Acceptance Criteria**:
- [ ] Permission efektif user dapat dihitung.
- [ ] Scope efektif user dapat dihitung.
- [ ] NOC `all_pop` melihat semua POP.
- [ ] POP Admin `selected_pop` hanya melihat POP tertentu.
- [ ] Service dapat digunakan middleware dan query.

---

### S10-T010 — Backward Compatibility RBAC Lama
**Status**: Todo  
**Tujuan**: Menjaga agar sistem tetap berjalan selama transisi dari RBAC lama ke Advanced RBAC.  
**Checklist**:
- [ ] Audit middleware permission lama.
- [ ] Audit helper permission lama.
- [ ] Buat adapter dari permission lama ke permission baru jika diperlukan.
- [ ] Pastikan route existing tidak langsung rusak.
- [ ] Pastikan menu existing masih tampil sesuai permission.
- [ ] Pastikan user existing masih bisa login.
- [ ] Pastikan role lama tidak dihapus sebelum mapping selesai.
- [ ] Tambahkan test login user existing.
- [ ] Tambahkan test akses halaman existing.

**Acceptance Criteria**:
- [ ] User existing tetap bisa login.
- [ ] Route existing tetap aman.
- [ ] Tidak ada breaking change besar.
- [ ] RBAC lama bisa berjalan selama migrasi.
- [ ] Transisi ke RBAC baru terdokumentasi.

---
---

# Sprint 11 — Advanced RBAC UI, Middleware, Scope Enforcement & Tests

## Tujuan Sprint 11
Menerapkan Advanced RBAC ke UI dan keamanan aplikasi: form tambah user, permission matrix, middleware feature-action, sidebar, tombol aksi, POP scope query, dan test keamanan.

### S11-T001 — Form Tambah/Edit User dengan Role dan Scope
**Status**: Todo  
**Tujuan**: Mengubah form tambah/edit user agar bisa memilih role dan scope data.  
**Checklist**:
- [ ] Tambahkan pilihan role baru.
- [ ] Tambahkan pilihan scope type.
- [ ] Jika role Owner, default scope `all_pop`.
- [ ] Jika role NOC, boleh `all_pop`, `selected_pop`, atau `pop_tree`.
- [ ] Jika role POP Admin, wajib `selected_pop`.
- [ ] Jika role Teknisi, boleh `selected_pop` atau `assigned_only`.
- [ ] Jika role Sales, boleh `selected_pop` atau `own_created`.
- [ ] Jika scope `selected_pop`, POP wajib dipilih.
- [ ] Jika scope `pop_tree`, POP parent wajib dipilih.
- [ ] Jika scope `all_pop`, POP tidak wajib.
- [ ] Validasi kombinasi role dan scope.
- [ ] Simpan ke `user_role_scopes`.
- [ ] Tampilkan error jika kombinasi tidak valid.

**Acceptance Criteria**:
- [ ] Admin dapat membuat user dengan role dan scope.
- [ ] NOC Pusat bisa dibuat dengan scope `all_pop`.
- [ ] POP Admin tidak bisa dibuat tanpa POP.
- [ ] Teknisi bisa dibatasi `selected_pop`/`assigned_only`.
- [ ] Sales bisa dibatasi `own_created`/`selected_pop`.
- [ ] Validasi role-scope berjalan.

---

### S11-T002 — Effective Permission Preview Saat Tambah/Edit User
**Status**: Todo  
**Tujuan**: Menampilkan ringkasan akses user sebelum disimpan.  
**Checklist**:
- [ ] Tampilkan role yang dipilih.
- [ ] Tampilkan scope yang dipilih.
- [ ] Tampilkan POP yang dipilih jika ada.
- [ ] Tampilkan ringkasan fitur yang bisa diakses.
- [ ] Tampilkan ringkasan action penting.
- [ ] Tampilkan warning jika scope `all_pop`.
- [ ] Tampilkan warning jika role dan scope tidak cocok.
- [ ] Tampilkan warning jika role memiliki permission sensitif.
- [ ] Tampilkan contoh data yang bisa dilihat user.
- [ ] Jangan izinkan simpan jika preview menunjukkan konfigurasi invalid.

**Acceptance Criteria**:
- [ ] Admin dapat melihat hak akses sebelum user disimpan.
- [ ] Scope `all_pop` terlihat jelas.
- [ ] Scope `selected_pop` terlihat jelas.
- [ ] Permission sensitif terlihat jelas.
- [ ] Konfigurasi invalid ditolak.

---

### S11-T003 — Permission Matrix UI Berbasis Feature Tree
**Status**: Todo  
**Tujuan**: Membuat halaman role permission matrix berbasis fitur bertingkat.  
**Checklist**:
- [ ] Buat halaman daftar role.
- [ ] Buat halaman matrix permission role.
- [ ] Tampilkan feature tree expand/collapse.
- [ ] Tampilkan kolom action.
- [ ] Tampilkan checkbox permission.
- [ ] Tampilkan fitur utama.
- [ ] Tampilkan cabang fitur.
- [ ] Tampilkan mini fitur.
- [ ] Simpan perubahan ke `role_permissions`.
- [ ] Batasi akses hanya Owner atau role yang diizinkan.
- [ ] Catat perubahan role permission ke audit log.
- [ ] Cegah role biasa mengubah permission.
- [ ] Tambahkan test update role permission.

**Acceptance Criteria**:
- [ ] Permission dapat diatur per role.
- [ ] Matrix berbasis feature tree.
- [ ] Mini fitur dapat punya permission sendiri.
- [ ] Perubahan permission masuk audit log.
- [ ] Hanya role berwenang yang bisa mengubah matrix.

---

### S11-T004 — Middleware Feature-Action Permission
**Status**: Todo  
**Tujuan**: Mengamankan route dengan permission berbasis feature dan action.  
**Checklist**:
- [ ] Buat middleware feature-action permission.
- [ ] Dukung pengecekan dengan permission code.
- [ ] Dukung pengecekan dengan feature code dan action code.
- [ ] Terapkan ke route dashboard.
- [ ] Terapkan ke route pelanggan.
- [ ] Terapkan ke route import.
- [ ] Terapkan ke route invoice.
- [ ] Terapkan ke route payment.
- [ ] Terapkan ke route laporan.
- [ ] Terapkan ke route audit log.
- [ ] Return forbidden jika tidak punya permission.
- [ ] Tambahkan test direct URL access.

**Acceptance Criteria**:
- [ ] Route dicek backend.
- [ ] User tanpa permission mendapat forbidden.
- [ ] Menu disembunyikan bukan satu-satunya proteksi.
- [ ] Direct URL access aman.
- [ ] Test middleware lulus.

---

### S11-T005 — POP Scope Query Enforcement
**Status**: Todo  
**Tujuan**: Memastikan query data mengikuti scope user.  
**Checklist**:
- [ ] Buat helper `applyUserScope`.
- [ ] Terapkan ke daftar pelanggan.
- [ ] Terapkan ke detail pelanggan.
- [ ] Terapkan ke invoice.
- [ ] Terapkan ke payment.
- [ ] Terapkan ke dashboard.
- [ ] Terapkan ke laporan pelanggan.
- [ ] Terapkan ke laporan tagihan.
- [ ] Terapkan ke laporan pembayaran.
- [ ] Terapkan ke import batch jika relevan.
- [ ] Terapkan ke audit log jika perlu.
- [ ] Test NOC `all_pop`.
- [ ] Test POP Admin `selected_pop`.
- [ ] Test Sales `own_created`.
- [ ] Test Teknisi `assigned_only` jika data assignment tersedia.

**Acceptance Criteria**:
- [ ] `all_pop` melihat semua POP.
- [ ] `selected_pop` hanya melihat POP yang dipilih.
- [ ] `pop_tree` melihat parent dan child POP.
- [ ] `own_created` hanya melihat data buatan sendiri jika diterapkan.
- [ ] `assigned_only` hanya melihat data assignment jika diterapkan.
- [ ] Tidak ada data cabang bocor.

---

### S11-T006 — Sidebar dan Tombol Aksi Berdasarkan Feature Permission
**Status**: Todo  
**Tujuan**: Menampilkan menu dan tombol aksi sesuai permission user.  
**Checklist**:
- [ ] Sidebar membaca permission user.
- [ ] Menu utama tampil jika user punya permission `view` pada fitur utama.
- [ ] Submenu tampil jika user punya permission pada sub fitur.
- [ ] Tombol create tampil jika punya permission `create`.
- [ ] Tombol edit tampil jika punya permission `update`.
- [ ] Tombol delete tampil jika punya permission `delete`.
- [ ] Tombol import tampil jika punya permission `import`.
- [ ] Tombol export tampil jika punya permission `export`.
- [ ] Tombol print tampil jika punya permission `print`.
- [ ] Tombol activate tampil jika punya permission `activate`.
- [ ] Tombol validate tampil jika punya permission `validate`.
- [ ] Field sensitive tampil jika punya permission `view_sensitive`.
- [ ] Pastikan route tetap aman walaupun tombol disembunyikan.

**Acceptance Criteria**:
- [ ] Menu sesuai permission.
- [ ] Tombol aksi sesuai permission.
- [ ] Field sensitive sesuai permission.
- [ ] User tanpa permission tidak melihat tombol.
- [ ] Route tetap dilindungi middleware.

---

### S11-T007 — Protect Sensitive Fields dengan Permission
**Status**: Todo  
**Tujuan**: Mengamankan field sensitif seperti PPPoE, WiFi, IP, VLAN, dan data teknis.  
**Checklist**:
- [ ] Audit field sensitif perangkat.
- [ ] Terapkan permission `view_sensitive`.
- [ ] Terapkan permission `update_sensitive`.
- [ ] Sembunyikan password PPPoE dari role tanpa permission.
- [ ] Sembunyikan password WiFi dari role tanpa permission.
- [ ] Sembunyikan IP/VLAN jika dianggap sensitif.
- [ ] Cegah update field sensitif via request langsung.
- [ ] Test Finance tidak bisa lihat password teknis.
- [ ] Test Helpdesk tidak bisa lihat password teknis.
- [ ] Test Teknisi dengan permission bisa lihat/update jika diizinkan.

**Acceptance Criteria**:
- [ ] Field sensitif aman di UI.
- [ ] Field sensitif aman dari request langsung.
- [ ] Role tanpa permission tidak bisa melihat password teknis.
- [ ] Role tanpa permission tidak bisa mengubah field sensitif.
- [ ] Test sensitive field lulus.

---

### S11-T008 — Audit Log untuk Perubahan RBAC
**Status**: Todo  
**Tujuan**: Mencatat semua perubahan role, permission, feature, action, dan user scope.  
**Checklist**:
- [ ] Catat create/update feature.
- [ ] Catat create/update action.
- [ ] Catat generate permission.
- [ ] Catat perubahan role permission.
- [ ] Catat perubahan user role scope.
- [ ] Catat perubahan role user.
- [ ] Catat user pelaku.
- [ ] Catat waktu perubahan.
- [ ] Catat old values dan new values jika memungkinkan.
- [ ] Tampilkan di audit log.
- [ ] Batasi akses audit log ke Owner/Atasan sesuai permission.

**Acceptance Criteria**:
- [ ] Perubahan RBAC tercatat.
- [ ] Perubahan scope user tercatat.
- [ ] Perubahan permission role tercatat.
- [ ] Audit log dapat dilihat role berwenang.
- [ ] User biasa tidak bisa menghapus audit log.

---

### S11-T009 — Test Matrix Advanced RBAC
**Status**: Todo  
**Tujuan**: Membuat test lengkap untuk role, permission, scope, route, field sensitif, dan menu.  
**Checklist**:
- [ ] Test Owner bisa semua.
- [ ] Test Atasan bisa dashboard/laporan.
- [ ] Test Admin operasional.
- [ ] Test NOC Pusat scope `all_pop`.
- [ ] Test NOC Cabang scope `selected_pop`.
- [ ] Test Helpdesk tidak bisa ubah nominal tagihan.
- [ ] Test FOP bisa survey/pemasangan.
- [ ] Test Teknisi tidak bisa pembayaran.
- [ ] Test Sales hanya registrasi/follow-up.
- [ ] Test POP Admin hanya POP yang dipilih.
- [ ] Test direct URL forbidden.
- [ ] Test field sensitive forbidden.
- [ ] Test menu visibility.
- [ ] Test button visibility.
- [ ] Test POP scope tidak bocor.

**Acceptance Criteria**:
- [ ] Semua role baru memiliki test.
- [ ] Scope `all_pop` teruji.
- [ ] Scope `selected_pop` teruji.
- [ ] Scope `pop_tree` teruji jika diterapkan.
- [ ] Scope `assigned_only`/`own_created` teruji jika diterapkan.
- [ ] Direct URL aman.
- [ ] Field sensitif aman.
- [ ] Semua test Advanced RBAC lulus.

---

### S11-T010 — Regression Test Setelah Advanced RBAC
**Status**: Todo  
**Tujuan**: Memastikan Advanced RBAC tidak merusak fitur lama.  
**Checklist**:
- [ ] Jalankan test login/auth.
- [ ] Jalankan test POP.
- [ ] Jalankan test paket internet.
- [ ] Jalankan test pelanggan.
- [ ] Jalankan test import.
- [ ] Jalankan test aktivasi.
- [ ] Jalankan test invoice.
- [ ] Jalankan test payment.
- [ ] Jalankan test dashboard.
- [ ] Jalankan test laporan.
- [ ] Jalankan test data teknis.
- [ ] Jalankan test audit log.
- [ ] Jalankan full test suite.
- [ ] Jalankan `npm run build`.
- [ ] Catat test yang gagal jika ada.

**Acceptance Criteria**:
- [ ] Fitur lama tetap berjalan.
- [ ] Full test suite lulus atau failure tercatat jelas.
- [ ] Build frontend lulus.
- [ ] Tidak ada regression critical.
- [ ] Catatan hasil test masuk `docs/TASKS.md`.

---
---

# Sprint 12 — PRD Compliance Audit & Hardening

## Tujuan Sprint 12
Menguji apakah implementasi dari Sprint 1 sampai Sprint 11 benar-benar sesuai PRD, business rules, Advanced RBAC, POP scope, status flow, database rules, dan definition of done.  
Sprint ini fokus audit, test, dan hardening. Bukan membuat fitur besar baru.

### S12-T001 — Audit Implementasi Terhadap PRD
**Status**: Todo  
**Tujuan**: Membandingkan seluruh implementasi Sprint 1–11 dengan PRD dan mencatat gap.  
**Checklist**:
- [ ] Audit modul Login.
- [ ] Audit modul Advanced RBAC.
- [ ] Audit modul POP/Cabang.
- [ ] Audit modul Paket Internet.
- [ ] Audit modul Input Manual Pelanggan.
- [ ] Audit modul Import Excel/CSV.
- [ ] Audit modul Import Legacy SQL.
- [ ] Audit modul Validasi Kelengkapan Data.
- [ ] Audit modul Aktivasi Layanan.
- [ ] Audit modul Tagihan.
- [ ] Audit modul Pembayaran.
- [ ] Audit modul Dashboard.
- [ ] Audit modul Laporan.
- [ ] Audit modul Data Teknis.
- [ ] Audit modul Audit Log.
- [ ] Catat fitur yang sudah sesuai.
- [ ] Catat fitur yang belum sesuai.
- [ ] Catat fitur yang keluar scope jika ada.

**Acceptance Criteria**:
- [ ] Laporan audit PRD tersedia.
- [ ] Semua modul MVP diaudit.
- [ ] Advanced RBAC diaudit.
- [ ] Gap implementasi tercatat.
- [ ] Tidak ada asumsi tanpa bukti.
- [ ] Rekomendasi perbaikan dibuat sebagai task kecil.

---

### S12-T002 — Audit POP Scope Semua Modul
**Status**: Todo  
**Tujuan**: Memastikan data cabang tidak bocor antar POP setelah Advanced RBAC.  
**Checklist**:
- [ ] Audit daftar pelanggan.
- [ ] Audit detail pelanggan.
- [ ] Audit import batch.
- [ ] Audit invoice.
- [ ] Audit payment.
- [ ] Audit dashboard.
- [ ] Audit laporan pelanggan.
- [ ] Audit laporan tagihan.
- [ ] Audit laporan pembayaran.
- [ ] Audit laporan import.
- [ ] Audit audit log jika perlu dibatasi.
- [ ] Audit NOC `all_pop`.
- [ ] Audit POP Admin `selected_pop`.
- [ ] Audit Sales `own_created`.
- [ ] Audit Teknisi `assigned_only` jika diterapkan.

**Acceptance Criteria**:
- [ ] `all_pop` benar-benar melihat semua.
- [ ] `selected_pop` hanya melihat POP tertentu.
- [ ] `pop_tree` hanya melihat parent-child POP yang valid.
- [ ] `own_created` tidak melihat data user lain jika diterapkan.
- [ ] `assigned_only` tidak melihat data tidak ditugaskan jika diterapkan.
- [ ] Tidak ada query global bocor ke role cabang.

---

### S12-T003 — Audit Status Flow dan Constant/Enum
**Status**: Todo  
**Tujuan**: Memastikan status pelanggan, layanan, invoice, pembayaran, import, POP, dan paket tidak ditulis sembarangan.  
**Checklist**:
- [ ] Audit status kelengkapan pelanggan.
- [ ] Audit status layanan pelanggan.
- [ ] Audit status invoice.
- [ ] Audit status payment.
- [ ] Audit status import batch.
- [ ] Audit status POP.
- [ ] Audit status paket.
- [ ] Pastikan status menggunakan constant/enum/helper jika tersedia.
- [ ] Catat hardcoded string status yang berulang.
- [ ] Buat task refactor jika ada status raw string berbahaya.
- [ ] Tambahkan test transisi status penting.

**Acceptance Criteria**:
- [ ] Status sesuai `STATUS_FLOW.md`.
- [ ] Tidak ada typo status.
- [ ] Transisi status penting tervalidasi.
- [ ] Pelanggan belum lengkap tidak bisa siap billing.
- [ ] Payment ditolak tidak membuat invoice lunas.
- [ ] Invoice batal tidak bisa dibayar.

---

### S12-T004 — Audit Database Constraint, Index, dan Relasi
**Status**: Todo  
**Tujuan**: Memastikan database sesuai `DATABASE_RULES.md` setelah Advanced RBAC.  
**Checklist**:
- [ ] Audit unique `users.email`.
- [ ] Audit unique `features.code`.
- [ ] Audit unique `actions.code`.
- [ ] Audit unique `permissions.code`.
- [ ] Audit unique `pops.pop_code`.
- [ ] Audit unique `customers.registration_number`.
- [ ] Audit unique `customers.cid`.
- [ ] Audit unique `invoices.invoice_number`.
- [ ] Audit unique `payments.payment_number`.
- [ ] Audit invoice per customer dan periode.
- [ ] Audit relasi feature parent-child.
- [ ] Audit relasi permission ke feature/action.
- [ ] Audit relasi role-permission.
- [ ] Audit relasi user-role-scope.
- [ ] Audit relasi customer ke POP.
- [ ] Audit relasi invoice/payment.
- [ ] Audit index untuk filter penting.
- [ ] Audit snapshot harga layanan dan invoice.

**Acceptance Criteria**:
- [ ] Relasi utama sesuai aturan.
- [ ] Unique constraint penting tersedia.
- [ ] Index filter penting tersedia.
- [ ] Advanced RBAC schema valid.
- [ ] Invoice tidak dobel untuk customer dan periode sama.
- [ ] Payment tidak berdiri tanpa invoice.
- [ ] Snapshot harga tersedia.

---

### S12-T005 — Audit ID Numbering dan Race Condition
**Status**: Todo  
**Tujuan**: Memastikan ID Request dan CID aman, unik, berjalan per POP, dan tidak rawan duplikasi.  
**Checklist**:
- [ ] Audit format ID Request.
- [ ] Audit format CID.
- [ ] Audit sequence registration per POP.
- [ ] Audit sequence CID per POP.
- [ ] Audit generator ID Request.
- [ ] Audit generator CID.
- [ ] Pastikan ID tidak dibuat dengan `count(customers) + 1`.
- [ ] Pastikan ada transaction/lock/retry jika diperlukan.
- [ ] Test dua pelanggan POP sama.
- [ ] Test dua pelanggan POP berbeda.
- [ ] Test CID tidak dibuat sebelum aktivasi.
- [ ] Test CID tidak dibuat dua kali.

**Acceptance Criteria**:
- [ ] ID Request unik.
- [ ] CID unik.
- [ ] Running number berjalan per POP.
- [ ] Running number registration dan CID terpisah.
- [ ] CID hanya dibuat saat aktivasi.
- [ ] Tidak ada potensi duplikasi sederhana.

---

### S12-T006 — Audit Import Data Sesuai IMPORT_SPEC.md
**Status**: Todo  
**Tujuan**: Memastikan modul import Excel/CSV dan import legacy mengikuti spesifikasi import.  
**Checklist**:
- [ ] Audit template import.
- [ ] Audit upload file.
- [ ] Audit preview import.
- [ ] Audit validasi field wajib.
- [ ] Audit validasi duplikasi.
- [ ] Audit validasi POP.
- [ ] Audit validasi paket.
- [ ] Audit validasi harga.
- [ ] Audit validasi tanggal.
- [ ] Audit validasi status layanan.
- [ ] Audit import batch.
- [ ] Audit import error.
- [ ] Audit import legacy SQL.
- [ ] Audit data valid masuk master pelanggan.
- [ ] Audit data invalid tidak masuk master pelanggan.
- [ ] Pastikan import tidak membuat invoice otomatis.
- [ ] Pastikan import tidak membuat payment otomatis.

**Acceptance Criteria**:
- [ ] Import sesuai `IMPORT_SPEC.md`.
- [ ] Import legacy terdokumentasi.
- [ ] Data invalid ditolak.
- [ ] Error import jelas.
- [ ] Import batch tersimpan.
- [ ] Data valid masuk struktur pelanggan yang sama.
- [ ] Import tidak membuat invoice/payment otomatis di MVP.

---

### S12-T007 — Audit Detail Pelanggan Sesuai CUSTOMER_DETAIL_SPEC.md
**Status**: Todo  
**Tujuan**: Memastikan detail pelanggan sudah menjadi pusat data pelanggan sesuai PRD.  
**Checklist**:
- [ ] Audit tab Ringkasan.
- [ ] Audit tab Identitas.
- [ ] Audit tab Alamat.
- [ ] Audit tab POP/Cabang.
- [ ] Audit tab Paket & Layanan.
- [ ] Audit tab Survey.
- [ ] Audit tab Pemasangan.
- [ ] Audit tab Modem/Perangkat.
- [ ] Audit tab Billing.
- [ ] Audit tab Tagihan.
- [ ] Audit tab Pembayaran.
- [ ] Audit tab Dokumen.
- [ ] Audit tab Riwayat Perubahan.
- [ ] Audit field yang belum lengkap.
- [ ] Audit tombol aktivasi layanan.
- [ ] Audit tombol buat tagihan.
- [ ] Audit tombol input pembayaran.
- [ ] Audit field sensitif perangkat.
- [ ] Audit permission tiap tab.

**Acceptance Criteria**:
- [ ] Semua tab penting tersedia atau punya alasan jika ditunda.
- [ ] Field belum lengkap terlihat.
- [ ] Status kelengkapan terlihat.
- [ ] Tombol aksi sesuai permission.
- [ ] Field sensitif aman.
- [ ] Admin/POP Admin tidak bisa membuka pelanggan di luar scope.

---

### S12-T008 — Audit Audit Log Semua Modul Penting
**Status**: Todo  
**Tujuan**: Memastikan audit log mencatat perubahan data penting.  
**Checklist**:
- [ ] Audit log perubahan pelanggan.
- [ ] Audit log perubahan POP.
- [ ] Audit log perubahan paket.
- [ ] Audit log perubahan invoice.
- [ ] Audit log perubahan payment.
- [ ] Audit log perubahan user.
- [ ] Audit log perubahan role.
- [ ] Audit log perubahan permission.
- [ ] Audit log perubahan feature/action.
- [ ] Audit log perubahan user role scope.
- [ ] Audit log perubahan data teknis.
- [ ] Audit log import.
- [ ] Audit halaman daftar audit log.
- [ ] Audit permission Owner/Atasan/Admin.
- [ ] Audit user biasa tidak bisa akses audit log.

**Acceptance Criteria**:
- [ ] Perubahan pelanggan tercatat.
- [ ] Perubahan invoice tercatat.
- [ ] Perubahan payment tercatat.
- [ ] Perubahan role/permission tercatat.
- [ ] Perubahan feature/action tercatat.
- [ ] Perubahan user scope tercatat.
- [ ] Import tercatat.
- [ ] User biasa tidak dapat mengubah audit log.

---

### S12-T009 — Perbaiki Kegagalan Legacy CustomerEditTest
**Status**: Todo  
**Tujuan**: Memperbaiki 2 kegagalan lama pada `CustomerEditTest` terkait cleanup file dokumen pelanggan agar full test suite bersih.  
**Checklist**:
- [ ] Jalankan full test suite dan pastikan error terkini.
- [ ] Identifikasi penyebab cleanup file dokumen pelanggan.
- [ ] Perbaiki test atau storage handling tanpa merusak modul dokumen baru.
- [ ] Pastikan tidak menghapus validasi dokumen.
- [ ] Jalankan test `CustomerEditTest`.
- [ ] Jalankan test dokumen pelanggan.
- [ ] Jalankan full test suite.

**Acceptance Criteria**:
- [ ] `CustomerEditTest` lulus.
- [ ] `CustomerDocumentTest` tetap lulus.
- [ ] Full test suite lulus tanpa kegagalan legacy.
- [ ] Tidak ada perubahan fitur di luar bugfix.
- [ ] Tidak ada regression pada upload dokumen.

---

### S12-T010 — Full Regression Test dan Build Gate
**Status**: Todo  
**Tujuan**: Menjadikan test suite dan build sebagai gerbang sebelum project dianggap stabil.  
**Checklist**:
- [ ] Jalankan `php artisan test`.
- [ ] Jalankan test dengan `VIEW_COMPILED_PATH` temp jika diperlukan.
- [ ] Jalankan `npm run build`.
- [ ] Catat total tests dan assertions.
- [ ] Catat semua test yang gagal jika ada.
- [ ] Pastikan kegagalan legacy sudah selesai.
- [ ] Pastikan tidak ada broken route utama.
- [ ] Pastikan tidak ada error build frontend.

**Acceptance Criteria**:
- [ ] Full test suite lulus.
- [ ] `npm run build` lulus.
- [ ] Tidak ada failed test yang diabaikan.
- [ ] Catatan hasil test masuk `docs/TASKS.md`.
- [ ] Project siap masuk UAT.

---
---

# Sprint 13 — UAT, Operational Readiness, dan Final MVP Review

## Tujuan Sprint 13
Menguji MVP dari sudut pandang pengguna operasional: Owner, Atasan, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, dan POP Admin.  
Sprint ini memastikan aplikasi tidak hanya lulus test teknis, tetapi juga layak digunakan secara operasional.

### S13-T001 — Buat Dataset UAT Realistis
**Status**: Todo  
**Tujuan**: Membuat data dummy/UAT realistis agar semua flow bisa diuji.  
**Checklist**:
- [ ] Buat minimal 1 POP Pusat.
- [ ] Buat minimal 2 POP Cabang.
- [ ] Buat minimal 1 Mini POP.
- [ ] Buat user Owner.
- [ ] Buat user Atasan.
- [ ] Buat user Admin.
- [ ] Buat user NOC Pusat dengan scope `all_pop`.
- [ ] Buat user NOC Cabang dengan scope `selected_pop`.
- [ ] Buat user Helpdesk.
- [ ] Buat user FOP.
- [ ] Buat user Teknisi.
- [ ] Buat user Sales.
- [ ] Buat user POP Admin.
- [ ] Buat beberapa paket internet aktif.
- [ ] Buat pelanggan lengkap.
- [ ] Buat pelanggan belum lengkap.
- [ ] Buat pelanggan aktif.
- [ ] Buat pelanggan isolir.
- [ ] Buat invoice belum bayar.
- [ ] Buat invoice sebagian.
- [ ] Buat invoice lunas.
- [ ] Buat payment cash/transfer/qris.
- [ ] Buat data survey, pemasangan, perangkat, dan dokumen.

**Acceptance Criteria**:
- [ ] Dataset UAT tersedia.
- [ ] Semua role baru dapat diuji.
- [ ] Semua scope utama dapat diuji.
- [ ] Semua status utama dapat diuji.
- [ ] Semua laporan memiliki data.
- [ ] Dashboard menampilkan angka realistis.

---

### S13-T002 — UAT Flow Owner
**Status**: Todo  
**Tujuan**: Menguji Owner sebagai pemilik akses penuh sistem.  
**Checklist**:
- [ ] Login sebagai Owner.
- [ ] Cek akses semua menu.
- [ ] Cek kelola POP.
- [ ] Cek kelola user.
- [ ] Cek kelola role.
- [ ] Cek kelola permission matrix.
- [ ] Cek kelola feature/action jika tersedia.
- [ ] Cek kelola paket.
- [ ] Cek lihat semua pelanggan.
- [ ] Cek lihat semua invoice.
- [ ] Cek lihat semua payment.
- [ ] Cek laporan semua cabang.
- [ ] Cek audit log.
- [ ] Cek field sensitif.

**Acceptance Criteria**:
- [ ] Owner dapat mengakses semua fitur MVP.
- [ ] Owner dapat mengelola RBAC.
- [ ] Owner dapat melihat semua POP.
- [ ] Owner dapat melihat audit log.
- [ ] Tidak ada menu utama MVP yang error.

---

### S13-T003 — UAT Flow Atasan
**Status**: Todo  
**Tujuan**: Menguji Atasan sebagai role monitoring, laporan, dan audit terbatas.  
**Checklist**:
- [ ] Login sebagai Atasan.
- [ ] Cek dashboard.
- [ ] Cek laporan pelanggan.
- [ ] Cek laporan tagihan.
- [ ] Cek laporan pembayaran.
- [ ] Cek export laporan jika diizinkan.
- [ ] Cek audit log jika diizinkan.
- [ ] Cek tidak bisa mengubah role/permission jika tidak diberi izin.
- [ ] Cek tidak bisa input pembayaran jika tidak diberi izin.
- [ ] Cek tidak bisa mengubah data teknis jika tidak diberi izin.

**Acceptance Criteria**:
- [ ] Atasan dapat monitoring data.
- [ ] Atasan dapat melihat laporan sesuai scope.
- [ ] Atasan tidak bisa melakukan aksi operasional yang tidak diizinkan.
- [ ] Atasan tidak bisa mengubah RBAC tanpa permission.

---

### S13-T004 — UAT Flow Admin
**Status**: Todo  
**Tujuan**: Menguji Admin sebagai role operasional utama.  
**Checklist**:
- [ ] Login sebagai Admin.
- [ ] Cek kelola pelanggan.
- [ ] Cek input pelanggan manual.
- [ ] Cek import pelanggan jika diizinkan.
- [ ] Cek validasi kelengkapan.
- [ ] Cek aktivasi layanan.
- [ ] Cek buat invoice.
- [ ] Cek input pembayaran jika diizinkan.
- [ ] Cek laporan operasional.
- [ ] Cek scope `all_pop` atau `selected_pop` sesuai setting user.

**Acceptance Criteria**:
- [ ] Admin dapat melakukan operasional sesuai permission.
- [ ] Admin tidak melewati scope data.
- [ ] Admin tidak mendapat permission sensitif berlebihan.
- [ ] Admin tidak bisa mengubah RBAC jika tidak diizinkan.

---

### S13-T005 — UAT Flow NOC Pusat dan NOC Cabang
**Status**: Todo  
**Tujuan**: Menguji role NOC dengan scope `all_pop` dan `selected_pop`.  
**Checklist**:
- [ ] Login sebagai NOC Pusat.
- [ ] Pastikan NOC Pusat melihat semua POP.
- [ ] Cek dashboard teknis/operasional yang diizinkan.
- [ ] Cek daftar pelanggan semua POP jika permission mengizinkan.
- [ ] Cek data perangkat jika permission mengizinkan.
- [ ] Login sebagai NOC Cabang.
- [ ] Pastikan NOC Cabang hanya melihat `selected_pop`.
- [ ] Cek tidak bisa membuka pelanggan POP lain lewat URL.
- [ ] Cek tidak bisa mencatat pembayaran jika tidak diizinkan.
- [ ] Cek tidak bisa mengubah nominal tagihan.

**Acceptance Criteria**:
- [ ] NOC Pusat `all_pop` berjalan.
- [ ] NOC Cabang `selected_pop` berjalan.
- [ ] NOC tidak bocor scope POP.
- [ ] NOC tidak bisa melakukan aksi billing/payment jika tidak diberi permission.

---

### S13-T006 — UAT Flow Helpdesk
**Status**: Todo  
**Tujuan**: Menguji Helpdesk sebagai role layanan pelanggan.  
**Checklist**:
- [ ] Login sebagai Helpdesk.
- [ ] Cek daftar pelanggan sesuai scope.
- [ ] Cek detail pelanggan.
- [ ] Cek status layanan.
- [ ] Cek status tagihan.
- [ ] Cek status pembayaran.
- [ ] Cek edit data kontak jika diizinkan.
- [ ] Cek tidak bisa mengubah nominal tagihan.
- [ ] Cek tidak bisa validasi pembayaran.
- [ ] Cek tidak bisa melihat password teknis jika tidak diizinkan.
- [ ] Cek tidak bisa menghapus pelanggan.

**Acceptance Criteria**:
- [ ] Helpdesk dapat membantu melihat data pelanggan.
- [ ] Helpdesk dapat melihat status pembayaran.
- [ ] Helpdesk tidak bisa mengubah nominal tagihan.
- [ ] Helpdesk tidak bisa validasi pembayaran.
- [ ] Helpdesk tidak bisa melihat field sensitif tanpa permission.

---

### S13-T007 — UAT Flow FOP
**Status**: Todo  
**Tujuan**: Menguji FOP sebagai role survey/pemasangan lapangan.  
**Checklist**:
- [ ] Login sebagai FOP.
- [ ] Cek daftar pelanggan sesuai scope.
- [ ] Cek data survey.
- [ ] Cek update survey.
- [ ] Cek data pemasangan.
- [ ] Cek update pemasangan.
- [ ] Cek upload foto survey/pemasangan.
- [ ] Cek tidak bisa validasi pembayaran.
- [ ] Cek tidak bisa membuat invoice jika tidak diizinkan.
- [ ] Cek tidak bisa mengubah role/permission.

**Acceptance Criteria**:
- [ ] FOP dapat mengelola survey.
- [ ] FOP dapat mengelola pemasangan.
- [ ] FOP tidak bisa mengakses pembayaran.
- [ ] FOP tidak bisa mengubah RBAC.
- [ ] Scope FOP berjalan.

---

### S13-T008 — UAT Flow Teknisi
**Status**: Todo  
**Tujuan**: Menguji Teknisi hanya mengisi data teknis dan tidak bisa mengakses pembayaran.  
**Checklist**:
- [ ] Login sebagai Teknisi.
- [ ] Cek daftar pelanggan yang diizinkan.
- [ ] Cek isi survey jika permission tersedia.
- [ ] Cek isi pemasangan jika permission tersedia.
- [ ] Cek isi perangkat.
- [ ] Cek upload foto teknis.
- [ ] Cek field sensitif sesuai permission.
- [ ] Cek tidak bisa membuka menu pembayaran.
- [ ] Cek tidak bisa membuka route pembayaran via URL.
- [ ] Cek tidak bisa mengubah nominal tagihan.
- [ ] Cek tidak bisa mengakses laporan keuangan.

**Acceptance Criteria**:
- [ ] Teknisi dapat mengisi data teknis.
- [ ] Teknisi tidak bisa mencatat pembayaran.
- [ ] Teknisi tidak bisa mengubah nominal tagihan.
- [ ] Teknisi tidak bisa mengakses laporan keuangan.
- [ ] Field sensitif mengikuti permission.

---

### S13-T009 — UAT Flow Sales
**Status**: Todo  
**Tujuan**: Menguji Sales sebagai role registrasi/follow-up pelanggan dengan scope `own_created` atau `selected_pop`.  
**Checklist**:
- [ ] Login sebagai Sales.
- [ ] Cek input calon pelanggan.
- [ ] Cek ID Request dibuat.
- [ ] Cek pelanggan yang dibuat sendiri terlihat.
- [ ] Cek pelanggan user lain tidak terlihat jika scope `own_created`.
- [ ] Cek `selected_pop` jika Sales dibatasi POP.
- [ ] Cek tidak bisa aktivasi layanan jika tidak diberi permission.
- [ ] Cek tidak bisa membuat invoice.
- [ ] Cek tidak bisa input pembayaran.
- [ ] Cek tidak bisa melihat laporan pembayaran.
- [ ] Cek tidak bisa melihat field teknis sensitif.

**Acceptance Criteria**:
- [ ] Sales dapat input calon pelanggan.
- [ ] Sales `own_created` berjalan jika diterapkan.
- [ ] Sales `selected_pop` berjalan jika diterapkan.
- [ ] Sales tidak bisa billing/payment.
- [ ] Sales tidak bisa melihat data sensitif.

---

### S13-T010 — UAT Flow POP Admin
**Status**: Todo  
**Tujuan**: Menguji POP Admin sebagai admin operasional untuk POP tertentu.  
**Checklist**:
- [ ] Login sebagai POP Admin.
- [ ] Pastikan scope `selected_pop` wajib.
- [ ] Cek dashboard hanya POP sendiri.
- [ ] Cek pelanggan hanya POP sendiri.
- [ ] Cek detail pelanggan POP sendiri.
- [ ] Cek tidak bisa membuka pelanggan POP lain lewat URL.
- [ ] Cek invoice hanya POP sendiri.
- [ ] Cek payment hanya POP sendiri.
- [ ] Cek laporan hanya POP sendiri.
- [ ] Cek export hanya POP sendiri.
- [ ] Cek tidak bisa mengelola role global.
- [ ] Cek tidak bisa melihat audit log global jika tidak diizinkan.

**Acceptance Criteria**:
- [ ] POP Admin tidak melihat data POP lain.
- [ ] URL langsung tetap aman.
- [ ] Export laporan tidak bocor.
- [ ] POP scope benar di pelanggan, invoice, payment, dashboard, dan laporan.

---

### S13-T011 — UAT Flow Pelanggan Manual sampai Pembayaran
**Status**: Todo  
**Tujuan**: Menguji flow bisnis utama dari input pelanggan manual sampai pembayaran lunas.  
**Checklist**:
- [ ] Input pelanggan baru manual.
- [ ] Pastikan ID Request dibuat.
- [ ] Simpan pelanggan belum lengkap.
- [ ] Lihat field yang belum lengkap.
- [ ] Lengkapi data pelanggan.
- [ ] Validasi kelengkapan menjadi lengkap.
- [ ] Aktivasi layanan.
- [ ] Pastikan CID dibuat.
- [ ] Buat invoice manual.
- [ ] Pastikan invoice belum dibayar.
- [ ] Input pembayaran sebagian.
- [ ] Pastikan invoice menjadi sebagian.
- [ ] Input pelunasan.
- [ ] Pastikan invoice menjadi lunas.
- [ ] Cek pembayaran muncul di detail pelanggan.
- [ ] Cek audit log.

**Acceptance Criteria**:
- [ ] Flow input pelanggan manual berhasil end-to-end.
- [ ] ID Request dan CID sesuai aturan.
- [ ] Pelanggan belum lengkap tidak bisa invoice.
- [ ] Invoice dibuat dari pelanggan aktif.
- [ ] Payment mengubah status invoice.
- [ ] Audit log tercatat.

---

### S13-T012 — UAT Flow Import Pelanggan sampai Aktivasi
**Status**: Todo  
**Tujuan**: Menguji flow import pelanggan lama sampai pelanggan bisa diaktifkan.  
**Checklist**:
- [ ] Download template import.
- [ ] Upload file import valid.
- [ ] Upload file import invalid.
- [ ] Cek preview data.
- [ ] Cek data invalid ditolak.
- [ ] Cek error import jelas.
- [ ] Konfirmasi import data valid.
- [ ] Cek data masuk master pelanggan.
- [ ] Cek ID pelanggan lama tersimpan.
- [ ] Cek ID Request sistem baru dibuat.
- [ ] Cek hasil import bisa diedit manual.
- [ ] Lengkapi data jika perlu.
- [ ] Aktivasi layanan.
- [ ] Pastikan CID dibuat.
- [ ] Pastikan import tidak membuat invoice/payment otomatis.

**Acceptance Criteria**:
- [ ] Import berjalan sesuai spesifikasi.
- [ ] Data invalid tidak masuk.
- [ ] Data valid masuk master pelanggan.
- [ ] Data hasil import bisa diedit.
- [ ] Import tidak membuat invoice/payment otomatis.
- [ ] Aktivasi setelah import berjalan.

---

### S13-T013 — Final Review MVP_SUCCESS_CHECKLIST.md
**Status**: Todo  
**Tujuan**: Mengecek seluruh MVP menggunakan checklist final.  
**Checklist**:
- [ ] Review checklist scope MVP.
- [ ] Review checklist fitur post-MVP tidak dibuat.
- [ ] Review checklist login/user.
- [ ] Review checklist Advanced RBAC.
- [ ] Review checklist POP/Cabang.
- [ ] Review checklist ID numbering.
- [ ] Review checklist paket.
- [ ] Review checklist pelanggan manual.
- [ ] Review checklist detail pelanggan.
- [ ] Review checklist import.
- [ ] Review checklist validasi kelengkapan.
- [ ] Review checklist aktivasi.
- [ ] Review checklist invoice.
- [ ] Review checklist payment.
- [ ] Review checklist dashboard.
- [ ] Review checklist laporan.
- [ ] Review checklist audit log.
- [ ] Tandai item yang belum selesai.
- [ ] Buat daftar bugfix/task lanjutan jika ada.

**Acceptance Criteria**:
- [ ] `MVP_SUCCESS_CHECKLIST.md` terisi.
- [ ] Semua item critical terpenuhi.
- [ ] Gap MVP tercatat jelas.
- [ ] Keputusan MVP layak/tidak layak dibuat.

---

### S13-T014 — Release Readiness Checklist
**Status**: Todo  
**Tujuan**: Menyiapkan project agar layak dipindahkan ke staging/production internal.  
**Checklist**:
- [ ] Pastikan `.env.example` lengkap.
- [ ] Pastikan migration berjalan dari nol.
- [ ] Pastikan seeder dasar tersedia.
- [ ] Pastikan role, feature, action, permission seeder tersedia.
- [ ] Pastikan storage link/document upload siap.
- [ ] Pastikan permission folder storage benar.
- [ ] Pastikan full test suite lulus.
- [ ] Pastikan `npm run build` lulus.
- [ ] Pastikan tidak ada debug route berbahaya.
- [ ] Pastikan tidak ada credential hardcoded.
- [ ] Pastikan backup database minimal terdokumentasi.
- [ ] Pastikan restore database minimal terdokumentasi.
- [ ] Pastikan panduan deploy/staging tersedia.
- [ ] Pastikan user owner awal tersedia.
- [ ] Pastikan dokumen UAT tersedia.

**Acceptance Criteria**:
- [ ] Project siap staging.
- [ ] Setup dari nol terdokumentasi.
- [ ] Seeder RBAC baru berjalan.
- [ ] Tidak ada credential hardcoded.
- [ ] Test dan build lulus.
- [ ] Deploy checklist tersedia.

---
---

# Notes Sprint 9–13

Sprint 9 sampai Sprint 13 adalah sprint lanjutan setelah fitur MVP utama selesai.

**Aturan**:
1. Jangan mengerjakan Sprint 9 sebelum **S8-T006 — Import Data Legacy sand_db_sandya.sql** selesai.
2. Jangan membuat role per cabang seperti NOC Siman, NOC Jetis, atau Teknisi Siman.
3. Gunakan pola **Role + Scope**.
4. Contoh benar: Role NOC, Scope `all_pop`.
5. Contoh benar: Role POP Admin, Scope `selected_pop`, POP Siman.
6. Permission harus berbasis feature-action.
7. Format permission: `{feature_code}.{action_code}`.
8. Query data wajib mengikuti user scope.
9. Route wajib dilindungi middleware permission.
10. Menu disembunyikan bukan pengganti middleware.
11. Field sensitif wajib dibatasi permission.
12. Semua perubahan RBAC wajib masuk audit log.
13. Jika ada bug ditemukan pada audit/UAT, buat task bugfix terpisah.
14. Jika full test suite gagal, jangan lanjut release readiness.
15. Jika MVP Success Checklist belum terpenuhi, MVP belum layak dianggap selesai.

**Urutan setelah S8-T006 selesai**:
1. Pindahkan S8-T006 ke Done.
2. Jadikan **S9-T001 — Normalisasi docs/TASKS.md dan Tambahkan Roadmap Advanced RBAC** sebagai In Progress.
3. Selesaikan Sprint 9 untuk dokumen dan desain Advanced RBAC.
4. Lanjut Sprint 10 untuk database dan core engine.
5. Lanjut Sprint 11 untuk UI, middleware, scope enforcement, dan tests.
6. Lanjut Sprint 12 untuk PRD compliance audit dan hardening.
7. Lanjut Sprint 13 untuk UAT dan release readiness.