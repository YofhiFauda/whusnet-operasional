# Migrasi & Mapping Permission RBAC (Lama → Baru)

Dokumen lanjutan dari [analisa-tampilan-rbac.md](analisa-tampilan-rbac.md). Berisi akar masalah yang dikonfirmasi langsung dari kode (bukan cuma dugaan visual), rencana solusi, dan mapping migrasi permission lama ke baru agar role existing tidak rusak saat rollout.

---

## 1. Analisis Tajam — Akar Masalah Terkonfirmasi di Kode

### A. Master Data: 3 menu sidebar TIDAK PUNYA permission sama sekali
Cek `config/rbac.php` → `allowed_actions`. Hanya ada `pops`, `packages`, `sla_timeline`. **Tidak ada** `wilayah`, `distribusi`, `status_pelanggan`. Artinya menu **Master Data Wilayah**, **Master Distribusi**, **Master Status Pelanggan** di sidebar bukan cuma "tidak terlihat di matrix" — permission-nya memang belum pernah dibuat di sistem. Semua role yang bisa akses menu ini kemungkinan lolos tanpa gate (atau ke-block total), bukan soal UI.

### B. Bukan duplikasi — 2-layer pipeline yang disengaja, tapi matrix tidak menampilkan hubungannya

**Koreksi dari draf sebelumnya:** klaim awal "dua sistem ditumpuk/tumpang tindih" kurang tepat. Berdasarkan `docs/fop-task/README.md` dan `TaskType.php`, ini memang 1 alur kerja yang sengaja dipecah 2 layer karena beda actor & beda waktu:

| | Layer 1 — `FopTask` (tiket) | Layer 2 — `Task` (eksekusi) |
|---|---|---|
| Controller | `FopTaskController` | `TaskController` |
| Kode permission | `fop_tasks.*` | `task.*` |
| Peran | FOP bikin tiket, **tentukan kategori pekerjaan** (SURVEY/PSB/MTN/dst) & prioritas, assign Team | Auto-generate begitu teknisi di-assign ke tiket (`FopTask::task_id`). FOP masih pegang kendali tambahan di sini (edit detail/jadwal eksekusi, ubah kategori Task eksekusi, override konflik, approve/reject laporan). Teknisi kerja checklist + submit laporan di sini |
| Kapan dipakai | Saat tiket dibuat/direncanakan | Setelah tiket jalan, sampai laporan selesai & di-approve FOP (quality gate) |
| Kode "ubah kategori" | `fop_tasks.update_sensitive` (`FopTaskController.php:267-268`) — kategori **tiket** | `task.edit.type` (`TaskFeatureSeeder.php:71`) — kategori **Task eksekusi**, sengaja beda gate, sengaja tidak default ke siapapun |

Dua kode "ubah kategori" ini **memang harus terpisah** — beda objek (tiket vs eksekusi), beda titik waktu, beda resiko. Bukan bug, ini by design.

**Masalah sebenarnya bukan redundansi, tapi matrix tidak menunjukkan pipeline-nya.** Operator lihat 2 grup mirip nama ("Task FOP" vs "Task Management → Aksi FOP") tanpa keterangan bahwa Layer 2 adalah kelanjutan otomatis dari Layer 1. Solusinya bukan menggabungkan/menghapus salah satu — cukup perjelas label & tambahkan catatan hubungan di matrix (lihat S4 revisi di bawah).

### C. Nama Action bersifat global per kode, bukan per fitur → label menyesatkan
`database/seeders/ActionSeeder.php`: kode `update_sensitive` dikasih nama tetap **"Update Timer SLA"**. Tapi kode yang sama dipakai ulang di dua fitur dengan arti benar-benar beda:
- `customers.detail.devices.update_sensitive` = ubah data sensitif perangkat (password PPPoE/WiFi)
- `fop_tasks.update_sensitive` = ubah kategori & prioritas tiket FOP (`docs/fop-task/README.md:45`)

Tidak ada satupun yang benar-benar berarti "Timer SLA". Nama Action disimpan satu kali per `ActionCode` (global), dipakai lintas fitur → matrix menampilkan label salah-konteks di banyak tempat. Ini murni bug nama, bukan cuma opini UX.

