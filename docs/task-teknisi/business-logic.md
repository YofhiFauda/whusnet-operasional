# Business Logic — Task Teknisi

## 1. Status Task (`TaskStatus` enum)

| Status | Arti | `isEditable()` | `isActive()` |
|--------|------|-----------------|--------------|
| `draft` | Task dibuat tapi belum ada jadwal/tim | ✔ | |
| `terjadwal` | Ada jadwal + tim (auto saat create kalau keduanya terisi) | ✔ | ✔ |
| `in_progress` | Teknisi sudah tekan "Mulai" | | ✔ |
| `selesai` | Teknisi submit laporan lengkap | | |
| `pending` | Ditangguhkan (teknisi atau FOP) | ✔ | |
| `dibatalkan` | Dibatalkan FOP | | |
| `waiting_survey`/`waiting_installation(s)` | Legacy/label alternatif, jarang dipakai langsung di Task teknisi (lebih relevan di `Customer.status`) | ✔ | |

**Aturan penentuan status saat create** (`TaskService::create()`): kalau ada anggota tim **dan** `scheduled_at` terisi → langsung `terjadwal`. Kalau salah satu kosong → `pending`. Tidak pernah start dari `draft` di jalur normal (create dari FOP selalu isi keduanya).

## 2. Guard Konflik Jadwal — Aturan Paling Kritis

**1 teknisi cuma boleh punya 1 Task `in_progress` di waktu bersamaan.** Ini dicek independen di 3 tempat kode berbeda (bukan 1 fungsi terpusat, karena Task teknisi & proses Survey/Instalasi Customer punya entry point terpisah):

1. `TaskService::start()` — dicek saat generic task mau dimulai.
2. `CustomerSurveyController::start()` — dicek saat mulai survey (Task tipe Survey).
3. `CustomerInstallationController::start()` — dicek saat mulai pemasangan (Task tipe Pemasangan).

Ketiganya pakai logic sama: kumpulkan `user_id` anggota tim (+ diri sendiri), cari Task lain (`id != current`) berstatus `in_progress` yang salah satu anggotanya overlap → kalau ketemu, **tolak** dengan pesan yang nyebut nomor task yang lagi ngeblok.

**Konflik jadwal terjadwal** (beda dari konflik "sedang in_progress" di atas) dicek terpisah oleh `TaskService::detectConflicts()` — dipakai saat **create/edit/reassign** Task buat cegah 1 teknisi dijadwalkan di 2 Task yang jam kerjanya overlap (`scheduled_at` s/d `scheduled_at + sla_minutes`). Beda dari guard start-in_progress: ini soal jadwal masa depan, bukan status sekarang. FOP bisa **override** lewat checkbox `conflict_override` — butuh permission `task.conflict.override` (guard tambahan, override gak otomatis diizinkan ke semua yang bisa edit).

## 3. Transisi Status — 2 Model Guard Berbeda

`TaskPolicy` pakai **dua mekanisme guard sekaligus**, tergantung ability:

### A. Permission string statis
`statusPending`, `uploadEvidence`, `editType`, `assignTeam` — cek `$user->hasPermission('task.xxx')` biasa + kadang syarat status tertentu.

### B. `WorkflowTransitionPermission` dinamis (`canTransitionTo()`)
`schedule`, `cancel`, `fopPending`(sebagiannya), `review`, `statusStart`, `statusComplete` — cek 3 lapis:
1. Ada row `workflow_transition_permissions` yang cocok `from_status`→`to_status`?
2. Role user terhubung ke rule itu (`role_workflow_transition`)?
3. User juga punya permission string yang disebut di `rule.permission_name`?

