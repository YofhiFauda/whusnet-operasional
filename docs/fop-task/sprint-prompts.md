# Prompt Sprint Task 1-14 — FOP Auto-Team

Base doc: `docs/fop-task/analisa-auto-team.md`. Copy-paste 1 prompt per sprint, jangan gabung beberapa Task sekaligus.

---

## Aturan umum (berlaku di semua prompt di bawah)

```
Sebelum mulai, baca ulang bagian "Kondisi kode nyata" di Task ini — kalau ada
line number/method/file yang sudah berubah dari yang tertulis, STOP dan laporkan,
jangan improvisasi asumsi baru. Ikuti Checklist sebagai daftar wajib. Setelah selesai,
cross-check tiap Acceptance Criteria benar-benar terpenuhi sebelum lapor selesai.
Jangan sentuh file di luar "File yang dibuat/dirubah". Jangan kerjakan Task lain
meski kelihatan terkait — kalau nemu prasyarat dari Task lain, tandai sebagai
catatan blocker, jangan otomatis dikerjakan. Jangan refactor/nambah fitur di luar
scope Task ini.
```

---

### Task 1
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 1, SOLUSI poin 1,
Sprint Backlog Task 1 (Auto-Team Formation Engine).

Implementasikan Task 1: FopTaskTeamService::rebuildTeamsForDate() dengan algoritma
connected components (Skenario A/B/C1/C2/C3), migrasi kolom manual_override_at,
HAPUS method teamStore()/teamUpdate()/teamDestroy() di FopTaskController + 3 route
fop-tasks.teams.store/update/destroy + panel "Kelola Team Harian" di index.blade.php,
tambah endpoint assign-to-team buat drop-in manual, index database di kolom yang
dipakai query graf.

[Aturan umum di atas]
```

---

### Task 2
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 2, SOLUSI poin 2,
Sprint Backlog Task 2 (Switch Teknisi antar Team). Task 1 harus sudah selesai
duluan (dependency) — konfirmasi FopTaskTeamService::rebuildTeamsForDate() sudah ada.

Implementasikan Task 2: endpoint switchTechnician() atomic (1 payload, DB transaction,
sync pivot fop_task_user di 2 task sekaligus), reuse conflict-check dari
TaskService.php (bukan bikin conflict-check sendiri), validasi switch cuma intra-hari,
trigger rebuild di 2 tanggal.

[Aturan umum di atas]
```

---

### Task 3
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 3, SOLUSI poin 3,
Sprint Backlog Task 3 (Switch Task antar Team / Drag & Drop Dashboard).
Task 1 & Task 2 harus sudah selesai duluan.

Implementasikan Task 3: method switchTeam() baru di FopDashboardController (yang
saat ini 100% read-only), drag-drop UI di fop/dashboard.blade.php, guard task
in_progress/Selesai/Cancel gak bisa di-drag, modal validasi pilih teknisi tujuan
sebelum commit, trigger rebuild setelah commit.

[Aturan umum di atas]
```

---

### Task 4
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 4, SOLUSI poin 4,
Sprint Backlog Task 4 (Penanggung Jawab per Team / PIC). Task 1 harus sudah selesai.

Implementasikan Task 4: migrasi pic_id di fop_task_teams (BUKAN di fop_tasks),
method setPic() baru + route baru, permission fop_tasks.update (konvensi underscore,
bukan hyphen), guard PIC harus anggota roster aktif, handler rebuild reset pic_id
kalau PIC ter-switch keluar team + badge "Perlu Pilih PIC".

[Aturan umum di atas]
```

---

### Task 5
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 5, SOLUSI poin 5,
Sprint Backlog Task 5 (Excel-Like Inline Assignment). Task 1 harus sudah selesai.

PENTING: Status & Priority SUDAH inline-editable (index.blade.php baris ~172, ~197,
updateStatus()/updatePriority()) — jangan bangun ulang, cukup terapkan pola yang
sama ke kolom Teknisi/PIC. Jangan sentuh kolom status (itu bagian Task 9, akan
dihapus di sana — koordinasikan urutan, jangan duplikasi kerja).