### D. Parent-child dependency: benar-benar tidak diimplementasi, bukan cuma "kurang jelas"
Cek `RoleManagementService::syncPermissions()` — isinya cuma `$role->permissions()->sync($sanitizedPermissions)` flat, tanpa validasi hierarki apapun. Tidak ada logic yang menolak/auto-tambah induk saat anak dicentang. Jadi 403 tanpa pesan yang disebut di analisa awal itu memang konsekuensi langsung: sistem sync menerima kombinasi permission apapun, termasuk kombinasi yang secara fungsional tidak akan pernah jalan di halaman nyata.

### E. Nuansa tambahan — tidak semua "sidebar lebih detail dari matrix" itu salah
Untuk **Tagihan** (Lunas/Belum Lunas/Semua) dan **Laporan** (Pelanggan/Tagihan/Pembayaran/Import): ini beda kasus dari poin B. Di sini sidebar cuma memecah **filter tampilan** dari resource yang sama (`invoices`, `reports`), bukan fitur beda. Satu permission `invoices.view` yang mengontrol ketiga menu itu justru desain yang benar — tidak perlu permission terpisah per filter. Yang perlu diperbaiki di sini cuma dokumentasi/tooltip ("permission ini mengontrol akses ke semua varian Tagihan"), bukan pemecahan permission baru. Jangan disamakan solusinya dengan kasus Task FOP (poin B) yang memang dua fitur/tabel berbeda.

---

## 2. Solusi — Diprioritaskan Berdasarkan Resiko & Effort

| # | Solusi | Jenis perubahan | Resiko | Prioritas |
|---|---|---|---|---|
| S1 | Rename label Action per-konteks fitur (fix "Update Timer SLA" dsb) | Update kolom `name`/tambah `display_name` — **tanpa ubah `code`** | Sangat rendah | 1 (segera) |
| S2 | Alias label matrix = nama menu sidebar | Update kolom `name` di `Feature`/`Permission` | Sangat rendah | 1 (segera) |
| S3 | Registrasi feature untuk Wilayah/Distribusi/Status Pelanggan | Tambah entry baru di `config/rbac.php` + generate permission baru | Rendah (additive) | 2 |
| S4 | Perjelas label 2 grup matrix jadi eksplisit pipeline: **"Tiket FOP — Perencanaan & Kategori"** (`fop_tasks.*`) dan **"Eksekusi Task — Penjadwalan, Checklist & Quality Gate"** (`task.*`), + tambah 1 baris keterangan di matrix "Layer 2 otomatis muncul setelah tiket di-assign teknisi". **Jangan digabung/dihapus** — keduanya perlu ada, cuma perlu keterangan hubungan | Update label `Feature`/grouping + copy keterangan, tanpa ubah `code` permission | Rendah | 2 |
| S5 | Gabung permission mikro Task Teknisi jadi 1 permission bisnis ("Menjalankan & Melaporkan Tugas Lapangan") | Merge beberapa `code` lama → 1 `code` baru | **Menengah** (breaking, perlu remap pivot) | 3 |
| S6 | Dependency chaining (parent-child) di `RoleManagementService` + UI checkbox | Tambah validasi baru, tidak ubah data permission | Rendah-menengah (logic baru) | 3 |
| S7 | Role template preset (Owner/Teknisi/Finance shortcut) | Fitur baru murni | Rendah | 4 |

**Catatan penting:** S1, S2, S3, S4 **tidak butuh migrasi data role_permissions** — cuma ubah label tampilan atau tambah data baru. Cuma **S5** yang benar-benar "migrasi lama → baru" dalam arti mapping ulang assignment role. Bagian 3 fokus di situ.

---

## 3. Mapping Migrasi Permission — Lama → Baru

Cakupan migrasi nyata cuma S5 (merge permission mikro Task Teknisi & FOP) plus S3 (permission baru untuk Master Data yang belum ada). S1/S2/S4 cukup lewat seeder update `name`, tidak perlu tabel mapping di bawah ini.

### 3.1 Merge Permission "Aksi Teknisi" (5 kode → 1 kode)

