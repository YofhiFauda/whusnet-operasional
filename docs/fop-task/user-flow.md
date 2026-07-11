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
2. Tabel nampilin tiket status Proses/Pending, urut: **tiket dengan `client_request_date` di masa depan (besok+) selalu di bawah** (section "Upcoming", walau prioritasnya Urgent — lihat [flowchart.md § 8](flowchart.md#8-antrian-sorting-berdasarkan-client_request_date-task-8)), baru di antara sisanya urut prioritas (Urgent→Low) lalu tanggal. Begitu `client_request_date` udah sama dengan hari ini (gak perlu nunggu refresh manual/cron), tiketnya otomatis "naik" ikut sorting prioritas normal di request berikutnya.
3. Kolom "Tanggal" nampilin badge kalau tiket punya `client_request_date`: **"JADWAL HARI INI"** (merah, kalau tanggalnya hari ini/udah lewat) atau **"Terjadwal — {tanggal}"** (abu-abu, kalau masih di masa depan).
4. Filter tersedia: search (nomor/tugas/issue), kategori, status, prioritas, desa, team.

### Tambah tiket manual

1. Klik "Tambah Task" → modal muncul.
2. Pilih kategori (Survey & PSB **gak muncul** di dropdown — itu auto-sync only).
3. Isi tanggal, tugas, desa, POP, pelanggan (opsional), issue, catatan. **Gak ada lagi field pilih Team** — dropdown `team_id` manual udah dihapus dari modal (lihat bagian 3, Team sekarang otomatis).
4. Pilih status: Proses (default) atau Pending (wajib isi alasan + tanggal request client).
5. Pilih prioritas — **cuma muncul kalau user punya `fop_tasks.update_sensitive`**, selain itu default Low/otomatis.
6. Pilih 1+ teknisi (wajib) → submit.
7. Sistem: simpan `FopTask`, `technicians()->sync()`, auto-buat `Task` eksekusi teknisi (title polos dulu), link ke `fop_task.task_id`, lalu **auto-rebuild Team** untuk tanggal tiket itu (lihat bagian 3) — title Task eksekusi ke-update lagi begitu Team-nya kebentuk.

### Edit tiket

1. Klik tiket di tabel → modal edit terisi data existing.
2. User biasa cuma bisa ubah: tanggal, tugas, desa/POP/pelanggan, issue/catatan, status, teknisi.
3. User dengan `fop_tasks.update_sensitive` bisa juga ubah kategori & prioritas.
4. Ganti status ke Pending → wajib isi alasan; ke Selesai/Proses → field pending auto-clear.
5. Submit → update `FopTask`, sync teknisi, update/buat `Task` terkait, catat `AuditLog`, **auto-rebuild Team** untuk tanggal tiket (dan tanggal lama juga kalau tanggalnya diubah).

### Hapus tiket

1. Klik hapus (icon/tombol) → konfirmasi browser.
2. Sistem detach teknisi, hapus `FopTask`, catat `AuditLog`.

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

1. Buka `/fop-tasks/history` — tabel tiket status Selesai/Cancel, urut update terbaru.
2. Filter sama kayak halaman aktif (search/kategori/status/prioritas/desa/team).
3. Dipakai buat audit & laporan kinerja, bukan buat aksi lanjutan (read-only report).

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