Implementasikan Task 5: kolom Teknisi jadi inline-editable reuse pola
updateStatus/updatePriority, optimistic UI + rollback kalau gagal.

[Aturan umum di atas]
```

---

### Task 6
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 6, SOLUSI poin 6&7 (Dialog
bagian Task 6), Sprint Backlog Task 6 (Dialog Lapor Sekarang/Lapor Nanti).

PENTING: modal "pending-task" existing di tasks/show.blade.php (baris ~975-1002)
dan TaskController::pending() (baris 314-335) SUDAH punya perilaku yang benar
buat "Lapor Nanti" (status berubah, assignment TIDAK lepas) — REUSE, jangan bikin
handler baru dari nol. Yang baru cuma: dialog 2-pilihan sebelum masuk modal itu,
kolom report_deferred buat pembeda dari Task 7, dan intercept tombol "Isi Laporan"
di tasks/own.blade.php (baris ~242-264) yang SAAT INI bypass langsung ke form
laporan — itu juga harus lewat dialog ini (cross-reference Task 11).

[Aturan umum di atas]
```

---

### Task 7
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 7, SOLUSI poin 6&7 (bagian
Task 7), Sprint Backlog Task 7 (Tombol Pending Top-Level / Reschedule Penuh).

PENTING: ini FITUR BARU TOTAL, BUKAN rename tombol lama. Existing "Set Pending"
(tasks/show.blade.php baris ~815-819, fopPending permission) itu FOP-triggered dan
eksplisit TIDAK lepas assignment — jangan disentuh/direuse, itu mekanisme beda.
Jangan reuse TaskStatus::PENDING existing juga (sudah dipakai 2 arti beda: fopPending
dan Lapor Nanti Task 6) — tambah case enum baru RESCHEDULE.

Implementasikan Task 7: enum TaskStatus::RESCHEDULE baru, tombol Pending baru
di top-level Detail Task (terpisah dari tombol Laporan Task 6 dan dari "Set Pending"
FOP-side), method reschedule() baru + route baru + ability policy baru
statusReschedule, lepas pivot teknisi, trigger rebuild team.

[Aturan umum di atas]
```

---

### Task 8
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 8, SOLUSI poin 8,
Sprint Backlog Task 8 (Antrian Sorting client_request_date).

FopTaskController::index() baris 45-52 SUDAH punya orderByRaw (priority + category
Survey/PSB) — TAMBAH 1 CASE baru buat client_request_date, JANGAN hapus 2 CASE
yang sudah ada.

Implementasikan Task 8: CASE tambahan client_request_date vs CURDATE(), badge
"JADWAL HARI INI" / "Terjadwal — {tanggal}", regression test buat sorting lama.

[Aturan umum di atas]
```

---

### Task 9
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 9, SOLUSI poin 9,
Sprint Backlog Task 9 (Status Realtime). Task 6 & Task 7 harus sudah selesai
(butuh enum report_deferred dan RESCHEDULE sudah ada).

Ada 2 dropdown status manual yang harus dihapus: index.blade.php baris ~172
(updateStatus(), inline select) dan baris ~417 (select di modal create/edit).

Implementasikan Task 9: TaskObserver sync Task.status+report_deferred ke
FopTask.status, tabel fop_task_status_history baru, hapus 2 dropdown di atas
ganti badge read-only + tombol approve/reject/cancel eksplisit.

[Aturan umum di atas]
```

---

### Task 10
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 10, SOLUSI poin 10,
Sprint Backlog Task 10 (Riwayat + SLA Dual-Cycle). Task 6, 7, 9 harus sudah selesai.

PRASYARAT WAJIB duluan: SlaTimelineController.php baris 37 query PackageSlaSetting
TIDAK filter task_type — fix ini dulu sebelum lanjut ke task_reports, atau SLA
target yang keambil bisa salah tipe. Task::actualDurationMinutes()/isOverSla()
existing itu single-cycle — jangan reuse langsung buat dual-cycle, itu perlu logic baru.

Implementasikan Task 10: fix filter task_type di SlaTimelineController, tabel
task_reports baru (siklus started_at/pending_at/resumed_at/completed_at), model
TaskReport dengan akumulasi durasi multi-siklus, tambah kolom SLA/tools di
history.blade.php (saat ini belum ada kolom itu sama sekali).

[Aturan umum di atas]
```

