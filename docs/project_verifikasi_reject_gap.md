# Gap: Reject Verifikasi Tidak Sinkron ke FopTask

Status: **FIXED 2026-07-14** (final, sudah lewat 2x iterasi desain — baca § "DESAIN FINAL" di bawah, bagian di bawahnya historis/superseded).

> **⚠️ SUPERSEDED sebagian (2026-07-20/21).** Semua referensi `FopTaskStatus::SELESAI`/`::PROSES`/`::CANCEL` di doc ini historis — enum `App\Enums\FopTaskStatus` **dihapus total**, diganti share vocab langsung sama `TaskStatus` (`selesai`/`in_progress`/`dibatalkan`, mirror 1:1 dari `Task.status`). Prinsip inti ("Task vs keputusan bisnis gak dicampur") TETAP berlaku dan malah DIPERLUAS ke semua task_type (dulu cuma SURVEY/PEMASANGAN yang gak didemosikan pas `selesai`+pending-review, sekarang task_type LAIN juga gak didemosikan lagi — lihat `docs/fop-task/database-schema.md` § Observer: TaskObserver). Selain itu, cancel/dibatalkan buat task_type SURVEY/PEMASANGAN sekarang DIKUNCI dari sisi Task/FopTask — wajib lewat halaman Customer (`docs/fop-task/flowchart.md` § 12).

## DESAIN FINAL (2026-07-14, iterasi ke-2 — baca ini dulu)

**Prinsip inti:** Task (kerjaan lapangan teknisi) VS keputusan bisnis (customer diterima/ditolak) itu 2 hal beda, SENGAJA GAK DICAMPUR di 1 bucket status. Task 10 (SLA dual-cycle) udah nganggep kerjaan "sukses" begitu `Task.status=selesai` (`TaskReport.completed_at` keisi saat itu juga, gak nunggu admin) — jadi `FopTask` harus konsisten sama prinsip ini juga.

Iterasi pertama (di bawah, § "Ringkasan masalah" s/d § "Susulan: Perlu Direview") keliru nyampur 2 konsep ini: task ditolak di-map ke `FopTaskStatus::CANCEL` (padahal Cancel = kerjaan gak jadi, bukan "kerjaan sukses tapi customer ditolak"), dan task nunggu keputusan di-exclude pakai query khusus dari antrian aktif (padahal Selesai emang udah otomatis gak pernah masuk `whereIn(Proses,Pending)`, gak butuh logic tambahan).

**Aturan final (`TaskObserver::resolveTarget()`):**
- Task_type **SURVEY/PEMASANGAN** (satu-satunya yang diputus lewat `CustomerVerificationController`, bukan review kualitas laporan biasa): begitu `Task.status=selesai` → `FopTask.status` SELALU `Selesai`, gak peduli `fop_review_status` udah diputus (`approved`/`rejected`) atau masih `pending`. Bedanya cuma di label histori granular: `selesai` / `selesai_menunggu_verifikasi` / `selesai_ditolak_verifikasi` — pola SAMA kayak `Pending` dipecah jadi `pending_reschedule`/`lapor_nanti`/`pending_fop` (3 label, 1 bucket).
- Task_type LAIN (MTN/DEAC/RELOKASI/dst): TETAP jalur lama — `selesai`+`pending` → `Proses`/`proses_review` ("Perlu Review"), karena reject di situ (`TaskController::review()`) BENERAN ngerevert `Task.status` balik `in_progress` (task-nya sendiri belum kelar dari sisi kualitas laporan, beda kasus dari keputusan bisnis customer).

**Efek ke UI:** gak ada lagi bucket/status khusus "Ditolak" atau "Perlu Direview" di kolom Status utama Riwayat. Kolom Status tetap cuma **Proses/Pending/Selesai/Cancel** (4 nilai asli). Nasib customer (Menunggu/Diterima/Ditolak) tampil di **kolom/badge KEDUA** ("Verifikasi") — overlay informasional dari `FopTask::verificationStatus()` (return `pending`/`approved`/`rejected`/`null`), gak pernah ngubah bucket status utama. Filter Riwayat juga dipisah 2 dimensi independen: `status` (Selesai/Cancel) dan `verifikasi` (menunggu/diterima/ditolak).