**Kalau salah satu gagal → ability ditolak**, walau user py permission string yang "kelihatannya" cocok. Ini beda banget dari fitur lain di sistem (kebanyakan cukup 1 permission check) — didesain supaya transisi status kritikal (start/complete/cancel/review) bisa diatur granular per Role tanpa ubah kode, lihat [docs/rbac/business-logic.md §7](../rbac/business-logic.md#7-workflow-transition-permission).

**Fallback tambahan** di `statusStart`/`statusComplete`: kalau `canTransitionTo()` gagal, masih ada jalur alternatif — misal task tipe `MAINTENANCE` atau task tipe Survey/Pemasangan yang lagi `pending` tetap bisa distart/dicomplete asal user permission generik (`task.status.start`) dan `isMember()` true. Jadi **jangan asumsikan satu jalur guard tunggal** — cek kedua kondisi kalau debug masalah akses.

## 4. Batasan Tim (1–3 Teknisi)

- Validasi form edit: `team_member_ids` max 3 (`TaskController::update()`).
- Anggota pertama otomatis `role_in_task=lead`, sisanya `teknisi` (`TaskTeam` pivot) — ditentukan urutan array, bukan pilihan eksplisit user.
- **Ganti anggota tim biasa** (`TaskController::update()` dengan `team_member_ids` baru) — hapus semua `TaskTeam` lama, insert ulang total. **Reassign 1 orang** (`TaskTeamController::update()` via `TaskService::reassignTeam()`) — beda mekanisme, update `user_id` di 1 row pivot tanpa hapus-insert semua, plus cek konflik dan kirim notifikasi personal ke yang lama & baru.

## 5. Syarat Complete (Evidence)

- `Task::canComplete()` saat ini **selalu return `true`** — placeholder, belum ada validasi hard-requirement jumlah evidence minimum di level model (komentar di code/dokumentasi lama menyebut "checklist wajib + min 1 bukti" tapi implementasi aktual permisif).
- `TaskEvidenceController::store()` cuma nyimpen foto — gak ada blocking logic yang connect ke `canComplete()`. Jangan asumsikan sistem menolak complete tanpa evidence — saat ini tidak.

## 6. Efek Complete → Review FOP → Transisi Customer

```
Task complete (fop_review_status=pending)
        │
        ▼
FOP notify (semua user role fop yang scope POP-nya cocok)
        │
        ▼
FOP review (approve/reject/pending) via TaskController::review()
        │
        ├─ approve:
        │    fop_review_status=approved
        │    task_type=SURVEY      → Customer transition ke WAITING_INSTALLATION
        │    task_type=PEMASANGAN  → Customer transition ke ACTIVE      ⚠️ (lihat catatan)
        │
        ├─ reject:
        │    status kembali IN_PROGRESS, fop_review_status=rejected
        │    task_type=SURVEY      → Customer transition ke SURVEY_IN_PROGRESS
        │    task_type=PEMASANGAN  → Customer transition ke INSTALLATION_IN_PROGRESS
        │
        └─ pending:
             status=PENDING, fop_review_status=pending (reset)
```

**✅ Fixed (2026-07-07):** `TaskController::review()` approve untuk `task_type === PEMASANGAN` sekarang **ditolak** — satu-satunya jalur aktivasi resmi adalah `CustomerVerificationController::finalVerify()` (`/verifications/{customer}/admin`, lihat [docs/customer-lifecycle](../customer-lifecycle/README.md)), yang generate Invoice AWAL + CID sekaligus. Detail bug lama & perbaikannya ada di [bug.md](bug.md).

**Klarifikasi (2026-07-14, fix reject-sync gap):** blok `reject:` di diagram atas ITU BUKAN jalur yang sama dengan `CustomerVerificationController::reject()` di Customer module. Diagram di atas = FOP nolak KUALITAS LAPORAN (foto kurang jelas dst) → `Task.status` balik `in_progress`, teknisi disuruh kerjain ulang, customer balik ke tahap in-progress, `FopTask` ikut mirror balik ke `in_progress` (unifikasi enum 2026-07-20 — dulu istilahnya `Proses`/"Perlu Review", enum `FopTaskStatus` itu sekarang udah dihapus). Reject di Customer module = admin nolak CUSTOMER-nya (gak eligible/belum bayar) → `Task.status` TETAP `selesai`, `fop_review_status=rejected`, terminal (`FopTask` TETAP `selesai` — kerjaan lapangan sukses, cuma dapet badge kedua "Verifikasi: Ditolak", gak ada jalur balik). Dua-duanya sama-sama nulis `fop_review_status=rejected` tapi efek ke `Task.status` & ke `FopTask` beda total — jangan disamain pas baca kode/log. Detail: `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL).

## 7. Maintenance Report — Jalur Khusus

- Task tipe `MAINTENANCE` (dan tipe non-Survey/Pemasangan lain) pakai form laporan sendiri (`TaskMaintenanceController`), bukan form Survey/Instalasi.
- Guard eksplisit menolak akses form ini kalau `task_type` Survey/Pemasangan — 2 form gak boleh dipakai silang.
- Submit laporan maintenance **langsung** panggil `TaskService::complete()` di akhir — 1 submit = simpan laporan + selesaikan task sekaligus (beda dari Survey/Instalasi yang punya form "lapor" terpisah dari transisi status, laporan maintenance gak py status draft/progress).

## 8. Audit

- `Task` — trait `RecordsAuditLogs`, module `Task Management`, event `created`/`updated`/`deleted` (otomatis dari Eloquent events) **plus** manual `AuditLog::log()` di titik-titik kunci (`created`, `completed`, `cancelled`, `approved`, `rejected`, `reassigned`) — jadi ada kemungkinan create Task tercatat 2x (event otomatis + manual call di `TaskService::create()`), perlu diperhatikan kalau baca riwayat audit.