---

### Task 11
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 11, SOLUSI poin 11,
Sprint Backlog Task 11 (/tasks-saya Tombol Bertahap).

PENTING — SEBAGIAN BESAR SUDAH ADA, JANGAN BANGUN ULANG: tombol Mulai Survey/
Mulai Pemasangan/Mulai Maintenance (own.blade.php baris 205-239), tombol Isi
Laporan (242-264), dan started_at timer SUDAH benar. Gap NYATA cuma 2: (1) info
pelanggan + link "Buka Detail" (baris 128-135, 200-203) tampil UNCONDITIONAL,
harus di-gate sampai status berubah dari terjadwal; (2) "Isi Laporan" harus
diarahkan ke dialog Task 6, bukan link langsung ke form laporan seperti sekarang.

Implementasikan Task 11: gate info pelanggan + Buka Detail di own.blade.php DAN
own-card.blade.php (partial AJAX, jangan lupa sinkron keduanya), sambungkan Isi
Laporan ke dialog Task 6.

[Aturan umum di atas]
```

---

### Task 12
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 12, SOLUSI poin 12,
Sprint Backlog Task 12 (Cancel dengan Alasan).

Permission baru pakai konvensi underscore existing: fop_tasks.cancel (BUKAN
fop-task.cancel hyphen) di config/rbac.php.

Implementasikan Task 12: kolom cancel_reason di fop_tasks, validasi
required_if:status,Cancel, permission fop_tasks.cancel baru (bukan hardcode role
FOP), sync cancel ke Task eksekusi + notif teknisi kalau in_progress, trigger
rebuildTeamsForDate() (cancel bisa pecah team kalau task itu jembatan satu-satunya).

[Aturan umum di atas]
```

---

### Task 13
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 13, SOLUSI poin 13,
Sprint Backlog Task 13 (Konsolidasi Sorting, cakupan minimal). Task 8 harus
sudah selesai duluan.

Sudah dikonfirmasi: tidak ada implementasi sorting lain di luar Task 8
(history() pakai orderBy updated_at, beda konteks, jangan disentuh).

Implementasikan Task 13: cukup tambah test end-to-end dari alur Survey selesai
→ client_request_date terisi → masuk section bawah → naik ke atas pas jadwalnya
tiba. JANGAN bikin implementasi sorting baru/kedua — pastikan cuma 1 query
sorting (dari Task 8) yang jalan.

[Aturan umum di atas]
```

---

### Task 14
```
Baca docs/fop-task/analisa-auto-team.md — Kebutuhan poin 14 & 15, SOLUSI poin 14,
Sprint Backlog Task 14 (Lock Survey/Pemasangan + Auto-fill POP/Area).

PENTING — SCOPE LEBIH SEMPIT DARI KELIHATANNYA: store() dan dropdown create SUDAH
terguard benar (TaskType::manualValues(), manualCategoriesData) — JANGAN sentuh
itu kecuali buat regression test. Gap NYATA cuma di EDIT: dropdown edit pakai
allCategoriesData (semua tipe termasuk Survey/PSB), gate cuma permission-based
(canEditFopTaskType) TANPA cek tipe record existing, dan update() pakai
Rule::enum(TaskType::class) yang lebih longgar dari store().

Implementasikan Task 14: (1) getter availableCategories di index.blade.php —
force-disable kalau record existing SURVEY/PSB, bukan cuma switch daftar opsi;
(2) update() tolak 422 perubahan category/customer_id/pop_id/village_id kalau
existing record SURVEY/PSB, terlepas dari permission; (3) ganti Rule::enum jadi
Rule::in(manualValues()) di update() buat kasus non-Survey/Pemasangan; (4) fix
bug selectCustomer() yang belum copy pop_id/village_id ke form.

[Aturan umum di atas]
```
