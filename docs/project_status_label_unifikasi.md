# Gap: Label Status Gak Seragam di Task FOP, Riwayat, dan /tasks-saya

Status: **DIANALISA 2026-07-15, revisi ke-2 setelah koreksi arah — solusi dirancang, BELUM diimplementasi.**

> **⚠️ SUPERSEDED sebagian (2026-07-20) — § C di bawah UDAH GAK BERLAKU.** Doc ini nolak nyampur `FopTaskStatus` bucket sama label Task, TAPI tetap mempertahankan `FopTaskStatus` (4 nilai: Proses/Pending/Selesai/Cancel) sebagai enum TERPISAH buat query concern (§ C: "TETAP kayak sekarang"). Keputusan itu DIBALIK atas permintaan eksplisit user — `App\Enums\FopTaskStatus` **DIHAPUS TOTAL**, `fop_tasks.status` sekarang share vocab persis `TaskStatus` (mirror langsung, gak ada bucket lagi sama sekali, termasuk buat task_type MTN/dkk yang di § C poin ke-2 disebut "TETAP bucket Proses saat selesai+belum-direview" — itu juga udah gak berlaku, sekarang mirror `selesai` polos). Prinsip "Task vs proses bisnis lain gak dicampur" di § label TETAP berlaku (nuansa granular tetap di histori/badge overlay, bukan status utama) — yang berubah CUMA bagian data/query (`FopTaskStatus` sebagai enum terpisah), bukan prinsip displaynya. Detail lengkap & akurat: `docs/fop-task/database-schema.md` § Observer: TaskObserver, `docs/fop-task/flowchart.md` § 9 & § 12.

## Koreksi dari analisa pertama (kenapa versi awal salah arah)

Draft pertama bikin "1 kamus 11 label" yang nyelipin `Menunggu Verifikasi`/`Ditolak Verifikasi`/`Perlu Review` sebagai VARIAN dari status Task. Itu **ngelanggar prinsip yang udah ditetapin di `project_verifikasi_reject_gap.md`**: Task (kerjaan lapangan teknisi) dan alur pelanggan (verifikasi/keputusan bisnis) itu 2 hal beda, sengaja gak dicampur. Nyelipin status verifikasi ke dalam vocabulary status Task — walaupun cuma di teks label, bukan di kolom `status` mentah — tetep ngelanggar prinsip itu, dan bikin maintenance ke depan rumit: tiap kali ada proses bisnis baru (verifikasi, review, approval, dst), orang harus inget nambahin cabang baru di "kamus status Task". Itu bukan status Task, itu status PROSES LAIN yang numpang nampil di badge Task — sumber kerumitan yang bakal numpuk.

**Prinsip yang ditegasin ulang:** Task itu representasi "penugasan teknisi buat 1 pekerjaan (pelanggan/request tertentu)". Selesai dari sisi Task = teknisi udah kelar kerja lapangan, TITIK. Task gak perlu "nunggu" apapun dari proses lain (verifikasi admin, review FOP, dst) buat nyatain dirinya beres — proses lain itu jalan di dunia/modul mereka sendiri (Customer module), gak numpang di status Task.

## Solusi yang benar: vocabulary Task murni, tetap (fixed), gak ada cabang

### A. Status Task = 6 label TETAP, gak nambah, gak berubah tergantung proses lain

Sumber: `TaskStatus` enum (`app/Enums/TaskStatus.php`) + kolom `report_deferred` (milik tabel `tasks` sendiri, bukan nyerempet proses/modul lain).

**Ketetapan final (dikonfirmasi user, 2026-07-15 — bukan cuma teks, PERILAKUNYA disamain juga):**

`TaskStatus::RESCHEDULE` **DIHAPUS TOTAL sebagai enum case terpisah**. Bukan cuma dileburin di teks — `reschedule` dan `pending` sekarang JADI SATU logic yang sama: **"Pending" = tim dilepas dari task, `FopTask.team_id`/`manual_override_at` dikosongin, `rebuildTeamsForDate()` ke-trigger, task balik ke antrian Task FOP nunggu di-assign ulang.** Siapapun yang men-trigger (teknisi lewat tombol Pending top-level, ATAU FOP lewat aksi Set Pending manual) — efeknya SAMA PERSIS, gak ada lagi 2 kelakuan beda buat 1 nama status.

