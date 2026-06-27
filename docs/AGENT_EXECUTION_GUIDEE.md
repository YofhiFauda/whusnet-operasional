# AGENT_EXECUTION_GUIDE.md — Sprint 11–13: Advanced RBAC (Feature–Action–Scope)

**Untuk:** AI Agent (Antigravity) yang mengerjakan Sprint 11–13
**Project:** WHUSNET Admin Payment
**Dokumen rujukan wajib:** `docs/TASKS.md`, `docs/RBAC_MATRIX.md`, `docs/DATABASE_RULES.md`, `docs/BUSINESS_RULES.md`, `docs/PAGE_STRUCTURE.md`, `docs/DEFINITION_OF_DONE.md`, `analisa-rbac-dinamis-whusnet.md`
**Status dokumen:** Living document — Agent WAJIB update kolom Status di setiap task setelah selesai.

---

## 0. ATURAN WAJIB SEBELUM AGENT MULAI KERJA

Baca seluruh bagian ini sebelum menyentuh task manapun. Ini bukan saran, ini gerbang.

1. **Jangan mulai Sprint 11 sebelum `S10-T003 — Update UI for Termination & ID Display Logic` berstatus `Done`.** Cek di `docs/TASKS.md`. Jika belum Done, STOP dan laporkan ke user — jangan lanjut.
2. **Hanya boleh ada SATU task berstatus `in_progress` dalam satu waktu**, di seluruh Sprint 11–13. Jika menemukan lebih dari satu, STOP dan laporkan konflik ke user.
3. **Kerjakan task sesuai urutan ID** (S11-T001 ? S11-T002 ? ... ? S15-T014). Jangan loncat task kecuali task tersebut eksplisit ditandai independen di bagian "Dependency" masing-masing.
4. **Dilarang membuat role baru per cabang/POP** (contoh yang DILARANG: `NOC Siman`, `Teknisi Jetis`). Role hanya boleh dari 9 yang sudah ditetapkan di §1. Cabang/wilayah diatur lewat Scope, bukan Role baru.
5. **Dilarang hardcode nama role di kode** (Controller, Middleware, Blade, dsb). Kode hanya boleh memeriksa `permission code` dengan format `{feature_code}.{action_code}`. Lihat §3 dan §6 untuk detail & contoh.
6. **Dilarang menulis filter scope manual berulang di tiap query** (`->where('pop_id', ...)` ditulis ulang di banyak Controller). Scope WAJIB diimplementasikan sebagai Eloquent Global Scope satu kali (lihat §6.3), dipakai semua model yang relevan.
7. **Setiap task yang menyentuh skema RBAC wajib disertai migration + seeder**, tidak boleh insert manual lewat DB client.
8. **Setiap perubahan Role/Permission/Scope wajib tercatat di `audit_logs`.** Jika model/trait audit log belum ada di task tsb, buat dulu sebagai sub-task sebelum lanjut.
9. **Definition of Done (DoD) di §8 berlaku untuk SEMUA task** Sprint 11–13, tambahan dari DoD spesifik per task.
10. **Jika ragu / requirement tidak jelas dari dokumen ini maupun dokumen rujukan, STOP dan tanyakan ke user.** Jangan mengasumsikan dan melanjutkan.

---

## 1. Konteks yang Wajib Dipahami Agent Sebelum Coding

### 1.1 Tiga konsep yang harus dipisah secara tegas di kode

| Konsep | Dijawab di mana | Boleh berubah tanpa redeploy? |
|---|---|---|
| Workflow / urutan status pelanggan (registered ? ... ? active) | Kode: `app/Services/CustomerWorkflowService.php`, `app/Enums/WorkflowStatus.php` | TIDAK — ini logika bisnis tetap |
| Role & Permission (siapa boleh memicu aksi) | Database: tabel `roles`, `permissions`, `role_permissions` | YA — wajib bisa diubah dari UI |
| Scope (data wilayah mana yang terlihat) | Database: tabel `user_role_scopes`, `user_role_scope_targets` | YA — wajib bisa diubah dari UI |

Agent TIDAK boleh mencampur ketiganya dalam satu mekanisme. Jangan menaruh pengecekan role di dalam `CustomerWorkflowService`. Jangan menaruh logic transisi status di dalam middleware permission.

