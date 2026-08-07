# User Flow — Task Teknisi

Aktor: **Teknisi** (kerjakan task), **FOP** (kelola/review task).

## 1. Teknisi — Dashboard Sendiri (`/tasks-saya`)

1. Buka `/tasks-saya` → lihat task yang dia jadi anggota tim: task hari ini, task `in_progress`/`pending` (kapan pun), atau task `terjadwal` yang udah lewat hari-H (overdue, gak boleh hilang dari list).
2. Section "Upcoming" — max 5 task `terjadwal` di masa depan (bukan hari ini).
3. Dashboard auto-update lewat Echo (WebSocket) — card task di-refresh partial tanpa reload penuh saat ada perubahan status.
4. Klik ikon jam di header → **Riwayat Task Saya** (`/tasks-saya/riwayat`, 2026-08-06) — arsip task berstatus `selesai` milik teknisi ini, lintas waktu (beda dari dashboard utama yang cuma nampilin task hari ini/aktif). Tiap kartu klik langsung ke Detail Task.

## 2. Teknisi — Kerjakan Task

1. Klik task → buka detail (`/tasks/{task}`) — info customer, alamat, riwayat maintenance sebelumnya (kalau ada, max 3 terakhir untuk customer yang sama).
2. Klik "Mulai" → sistem cek dulu gak ada task lain yang lagi `in_progress` di tim ini, cek juga gak mulai sebelum hari-H jadwal. Kalau lolos, timer mulai jalan.
3. Selesai kerja — **tombol "Selesai" mengarahkan ke form Laporan** (bukan upload foto bebas terpisah lagi — fitur "Foto Bukti" generik dihapus 2026-08-06, foto wajib sudah jadi bagian form laporan):
   - Task tipe **Survey/Pemasangan** → isi form laporan khusus (lihat [docs/customer-lifecycle/user-flow.md](../customer-lifecycle/user-flow.md)), bukan lewat tombol "Selesai" biasa.
   - Task tipe **Maintenance/lainnya** → isi form laporan maintenance (`/tasks/{task}/maintenance-report`): kendala teknis, material terpakai, foto OPM + speedtest wajib. Submit langsung menyelesaikan task.
   - Tombol "Kembali" di form laporan + redirect setelah submit sukses sekarang **ikut halaman asal** (`return_to`) — dari Detail Task balik ke Detail Task, dari Dashboard Task Saya balik ke situ juga (2026-08-06, sebelumnya hardcoded selalu ke Antrean Survey/Verifikasi Queue).
4. Setelah selesai, isi laporan (kendala teknis, material terpakai, foto) **tampil balik** di Detail Task lewat blok "Laporan Pekerjaan Teknisi" — untuk task Maintenance/lainnya. Task Survey/Pemasangan punya halaman laporan lengkap sendiri, diakses lewat link "Lihat/Lanjutkan Laporan".
5. Kalau kerjaan gak bisa lanjut (misal alat kurang, customer gak di rumah) → klik "Pending", isi alasan. Task masuk antrean pending, timer survey/instalasi (kalau ada) otomatis ditutup.

## 3. FOP — Kelola Task

1. Lihat semua task (via FOP Dashboard `/fop` atau list task) — bukan cuma task sendiri.
2. **Edit task** — ubah judul/deskripsi/jadwal/tim (max 3 teknisi). Ubah jadwal → sistem cek konflik jadwal teknisi, tampilkan detail bentrokan (nama teknisi + jam task yang nabrak), FOP bisa centang "Override konflik" (butuh permission khusus) buat tetap lanjut.
3. **Ubah tipe task** — permission terpisah dari edit biasa (`task.edit.type`), gak semua yang bisa edit otomatis bisa ubah tipe.
4. **Reassign 1 teknisi** — tanpa buka form edit penuh, langsung ganti 1 orang di tim (cek konflik juga), teknisi lama & baru dapat notifikasi otomatis.
5. **Cancel task** — isi alasan, task jadi final `dibatalkan` (gak bisa diapa-apain lagi).

## 4. FOP — Review Laporan Task Selesai

1. Task yang teknisi tandai selesai masuk antrean review FOP (notifikasi in-app otomatis terkirim ke FOP yang scope POP-nya cocok).
2. Buka detail task, pilih:
   - **Approve** — laporan diterima. Untuk task tipe Survey, ini juga memicu transisi status Customer. Untuk tipe **Pemasangan, tombol ini gak tersedia** — sistem arahkan ke `/verifications/{customer}/admin`, satu-satunya jalur resmi aktivasi (lihat [bug.md](bug.md) untuk riwayat kenapa).
   - **Reject** — isi alasan, task balik ke `in_progress` (teknisi harus perbaiki & submit ulang), status Customer terkait juga direvert.
   - **Pending** — isi alasan, task balik ke antrean pending.
3. Selain lewat `review()`, FOP juga bisa langsung **Reject** task yang masih `pending` (belum sempat dikerjakan) atau **Pending**-kan task yang `terjadwal`/`in_progress` — 2 aksi terpisah (`fop-reject`, `fop-pending`) dari review laporan biasa.

## 5. Utility — Pencarian & Cek Konflik (API)

- `GET /api/tasks/search-customers?q=...` — autocomplete cari pelanggan by nama/CID/kode saat FOP mau kaitkan task ke pelanggan tertentu.
- `POST /api/tasks/check-conflict` — cek konflik jadwal sebelum submit form (dipakai JS form edit task realtime, sebelum submit beneran).

## Guard / Permission Ringkas

| Aksi | Siapa |
|------|-------|
| Lihat task sendiri | Teknisi, `task.view.own` |
| Lihat semua task | FOP/Admin, `task.view.all` |
| Mulai/Selesai/Pending task | Anggota tim task itu SAJA (`isMember()`), + permission/transition rule terpenuhi |
| Lihat riwayat task selesai sendiri (`/tasks-saya/riwayat`) | Teknisi, `task.view.own` |
| Edit/Cancel/Review/Reassign | FOP, sesuai kombinasi permission + `WorkflowTransitionPermission` (lihat [docs/rbac](../rbac/README.md)) |

## Gotcha Penting

- **1 teknisi = 1 task in_progress** — kalau mentok "tidak dapat memulai task", cek dulu apakah ada task lain (termasuk Survey/Instalasi customer) yang masih jalan di tim yang sama.
- **`canComplete()` gak strict di level Task** — model-nya gak nge-block apa-apa. Foto & data wajib dicek di level form Laporan masing-masing tipe (Survey/Pemasangan/Maintenance), bukan di tombol "Selesai" generik.
- **2 form "selesai" berbeda** — Survey/Pemasangan lewat form laporan customer, tipe lain lewat form maintenance/tombol "Selesai" biasa. Salah pilih form bakal ditolak sistem.
- **Foto Bukti generik (`TaskEvidence`) sudah gak ada** (2026-08-06) — kalau nemu referensi lama ke upload foto bebas di luar form Laporan, itu fitur yang sudah dihapus, jangan diasumsikan masih jalan.