**Kenapa ini keputusan yang benar (bukan cuma nurut, ini emang lebih simpel):** sebelumnya ada 2 enum (`reschedule`/`pending`) ⇒ 2 fungsi controller beda (`TaskController::reschedule()` lepas tim+rebuild, `TaskController::pending()`/`fopPending` cuma ganti status doang) ⇒ 2 kelakuan beda buat 1 kata "Pending" yang keliatan sama di layar. Itu jebakan maintenance: siapapun baca "task ini Pending" gak bisa nebak dari status doang apa tim-nya masih nempel atau udah lepas — harus buka kode buat tau SIAPA yang nge-pending-in. Sekarang: liat status "Pending" = PASTI tim udah lepas, PASTI ada di antrian nunggu di-assign ulang. Gak ada assumption ganda.

**`lapor_nanti` (Task.status=`pending` + `report_deferred=true`) TETAP kondisi tersendiri, TIDAK ikut jadi "Pending".** Alasan: perilakunya beneran beda — Lapor Nanti = kerjaan lapangan UDAH KELAR, laporan aja yang ditunda, task TETAP di teknisi yang sama (TIM TIDAK DILEPAS, gak balik ke antrian). Ini satu-satunya sub-kondisi yang emang harus beda kata, karena beneran beda kejadian di lapangan (tim lepas vs tim tetap).

| `TaskStatus` value | Kondisi tambahan | Label |
|---|---|---|
| `draft` | | Draft |
| `terjadwal` | | Terjadwal |
| `in_progress` | | Sedang Dikerjakan |
| `pending` | `report_deferred=false` | **Pending** — tim dilepas, balik ke antrian (perilaku `reschedule` lama, sekarang jadi SATU-SATUNYA perilaku "pending") |
| `pending` | `report_deferred=true` | **Lapor Nanti** — tim TETAP nempel, kerjaan udah kelar |
| `selesai` | | **Selesai** — SELALU, gak peduli `fop_review_status` apa, gak peduli `task_type` apa. Titik. |
| `dibatalkan` | | Dibatalkan |

**Total 6 kata unik yang bakal muncul di layar:** Draft, Terjadwal, Sedang Dikerjakan, Pending, Lapor Nanti, Selesai, Dibatalkan *(7 kata, 6 status konsep — Lapor Nanti itu sub-kasus Pending secara data tapi kata sendiri).*

**Gak ada lagi:** "Reschedule" (enum case-nya sendiri DIHAPUS, bukan cuma teksnya diganti), "Perlu Review", "Menunggu Verifikasi", "Ditolak Verifikasi", "Selesai — apapun".

**Editability (keputusan dikonfirmasi):** task berstatus `pending` (tim udah lepas) **TIDAK BISA diedit langsung** — harus di-assign ulang teknisi dulu (balik ke `terjadwal`/`in_progress`) baru bisa diedit. Konsisten sama semantik baru: pending = slot kosong nunggu di-assign, bukan task yang lagi "dipegang" siapa-siapa buat diutak-atik. `TaskStatus::isEditable()` disesuaikan: `PENDING` **dikeluarin** dari daftar editable (nyusul `RESCHEDULE` lama yang emang udah gak editable) — `report_deferred=true` (Lapor Nanti) TETAP editable karena tim masih pegang task-nya.

**Konsekuensi implementasi (lebih besar dari sekadar ganti label — ini refactor perilaku):**
1. `app/Enums/TaskStatus.php` — hapus case `RESCHEDULE` dari enum. **Perlu migration data**: row `tasks` existing yang `status='reschedule'` di-update jadi `status='pending'` (biar gak ada FK/nilai orphan pas enum case-nya dihapus).
2. `TaskController::reschedule()` — logicnya (detach team, null `team_id`/`manual_override_at`, `rebuildTeamsForDate()`, `AuditLog`) **DIPINDAH jadi logic bersama**, dipanggil oleh KEDUA entry point: tombol teknisi top-level DAN `fopPending()`. Paling rapi: satuin ke 1 method service (misal `TaskService::setPending()`), 2 controller method (teknisi vs FOP) sama-sama manggil situ, cuma beda guard permission-nya siapa yang boleh akses.
3. `app/Observers/TaskObserver.php::resolveTarget()` — match arm `RESCHEDULE` dihapus (udah gak ada case itu), tinggal 1 match arm `PENDING` (dengan sub-cabang `report_deferred` yang emang udah ada).
4. `app/Models/FopTaskStatusHistory.php::label()` — label `pending_reschedule` bisa dipertahanin sebagai bagian histori LAMA (data historis yang udah kesimpen sebelum migration), tapi gak akan ada transisi baru yang nulis label ini lagi — transisi baru semua nulis label `pending_fop` aja (atau namain ulang jadi netral, misal `pending`, biar gak nyisain jejak "reschedule" vs "fop" yang udah gak relevan bedanya).
5. `TaskStatus::isEditable()` — hapus `PENDING` dari daftar (RESCHEDULE otomatis hilang karena case-nya udah gak ada).