### 1.2 Daftar Role (Dinamic) — bisa tambah/kurang lewat UI

```
owner, admin, atasan, noc, helpdesk, fop, teknisi, sales, pop_admin
```

### 1.3 Daftar Scope Type (FIXED)

```
all_pop        -> lihat semua Cabang/POP/Mini POP
selected_pop   -> hanya POP yang dipilih manual (many-to-many)
pop_tree       -> satu Cabang POP + seluruh Mini POP di bawahnya
assigned_only  -> hanya data yang di-assign ke user tsb
own_created    -> hanya data yang dibuat sendiri oleh user tsb
```

### 1.4 Daftar Action (FIXED, lintas-feature)

```
view, create, update, delete, import, export, print,
validate, activate, cancel, upload, download,
view_sensitive, update_sensitive
```

### 1.5 Format Permission Code (WAJIB diikuti, tidak boleh variasi)

```
{feature_code}.{action_code}
```
Contoh: `customer.survey.validate`, `invoice.create`, `customer.technical_detail.view_sensitive`

---

## 2. Pemetaan Permission ke Workflow Existing (Acuan Seeder Awal)

Tabel ini WAJIB dipakai sebagai isi awal seeder `role_permissions` di Sprint 12. Jangan bikin permission baru di luar tabel ini kecuali fitur baru yang memang belum ada.

| Feature code | Action | Permission code | Controller terkait | Role default |
|---|---|---|---|---|
| `customer.registration` | `create` | `customer.registration.create` | `CustomerRegistrationController@store` | sales, admin |
| `customer.survey` | `validate` | `customer.survey.validate` | `SurveyController@start` | fop, teknisi |
| `customer.survey` | `update` | `customer.survey.update` | `SurveyController@complete` | teknisi |
| `customer.installation` | `validate` | `customer.installation.validate` | `VerificationController@processToTeam` | admin, noc |
| `customer.installation` | `activate` | `customer.installation.activate` | `InstallationController@start` | fop, teknisi |
| `customer.installation` | `update` | `customer.installation.update` | `InstallationController@complete` | teknisi |
| `customer.verification` | `activate` | `customer.verification.activate` | `VerificationController@finalVerify` | admin, noc |
| `invoice` | `create` | `invoice.create` | bagian flow `finalVerify` | admin, helpdesk |
| `customer.technical_detail` | `view_sensitive` | `customer.technical_detail.view_sensitive` | Detail Pelanggan view | noc, teknisi |

Jika saat Sprint 12/11 agent menemukan Controller/route lain yang belum punya permission code, TAMBAHKAN ke tabel ini (edit dokumen ini), JANGAN langsung hardcode role di Controller tsb sebagai jalan pintas.

---

## 3. Struktur Tracking Task (FORMAT WAJIB)

Setiap task di §4–§8 mengikuti format berikut. Agent WAJIB update bagian **Status** dan **Log** setiap kali progress, dan **HANYA** bagian itu yang boleh diubah agent — bagian Tujuan/Checklist/DoD bersifat tetap kecuali user yang mengubah.

```markdown
### [SPRINT-ID] — Judul Task
**Status:** todo | in_progress | blocked | done
**Dependency:** [ID task lain yang harus Done duluan, atau "tidak ada"]
**Tujuan:** ...
**Checklist:**
- [ ] item 1
- [ ] item 2
**Definition of Done:** ...
**Log Agent:** (tambahkan baris baru tiap update, jangan hapus log lama)
- 2026-06-20 — todo ? in_progress — mulai kerjakan migration features table
- 2026-06-20 — in_progress ? done — migration + seeder lulus test
```

**Aturan transisi status:**
- `todo ? in_progress`: hanya jika tidak ada task lain yang sedang `in_progress`, dan semua Dependency sudah `done`.
- `in_progress ? blocked`: jika menemukan requirement tidak jelas atau dependency gagal. WAJIB isi alasan di Log.
- `in_progress ? done`: HANYA jika seluruh Checklist tercentang DAN Definition of Done terpenuhi DAN DoD Global (§8) terpenuhi.
- Dilarang melompat `todo ? done` tanpa melalui `in_progress`.

---

## 4. SPRINT 9 — Dokumentasi & Desain (Tidak ada perubahan kode/DB)