**File final:**
- `app/Observers/TaskObserver.php` — `resolveTarget()` cek `$isCustomerDecisionTask = in_array($task->task_type, [SURVEY, PEMASANGAN])`, lalu SELESAI selalu ke `FopTaskStatus::SELESAI` buat kasus itu (3 label granular), fallback ke `Proses`/`proses_review` buat task_type lain.
- `app/Models/FopTaskStatusHistory.php::label()` — label baru `selesai_menunggu_verifikasi`/`selesai_ditolak_verifikasi` (ganti `ditolak_verifikasi` yang dipakai iterasi pertama).
- `app/Models/FopTask.php::verificationStatus()` — helper baca `fop_review_status` dari Task terkait (return null kalau bukan Survey/Pemasangan), MURNI buat display, gak dipakai query filter/exclude. (Method `needsAdminReview()`/`scopeNeedsAdminReview()` dari iterasi pertama **DIHAPUS**, gak dipakai lagi — Selesai udah otomatis exclude dari antrian aktif tanpa logic tambahan.)
- `app/Http/Controllers/FopTaskController.php` — `index()`/`$switchTargetTasks`/`$teams` (workload/activeCount) balik ke query POLOS (gak ada exclusion tambahan, gak dibutuhin). `history()` balik ke `whereIn(SELESAI,CANCEL)` polos + filter baru `verifikasi=menunggu|diterima|ditolak` (independen dari filter `status`).
- `resources/views/fop_tasks/history.blade.php` — kolom Status balik ke dropdown polos (Proses/Pending/Selesai/Cancel, gak ada cabang khusus). Kolom BARU "Verifikasi" — badge Menunggu(biru)/Diterima(hijau)/Ditolak(merah) + link ke halaman Verif & Pemasangan kalau masih Menunggu. Filter dropdown baru "Verifikasi Admin".
- `resources/views/fop_tasks/history_detail.blade.php` — badge status utama polos + badge kedua "Verifikasi: ..." kalau `verificationStatus()` gak null. Alasan Cancel (manual FOP) dan Alasan Ditolak (verifikasi admin) dipisah jadi 2 section independen (bukan 1 section yang bercabang kayak sebelumnya).
- Test: `tests/Feature/FopTaskVerificationOverlayTest.php` (BARU, 4 test — gantiin `FopTaskPerluDireviewTest.php` yang dihapus karena premisnya udah gak berlaku) + `tests/Feature/CustomerVerificationRejectFopSyncTest.php` (2 test, assertion diupdate ke `FopTaskStatus::SELESAI`). Regresi: 91 test FOP/task/verifikasi — semua PASS.

**Dokumentasi fop-task/customer-lifecycle dari iterasi pertama (badge "Ditolak (Verifikasi Admin)" lewat Cancel, "Perlu Direview" lewat exclusion) MASIH PERLU DIUPDATE ke desain final ini** — kalau baca docs lain dan nemu istilah itu, itu udah OUTDATED, rujuk ke sini.

---

## Riwayat iterasi (historis, sebagian sudah superseded oleh § DESAIN FINAL di atas)

## Implementasi final (bukan rencana lagi — ini yang beneran jalan)

Keputusan desain berubah dari rencana awal setelah baca komentar eksplisit di `TaskObserver.php:16-21`: nambah case baru di `FopTaskStatus` enum akan mecahin banyak `whereIn('status', ['Proses','Pending'])` yang tersebar di codebase (di luar scope). Jadi **TIDAK jadi nambah `FopTaskStatus::DITOLAK`** — solusi final reuse `FopTaskStatus::CANCEL` yang udah ada, dibedain via label histori granular (`fop_task_status_history.to_status = 'ditolak_verifikasi'`), konsisten sama pola existing (`proses_review`, `pending_reschedule`, dst).