| Kode lama | Nama lama | Aksi migrasi |
|---|---|---|
| `task.status.start` | Mulai Task | → digantikan |
| `task.status.complete` | Selesaikan Task | → digantikan |
| `task.evidence.upload` | Upload Bukti Foto | → digantikan |
| `task.status.pending` | Laporan Nanti | → digantikan |
| `task.view.own` | Lihat Task Sendiri | **tetap terpisah** (view != action, jangan digabung ke action gabungan) |

**Kode baru:** `task.execute` — "Menjalankan & Melaporkan Tugas Lapangan (Teknisi)"

Role yang punya salah satu dari 4 kode di atas → auto-grant `task.execute`. Role `teknisi` saat ini sudah punya keempatnya (`RolePermissionSeeder.php:143-161` scope penuh) → otomatis dapat `task.execute` penuh, tidak ada perubahan perilaku user teknisi.

### 3.2 Merge Permission "Aksi FOP — Edit" (3 kode → 1 kode)

| Kode lama | Nama lama | Aksi migrasi |
|---|---|---|
| `task.edit` | Edit Task | → digantikan |
| `task.schedule` | Ubah Jadwal Task (via Edit) | → digantikan |
| `task.edit.type` | Ubah Tipe Task | **JANGAN digabung** — ini sengaja dipisah & tidak di-assign default ke siapapun (`TaskFeatureSeeder.php:122-123`, keputusan by design biar ubah tipe task perlu izin eksplisit dari Owner/Admin). Merge di sini justru menghapus safeguard yang sudah sengaja dibuat. |

**Kode baru:** `task.manage` — "Mengubah Detail & Jadwal Tugas (FOP)" (gabungan `task.edit` + `task.schedule` saja)

`task.edit.type` **tetap berdiri sendiri** sebagai permission terpisah, cukup direname labelnya jadi lebih jelas: **"Ubah Kategori Task (Perlu Otorisasi Khusus)"** supaya jelas bahwa ini bukan bagian paket edit biasa.

Role yang punya `task.edit` DAN `task.schedule` (role `fop`, `RolePermissionSeeder.php:122-141` via `fop_tasks.*` — **catatan: ini beda sistem**, cek ulang assignment aktual di tabel `role_permissions` sebelum migrasi, jangan asumsi dari seeder kode saja karena data prod bisa sudah didandani manual lewat halaman matrix).

### 3.3 Permission Baru (additive, tidak ganti apapun)

| Fitur baru | Kode | Action tersedia | Default grant |
|---|---|---|---|
| Master Data Wilayah | `master_wilayah` | view, create, update, delete | sama pola dengan `pops` — admin/owner |
| Master Distribusi | `master_distribusi` | view, create, update, delete | sama pola dengan `pops` |
| Master Status Pelanggan | `master_status_pelanggan` | view, create, update, delete | sama pola dengan `packages` |

Tambahkan ke `config/rbac.php` → `allowed_actions`, jalankan `PermissionGeneratorService`, lalu assign eksplisit ke role `admin`/`owner` di `RolePermissionSeeder.php` (pola sama seperti `pops.*`, `packages.*`).

### 3.4 Rename-Only (tidak masuk tabel migrasi, cukup update `name`)

| Kode | Nama lama (salah konteks) | Nama baru |
|---|---|---|
| `fop_tasks.update_sensitive` | Update Timer SLA | Ubah Kategori & Prioritas Tiket |
| `customers.detail.devices.update_sensitive` | Update Timer SLA | Ubah Data Sensitif Perangkat |
| `customers.detail.devices.view_sensitive` | View Sensitive | Lihat Data Sensitif Perangkat |

---

## 4. Prosedur Migrasi (Langkah Eksekusi)

1. **Backup** tabel `permissions` dan `role_permissions` sebelum eksekusi (mysqldump / snapshot).
2. **Tambah permission baru** (`task.execute`, `task.manage`, `master_wilayah.*`, dll) via seeder idempotent (`updateOrCreate` pola sama seperti `TaskFeatureSeeder.php`) — ini tidak menghapus apapun, aman dijalankan kapan saja.
3. **Migrasi data pivot** — untuk tiap role yang punya salah satu kode lama di 3.1/3.2, tambahkan kode baru via `syncWithoutDetaching` (bukan `sync`, supaya tidak hapus permission lain yang tidak terkait).
4. **Masa transisi (dual-grant)** — biarkan kode lama & baru aktif bersamaan minimal 1 sprint. Middleware/Policy tetap cek kode lama supaya tidak ada downtime akses.
5. **Update kode aplikasi** — ganti pengecekan `hasPermission('task.edit')` dkk di `TaskController`/`TaskPolicy` jadi `hasPermission('task.manage')`. Deploy setelah langkah 3 selesai di semua environment.
6. **Cleanup** — setelah dikonfirmasi tidak ada role yang masih bergantung ke kode lama (`role_permissions` cross-check kosong) DAN audit log tidak menunjukan akses via kode lama selama masa transisi, hapus kode lama & baris `permissions` terkait lewat migration terpisah.