**Tujuan Sprint:** Merapikan dan melengkapi dokumen desain sebelum implementasi database/kode dimulai.
**Dependency Sprint:** `S8-T006` harus `Done`.

### S11-T001 — Normalisasi docs/TASKS.md
**Status:** done
**Dependency:** S8-T006 (Done)
**Tujuan:** Merapikan `docs/TASKS.md`: hapus duplikasi, masukkan roadmap Sprint 11–13.
**Checklist:** lihat checklist asli di `Qwen_markdown` S11-T001.
**Definition of Done:** Tidak ada task/sprint duplikat; hanya satu task `In Progress`; Sprint 11+ semua `Todo`.

### S11-T002 — Update docs/RBAC_MATRIX.md
**Status:** in_progress
**Dependency:** S11-T001 (done)
**Tujuan:** Dokumentasikan role, Feature Tree, Action Permission, User Scope sesuai §1 dokumen ini.
**Checklist:** lihat checklist asli S11-T002, **plus**: sertakan tabel pemetaan dari §2 dokumen ini ke dalam `RBAC_MATRIX.md`.
**Definition of Done:** Semua 9 role terdokumentasi dengan permission default sesuai §2; tidak ada rekomendasi role per cabang.

### S11-T003 — Update docs/DATABASE_RULES.md
**Status:** todo
**Dependency:** S11-T002 (done)
**Tujuan:** Tuliskan skema tabel dari §6.1 dokumen analisa (`features`, `actions`, `permissions`, `roles`, `role_permissions`, `user_role_scopes`, `user_role_scope_targets`, `user_permission_overrides`) ke `DATABASE_RULES.md`.
**Checklist:** lihat checklist asli S11-T003.
**Definition of Done:** Semua constraint unik tercatat; aturan format `{feature_code}.{action_code}` tertulis eksplisit; larangan hardcode permission tertulis eksplisit.

### S11-T004 — Update docs/BUSINESS_RULES.md
**Status:** todo
**Dependency:** S11-T003 (done)
**Definition of Done:** Aturan bisnis per permission tertulis jelas — mencakup: permission mana yang membatasi akses ke modul pembayaran, permission mana yang membatasi edit nominal tagihan, permission mana yang membatasi akses laporan keuangan, dan bagaimana scope `selected_pop` membatasi visibilitas data antar-POP. Aturan ini ditulis dalam bentuk "siapa yang PUNYA permission X boleh Y" — bukan "role Z tidak boleh Y" — karena nama role bisa berubah kapan saja tanpa deploy ulang.

### S11-T005 — Update docs/PAGE_STRUCTURE.md
**Status:** todo
**Dependency:** S11-T004 (done)
**Definition of Done:** Halaman Feature Management, Action Management, Permission Matrix, Role Permission Matrix, User Role Scope terstruktur jelas termasuk siapa yang boleh akses halaman ini (hanya `owner`, `admin`).

### S11-T006 — Update docs/DEFINITION_OF_DONE.md
**Status:** todo
**Dependency:** S11-T005 (done)
**Definition of Done:** DoD untuk tiap komponen Advanced RBAC tertulis, termasuk larangan tandai Done jika route belum aman / scope bocor / akses URL langsung tanpa permission.

### S11-T007 — Update docs/MVP_SUCCESS_CHECKLIST.md
**Status:** todo
**Dependency:** S11-T006 (done)
**Definition of Done:** Checklist final mencakup seluruh item Advanced RBAC.

**Gate keluar Sprint 11:** Semua S11-T001 s/d S11-T007 berstatus `done` sebelum agent boleh mulai S12-T001.

---

## 5. SPRINT 10 — Database & Core Engine

**Tujuan Sprint:** Implementasi skema database dan service inti RBAC. **Belum ada UI.**
**Dependency Sprint:** Seluruh Sprint 11 `done`.

### S12-T001 — Migration: tabel `features`
**Status:** todo
**Dependency:** S11-T003 (done)
**Checklist:**
- [ ] Buat migration sesuai skema §6.1 dokumen analisa (`id`, `parent_id`, `code` unique, `name`, `sort_order`)
- [ ] Tambahkan foreign key self-referencing untuk `parent_id`
- [ ] Tulis test migration (up/down jalan tanpa error)
**Definition of Done:** Migration jalan dari nol tanpa error; constraint unique `code` aktif.

