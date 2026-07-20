# User Flow — Modul FOP Task

Aktor utama: **FOP** (koordinator lapangan, permission `fop_tasks.*`). Aktor sekunder: **Teknisi** (lihat [task-workflow (archive)](archive/task-workflow.md) untuk flow submit laporan).

## 1. FOP buka Dashboard (`/fop`)

1. Login sebagai user dengan policy `viewAll` di `Task` (biasanya role FOP/Owner).
2. Landing di `/fop` → lihat:
   - Stat card: Antrean Survey, Perlu Aksi FOP, Sedang Berjalan, Selesai Hari Ini (+ badge overdue merah kalau ada SLA lewat).
   - **Team FOP Aktif** — card per team (nama, tanggal, list tiket, avatar anggota, progress bar).
   - Antrean survey (list pelanggan belum disurvey, urut yang paling lama).
   - List teknisi + status (aktif/terjadwal/standby) + lokasi kalau ada task in-progress.
3. Klik card Team → buka detail panel (tugas per anggota).
4. Klik "Kelola Team →" → pindah ke `/fop-tasks`.

## 2. FOP kelola tiket (`/fop-tasks`)

### Lihat & filter tiket aktif

1. Buka `/fop-tasks` → sistem auto-sync dulu (buat tiket Survey/PSB baru dari pelanggan yang belum ada tiketnya, recalculate prioritas semua tiket aktif).
2. Tabel nampilin tiket status aktif (draft/terjadwal/in_progress/pending — bukan selesai/dibatalkan), urut: **tiket dengan `client_request_date` di masa depan (besok+) selalu di bawah** (section "Upcoming", walau prioritasnya Urgent — lihat [flowchart.md § 8](flowchart.md#8-antrian-sorting-berdasarkan-client_request_date-task-8)), baru di antara sisanya urut prioritas (Urgent→Low) lalu tanggal. Begitu `client_request_date` udah sama dengan hari ini (gak perlu nunggu refresh manual/cron), tiketnya otomatis "naik" ikut sorting prioritas normal di request berikutnya.
3. Kolom "Tanggal" nampilin badge kalau tiket punya `client_request_date`: **"JADWAL HARI INI"** (merah, kalau tanggalnya hari ini/udah lewat) atau **"Terjadwal — {tanggal}"** (abu-abu, kalau masih di masa depan).
4. Filter tersedia: search (nomor/tugas/issue), kategori, status, prioritas, desa, team.

### Tambah tiket manual

1. Klik "Tambah Task" → modal muncul.
2. Pilih kategori (Survey & PSB **gak muncul** di dropdown — itu auto-sync only).
3. Isi tanggal, tugas, desa, POP, pelanggan (opsional), issue, catatan. **Gak ada lagi field pilih Team** — dropdown `team_id` manual udah dihapus dari modal (lihat bagian 3, Team sekarang otomatis).
4. Pilih status: **Terjadwal** (default) atau **Pending** (wajib isi alasan + tanggal request client) — cuma 2 opsi ini, buat klasifikasi awal tiket BARU (belum ada `Task` eksekusi buat di-derive statusnya). Opsi ini **cuma muncul di modal Tambah**, bukan modal Edit (lihat "Status Realtime" di bawah — sejak Task 9, status tiket existing gak bisa diubah manual lagi lewat modal).
5. Pilih prioritas — **cuma muncul kalau user punya `fop_tasks.update_sensitive`**, selain itu default Low/otomatis.
6. Pilih 1+ teknisi (wajib) → submit.
7. Sistem: simpan `FopTask`, `technicians()->sync()`, auto-buat `Task` eksekusi teknisi (title polos dulu), link ke `fop_task.task_id`, lalu **auto-rebuild Team** untuk tanggal tiket itu (lihat bagian 3) — title Task eksekusi ke-update lagi begitu Team-nya kebentuk. Begitu `Task` eksekusi ada, statusnya langsung ikut aturan "Status Realtime" (lihat di bawah).

### Edit tiket

1. Klik tiket di tabel → modal edit terisi data existing.
2. User biasa cuma bisa ubah: tanggal, tugas, desa/POP/pelanggan, issue/catatan, teknisi.
3. User dengan `fop_tasks.update_sensitive` bisa juga ubah kategori & prioritas.
4. **Status TIDAK bisa diubah dari modal ini lagi (sejak Task 9)** — field Status di modal edit sekarang cuma badge read-only (ngikutin status realtime tiket), bukan dropdown. Perubahan status cuma bisa lewat: (a) status teknisi berubah di lapangan (auto-derive, lihat "Status Realtime" di bawah), atau (b) tombol **Cancel** eksplisit di tabel (lihat di bawah).
5. Submit → update `FopTask` (field non-status), sync teknisi, update/buat `Task` terkait, catat `AuditLog`, **auto-rebuild Team** untuk tanggal tiket (dan tanggal lama juga kalau tanggalnya diubah).

### Hapus tiket

1. Klik hapus (icon/tombol) → konfirmasi browser.
2. Sistem detach teknisi, hapus `FopTask`, catat `AuditLog`.
3. **Kategori Survey & PSB gak punya tombol Hapus sama sekali (2026-07-20)** — beda dari Cancel di atas (soal ubah status), ini soal hapus permanen barisnya (`FopTaskController::destroy()`, `abort(422)` dari awal). Sebelumnya tombolnya masih kelihatan (disabled + tooltip alasan); sekarang gak dirender. Kelola SRV/PSB (batal/gagal) lewat halaman Customer, bukan hapus. Tiket dari Ticketing juga gak bisa dihapus (disabled + tooltip beda: histori tiket harus tetap traceable) — batalkan lewat Cancel kalau salah input. Task_type lain tetap bisa dihapus normal.

### Status Realtime & Riwayat Transisi (Task 9)

**Status tiket gak lagi FOP yang kontrol manual** (kecuali Cancel) — dia ngikutin status `Task` eksekusi teknisi terkait secara otomatis, lewat `TaskObserver`. Detail algoritma & tabel mapping lengkap di [flowchart.md § 9](flowchart.md#9-status-realtime--sync-task-eksekusi--foptask-task-9).

1. Kolom Status di tabel `/fop-tasks` sekarang badge read-only (bukan dropdown), teksnya SERAGAM di semua halaman (`/fop-tasks`, `/fop-tasks/history`, `/tasks-saya` — 1 sumber `TaskStatus::displayLabel()`, lihat `docs/project_status_label_unifikasi.md`): **"Draft"**, **"Terjadwal"**, **"Sedang Dikerjakan"**, **"Pending"**, **"Lapor Nanti"**, **"Selesai"**, **"Dibatalkan"**. Tiket standalone (belum ada teknisi di-assign, `status=draft`) dikasih label khusus **"Belum Ditugaskan"** biar gak nyesatin. Laporan yang lagi nunggu FOP review TETAP tampil **"Selesai"** polos (unifikasi 2026-07-20 — gak ada lagi demosi/label beda buat kondisi ini, nuansa "masih ditinjau" cuma ada di histori granular `selesai_menunggu_verifikasi`, bukan badge utama).
2. FOP tetap bisa **Cancel** tiket kapan aja (selama belum `Selesai`/`Dibatalkan`) lewat tombol kecil **"Cancel"** di bawah badge status — satu-satunya override manual yang masih ada. Begitu di-Cancel, sync otomatis dari `Task` eksekusi berhenti buat tiket itu (gak ke-overwrite lagi walau `Task` terkait berubah status belakangan). **Pengecualian (2026-07-21):** tombol ini TIDAK MUNCUL buat kategori **Survey** & **PSB** — batalin 2 kategori itu WAJIB lewat halaman Customer (tab Survey/Pemasangan, atau tombol "Batalkan" di `/surveys/queue`), biar `Customer.status` ikut ke-set `rejected` (masuk List Pelanggan Gagal). Lihat [flowchart.md § 12](flowchart.md#12-canceldibatalkan-srv--psb-terkunci-dari-taskfoptask-2026-07-21).
   - **Alasan wajib (Task 12, 2026-07-22):** klik "Cancel" buka MODAL (bukan langsung confirm-dialog polos) — textarea alasan wajib diisi (data ganda, salah input POP, dll), submit ditolak (422) kalau kosong. Tombol cuma muncul buat user dengan permission `fop_tasks.cancel` baru (role FOP/Admin otomatis dapet). Kalau task yang dibatalkan lagi `Sedang Dikerjakan` (ada teknisi in_progress), teknisi tsb dapet notifikasi in-app "Task dibatalkan: {alasan}". Detail: [flowchart.md § 13](flowchart.md#13-cancel-dengan-alasan--task_type-non-srvpsb-task-12-2026-07-22).
4. **"Pending" sekarang SATU logic** (2026-07-15) — teknisi klik tombol Pending top-level ATAU FOP klik "Set Pending" manual, dua-duanya SAMA PERSIS: tim dilepas, jadwal di-rebuild, tiket balik ke antrian nunggu di-assign ulang. `TaskStatus::RESCHEDULE` (enum terpisah, dulu cuma dipakai jalur teknisi) UDAH DIHAPUS. Tiket berstatus Pending gak bisa diedit langsung — harus di-assign ulang teknisi dulu.
5. Tiap transisi status (baik yang derived otomatis maupun Cancel manual) tercatat di tabel `fop_task_status_history` — ditampilin lengkap di halaman Detail Riwayat (Task 10, lihat bagian 4 di bawah).
6. **Tiket Survey/Pemasangan yang teknisinya udah selesai kerja** (`Task.status=selesai`) **LANGSUNG jadi "Selesai" dan pindah ke Riwayat**, badge TETAP "Selesai" POLOS — gak peduli admin udah/belum putus keputusan customer-nya, dan GAK ADA badge/teks verifikasi tambahan di UI Task FOP (draft sebelumnya sempet nambah badge kedua "Verifikasi: Menunggu/Diterima/Ditolak" — UDAH DICABUT 2026-07-15, dianggap bikin ambigu). Nasib keputusan customer cuma bisa dilihat di halaman Verif & Pemasangan (Customer module), bukan di Task FOP. Lihat bagian 4.

## 3. Team Harian — Otomatis (Task 1), Bukan Bikin Manual Lagi

**Berubah total.** Panel "Kelola Team" (bikin/edit/hapus Team manual) **sudah dihapus** dari `/fop-tasks`. FOP gak perlu (dan gak bisa lagi) bikin Team sebelum assign tiket — Team kebentuk sendiri berdasar siapa kerja bareng siapa hari itu.

### Aturan otomatis (ringkas — detail algoritma di [flowchart.md](flowchart.md#5-auto-team-formation-connected-components))

- Tiket dengan **>1 teknisi** → otomatis jadi/gabung ke 1 Team (nama auto `"Team {n}"`).
- Teknisi yang overlap ke beberapa tiket hari itu → tiket-tiketnya ke-gabung ke Team yang sama (dia jadi "jembatan").
- Tiket **solo (1 teknisi)**: kalau teknisinya udah ada di Team lain hari itu → otomatis ikut Team itu. Kalau belum pernah punya Team sama sekali → tetap `team_id = null`, muncul tombol kecil **"+ Masukkan ke Team..."** di kolom Team buat drop-in manual.
- Tiket yang narik 2 teknisi dari **2 Team beda** sekaligus → sistem GAK auto-gabung, muncul **modal "Konflik Team Terdeteksi"** minta FOP pilih: masuk Team A, Team B, atau bikin Team baru gabungan. Modal ini bisa dibuka ulang kapan aja lewat tombol "Konflik Team (n)" di header selama konfliknya belum diputuskan (gak ilang walau ke-close atau halaman di-refresh).

### Drop-in manual / resolve konflik

1. Klik "+ Masukkan ke Team..." (tiket solo) atau pilih Team dari modal konflik.
2. Sistem assign `team_id`, kunci pilihan itu (`manual_override_at`) supaya gak ke-timpa rebuild otomatis berikutnya — sampai teknisi tiket itu diganti lagi lewat edit biasa.
3. Kalau drop-in ini bikin teknisi keluar dari Team lamanya (dia masih nempel di tiket lain, Team beda, tanggal sama) → sistem otomatis cabut dia dari tiket lama itu, roster Team lama ke-refresh, title Task eksekusi tiket lama ikut ke-update.

### Switch Teknisi antar Team (Task 2) — cara cepat pindahin teknisi

1. Klik chip nama teknisi di kolom Teknisi (tabel `/fop-tasks`).
2. Modal muncul: pilih **Task Tujuan** (tiket lain di tanggal yang sama) + **Pengganti** di Task asal (boleh teknisi baru, boleh yang udah ada di Task asal).
3. Submit sekali → dalam 1 transaksi: teknisi pindah ke Task tujuan, pengganti masuk gantiin dia di Task asal, kedua Team ke-rebuild, notifikasi terkirim ke 2 teknisi, audit log tercatat.
4. Kalau pengganti gak dipilih/invalid, atau lagi `in_progress` di tiket lain, atau Task tujuan beda hari → ditolak, **gak ada perubahan sama sekali** (Task asal gak pernah kosong teknisi).

### Beban kerja per anggota

Dashboard `/fop` nampilin ringkasan beban kerja per anggota Team (jumlah tiket) — sama seperti sebelumnya, cuma sumber data Team-nya sekarang auto, bukan manual.

## 4. FOP lihat riwayat (`/fop-tasks/history`)

1. Buka `/fop-tasks/history` — tabel tiket urut update terbaru. Kolom **Status** nampilin label seragam (`TaskStatus::displayLabel()`, sama kayak `/fop-tasks` & `/tasks-saya`):
   - **Selesai** (badge hijau) — kerjaan lapangan teknisi kelar (buat Survey/Pemasangan, ini muncul begitu teknisi submit laporan, GAK NUNGGU admin approve/reject — lihat prinsip di bagian 2 poin 6). Badge ini TETAP "Selesai" polos walau customer-nya belakangan ditolak admin — **gak ada badge/kolom Verifikasi terpisah lagi** (sempet ada di draft sebelumnya, DICABUT 2026-07-15 karena bikin ambigu).
   - **Dibatalkan/Cancel** (badge merah) — dibatalkan (baik `Task.status=dibatalkan` maupun `FopTask` di-Cancel manual FOP). Alasan pembatalan (`cancel_reason`, wajib diisi sejak Task 12) tampil sebagai teks kecil di bawah badge, dan di Detail Riwayat sebagai "Alasan Cancel" (**bugfix 2026-07-22** — sebelumnya field ini SALAH baca `pending_reason`, plus badge match-nya sempet gak pernah nyala gara-gara kelewatan pas migrasi enum ke `TaskStatus` lowercase 2026-07-20).
2. Filter sama kayak halaman aktif (search/kategori/prioritas/desa/team) + dropdown **Status** (Selesai/Dibatalkan). Nasib keputusan customer (approved/pending/rejected) TETAP kesimpen (`fop_review_status`, `fop_task_status_history`) buat audit, tapi cek/putusinnya lewat halaman Verif & Pemasangan (Customer module) — bukan lewat filter di sini.
3. Klik tiket manapun → buka **Detail Riwayat** (`/fop-tasks/history/{id}`, Task 10): Info Task (termasuk Alasan Cancel kalau `dibatalkan`), **Detail Registrasi** (baru 2026-07-20 — data pelanggan CID/Nama/No HP/ODP/Paket/Alamat/Perangkat/Koordinat, khusus SRV/PSB/MTN-C-REQ native, sebelumnya section ini gak ada), Detail Ticket (khusus MTN/C-REQ asal Ticketing, snapshot data pelanggan dari `Ticket`), Durasi & SLA Pengerjaan (dual-cycle: mulai/pending/resume/selesai, status SLA on-time/over), Laporan (baca dari Survey/Instalasi/Maintenance sesuai kategori), dan **Histori Status** — log lengkap tiap perubahan status + siapa + kapan, audit trail utama.
4. Dipakai buat audit & laporan kinerja, bukan buat aksi lanjutan (read-only report) — keputusan approve/reject/revisi tetap di halaman Verif & Pemasangan (Customer module), bukan di sini.

## Guard / Permission per Aksi

| Aksi | Permission dibutuhkan |
|------|------------------------|
| Lihat dashboard `/fop` | Policy `viewAll` di `Task` |
| Lihat `/fop-tasks`, `/fop-tasks/history` | `fop_tasks.view` |
| Tambah tiket | `fop_tasks.create` |
| Edit tiket, drop-in Team manual (`assign-to-team`), Switch Teknisi (`switch-technician`) | `fop_tasks.update` |
| Edit kategori & prioritas tiket | `fop_tasks.update_sensitive` |
| Hapus tiket | `fop_tasks.delete` |

Owner / user `hasFullAccess()` selalu lolos guard tanpa cek permission granular (lihat `FopTaskController::authorizeAccess()`). Team gak lagi punya permission/endpoint CRUD sendiri (`fop-tasks.teams.*` udah dihapus) — semua Team dikelola implisit lewat permission tiket di atas.