## 5. Rollback

Karena migrasi cuma additive (langkah 2-3) sebelum cleanup (langkah 6), rollback minimal: hapus grant kode baru dari pivot (`role_permissions`) tanpa berdampak ke kode lama yang masih aktif berdampingan. Rollback aman selama langkah 6 (cleanup/hapus kode lama) belum dieksekusi — jadikan langkah 6 gerbang titik-tanpa-jalan-balik, jalankan hanya setelah sign-off eksplisit.

## 6. Checklist Testing

- [ ] Role `teknisi` existing tetap bisa Mulai/Selesai/Upload/Laporan Nanti setelah dapat `task.execute`
- [ ] Role `fop` existing tetap bisa Edit + Ubah Jadwal setelah dapat `task.manage`, TIDAK dapat `task.edit.type` (tetap perlu grant eksplisit)
- [ ] Halaman Master Data Wilayah/Distribusi/Status Pelanggan sekarang muncul di matrix & bisa di-toggle per role
- [ ] Label `fop_tasks.update_sensitive` & `customers.detail.devices.*_sensitive` tampil benar (bukan lagi "Update Timer SLA") di halaman matrix
- [ ] Role tanpa permission induk (`customers.view`) tidak bisa lagi centang permission anak setelah S6 (dependency chaining) diimplementasi
- [ ] Setelah cleanup (langkah 6), tidak ada 403 baru yang muncul untuk role manapun di seluruh modul Task

---

## 7. Temuan Verifikasi Pasca-Implementasi (2026-07-09) — Screenshot `halaman-matrix-rbac.png`