**File yang diubah:**
1. `app/Observers/TaskObserver.php` — `resolveTarget()` tambah 1 match arm: `SELESAI && fop_review_status==='rejected'` → `[FopTaskStatus::CANCEL, 'ditolak_verifikasi']`, dicek SEBELUM catch-all `SELESAI` (yang tetep `Proses`/`proses_review` buat kasus pending — behavior "FOP reject laporan → Proses kembali" generic MTN/gangguan gak berubah).
2. `app/Models/FopTaskStatusHistory.php` — tambah label `'ditolak_verifikasi' => 'Ditolak (Verifikasi Admin)'`.
3. `app/Http/Controllers/FopTaskController.php::history()` — TIDAK perlu ubah query (`CANCEL` udah termasuk dari awal), cuma tambah eager-load `task:id,status,fop_review_status,reject_reason` buat kebutuhan badge di view.
4. `app/Http/Controllers/CustomerVerificationController.php::reject()` — sekarang stage-aware: infer `$isInstallStage` dari `$customer->status` SEBELUM `$workflowService->transition()` dipanggil. Kalau install stage (`installation_in_progress|revision_installation|installed|verification_admin`) → target Task **Pemasangan**; kalau selain itu → Task **Survey** (behavior lama, gak berubah).
5. `resources/views/verifications/admin.blade.php` — tombol baru **"Tolak"** (merah, di sebelah kiri "Revisi Pemasangan") + modal `rejectModal` dgn peringatan eksplisit "final, gak bisa dibuka lagi, harus registrasi ulang" sesuai keputusan bisnis. Post ke route yang sama (`customers.verification.reject`) yang sebelumnya cuma dipakai queue page tahap survey.
6. `resources/views/fop_tasks/history.blade.php` + `history_detail.blade.php` — badge/label "Ditolak (Verifikasi Admin)" beda dari "Cancel" biasa (FOP manual cancel), plus alasan ditampilkan dari `Task.reject_reason` (bukan `FopTask.pending_reason` yang dipakai buat cancel manual).
7. Test baru: `tests/Feature/CustomerVerificationRejectFopSyncTest.php` — 2 test, PASS. Regression existing (`TaskFopActionsTest`, `CustomerFinalVerificationTest`, `FopTaskHistoryDetailPageTest`, `CustomerSurveyTest`) — semua PASS, gak ada yg rusak.

**Kenapa list "Pelanggan Gagal" gak disentuh:** udah otomatis kerja dari sebelumnya — `CustomerController.php:70` statusGroup `failed` udah `whereIn('status', ['failed','rejected','gagal'])`, dan `reject()` udah set `Customer.status='rejected'` dari awal. No-op, cuma perlu dipastiin tampil (udah, karena workflow-nya gak diubah).

**Yang sengaja TIDAK dikerjakan (di luar scope, catatan buat nanti):** ~~filter "Ditolak" terpisah di dropdown Status Riwayat~~ — SUDAH ditambah (lihat § Perlu Direview di bawah, sekalian nambah filter ini).

---

## Susulan: state "Perlu Direview" (task selesai, nunggu Verif & Pemasangan) — FIXED 2026-07-14

**Kasus:** Teknisi 1 & 2 ngerjain Task PSB, laporan submit, `Task.status=SELESAI` + `fop_review_status=pending`, customer masuk status `verification_admin`. Sebelum admin approve/reject, task ini ada DI MANA?

**Analisa sebelum fix:** FopTask-nya tetap `Proses` (badge "Perlu Review") dan nangkring di halaman **Task FOP** (antrian aktif) — BUKAN Riwayat (karena belum final, approved/rejected belum diputusin). Dicek ke kode: ini gak nge-block penjadwalan teknisi baru (`TaskService::detectConflicts()` cuma cek `TERJADWAL`/`IN_PROGRESS`, Task yang udah `SELESAI` gak dianggap sibuk). TAPI ada efek kosmetik nyata: task ini tetap kehitung di `activeCount`/`task_count`/`workload` counter panel Tim (`FopTaskController.php` — computation di `index()` & `history()`), jadi angka beban kerja teknisi keliatan lebih tinggi dari kenyataan.

**Keputusan (dikonfirmasi user):** pindahin task begini ke Riwayat dengan status khusus "Perlu Direview" — bukan "Selesai" (belum tentu diterima) dan bukan aktif di Task FOP (kerjaan lapangan udah kelar, gak butuh dijadwalin lagi). Riwayat memang cuma tempat nampung, aksi approve/reject-nya tetap di halaman Verif & Pemasangan (Customer module) — bukan di modul FopTask.