### B. Kebutuhan "FOP perlu tau ada laporan yang perlu ditindaklanjuti" — dipisah jadi AKSI, bukan STATUS

FOP tetep butuh cara tau "task ini kerjaannya udah kelar (Selesai) tapi masih ada proses lanjutan yang perlu gue klik" — solusinya BUKAN ubah teks status, tapi CTA (call-to-action) link terpisah, muncul DI SAMPING badge "Selesai" (bukan gantiin), aktif kalau `fop_review_status='pending'`:

- Task_type MTN/dkk (review kualitas laporan sendiri): badge "Selesai" + link kecil **"Review Laporan →"** ke halaman Task (behavior ini UDAH ADA sekarang di `/fop-tasks`, tinggal dipastiin link ini konsisten muncul di ketiga halaman kalau relevan, bukan cuma index).
- Task_type SURVEY/PEMASANGAN (keputusan bisnis customer): badge "Selesai" + link kecil **"Lihat di Verif & Pemasangan →"** ke halaman Customer module (ini juga sebenernya udah pernah dibikin, tinggal dipasang lagi tapi TANPA badge kedua yang bikin ambigu).

**Bedanya sama desain sebelumnya:** dulu info ini nempel jadi TEKS STATUS ("Selesai — Menunggu Verifikasi") atau BADGE KEDUA ("Verifikasi: Menunggu") — dua-duanya bikin Task keliatan kayak dia sendiri yang "nunggu". Sekarang: status Task tegas "Selesai" (gak nunggu apa-apa, kerjaannya emang beres), link CTA di sebelahnya cuma informasi tambahan "ada proses lanjutan di modul lain yang perlu ditengok" — bukan bagian dari identitas status Task.

### C. FopTask bucket (`Proses`/`Pending`/`Selesai`/`Cancel`) — TETAP kayak sekarang, ini query concern bukan display concern

Ini PENTING dipisah dari soal label: `FopTaskStatus` (4 nilai) dipakai buat nentuin **antrian aktif vs Riwayat** (query `whereIn`), BUKAN buat teks yang ditampilin ke user. Gak berubah dari desain final sebelumnya:
- Survey/PSB `selesai` → bucket `Selesai` langsung (independen dari `fop_review_status`) — udah bener, gak diubah.
- Task_type lain (MTN dkk) `selesai`+belum-direview-FOP-sendiri → bucket `Proses` (karena reject beneran ngerevert `Task.status`, jadi secara data emang belum final) — udah bener, gak diubah.

Yang diubah CUMA lapisan display: dulu bucket `Proses` itu dikasih LABEL TEKS "Perlu Review" (nyampur bucket dengan label). Sekarang: badge teks tetep ngikutin `Task.status` literal ("Selesai", karena `Task.status` di DB emang udah `selesai`) + link CTA terpisah. Task ini SECARA VISUAL keliatan "Selesai" walau masih di antrian aktif (bucket `Proses`) — dan itu BENER, karena dari sisi Task/teknisi emang udah selesai, cuma FOP-nya yang belum klik review.

### D. Riwayat & `/tasks-saya` — 1 fungsi tipis, dipake 3 halaman

```php
// app/Enums/TaskStatus.php — tambahan method di enum yang sama, bukan class/service baru
// (RESCHEDULE udah gak ada case-nya lagi di enum — jadi cuma 1 pengecualian tersisa)
public function displayLabel(bool $reportDeferred = false): string
{
    return match (true) {
        $this === self::PENDING && $reportDeferred => 'Lapor Nanti',  // satu-satunya kondisi tersendiri
        default => $this->label(),                                    // passthrough apa adanya, termasuk PENDING biasa → "Pending"
    };
}
```

Dipanggil `$task->status->displayLabel($task->report_deferred)` di ketiga halaman — SATU fungsi, gak ada logic ganda yang perlu di-maintain paralel di 3 tempat. `label()` lama TETAP ada apa adanya (dipake tempat lain yang butuh nama teknis mentah), `displayLabel()` yang baru khusus buat badge yang dilihat user.

Gak perlu `TaskStatusResolver` kompleks kayak draft pertama, gak perlu `FopTaskStatusHistory::label()` buat badge utama (tetep ada, tapi cuma buat log histori granular di halaman Detail Riwayat § Histori Status — bukan buat badge utama lagi).

## Implementasi konkret

