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
2. Tabel nampilin tiket status Proses/Pending, urut prioritas (Urgent→Low) lalu tanggal.
3. Filter tersedia: search (nomor/tugas/issue), kategori, status, prioritas, desa, team.

### Tambah tiket manual

1. Klik "Tambah Task" → modal muncul.
2. Pilih kategori (Survey & PSB **gak muncul** di dropdown — itu auto-sync only).
3. Isi tanggal, tugas, desa, POP, pelanggan (opsional), team (opsional), issue, catatan.
4. Pilih status: Proses (default) atau Pending (wajib isi alasan + tanggal request client).
5. Pilih prioritas — **cuma muncul kalau user punya `fop_tasks.update_sensitive`**, selain itu default Low/otomatis.
6. Pilih 1+ teknisi (wajib) → submit.
7. Sistem: simpan `FopTask`, `technicians()->sync()`, auto-buat `Task` eksekusi teknisi, link ke `fop_task.task_id`.

### Edit tiket

1. Klik tiket di tabel → modal edit terisi data existing.
2. User biasa cuma bisa ubah: tanggal, tugas, desa/POP/pelanggan/team, issue/catatan, status, teknisi.
3. User dengan `fop_tasks.update_sensitive` bisa juga ubah kategori & prioritas.
4. Ganti status ke Pending → wajib isi alasan; ke Selesai/Proses → field pending auto-clear.
5. Submit → update `FopTask`, sync teknisi, update/buat `Task` terkait, catat `AuditLog`.

### Hapus tiket

1. Klik hapus (icon/tombol) → konfirmasi browser.
2. Sistem detach teknisi, hapus `FopTask`, catat `AuditLog`.

## 3. FOP kelola Team harian

### Bikin Team baru

1. Buka panel "Kelola Team" di `/fop-tasks`.
2. Isi nama (opsional, default "Tim dd/mm"), tanggal kerja, pilih anggota teknisi.
3. Submit → sistem cek konflik: kalau ada teknisi yang udah masuk team aktif lain di tanggal sama → **ditolak**, muncul pesan konflik per nama.
4. Kalau lolos → Team dibuat, siap dipakai buat assign tiket.

### Assign tiket ke anggota Team

- Waktu create/edit tiket, pilih `team_id` dari dropdown (opsional) — lalu tetap pilih teknisi manual per tiket (sistem gak auto-bagi rata).
- Dashboard `/fop` nampilin ringkasan beban kerja per anggota (jumlah tiket).

### Edit / Hapus Team

1. Edit: ubah nama dan/atau roster anggota — cek konflik ulang kalau roster diubah. Tiket yang udah ke-assign ke anggota lama **gak berubah** PIC-nya.
2. Hapus: detach semua anggota, hapus Team — tiket yang masih nempel `team_id`-nya jadi `null` (FK `set null`), gak ikut kehapus.

## 4. FOP lihat riwayat (`/fop-tasks/history`)

1. Buka `/fop-tasks/history` — tabel tiket status Selesai/Cancel, urut update terbaru.
2. Filter sama kayak halaman aktif (search/kategori/status/prioritas/desa/team).
3. Dipakai buat audit & laporan kinerja, bukan buat aksi lanjutan (read-only report).

## Guard / Permission per Aksi

| Aksi | Permission dibutuhkan |
|------|------------------------|
| Lihat dashboard `/fop` | Policy `viewAll` di `Task` |
| Lihat `/fop-tasks`, `/fop-tasks/history` | `fop_tasks.view` |
| Tambah tiket/Team | `fop_tasks.create` |
| Edit tiket/Team | `fop_tasks.update` |
| Edit kategori & prioritas tiket | `fop_tasks.update_sensitive` |
| Hapus tiket/Team | `fop_tasks.delete` |

Owner / user `hasFullAccess()` selalu lolos guard tanpa cek permission granular (lihat `FopTaskController::authorizeAccess()`).