**Implementasi:**
- `app/Models/FopTask.php` — method `needsAdminReview()` + scope `scopeNeedsAdminReview()`: true kalau `FopTask.status===Proses && Task.status===SELESAI && Task.fop_review_status==='pending'`. Tetap gak nambah value baru di `FopTaskStatus` enum (sama alasan kayak kasus Ditolak — `status` mentah tetap `Proses`, cuma DIKELUARIN dari kueri antrian aktif & DIMASUKIN ke kueri Riwayat).
- `FopTaskController::index()` — query aktif exclude row `needsAdminReview()`. `$switchTargetTasks` (dropdown Task Tujuan di modal Switch Teknisi) ikut di-exclude juga.
- `FopTaskController::index()` & `history()` — komputasi `$teams` (activeCount/workload panel Tim) exclude row ini dari `$activeTasks` sebelum ngitung, jadi angka beban kerja teknisi akurat.
- `FopTaskController::history()` — query nambah `orWhere(needsAdminReview())`, plus filter dropdown baru `status=PerluReview`.
- `resources/views/fop_tasks/history.blade.php` — badge biru "Perlu Direview" (bukan dropdown edit manual, karena statusnya dikontrol otomatis observer, bukan FOP manual) + link "Lihat di Verif & Pemasangan →" ke `customers.verification.admin`. Filter dropdown Status nambah opsi "Perlu Direview".
- `resources/views/fop_tasks/history_detail.blade.php` — badge sama + link. Log histori status ("Histori Status" section, dari `fop_task_status_history`) TETAP kepampang apa adanya, termasuk entri `proses_review` → label "Perlu Review" — ini yang jadi audit trail granular sesuai permintaan user (kapan mulai, kapan submit laporan, kapan masuk nunggu review — semua tercatat per baris histori, changed_by + changed_at).
- Test baru `tests/Feature/FopTaskPerluDireviewTest.php` (3 test, PASS): exclude dari antrian aktif, muncul di Riwayat + filter, dan angka workload tim akurat (0, bukan numpuk).
- Regresi: 61 test FOP-related (FopTasksTest, FopTaskSortingTest, FopTaskSwitchTeamTest, FopTaskSwitchTechnicianTest, FopTaskTeamServiceTest, TaskReportDialogTest, dll) — semua PASS.

**Kenapa gak taro fitur approve/reject langsung di Riwayat:** keputusan final tetap di Customer module (Verif & Pemasangan) sesuai prinsip yang dikonfirmasi user — "Riwayat task itu cuma nampung task yang udah dikerjakan dengan berbagai status, yang beneran action cuma di pelanggan." Riwayat murni informasional buat status ini, dikasih link ke halaman aksi yang bener.

## Ringkasan masalah

`TaskObserver::resolveTarget()` (app/Observers/TaskObserver.php:151-163) gak bedain Task `SELESAI` yang masih nunggu review (`fop_review_status=pending`) sama Task `SELESAI` yang udah **ditolak final** (`fop_review_status=rejected`) — dua-duanya jatuh ke bucket sama: `FopTaskStatus::PROSES` label `proses_review` (line 160). Akibatnya task yg udah ditolak keliatan sama kayak task yg belum direview, stuck permanen di antrian FOP aktif.

**Koreksi dari analisa sebelumnya: bug ini BUKAN cuma laten/potensial — udah AKTIF buat jalur Survey.**

- `CustomerVerificationController::reject()` (line 279-313) dipanggil dari `resources/views/verifications/queue.blade.php` (tombol Batalkan/Gagal, elseif branch waiting_installation/waiting_acc/surveyed) — ini reachable sekarang, bukan hipotetis.
- Setiap kali admin nolak customer di tahap survey, Task Survey terkait jadi `SELESAI` + `fop_review_status=rejected`, lalu observer nyasarin FopTask-nya ke `Proses` selamanya. **Task ini kemungkinan besar udah numpuk di antrian FOP produksi sekarang**, keliatan kayak butuh dikerjain padahal customernya udah final ditolak.
- Buat tahap install (`Verif & Pemasangan` / `admin.blade.php`): tombol Tolak belum ada di UI, cuma Approve + Revisi. Tapi `WorkflowTransition::VERIFICATION_ADMIN` udah izinin transisi ke `REJECTED`, dan route `customers.verification.reject` gak py stage guard. Begitu core bug di observer di-fix, kita bisa aman nambah tombol Tolak di install stage tanpa nabrak masalah sama.