1. **`app/Enums/TaskStatus.php`** — tambah method `displayLabel(bool $reportDeferred = false): string` kayak di atas. `label()` lama gak diubah/dihapus (masih dipake tempat lain).
2. **`resources/views/tasks/own.blade.php` + `own-card.blade.php`** — ganti `{{ $task->status->label() }}` jadi `{{ $task->status->displayLabel($task->report_deferred) }}`.
3. **`resources/views/fop_tasks/index.blade.php`** — ganti `$latestHistory?->label() ?? $statusValue` (sumber histori granular) jadi `$task->task?->status->displayLabel($task->task->report_deferred) ?? $statusValue` (fallback ke `FopTaskStatus::value` kalau tiket manual tanpa Task eksekusi, mis. tiket dibuat FOP tanpa assign teknisi). Tombol/link "Review Laporan →" TETAP ada, tapi jadi elemen terpisah dari badge (bukan ganti teks badge).
4. **`resources/views/fop_tasks/history.blade.php`** — ganti `{{ $task->status->value }}` (FopTaskStatus mentah) jadi `{{ $task->task?->status->displayLabel($task->task->report_deferred) ?? $task->status->value }}`. Efeknya: baris Riwayat nampilin "Selesai" atau "Dibatalkan" (dari Task), bukan "Selesai"/"Cancel" (dari FopTask) — disamain persis 7 label di atas. Kalau `FopTask.status===Cancel` gara-gara FOP manual cancel TANPA Task eksekusi (tiket manual, `task_id=null`), fallback tetep munculin "Cancel" apa adanya (gak ada Task buat dijadiin rujukan) — kasus minor, jarang kejadian.
5. **`app/Models/FopTaskStatusHistory.php::label()`** — tetep dipertahanin APA ADANYA, tapi scope-nya diperjelas di komentar: cuma dipake di section "Histori Status" (log audit granular di halaman Detail Riwayat), BUKAN buat badge status utama manapun lagi.

## Test yang perlu ditambah/ubah

- 1 Task yang sama (task_type SURVEY, `status=selesai`, `fop_review_status` gonta-ganti pending/approved/rejected) → assert badge "Selesai" MUTLAK SAMA di ketiga halaman (`/tasks-saya`, `/fop-tasks`, `/fop-tasks/history`), gak berubah walau `fop_review_status` beda-beda.
- Task MTN `status=selesai`+`fop_review_status=pending` → badge "Selesai" (bukan "Perlu Review" lagi) + assert link "Review Laporan →" tetep muncul.
- `Task.status=pending+report_deferred=false` → assert `displayLabel()` = "Pending".
- `Task.status=pending+report_deferred=true` → assert `displayLabel()` = "Lapor Nanti", BEDA dari kasus Pending biasa di atas.
- **Regresi perilaku (paling penting, ini yang berubah beneran):** FOP klik "Set Pending" manual (`fopPending`) → assert SEKARANG JUGA lepas tim (`teamMembers` detached), `FopTask.team_id`/`manual_override_at` null, `rebuildTeamsForDate()` ke-trigger, `AuditLog` tercatat — sebelumnya test ini cuma assert `Task.status='pending'` doang tanpa efek tim. Cek juga test existing `TaskFopActionsTest::test_fop_can_set_scheduled_task_to_pending` (assert lama mungkin masih nganggep tim gak ke-detach — perlu diupdate).
- Teknisi klik "Pending" top-level → perilaku TETAP SAMA kayak sekarang (udah bener dari awal), cuma pastiin `Task.status` yang ditulis sekarang `pending` (bukan `reschedule` lagi).
- Regresi: `FopTaskStatusSyncTest`, `TaskReportDialogTest` — cek assertion yang masih ngarep teks lama ("Perlu Review", "Sedang Dikerjakan" dari histori) disesuaikan ke sumber baru.

## Kenapa ini lebih robust buat maintenance jangka panjang

- **Vocabulary status Task FIXED — 7 kata, gak nambah lagi** selamanya, karena gak nyambungin ke proses bisnis luar apapun. Proses baru (misal nanti ada "approval budget", "jadwal ulang customer", dst) gak akan pernah butuh nambahin cabang baru ke label Task — cukup ditambah sebagai CTA link terpisah kalau relevan, gak ganggu vocabulary status.
- **1 sumber (`TaskStatus::displayLabel()`), bukan fungsi baru yang perlu di-maintain paralel** di 3 tempat — dan cuma 2 baris pengecualian di dalemnya (reschedule→Pending, lapor_nanti→beda), sisanya passthrough polos.
- **Task dan alur pelanggan resmi kepisah di SEMUA layer** — data (`FopTaskStatus` bucket), maupun display (label teks) — bukan cuma di data doang kayak fix sebelumnya.