### S12-T002 — Migration: tabel `actions`
**Status:** todo
**Dependency:** S12-T001 (done)
**Checklist:**
- [ ] Buat migration `actions` (`id`, `code` unique)
- [ ] Seeder isi 13 action tetap sesuai §1.4 dokumen ini
**Definition of Done:** 13 action ter-seed, tidak ada duplikat.

### S12-T003 — Migration: tabel `permissions`
**Status:** todo
**Dependency:** S12-T002 (done)
**Checklist:**
- [ ] Buat migration `permissions` (`feature_id`, `action_id`, `code` unique, unique constraint kombinasi `feature_id`+`action_id`)
- [ ] Buat helper/observer yang auto-generate `code` dari `{feature.code}.{action.code}` saat record dibuat — JANGAN izinkan input manual `code` yang menyimpang dari pola ini
**Definition of Done:** Tidak mungkin membuat permission dengan code yang tidak mengikuti format `{feature_code}.{action_code}`.

### S12-T004 — Migration: tabel `roles` + `role_permissions`
**Status:** todo
**Dependency:** S12-T003 (done)
**Checklist:**
- [ ] Migration `roles` (`code` unique, `is_system` boolean)
- [ ] Migration `role_permissions` (pivot)
- [ ] Seeder 9 role sesuai §1.2, set `is_system = true` HANYA untuk `owner`
**Definition of Done:** 9 role ter-seed; role `owner` tidak bisa dihapus (proteksi di level service, bukan hanya UI).

### S12-T005 — Migration: tabel `user_role_scopes` + `user_role_scope_targets`
**Status:** todo
**Dependency:** S12-T004 (done)
**Checklist:**
- [ ] Migration sesuai skema §6.5 dokumen analisa
- [ ] Constraint: `scope_type` HARUS salah satu dari 5 nilai di §1.3 — gunakan enum/CHECK constraint di DB, bukan validasi aplikasi saja
- [ ] Jika `scope_type IN ('selected_pop','pop_tree')`, minimal 1 baris di `user_role_scope_targets` wajib ada (validasi di Service layer)
**Definition of Done:** Tidak mungkin `selected_pop` tanpa target POP tersimpan.

### S12-T006 — Migration: tabel `user_permission_overrides` (optional)
**Status:** todo
**Dependency:** S12-T005 (done)
**Definition of Done:** Tabel tersedia, belum wajib dipakai di Sprint 12, siap dipakai Sprint 13 jika diperlukan.

### S12-T007 — Seeder permission awal sesuai pemetaan §2
**Status:** todo
**Dependency:** S12-T003 (done)
**Checklist:**
- [ ] Insert seluruh baris di tabel §2 dokumen ini sebagai `features` + `permissions`
- [ ] Assign ke role default sesuai kolom "Role default" di §2
**Definition of Done:** Seluruh 9 permission di §2 ter-seed dan ter-assign ke role yang benar.

### S12-T008 — `app/Services/RbacService.php` (core engine)
**Status:** todo
**Dependency:** S12-T007 (done)
**Checklist:**
- [ ] Method `userHasPermission(User $user, string $permissionCode): bool` — cek lewat role + override
- [ ] Method `getEffectivePermissions(User $user): Collection`
- [ ] Method `userScopeFor(User $user, string $roleCode): ?UserRoleScope`
- [ ] Unit test: user dengan role tanpa permission terkait ? `false`; user dengan override `deny` ? `false` meski role punya akses; user dengan override `allow` ? `true` meski role tidak punya
**Definition of Done:** Coverage test mencakup semua kombinasi role+override.

### S12-T009 — Helper Scope resolver (`app/Services/PopScopeResolver.php`)
**Status:** todo
**Dependency:** S12-T005 (done)
**Checklist:**
- [ ] Method `resolvePopIds(UserRoleScope $scope): array` — translate `pop_tree` jadi daftar ID Mini POP turunannya (pakai struktur Cabang/POP dari `spesifikasi-pop-distribusi-cid.md`)
- [ ] Unit test tiap scope_type
**Definition of Done:** `pop_tree` benar mengembalikan seluruh Mini POP di bawah satu Cabang POP.