## Fakta pendukung desain solusi

- `WorkflowTransition::REJECTED->allowedNextTransitions()` = `[]` (WorkflowTransition.php:44) → **REJECTED itu status terminal**, gak ada jalur reopen di workflow. Kalau customer ditolak (survey maupun install), gak akan pernah balik lagi lewat transition ini — kalau mau retry, harus bikin record customer/registrasi baru. Ini simplifikasi penting: gak perlu desain jalur "un-reject".
- `fop_tasks.status` cuma kolom `string(20)`, gak ada DB-level enum constraint (migration 2026_06_30_000001) → nambah value status baru gak perlu migration, cukup ubah `FopTaskStatus` enum + kode yang baca/tulis dia.
- Ada tabel `fop_task_status_history` (migration 2026_07_17_000001) yang udah nyimpen label granular tiap perubahan (`proses_review`, `pending_reschedule`, dst) → infrastruktur audit trail granular UDAH ADA, tinggal ditambah label baru, gak perlu bikin sistem baru.
- Desain existing utk task non-PSB (MTN/gangguan) di `docs/fop-task/analisa-auto-team.md` § 10: "FOP reject laporan → `Proses` kembali" — ini **memang disengaja** buat kasus kualitas laporan teknisi jelek (minta dikerjain ulang). Fix di bawah TIDAK boleh ganggu perilaku ini — cuma `fop_review_status='rejected'` (bukan sekadar belum-approved) yang harus dapet perlakuan beda.

## Pendapat: kenapa ini penting buat dibenerin sebelum go-live real user

1. **Audit/pelaporan rusak diam-diam.** Task yang ditolak customer-nya keliatan identik dgn task yg lagi nunggu review FOP — kalau ada audit "kenapa task ini belum diapa-apain 2 minggu", jawabannya "oh ternyata udah ditolak dari verifikasi" gak keliatan dari status manapun. Sistem gak punya cara bedain "belum diproses" vs "udah final ditolak" di level FopTask.
2. **Operasional FOP kesulitan jadwal.** Task nyangkut di antrian aktif (Proses) padahal harusnya udah keluar dari alur kerja — ganggu kapasitas/slot penjadwalan tim, sesuai kekhawatiran awal lu.
3. **Gak ada test yang jaga alur ini** — perubahan behavior di masa depan bisa makin nutup celah ini tanpa ketauan.

## Solusi yang diusulkan (robust, gak ganggu alur lain)

**A. Enum & observer (core fix, benerin jalur Survey + Install sekaligus):**
1. Tambah `FopTaskStatus::DITOLAK = 'Ditolak'` di `app/Enums/FopTaskStatus.php`.
2. Di `TaskObserver::resolveTarget()`, tambah branch SEBELUM catch-all SELESAI:
   ```php
   $task->status === TaskStatus::SELESAI && $task->fop_review_status === 'rejected'
       => [FopTaskStatus::DITOLAK, 'ditolak_verifikasi'],
   $task->status === TaskStatus::SELESAI && $task->fop_review_status === 'approved'
       => [FopTaskStatus::SELESAI, 'selesai'],
   $task->status === TaskStatus::SELESAI => [FopTaskStatus::PROSES, 'proses_review'],
   ```
   Match arm urutan penting: `rejected` & `approved` dicek eksplisit dulu, baru catch-all `pending` jatuh ke `proses_review` — jadi behavior "FOP reject laporan → Proses kembali" (generic MTN/gangguan, fop_review_status tetap pending) tetap gak berubah.

**B. Riwayat & tampilan:**
3. `FopTaskController::history()` query (line ~765): ubah `whereIn('status', [SELESAI, CANCEL])` → tambah `DITOLAK`, biar task ditolak keliatan di Riwayat (bukan ilang, bukan nyangkut di aktif).
4. `resources/views/fop_tasks/history.blade.php` (+ `history_detail.blade.php` kalau ada): tambah badge/label beda buat `Ditolak` (misal merah), biar admin/auditor gampang bedain dari `Selesai` (hijau) sekilas mata.
5. Pastiin query antrian aktif (`FopTaskController.php` line 49/717/962, `whereIn(['Proses','Pending'])`) otomatis exclude `Ditolak` — udah otomatis bener asal enum baru gak dimasukin ke list itu, tinggal diverifikasi gak ada tempat lain yg nganggep "not Cancel" = aktif.