Setelah S1-S6 selesai dikerjakan, dicek ulang pakai screenshot nyata halaman matrix (role Admin) dibanding sidebar. Ketemu 3 gap yang belum masuk cakupan S1-S7 sebelumnya. Detail akar masalah & kronologi ada di [analisa-tampilan-rbac.md bagian 5](analisa-tampilan-rbac.md#5-verifikasi-pasca-implementasi-2026-07-09--temuan-sisa-dari-screenshot-matrix-terbaru). Bagian ini fokus ke rencana perbaikannya.

### S8 — Alias label "Manajemen User & POP"

Temuan 1.A asli (`pops`/`users` dipecah 2 modul padahal sidebar gabung 1 menu) kelewat, gak pernah masuk S1-S7. Ini **bukan permission baru, bukan merge** — cuma soal presentasi:

- **Opsi A (minim effort):** tambah 1 baris keterangan di header matrix, mirip pola S4 — modul "POP/Cabang" & "User Management" dikasih catatan kecil "Keduanya mengontrol menu gabungan 'Manajemen User & POP' di sidebar." Gak ubah struktur data.
- **Opsi B (lebih rapi tapi effort lebih):** render 2 modul itu sebagai 1 grup visual (union), tapi tetep 2 set checkbox terpisah di baliknya (POP tetap `pops.*`, User tetap `users.*` — jangan digabung jadi 1 permission, karena secara bisnis emang beda scope akses).

Rekomendasi: **Opsi A** dulu — resiko sangat rendah, konsisten sama pendekatan S4 yang udah dipakai buat kasus Tiket FOP/Eksekusi Task.

**Jenis perubahan:** update `Feature.name`/tambah copy keterangan di `matrix.blade.php`. Tidak butuh migrasi data.

### S9 — Reorder grouping Master Data biar nyambung visual sama sidebar

`pops` (sort_order lama, dekat urutan atas) dan `packages` juga secara konsep bagian dari "Master Data" tapi posisinya jauh dari `master_wilayah`/`master_distribusi`/`master_status_pelanggan`/`sla_timeline` (sort_order 12-14, ditambah belakangan pas S3).

**Perbaikan:** samakan `sort_order` semua Feature yang masuk grup Master Data (`pops`, `packages`, `master_wilayah`, `master_distribusi`, `master_status_pelanggan`, `sla_timeline`) jadi satu blok angka berurutan di `FeatureSeeder.php`, sesuai urutan tampil di sidebar (`sidebar2.png`): Wilayah → POP/Cabang → Distribusi → Paket Internet → Status Pelanggan → Timeline SLA.

**Jenis perubahan:** update kolom `sort_order` doang (angka), lewat seeder `updateOrCreate` — idempotent, tidak menyentuh `code`/pivot `role_permissions`. Resiko sangat rendah.

### S10 — Fix styling "⚠ sensitif" hilang di permission level root

`matrix.blade.php` cuma taro `$isSensitive = str_contains($perm->code, 'sensitive')` di loop level grandchild (nested di bawah Detail Pelanggan). Permission sensitif yang nempel langsung di Feature `ROOT` (contoh: `fop_tasks.update_sensitive`) gak lewat loop itu, jadi render polos tanpa warning merah/⚠ — padahal ini salah satu permission paling beresiko (siapa boleh ubah kategori & prioritas tiket FOP).

**Perbaikan:** pindahkan logic `$isSensitive` (deteksi `str_contains($perm->code, 'sensitive')` + class merah + ikon ⚠) jadi dipakai di **ketiga level loop** (root, child, grandchild) di `matrix.blade.php`, bukan cuma grandchild.

**Jenis perubahan:** murni template Blade, tidak menyentuh data/permission. Resiko sangat rendah, langsung kerjain.

### Catatan operasional — ✅ Selesai (Poin D)

Label `fop_tasks.update_sensitive` di screenshot masih nampilin nama lama "Update Timer SLA" karena migrasi `2026_07_09_000000_migrate_rbac_permissions.php` (step 3, rename) belum ke-apply ke database yang dipakai ambil screenshot.

**Investigasi:** `php artisan migrate:status` nunjukin migrasi ini udah tercatat **"Ran"** (batch 1, pas database di-fresh). Tapi dicek isi datanya — kolom `name` buat `fop_tasks.update_sensitive`, `customers.detail.devices.update_sensitive`, `customers.detail.devices.view_sensitive` tetap `null`, dan `updated_at` sama persis `created_at` di **seluruh tabel `permissions`** (0 baris pernah ke-`update()`). Artinya step 3 gak pernah benar-benar nge-apply pas run yang tercatat itu — walau kode step 3-nya sendiri udah benar. Karena migrasi yang udah tercatat "Ran" gak akan Laravel jalankan ulang (walau isi file diedit belakangan), gak bisa langsung `php artisan migrate` lagi buat re-trigger.

**Fix:** jalankan langsung logic rename-nya (persis sama step 3) lewat `php artisan tinker`:
```php
$renames = [
    'fop_tasks.update_sensitive' => 'Ubah Kategori & Prioritas Tiket',
    'customers.detail.devices.update_sensitive' => 'Ubah Data Sensitif Perangkat',
    'customers.detail.devices.view_sensitive' => 'Lihat Data Sensitif Perangkat',
];
foreach ($renames as $code => $name) {
    Permission::where('code', $code)->update(['name' => $name]);
}
```
Dikonfirmasi ketiga baris sekarang punya `name` yang benar.

**Root cause exact kenapa step 3 gak nge-apply pas migrasi jalan otomatis belum ketemu** (kemungkinan versi file yang benar-benar tereksekusi beda dari versi final di working tree sekarang). **Catatan buat rollout ke staging/prod nanti:** jangan cuma percaya `migrate:status` bilang "Ran" — verifikasi juga isi datanya (`SELECT name FROM permissions WHERE code IN (...)`) setelah migrasi jalan, biar ketauan kalau ada step yang diam-diam gak nge-apply kayak kejadian ini.

| # | Solusi | Jenis perubahan | Resiko | Prioritas | Status |
|---|---|---|---|---|---|
| S8 | Alias label "Manajemen User & POP" (catatan keterangan, bukan merge) | Update `name`/copy di view | Sangat rendah | 2 | ✅ Selesai |
| S9 | Reorder `sort_order` grup Master Data biar nyambung sidebar | Update kolom `sort_order` via seeder | Sangat rendah | 2 | ✅ Selesai |
| S10 | Perbaiki styling sensitif hilang di level root/child | Template Blade doang | Sangat rendah | 1 (bug visual, segera) | ✅ Selesai |
| Poin D | Rename label belum ke-apply di data real | Manual `Permission::update()` via tinker | Sangat rendah | 1 (data fix, segera) | ✅ Selesai |
| S11 | Perbaiki 3 deskripsi fitur yang salah/kebalik + 1 minor | Update copy di array `$descriptions`, `matrix.blade.php` | Sangat rendah | 1 (info menyesatkan operator) | ✅ Selesai |

### S11 — Fix deskripsi fitur yang salah di array `$descriptions`

Array `$descriptions` (satu baris keterangan per fitur di header matrix) ditambah belakangan di `matrix.blade.php`, di luar cakupan S1-S10. Dicek satu-satu ke kode/dokumentasi terkait:

| Kode | Sebelum (salah) | Sesudah (fix) | Bukti |
|---|---|---|---|
| `dashboard` | "...dan grafik billing." | "...termasuk ringkasan status billing pelanggan." | `DashboardController.php`/`dashboard.blade.php` gak ada chart/canvas — cuma stat card angka |
| `sla_timeline` | "...untuk **penyelesaian** tiket gangguan/pemasangan." | "...batas waktu **wajib mulai ditangani**... bukan durasi pengerjaan teknisi di lapangan." | `docs/master/sla-timeline/README.md`: *"Bukan durasi pengerjaan teknisi di lapangan"* — deskripsi lama kebalik |
| `customers.detail.packages` | "...dan **status aktivasi layanan**." | "...Status aktivasi layanan diatur lewat 'Pemasangan Pelanggan', bukan di sini." | `config/rbac.php`: action `ACTIVATE` cuma ada di `customers.detail.installation`, bukan `.packages` (yang cuma VIEW/UPDATE) |
| `invoices` | "...penerbitan, **pembatalan**, pencetakan..." | "...penerbitan, penyesuaian nominal, **penghapusan**, dan pencetakan..." | `config/rbac.php`: `invoices` gak ada action `CANCEL`, cuma VIEW/CREATE/UPDATE/DELETE/PRINT |

Semua perubahan cuma teks copy di `matrix.blade.php` — gak nyentuh data/permission, resiko nol.

---

## 8. Bug Ditemukan Saat Verifikasi Runtime (2026-07-09) — S6 Sibling-View Gap

Diverifikasi langsung lewat HTTP request beneran (login session, submit form matrix, cek `role_permissions` di DB) — bukan cuma baca kode. Ketemu bug fungsional nyata di dependency chaining (S6) yang gak kelihatan dari code review.

### Bug: dependency chaining cuma jalan buat ancestor, gak buat sibling-dalam-fitur-yang-sama

**Repro:** submit matrix cuma centang `task.manage` (id 3) doang buat role baru → `role_permissions` hasilnya cuma `["task.manage"]`. Harusnya `task.view.all` ikut ke-grant otomatis (S6), tapi enggak.

**Root cause:** `RoleManagementService::syncPermissions()` versi lama cuma jalanin loop mulai dari **PARENT** feature milik permission yang dicentang — gak pernah cek feature MILIK PERMISSION ITU SENDIRI. Ini kebetulan gak masalah buat kasus `customers.detail.survey.view` (MINI_FEATURE bertingkat, ancestor-chain `customers.detail` → `customers` jalan normal). Tapi buat `task.manage` — feature-nya `tasks.fop` (flat, gak ada nesting), dan `task.view.all` itu **sibling** (bukan ancestor) di feature yang sama. Loop versi lama jalan dari parent `tasks.fop` yaitu `tasks` (root, gak override, gak ada permission `tasks.view`) — jadi gak pernah nyentuh `tasks.fop` itu sendiri. Config `view_permission_overrides` (S6 awal) sebenarnya sia-sia buat 2 entry `tasks.fop`/`tasks.teknisi`-nya — gak pernah ke-match, karena dicek sebagai kode PARENT, padahal 'tasks.fop'/'tasks.teknisi' gak pernah jadi parent siapapun di tree ini.

**Dampak lebih luas dari yang keliatan:** gap yang sama juga kena kasus `customers.detail.survey.update` dicentang tanpa `.view` — sibling `.view` di feature yang sama (`customers.detail.survey`) juga gak ke-grant otomatis (walau ancestor `customers.detail`/`customers` tetap ke-grant benar). Ini persis komplain awal di `analisa-tampilan-rbac.md` poin 3.C (403 tanpa alasan kalau cuma centang level bawah) — S6 versi awal cuma nutup separuh kasusnya.

**Fix:** `RoleManagementService::syncPermissions()` — loop sekarang mulai dari feature MILIK PERMISSION itu sendiri (cek sibling-view dulu), baru lanjut ke ancestor. Satu logic yang sama nutup kedua kasus (sibling-dalam-fitur-flat & ancestor-chain-nested).

**Verifikasi ulang pasca-fix** (HTTP request beneran, bukan cuma baca kode):
| Skenario | Submit | Hasil `role_permissions` |
|---|---|---|
| Sibling-view (flat feature) | `task.manage` doang | `task.view.all`, `task.manage` ✅ |
| Ancestor-chain (nested) | `customers.detail.survey.view` doang | `customers.view`, `customers.detail.view`, `customers.detail.survey.view` ✅ |
| Sibling + ancestor sekaligus | `customers.detail.survey.update` doang | `customers.view`, `customers.detail.view`, `customers.detail.survey.view`, `customers.detail.survey.update` ✅ |
| Sanity (fitur flat tanpa hierarki) | `dashboard.view` doang | `dashboard.view` doang (gak ada over-grant) ✅ |
| Permission id gak valid | `permissions[]=999999` | Ditolak, state role gak berubah ✅ |

**Jenis perubahan:** logic PHP di `RoleManagementService.php` doang, gak ubah skema/data. Resiko rendah, langsung berlaku begitu deploy (gak butuh migrasi).

---

## 9. Anomali Rename Label Balik Null — Root Cause Ketemu & Fix Permanen (2026-07-09)

Poin D (bagian 7) sempat dianggap selesai lewat fix manual `tinker`, tapi user konfirmasi setelah refresh database (`migrate:fresh --seed`), label balik jadi `null` lagi. Ditelusuri ulang dan root cause-nya ketemu — bukan soal kode step 3 salah, tapi soal **urutan eksekusi migration vs seeder**.

### Root cause

Migrasi `2026_07_09_000000_migrate_rbac_permissions.php` step 1 manggil `PermissionGeneratorService::generate()`, yang butuh tabel `actions` udah keisi (dari `ActionSeeder`). Tapi `ActionSeeder` itu bagian dari `DatabaseSeeder` (fase `--seed`), yang jalan **SETELAH** semua migration selesai — bukan sebelum. Jadi saat step 1 migrasi kita jalan (masih fase migrations), `Action::all()` masih kosong → `generate()` skip semua kombinasi feature+action (gak ada yang match) → permission `fop_tasks.update_sensitive` dkk **belum ada** di titik itu. Step 3 (rename) yang jalan setelahnya di migration yang sama nyari `Permission::where('code', ...)` yang belum ada → `update()` kena 0 baris, gak error, gak ketauan.

Permission-nya baru bener-bener ke-create belakangan, lewat `SlaTimelineFeatureSeeder` (dipanggil di `DatabaseSeeder`, fase `--seed`, saat itu `Action` udah ada) — tapi dibuat dengan `name = null` (default `PermissionGeneratorService`), dan gak ada proses lain sesudahnya yang rename ulang.

Fix manual saya sebelumnya (lewat `tinker`) "berhasil" cuma karena saat itu dijalankan permission-nya **udah ada** (dari seed sebelumnya) — beda kondisi dari saat migration asli jalan di database kosong.

### Fix permanen

Rename dipindah dari migration one-off ke tempat yang PASTI jalan tiap kali permission itu dibuat/ditemukan:

1. **`config/rbac.php`** — tambah `permission_name_overrides`, map kode permission → label kontekstual (3 entry yang sama: `fop_tasks.update_sensitive`, `customers.detail.devices.update_sensitive`, `customers.detail.devices.view_sensitive`).
2. **`PermissionGeneratorService::generate()`** — dua titik:
   - Saat **create** baris baru: `'name' => $nameOverrides[$permissionCode] ?? null` (langsung benar sejak lahir, gak nunggu proses lain).
   - Saat permission **sudah ada** tapi `name`-nya belum sesuai override: backfill (`$existing->update(['name' => ...])`) — jaga-jaga kalau ada yang sempat ke-generate sebelum override didaftarkan, atau kena reset kayak kejadian ini.

Karena `PermissionGeneratorService::generate()` dipanggil berkali-kali di banyak seeder (`SlaTimelineFeatureSeeder` 2x, dll), fix ini otomatis "self-healing" — gak peduli urutan/timing migration vs seeder, tiap kali `generate()` jalan dan permission itu ketemu, label-nya dipastikan benar.

Migration `2026_07_09_000000...php` step 3 **dibiarkan ada** sebagai fallback tambahan (gak salah, cuma gak reliable sendirian) — dikasih komentar penjelasan biar gak dikira satu-satunya tempat fix ini diterapkan.

**Verifikasi:** dijalankan ulang `app(PermissionGeneratorService::class)->generate()` langsung (mensimulasikan efek dari seeder manapun yang manggil dia) — hasil: `permissions_created: 0`, `permissions_skipped: 82`, dan ketiga label sekarang benar (`Ubah Kategori & Prioritas Tiket`, `Ubah Data Sensitif Perangkat`, `Lihat Data Sensitif Perangkat`).

**Jenis perubahan:** `config/rbac.php` (data konfigurasi) + `PermissionGeneratorService.php` (logic). Idempotent, aman dijalankan berkali-kali, gak butuh migrasi data tambahan — otomatis kepasang tiap kali seeder yang manggil `generate()` jalan (termasuk `migrate:fresh --seed` berikutnya).

### Implementasi S8-S10 (2026-07-09)

- **S8**: `matrix.blade.php` — tambah catatan `@if($feature->code === 'pops' || $feature->code === 'users')` di header root feature, pola sama seperti S4 (fop_tasks/tasks).
- **S9**: `sort_order` blok Master Data dirapikan jadi kontigu di `FeatureSeeder.php`: `master_wilayah`=2, `pops`=3, `master_distribusi`=4, `packages`=5, `master_status_pelanggan`=6. `sla_timeline` (`SlaTimelineFeatureSeeder.php`) diubah dari **dinamis** (`max(sort_order)+1` — akar masalah kenapa dia selalu jatuh paling bawah) jadi **fixed = 7**, biar Timeline SLA nutup blok Master Data persis di posisi terakhir sesuai urutan sidebar. Fitur lain di-geser: `users`=8, `roles`=9, `customers`=10, `invoices`=11, `payments`=12, `reports`=13, `audit_logs`=14, `fop_tasks`=15, `tasks` (`TaskFeatureSeeder.php`, root "Eksekusi Task")=16.
  - **Wajib**: jalankan `php artisan db:seed --class=FeatureSeeder`, `--class=SlaTimelineFeatureSeeder`, `--class=TaskFeatureSeeder` ulang (atau lewat migrasi `2026_07_09_000000_migrate_rbac_permissions.php` yang udah manggil ketiganya) supaya `sort_order` di DB ke-update — `updateOrCreate` di seeder ini idempotent, aman dijalankan ulang kapan saja, gak nyentuh `role_permissions`.
- **S10**: `$isSensitive` (deteksi `str_contains($perm->code, 'sensitive')` + styling merah + ikon ⚠) sekarang dipasang di ketiga level loop (root, child, grandchild) di `matrix.blade.php`, bukan cuma grandchild. `fop_tasks.update_sensitive` sekarang ikut kehighlight merah+⚠ seperti permission sensitif lainnya.

Ketiganya murni perubahan seeder/view (`updateOrCreate` idempotent + template Blade) — tidak menyentuh pivot `role_permissions`, aman dijalankan kapan saja tanpa prosedur migrasi bertahap seperti S5.