**Gate keluar Sprint 12:** S12-T001 s/d S12-T009 `done`. Tidak ada UI dibuat di sprint ini.

---

## 6. SPRINT 11 — UI, Middleware, Scope Enforcement, Tests

**Dependency Sprint:** Seluruh Sprint 12 `done`.

### S13-T001 — Middleware `CheckFeaturePermission`
**Status:** todo
**Dependency:** S12-T008 (done)
**Checklist:**
- [ ] Buat `app/Http/Middleware/CheckFeaturePermission.php` (lihat contoh kode di §7.1 dokumen analisa `analisa-rbac-dinamis-whusnet.md`)
- [ ] Register alias `permission` di kernel
- [ ] Test: request tanpa permission ? 403; request dengan permission ? lanjut
**Definition of Done:** Middleware reusable untuk semua route, tidak ada nama role di dalamnya.

### S13-T002 — Pasang middleware ke seluruh route dari §2
**Status:** todo
**Dependency:** S13-T001 (done)
**Checklist:**
- [ ] Untuk SETIAP baris di tabel §2 dokumen ini, pasang `->middleware('permission:{code}')` ke route Controller terkait
- [ ] Hapus SELURUH pengecekan role hardcode (`if ($user->role === ...)`) yang ditemukan di Controller terkait, ganti dengan reliance ke middleware
**Definition of Done:** `grep -r "role ===" app/Http/Controllers` tidak menemukan hasil untuk Controller yang terdaftar di §2.

### S13-T003 — Eloquent Global Scope `PopScope`
**Status:** todo
**Dependency:** S12-T009 (done)
**Checklist:**
- [ ] Buat `app/Models/Scopes/PopScope.php` (lihat contoh §7.4 dokumen analisa)
- [ ] Terapkan ke model: `Customer`, `CustomerSurvey`, `CustomerInstallation`, `Invoice` (sesuaikan jika ada model lain yang punya kolom `pop_id`/relasi POP)
- [ ] Test: user scope `own_created` hanya lihat data miliknya; `selected_pop` hanya lihat POP yang di-assign; `all_pop` lihat semua
**Definition of Done:** Tidak ada filter scope manual (`->where('pop_id', ...)`) tersisa di Controller/Service untuk model-model di atas.

### S13-T004 — Halaman Feature Management
**Status:** todo
**Dependency:** S13-T001 (done), S11-T005 (done)
**Definition of Done:** CRUD `features` dari UI berjalan dengan benar. Akses ke halaman ini dilindungi middleware `permission:feature.manage` (atau permission setara yang didefinisikan di seeder) — bukan dicek via `hasRole('owner')` atau `hasRole('admin')` di Controller. Siapa yang boleh akses halaman ini ditentukan dari assignment `role_permissions` di database, bukan dari nama role di kode.

### S13-T005 — Halaman Action Management
**Status:** todo
**Dependency:** S13-T004 (done)
**Definition of Done:** List `actions` read-only (13 action di §1.4 tidak boleh dihapus dari UI ini — actions ini fixed).

### S13-T006 — Halaman Permission Matrix
**Status:** todo
**Dependency:** S13-T005 (done)
**Definition of Done:** UI menampilkan grid Feature x Action, toggle untuk generate/hapus permission. Permission yang sudah dipakai role tidak bisa dihapus tanpa konfirmasi (cegah broken reference).

### S13-T007 — Halaman Role Permission Matrix
**Status:** todo
**Dependency:** S13-T006 (done)
**Checklist:**
- [ ] UI checklist permission per role
- [ ] Role dengan flag `is_system=true` (dalam seeder awal: hanya role berkode `owner`) tidak bisa diedit/dihapus dari UI — proteksi ini berbasis kolom `is_system` di tabel `roles`, bukan via `hasRole('owner')` di Controller. Logika pengecekan: `if ($role->is_system) abort(403)` — murni cek data, bukan cek nama role.
**Definition of Done:** Perubahan permission role langsung berlaku tanpa restart/deploy (tervalidasi via test).

### S13-T008 — Halaman User Role Scope
**Status:** todo
**Dependency:** S13-T007 (done), S12-T005 (done)
**Checklist:**
- [ ] Form assign role + scope_type ke user
- [ ] Jika `selected_pop`/`pop_tree` dipilih, wajib pilih minimal 1 POP target
- [ ] Validasi: tidak bisa assign role tanpa scope (sesuai aturan "Role tidak boleh dibuat per cabang" — scope-lah yang membatasi wilayah)
**Definition of Done:** Tidak mungkin menyimpan `user_role_scopes` tanpa `scope_type` valid.