**C. `CustomerVerificationController::reject()` — stage-aware, sentuh Task yang bener:**
6. Infer stage dari `$customer->status` SEBELUM `$workflowService->transition(...)` dipanggil (baris ~290):
   - `waiting_acc|survey_in_progress|surveyed|waiting_installation` → target Task **Survey** (behavior existing, gak berubah).
   - `installation_in_progress|installed|verification_admin` → target Task **Pemasangan** (task_type PEMASANGAN, status SELESAI, fop_review_status pending) → set `fop_review_status='rejected'` + `reject_reason`.
7. Tambah UI tombol "Tolak" di `resources/views/verifications/admin.blade.php` (halaman Verif & Pemasangan), form post ke route yang sama (`customers.verification.reject`), styling beda tegas dari tombol "Revisi" — plus copy/label yang jelas bedain maksud:
   - **Revisi** = pekerjaan lapangan ada yg kurang, pelanggan tetap lanjut, balik ke teknisi.
   - **Tolak** = pelanggan gak eligible/gak bayar, proses instalasi ini final gagal, gak ada jalur balik (workflow REJECTED = terminal).

**D. Test coverage (biar gak regresi lagi ke depan):**
8. Feature test baru: reject di tahap survey → assert Task Survey `fop_review_status=rejected` + FopTask jadi `Ditolak` (bukan `Proses`).
9. Feature test baru: reject di tahap install → assert Task Pemasangan (bukan Survey) yang ke-update, FopTask `Ditolak`, muncul di Riwayat.
10. Regression test: generic FOP reject-laporan (non-PSB, MTN/gangguan) tetap balik ke `Proses` — pastiin fix di observer gak ganggu jalur ini (`tests/Feature/TaskFopActionsTest.php` yang udah ada jadi baseline).

**E. Data cleanup (produksi):**
11. Setelah fix di-deploy, jalanin one-off script/tinker: cari `Task` yg `status=SELESAI AND fop_review_status=rejected`, re-trigger observer (`$task->touch()` atau save ulang) biar FopTask yang nyangkut di `Proses` sekarang otomatis kereorganisir ke `Ditolak`. Perlu di-cek dulu berapa banyak row kena biar tau skala cleanup-nya.

## Yang masih perlu keputusan bisnis (bukan teknis)

- Kalau customer ditolak final di tahap Verif & Pemasangan, apa perlu ada cara buat "buka lagi" case itu (misal ternyata reject-nya salah klik), atau emang harus bikin registrasi customer baru dari nol? Workflow sekarang (`REJECTED` terminal, no transition out) implikasinya harus mulai dari nol — perlu dikonfirmasi ini emang maksud bisnisnya sebelum di-lock jadi behavior final.

**JAWAB.**

- Harus final dan ketika di tolak harus mengulang dari awal dan pelanggan yang di tolak akan masuk kedalamn list pelanggan gagal dan tasknya akan masuk riwayat task

**Locked-in (2026-07-14):**
- Reject = final, gak ada reopen. Customer harus registrasi ulang dari nol kalau mau retry. Konsisten sama `WorkflowTransition::REJECTED->allowedNextTransitions()=[]` yg emang udah terminal — gak perlu ubah workflow enum.
- List "pelanggan gagal" **UDAH ADA**, gak perlu bikin baru: `CustomerController.php:70` statusGroup `failed` udah nge-query `whereIn('status', ['failed','rejected','gagal'])`, dan `reject()` udah set `Customer.status='rejected'` via `CustomerWorkflowService`. Begitu reject dipanggil, customer OTOMATIS nongol di situ — no-op, cuma perlu diverifikasi tampil bener.
- Satu-satunya gap teknis yg beneran perlu digarap: **Task masuk Riwayat setelah ditolak** — ini bagian A+B di solusi (enum `DITOLAK`, observer branch, query Riwayat). Bagian C (tombol Tolak di admin.blade.php) + D (test) tetep jalan spt rencana.