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