### S13-T009 — Preview Effective Permission
**Status:** todo
**Dependency:** S12-T008 (done), S13-T008 (done)
**Definition of Done:** Admin bisa memilih satu user dan melihat daftar permission efektif (gabungan role + override) tanpa perlu trace manual ke database.

### S13-T010 — Sidebar/menu berbasis permission
**Status:** todo
**Dependency:** S13-T002 (done)
**Checklist:**
- [ ] Menu hanya tampil jika user punya minimal 1 permission `view` di feature terkait
- [ ] **WAJIB diingat:** menyembunyikan menu BUKAN pengganti middleware route. Middleware di S13-T002 tetap berlaku meski menu disembunyikan.
**Definition of Done:** Akses langsung via URL tetap diblok middleware meski menu tidak terlihat di sidebar.

### S13-T011 — Field-level permission untuk data sensitif
**Status:** todo
**Dependency:** S13-T003 (done)
**Checklist:**
- [ ] Field teknis sensitif (lihat `customer_technical_details`) hanya tampil jika user punya `customer.technical_detail.view_sensitive`
- [ ] Form edit field tsb hanya aktif jika punya `update_sensitive`
**Definition of Done:** User yang tidak memiliki permission `customer.technical_detail.view_sensitive` tidak bisa melihat field sensitif meski request langsung ke endpoint data — pengecekan ada di Service layer, bukan hanya disembunyikan di view. User yang tidak memiliki permission `customer.technical_detail.update_sensitive` tidak bisa mengedit field tersebut. Siapa yang punya atau tidak punya permission ini ditentukan sepenuhnya dari `role_permissions` di database — tidak ada nama role yang dicek di kode.

### S13-T012 — Test suite Advanced RBAC
**Status:** todo
**Dependency:** S13-T001 s/d S13-T011 (semua done)
**Definition of Done:** Feature test mencakup: akses ditolak tanpa permission, scope tidak bocor antar-POP, role `owner` tidak bisa dihapus, override `deny` mengalahkan role permission.

**Gate keluar Sprint 13:** Semua S13-T001 s/d S13-T012 `done`.

---

## 7. SPRINT 12 — PRD Compliance Audit & Hardening

**Dependency Sprint:** Seluruh Sprint 13 `done`.

### S14-T001 — Audit seluruh route terhadap §2 dan dokumen RBAC_MATRIX.md
**Status:** todo
**Dependency:** S13-T012 (done)
**Checklist:**
- [ ] List seluruh route aplikasi
- [ ] Tandai route mana yang TIDAK punya middleware `permission:*`
- [ ] Untuk tiap route tanpa middleware, tentukan apakah perlu permission baru (tambahkan ke §2 dokumen ini) atau memang public/auth-only
**Definition of Done:** Tidak ada route yang mengakses data sensitif tanpa middleware permission.

### S14-T002 — Audit log untuk seluruh perubahan RBAC
**Status:** todo
**Dependency:** S14-T001 (done)
**Definition of Done:** Setiap insert/update/delete di `roles`, `role_permissions`, `user_role_scopes` tercatat di `audit_logs` dengan `old_values`/`new_values`.

### S14-T003 — Penetration-style test: akses lintas-scope
**Status:** todo
**Dependency:** S14-T001 (done)
**Checklist:**
- [ ] Coba akses data POP lain via manipulasi ID di URL sebagai user `selected_pop`
- [ ] Coba akses data assigned ke teknisi lain
- [ ] Coba bypass middleware lewat route alias/typo
**Definition of Done:** Semua percobaan di atas gagal (403/404), tidak ada kebocoran data.

### S14-T004 — Hardening: rate-limit & validasi input halaman RBAC management
**Status:** todo
**Dependency:** S13-T011 (done)
**Definition of Done:** Halaman Permission Matrix dan Role Permission Matrix tahan terhadap submit massal/abuse.

**Gate keluar Sprint 14:** Semua S14-T001 s/d S14-T004 `done`.

---

## 8. SPRINT 13 — UAT & Release Readiness

**Dependency Sprint:** Seluruh Sprint 14 `done`.

> Catatan: Task S15-T001 s/d S15-T009 mengikuti UAT per-role yang sudah dirancang di `Qwen_markdown` asli (NOC, Helpdesk, FOP, Teknisi, Sales, dst — termasuk S15-T010 POP Admin, S15-T011 Flow Manual sampai Pembayaran, S15-T012 Flow Import, S15-T013 Final Review, S15-T014 Release Readiness yang sudah lengkap di dokumen sumber). Agent **WAJIB membaca task tersebut langsung dari `Qwen_markdown` asli** karena checklist UAT per-role sudah detail di sana — dokumen ini tidak menduplikasi isinya untuk menghindari drift antara dua sumber.

### S15-T010 — UAT Flow POP Admin
**Status:** todo
**Dependency:** S14-T003 (done)
*(checklist & acceptance criteria: ikuti persis isi `Qwen_markdown` S15-T010)*

### S15-T011 — UAT Flow Pelanggan Manual sampai Pembayaran
**Status:** todo
**Dependency:** S15-T010 (done)
*(checklist & acceptance criteria: ikuti persis isi `Qwen_markdown` S15-T011)*

### S15-T012 — UAT Flow Import Pelanggan sampai Aktivasi
**Status:** todo
**Dependency:** S15-T011 (done)
*(checklist & acceptance criteria: ikuti persis isi `Qwen_markdown` S15-T012)*

### S15-T013 — Final Review MVP_SUCCESS_CHECKLIST.md
**Status:** todo
**Dependency:** S15-T012 (done)
*(checklist & acceptance criteria: ikuti persis isi `Qwen_markdown` S15-T013)*

### S15-T014 — Release Readiness Checklist
**Status:** todo
**Dependency:** S15-T013 (done)
*(checklist & acceptance criteria: ikuti persis isi `Qwen_markdown` S15-T014)*

**Gate keluar Sprint 15:** Semua task `done` DAN `MVP_SUCCESS_CHECKLIST.md` menyatakan layak rilis.

---

## 9. Definition of Done — GLOBAL (berlaku semua task Sprint 11–13)

Sebuah task TIDAK BOLEH ditandai `done` jika salah satu berikut belum terpenuhi:

- [ ] Tidak ada nama role (`'fop'`, `'teknisi'`, dst) yang di-hardcode sebagai pengecekan akses di kode aplikasi (Controller/Middleware/Blade/Service) — kecuali di file seeder/migration.
- [ ] Tidak ada role baru dibuat per cabang/POP.
- [ ] Setiap permission baru mengikuti format `{feature_code}.{action_code}`.
- [ ] Setiap route yang mengubah/melihat data sensitif dilindungi middleware `permission:*`.
- [ ] Scope diterapkan via Global Scope Eloquent, bukan filter manual berulang.
- [ ] Perubahan RBAC tercatat di `audit_logs`.
- [ ] Test (unit/feature) ditulis dan lulus untuk task tsb.
- [ ] Dokumen ini (`AGENT_EXECUTION_GUIDE.md`) sudah di-update: Status task + Log Agent.

Jika ada SATU saja poin di atas tidak terpenuhi, status WAJIB tetap `in_progress` atau `blocked`, bukan `done`.

---

## 10. Sumber Kebenaran Jika Ada Konflik Antar-Dokumen

Urutan prioritas jika ada informasi yang bertentangan antar dokumen:

1. Instruksi langsung dari user di percakapan saat ini
2. `AGENT_EXECUTION_GUIDE.md` (dokumen ini)
3. `analisa-rbac-dinamis-whusnet.md`
4. `Qwen_markdown` (roadmap asli Sprint 11–13)
5. `implementation-plan-registrasi-survey-verifikasi.md`
6. `spesifikasi-pop-distribusi-cid.md`

Jika dokumen ini (level 2) berbeda dengan `Qwen_markdown` (level 4) soal detail checklist UAT Sprint 15, **`Qwen_markdown` yang dipakai untuk isi checklist**, karena dokumen ini sengaja tidak menduplikasi UAT detail (lihat catatan di §8). Untuk aturan arsitektur (role/scope/permission format), dokumen ini yang menjadi acuan utama.
